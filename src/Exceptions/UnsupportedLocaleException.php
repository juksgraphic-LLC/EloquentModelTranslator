<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Exceptions;

class UnsupportedLocaleException extends TranslationException
{
    /**
     * @param list<string> $allowed
     */
    public static function forLocale(string $locale, array $allowed): self
    {
        return new self(sprintf(
            'Locale [%s] is not supported. Allowed locales: %s.',
            $locale,
            implode(', ', $allowed)
        ));
    }
}
