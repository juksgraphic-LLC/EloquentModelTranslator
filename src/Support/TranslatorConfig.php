<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Support;

use Juksgraphic\EloquentModelTranslator\Exceptions\InvalidConfigurationException;
use Juksgraphic\EloquentModelTranslator\Exceptions\UnsupportedLocaleException;

/**
 * Immutable, validated configuration shared by the whole package.
 */
final readonly class TranslatorConfig
{
    /**
     * Locales supported by the application (deduplicated, reindexed).
     *
     * @var list<string>
     */
    public array $locales;

    /**
     * Locale used when a translation is missing.
     *
     * @var string
     */
    public string $fallbackLocale;

    /**
     * @param list<string> $locales        Every supported locale, source included.
     * @param string       $sourceLocale   Locale in which the model columns are stored.
     * @param string|null  $fallbackLocale Locale used when a translation is missing (defaults to the source locale).
     * @param string       $table          Name of the translations table.
     *
     * @throws InvalidConfigurationException When the locales, source or fallback are inconsistent.
     */
    public function __construct(
        array $locales = ['fr', 'en', 'es', 'ht'],
        public string $sourceLocale = 'fr',
        ?string $fallbackLocale = null,
        public string $table = 'translations',
    ) {
        $locales = array_values(array_unique($locales));

        if ($locales === []) {
            throw InvalidConfigurationException::emptyLocales();
        }

        if (! in_array($sourceLocale, $locales, true)) {
            throw InvalidConfigurationException::sourceLocaleNotAllowed($sourceLocale);
        }

        $fallbackLocale ??= $sourceLocale;

        if (! in_array($fallbackLocale, $locales, true)) {
            throw InvalidConfigurationException::invalidFallback($fallbackLocale);
        }

        $this->locales = $locales;
        $this->fallbackLocale = $fallbackLocale;
    }

    /**
     * Locales a model must be translated into (all locales except the source).
     *
     * @return list<string>
     */
    public function targetLocales(): array
    {
        return array_values(array_diff($this->locales, [$this->sourceLocale]));
    }

    /**
     * Checks whether the locale is supported.
     *
     * @param string $locale
     * @return bool
     */
    public function isAllowed(string $locale): bool
    {
        return in_array($locale, $this->locales, true);
    }

    /**
     * Checks whether the locale is the one in which model columns are stored.
     *
     * @param string $locale
     * @return bool
     */
    public function isSource(string $locale): bool
    {
        return $locale === $this->sourceLocale;
    }

    /**
     * Ensures the locale is supported.
     *
     * @param string $locale
     * @return void
     *
     * @throws UnsupportedLocaleException When the locale is not in the allowed list.
     */
    public function assertAllowed(string $locale): void
    {
        if (! $this->isAllowed($locale)) {
            throw UnsupportedLocaleException::forLocale($locale, $this->locales);
        }
    }
}
