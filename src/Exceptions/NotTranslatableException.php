<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Exceptions;

class NotTranslatableException extends TranslationException
{
    public static function attribute(string $model, string $attribute): self
    {
        return new self("Attribute [{$attribute}] is not translatable on [{$model}].");
    }

    public static function noTranslatableAttributes(string $model): self
    {
        return new self("Model [{$model}] does not declare any translatable attribute.");
    }
}