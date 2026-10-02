<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Contracts;

use Juksgraphic\EloquentModelTranslator\Exceptions\ModelNotSavedException;
use Juksgraphic\EloquentModelTranslator\Exceptions\NotTranslatableException;
use Juksgraphic\EloquentModelTranslator\Exceptions\UnsupportedLocaleException;

interface Translatable
{
    /**
     * Lists the translatable attribute keys.
     *
     * @return list<string>
     */
    public function getTranslatableAttributes(): array;

    /**
     * Checks whether an attribute is translatable.
     *
     * @param string $key
     * @return bool
     */
    public function isTranslatable(string $key): bool;

    /**
     * Forces a locale on this instance (null restores the resolved locale).
     *
     * @param string|null $locale
     * @return static
     *
     * @throws UnsupportedLocaleException
     */
    public function setLocale(?string $locale): static;

    /**
     * Reads one attribute in a locale, following the fallback chain when allowed.
     *
     * @param string $key
     * @param string|null $locale Defaults to the active locale.
     * @param bool $useFallback
     * @return mixed
     *
     * @throws NotTranslatableException
     * @throws UnsupportedLocaleException
     */
    public function getTranslation(string $key, ?string $locale = null, bool $useFallback = true): mixed;

    /**
     * Checks whether a non-empty value exists for the attribute in the locale.
     *
     * @param string $key
     * @param string|null $locale Defaults to the active locale.
     * @return bool
     */
    public function hasTranslation(string $key, ?string $locale = null): bool;

    /**
     * Stages one translation (written on save() or flushTranslations()).
     * For the source locale, sets the model attribute itself.
     *
     * @param string $key
     * @param string $locale
     * @param mixed $value
     * @return static
     *
     * @throws NotTranslatableException
     * @throws UnsupportedLocaleException
     */
    public function setTranslation(string $key, string $locale, mixed $value): static;

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
    public function setTranslations(string $locale, array $values): static;

    /**
     * Writes staged translations to the database (merged with existing content).
     *
     * @return static
     *
     * @throws ModelNotSavedException When the model does not exist yet.
     */
    public function flushTranslations(): static;
}