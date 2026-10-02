<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Juksgraphic\EloquentModelTranslator\Exceptions\ModelNotSavedException;
use Juksgraphic\EloquentModelTranslator\Exceptions\NotTranslatableException;
use Juksgraphic\EloquentModelTranslator\Exceptions\UnsupportedLocaleException;
use Juksgraphic\EloquentModelTranslator\Models\Translation;
use Juksgraphic\EloquentModelTranslator\Translator;

/**
 * Adds per-attribute translations to an Eloquent model.
 * The model must implement the Translatable contract and declare a $translatable array.
 *
 * Source-locale values stay in the model columns; other locales live in the translations table.
 * Translations are written on save()/delete(): no event dispatcher is required.
 *
 * @mixin Model
 *
 * @property bool $exists
 * @property-read Collection<int, Translation> $translations
 */
trait HasTranslations
{
    /**
     * Locale forced on this instance, bypassing the resolved locale.
     *
     * @var string|null
     */
    protected ?string $translationLocale = null;

    /**
     * Staged translations waiting to be written: locale => [attribute => value].
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $pendingTranslations = [];

    /**
     * Polymorphic relation to the translations table.
     *
     * @return MorphMany<Translation, $this>
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Lists the translatable attribute keys declared on the model.
     *
     * @return list<string>
     */
    public function getTranslatableAttributes(): array
    {
        return property_exists($this, 'translatable') ? $this->translatable : [];
    }

    /**
     * Checks whether an attribute is translatable.
     *
     * @param string $key
     * @return bool
     */
    public function isTranslatable(string $key): bool
    {
        return in_array($key, $this->getTranslatableAttributes(), true);
    }

    /**
     * Returns the translated value for translatable attributes.
     *
     * @param mixed $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (is_string($key) && $this->isTranslatable($key)) {
            return $this->getTranslation($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Applies translations when the model is converted with toArray()/toJson().
     *
     * @return array<string, mixed>
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        foreach ($this->getTranslatableAttributes() as $key) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = $this->getTranslation($key);
            }
        }

        return $attributes;
    }

    /**
     * Forces a locale on this instance.
     *
     * @param string|null $locale
     * @return static
     *
     * @throws UnsupportedLocaleException
     */
    public function setLocale(?string $locale): static
    {
        if ($locale !== null) {
            Translator::config()->assertAllowed($locale);
        }

        $this->translationLocale = $locale;

        return $this;
    }

    /**
     * Reads one attribute in a locale, following locale -> fallback -> source.
     *
     * @param string $key
     * @param string|null $locale Defaults to the active locale.
     * @param bool $useFallback
     * @return mixed
     *
     * @throws NotTranslatableException
     * @throws UnsupportedLocaleException
     */
    public function getTranslation(string $key, ?string $locale = null, bool $useFallback = true): mixed
    {
        $this->assertTranslatable($key);

        $config = Translator::config();

        if ($locale !== null) {
            $config->assertAllowed($locale);
        }

        $locale ??= $this->getActiveLocale();

        if ($config->isSource($locale)) {
            return $this->getSourceAttribute($key);
        }

        $chain = $useFallback
            ? array_unique([$locale, $config->fallbackLocale, $config->sourceLocale])
            : [$locale];

        foreach ($chain as $candidate) {
            $value = $config->isSource($candidate)
                ? $this->getSourceAttribute($key)
                : $this->lookupTranslation($key, $candidate);

            if ($value !== null && ($candidate === $locale || $value !== '')) {
                return $value;
            }
        }

        return $useFallback ? $this->getSourceAttribute($key) : null;
    }

    /**
     * Reads one attribute in every supported locale, without fallback.
     *
     * @param string $key
     * @return array<string, mixed> locale => value (null when missing)
     *
     * @throws NotTranslatableException
     */
    public function getTranslations(string $key): array
    {
        $result = [];

        foreach (Translator::config()->locales as $locale) {
            $result[$locale] = $this->getTranslation($key, $locale, false);
        }

        return $result;
    }

    /**
     * Checks whether a non-empty value exists for the attribute in the locale.
     *
     * @param string $key
     * @param string|null $locale Defaults to the active locale.
     * @return bool
     */
    public function hasTranslation(string $key, ?string $locale = null): bool
    {
        $locale ??= $this->getActiveLocale();

        $value = Translator::config()->isSource($locale)
            ? $this->getSourceAttribute($key)
            : $this->lookupTranslation($key, $locale);

        return $value !== null && $value !== '';
    }

    /**
     * Stages one translation. The source locale sets the model attribute itself.
     *
     * @param string $key
     * @param string $locale
     * @param mixed $value
     * @return static
     *
     * @throws NotTranslatableException
     * @throws UnsupportedLocaleException
     */
    public function setTranslation(string $key, string $locale, mixed $value): static
    {
        $this->assertTranslatable($key);

        $config = Translator::config();
        $config->assertAllowed($locale);

        if ($config->isSource($locale)) {
            $this->setAttribute($key, $value);

            return $this;
        }

        $this->pendingTranslations[$locale][$key] = $value;

        return $this;
    }

    /**
     * Stages several attributes for one locale.
     *
     * @param string $locale
     * @param array<string, mixed> $values
     * @return static
     *
     * @throws NotTranslatableException
     * @throws UnsupportedLocaleException
     */
    public function setTranslations(string $locale, array $values): static
    {
        foreach ($values as $key => $value) {
            $this->setTranslation((string) $key, $locale, $value);
        }

        return $this;
    }

    /**
     * Writes staged translations, merged with the existing content of each locale.
     *
     * @return static
     *
     * @throws ModelNotSavedException When the model does not exist yet.
     */
    public function flushTranslations(): static
    {
        if ($this->pendingTranslations === []) {
            return $this;
        }

        if (!$this->exists) {
            throw ModelNotSavedException::forModel(static::class);
        }

        $pending = $this->pendingTranslations;

        $this->getConnection()->transaction(function () use ($pending): void {
            foreach ($pending as $locale => $fields) {
                $existing = $this->translations()->where('locale', $locale)->first();
                $content  = array_merge($existing?->content ?? [], $fields);

                $this->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['content' => $content]
                );
            }
        });

        $this->pendingTranslations = [];
        $this->unsetRelation('translations');

        return $this;
    }

    /**
     * Saves the model, then its staged translations, in one transaction.
     *
     * @param array<string, mixed> $options
     * @return bool
     */
    public function save(array $options = []): bool
    {
        if ($this->pendingTranslations === []) {
            return parent::save($options);
        }

        return (bool) $this->getConnection()->transaction(function () use ($options): bool {
            if (!parent::save($options)) {
                return false;
            }

            $this->flushTranslations();

            return true;
        });
    }

    /**
     * Deletes the model and its translations (kept on soft delete).
     *
     * @return bool|null
     */
    public function delete()
    {
        return $this->getConnection()->transaction(function () {
            
            $deleted = parent::delete();

            if ($deleted && !$this->isSoftDeleting()) {
                $this->translations()->delete();
            }

            return $deleted;
        });
    }

    /**
     * Eager loads translations for the active locale and the fallback (avoids N+1).
     *
     * @param Builder<static> $query
     * @param string|null $locale
     * @return Builder<static>
     *
     * @throws UnsupportedLocaleException
     */
    public function scopeWithTranslations(Builder $query, ?string $locale = null): Builder
    {
        $config = Translator::config();

        if ($locale !== null) {
            $config->assertAllowed($locale);
        }

        $locales = array_values(array_diff(
            array_unique([$locale ?? $this->getActiveLocale(), $config->fallbackLocale]),
            [$config->sourceLocale]
        ));

        if ($locales === []) {
            return $query;
        }

        return $query->with(['translations' => fn($relation) => $relation->whereIn('locale', $locales)]);
    }

    /**
     * Raw source-locale value, with the model's own casts and accessors.
     *
     * @param string $key
     * @return mixed
     */
    public function getSourceAttribute(string $key): mixed
    {
        return parent::getAttribute($key);
    }

    /**
     * Active locale: forced, else resolved, else the fallback when unsupported.
     *
     * @return string
     */
    protected function getActiveLocale(): string
    {
        $config = Translator::config();
        $locale = $this->translationLocale ?? Translator::currentLocale();

        return $config->isAllowed($locale) ? $locale : $config->fallbackLocale;
    }

    /**
     * Finds a non-empty translated value for a non-source locale.
     *
     * @param string $key
     * @param string $locale
     * @return mixed null when missing or empty
     */
    protected function lookupTranslation(string $key, string $locale): mixed
    {
        $value = $this->pendingTranslations[$locale][$key] ?? null;

        if ($value === null) {
            $content = $this->translations->firstWhere('locale', $locale)?->content;
            $value   = is_array($content) ? ($content[$key] ?? null) : null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return $this->castTranslatedValue($key, $value);
    }

    /**
     * Applies the model's declared cast to a translated string.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function castTranslatedValue(string $key, mixed $value): mixed
    {
        if (is_string($value) && $this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Ensures the attribute is declared translatable.
     *
     * @param string $key
     * @return void
     *
     * @throws NotTranslatableException
     */
    protected function assertTranslatable(string $key): void
    {
        if (!$this->isTranslatable($key)) {
            throw NotTranslatableException::attribute(static::class, $key);
        }
    }

    /**
     * Whether the model is being soft deleted (translations must be kept).
     *
     * @return bool
     */
    protected function isSoftDeleting(): bool
    {
        return method_exists($this, 'isForceDeleting') && !$this->isForceDeleting();
    }
}
