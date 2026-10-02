<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Services;

use Illuminate\Database\Eloquent\Model;
use Juksgraphic\EloquentModelTranslator\Contracts\Translatable;
use Juksgraphic\EloquentModelTranslator\Contracts\TranslationProvider;
use Juksgraphic\EloquentModelTranslator\Exceptions\NotTranslatableException;
use Juksgraphic\EloquentModelTranslator\Exceptions\TranslationFailedException;
use Juksgraphic\EloquentModelTranslator\Exceptions\UnsupportedLocaleException;
use Juksgraphic\EloquentModelTranslator\Support\TranslatorConfig;
use Juksgraphic\EloquentModelTranslator\Translator;

/**
 * Orchestrates automatic translation through a provider.
 * Nothing is written unless every locale was translated successfully.
 */
class TranslationService
{
    /**
     * @var TranslatorConfig
     */
    protected TranslatorConfig $config;

    /**
     * @param TranslationProvider $provider
     * @param TranslatorConfig|null $config Defaults to Translator::config().
     */
    public function __construct(
        protected TranslationProvider $provider,
        ?TranslatorConfig $config = null,
    ) {
        $this->config = $config ?? Translator::config();
    }

    /**
     * Translates the model's source values into every target locale and saves the result.
     * By default, existing translations (e.g. manual edits) are kept.
     *
     * @param Model&Translatable $model
     * @param list<string>|null $attributes Limit to these attributes (e.g. the changed ones).
     * @param bool $overwrite Replace existing translations.
     * @return void
     *
     * @throws NotTranslatableException
     * @throws TranslationFailedException
     */
    public function translate(Model&Translatable $model, ?array $attributes = null, bool $overwrite = false): void
    {
        $fields = $this->extractFields($model, $attributes);

        if ($fields === []) {
            return;
        }

        $results = [];

        foreach ($this->config->targetLocales() as $locale) {
            $pending = $overwrite
                ? $fields
                : array_filter($fields, static fn($value, $key): bool => !$model->hasTranslation((string) $key, $locale), ARRAY_FILTER_USE_BOTH);

            if ($pending === []) {
                continue;
            }

            $results[$locale] = $this->requestTranslation($pending, $locale);
        }

        foreach ($results as $locale => $translated) {
            $model->setTranslations((string) $locale, $translated);
        }

        $model->flushTranslations();
    }

    /**
     * Saves manual translations: [locale => [attribute => value]].
     *
     * @param Model&Translatable $model
     * @param array<string, array<string, mixed>> $translationsByLocale
     * @return void
     *
     * @throws NotTranslatableException
     * @throws UnsupportedLocaleException
     */
    public function saveTranslations(Model&Translatable $model, array $translationsByLocale): void
    {
        foreach ($translationsByLocale as $locale => $fields) {
            $model->setTranslations((string) $locale, $fields);
        }

        $model->flushTranslations();
    }

    /**
     * Collects the non-empty string source values to translate.
     *
     * @param Model&Translatable $model
     * @param list<string>|null $attributes
     * @return array<string, string>
     *
     * @throws NotTranslatableException
     */
    protected function extractFields(Model&Translatable $model, ?array $attributes): array
    {
        $attributes ??= $model->getTranslatableAttributes();
        $raw          = $model->getAttributes();
        $fields       = [];

        foreach ($attributes as $attribute) {
            if (!$model->isTranslatable($attribute)) {
                throw NotTranslatableException::attribute($model::class, $attribute);
            }

            $value = $raw[$attribute] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $fields[$attribute] = $value;
            }
        }

        return $fields;
    }

    /**
     * Calls the provider and validates its response.
     *
     * @param array<string, string> $fields
     * @param string $locale
     * @return array<string, string>
     *
     * @throws TranslationFailedException
     */
    protected function requestTranslation(array $fields, string $locale): array
    {
        $translated = $this->provider->translate($fields, $this->config->sourceLocale, $locale);

        $missing = array_keys(array_diff_key($fields, $translated));

        if ($missing !== []) {
            throw TranslationFailedException::missingKeys($this->provider::class, $missing);
        }

        $translated = array_intersect_key($translated, $fields);

        foreach ($translated as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw TranslationFailedException::invalidResponse($this->provider::class, 'non-string or empty value');
            }
        }

        return $translated;
    }
}
