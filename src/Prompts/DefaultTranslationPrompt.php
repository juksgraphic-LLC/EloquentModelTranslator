<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Prompts;

use JsonException;
use Juksgraphic\EloquentModelTranslator\Contracts\TranslationPrompt;
use Juksgraphic\EloquentModelTranslator\Exceptions\TranslationFailedException;

/**
 * JSON-in / JSON-out translation prompt.
 */
class DefaultTranslationPrompt implements TranslationPrompt
{
    /**
     * @param array<string, string> $localeNames Human names, clearer for the model than ISO codes (e.g. "ht").
     * @param string $extraInstructions Appended to the system prompt (glossary, tone, brand names...).
     */
    public function __construct(
        protected array $localeNames = [
            'fr' => 'French',
            'en' => 'English',
            'es' => 'Spanish',
            'ht' => 'Haitian Creole',
        ],
        protected string $extraInstructions = '',
    ) {
    }

    public function system(string $sourceLocale, string $targetLocale): string
    {
        $source = $this->label($sourceLocale);
        $target = $this->label($targetLocale);

        $prompt = "You are a professional translator. Translate the JSON values from {$source} to {$target}. "
            . 'Keep the JSON keys unchanged. Maintain tone, HTML/formatting tags, placeholders and meaning. '
            . 'Respond STRICTLY with a valid JSON object matching the input keys, without markdown or explanation.';

        return $this->extraInstructions === '' ? $prompt : $prompt . ' ' . $this->extraInstructions;
    }

    public function user(array $fields): string
    {
        try {
            return json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw TranslationFailedException::requestFailed('prompt', $e);
        }
    }

    public function parse(string $content, string $provider): array
    {
        $content = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)));

        try {
            $translated = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw TranslationFailedException::invalidResponse($provider, 'malformed JSON');
        }

        if (! is_array($translated)) {
            throw TranslationFailedException::invalidResponse($provider, 'content is not a JSON object');
        }

        return $translated;
    }

    protected function label(string $locale): string
    {
        return isset($this->localeNames[$locale]) ? "{$this->localeNames[$locale]} ({$locale})" : $locale;
    }
}
