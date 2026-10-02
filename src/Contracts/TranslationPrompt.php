<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Contracts;

use Juksgraphic\EloquentModelTranslator\Exceptions\TranslationFailedException;

/**
 * Owns everything about the prompt: what is asked to the model and how its answer is read.
 * Independent from any HTTP provider, so it can be swapped or reused by every LLM provider.
 */
interface TranslationPrompt
{
    /**
     * Instructions given to the model (role, rules, output format).
     */
    public function system(string $sourceLocale, string $targetLocale): string;

    /**
     * Payload to translate (['title' => 'My title']) serialised for the model.
     *
     * @param array<string, string> $fields
     *
     * @throws TranslationFailedException When the fields cannot be serialised.
     */
    public function user(array $fields): string;

    /**
     * Turns the raw text answered by the model into the translated associative array.
     *
     * @return array<string, string>
     *
     * @throws TranslationFailedException When the answer is not a valid JSON object.
     */
    public function parse(string $content, string $provider): array;
}
