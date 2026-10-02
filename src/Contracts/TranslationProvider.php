<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Contracts;

use Juksgraphic\EloquentModelTranslator\Exceptions\TranslationFailedException;

interface TranslationProvider
{
    /**
     * Translates an associative array (['title' => 'My title']) into the target locale.
     * The returned array MUST contain exactly the input keys, with string values.
     *
     * @param array<string, string> $fields
     * @return array<string, string>
     *
     * @throws TranslationFailedException When no valid translation can be produced.
     */
    public function translate(array $fields, string $sourceLocale, string $targetLocale): array;
}
