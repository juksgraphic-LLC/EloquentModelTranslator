<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Exceptions;

class ModelNotSavedException extends TranslationException
{
    /**
     * @param string $model Class name of the unsaved model.
     * @return self
     */
    public static function forModel(string $model): self
    {
        return new self("Translations of [{$model}] cannot be written before the model is saved.");
    }
}
