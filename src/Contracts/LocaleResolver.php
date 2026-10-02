<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Contracts;

/**
 * Tells the package which locale is currently active.
 * Implemented by each framework adapter (Laravel, Flight, ...).
 */
interface LocaleResolver
{
    /**
     * @return string|null The active locale, or null when it cannot be determined.
     */
    public function resolve(): ?string;
}
