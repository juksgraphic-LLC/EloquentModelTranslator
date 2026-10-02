<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Tests\Fixtures;

use Closure;
use Juksgraphic\EloquentModelTranslator\Contracts\TranslationProvider;

final class CallbackProvider implements TranslationProvider
{
    public function __construct(private Closure $callback) {}

    public function translate(array $fields, string $sourceLocale, string $targetLocale): array
    {
        return ($this->callback)($fields, $sourceLocale, $targetLocale);
    }
}
