<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Providers;

use Juksgraphic\EloquentModelTranslator\Contracts\TranslationProvider;

/**
 * Offline provider for tests and local development.
 * Prefixes every value with the target locale and records each call.
 */
class ArrayTranslationProvider implements TranslationProvider
{
    /**
     * Calls received by the provider, in order.
     *
     * @var list<array{fields: array<string, string>, source: string, target: string}>
     */
    protected array $calls = [];

    /**
     * @param string $format sprintf format receiving the target locale then the value.
     */
    public function __construct(
        protected string $format = '[%s] %s',
    ) {
    }

    /**
     * Translates fields by prefixing each value with the target locale.
     *
     * @param array<string, string> $fields
     * @param string $sourceLocale
     * @param string $targetLocale
     * @return array<string, string>
     */
    public function translate(array $fields, string $sourceLocale, string $targetLocale): array
    {
        $this->calls[] = [
            'fields' => $fields,
            'source' => $sourceLocale,
            'target' => $targetLocale,
        ];

        $translated = [];

        foreach ($fields as $key => $value) {
            $translated[$key] = sprintf($this->format, $targetLocale, $value);
        }

        return $translated;
    }

    /**
     * Returns every call received so far (useful for assertions).
     *
     * @return list<array{fields: array<string, string>, source: string, target: string}>
     */
    public function calls(): array
    {
        return $this->calls;
    }
}
