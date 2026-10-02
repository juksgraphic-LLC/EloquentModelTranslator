<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Exceptions;

class InvalidConfigurationException extends TranslationException
{
    /**
     * Send an exception for empty locales
     * @return InvalidConfigurationException
     */
    public static function emptyLocales(): self
    {
        return new self('The list of allowed locales must not be empty.');
    }

    /**
     * Send an exception for not allowed source locale
     * @param string $locale
     * @return InvalidConfigurationException
     */
    public static function sourceLocaleNotAllowed(string $locale): self
    {
        return new self("The source locale [{$locale}] must be part of the allowed locales.");
    }

    /**
     * Send an exception for invalid fallback
     * @param string $locale
     * @return InvalidConfigurationException
     */
    public static function invalidFallback(string $locale): self
    {
        return new self("The fallback locale [{$locale}] must be part of the allowed locales.");
    }

    /**
     * Send an exception when no default HTTP implementation can be found
     * @param string $what
     * @param string $suggestion
     * @return InvalidConfigurationException
     */
    public static function missingHttpImplementation(string $what, string $suggestion): self
    {
        return new self("No {$what} found. Pass one explicitly or install {$suggestion}.");
    }
}
