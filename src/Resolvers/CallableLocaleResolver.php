<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Resolvers;

use Closure;
use Juksgraphic\EloquentModelTranslator\Contracts\LocaleResolver;

/**
 * Resolves the active locale through a user-supplied callable.
 *
 * Laravel: new CallableLocaleResolver(fn () => app()->getLocale())
 * Flight:  new CallableLocaleResolver(fn () => Flight::getLocale())
 */
class CallableLocaleResolver implements LocaleResolver
{
    /**
     * @var Closure(): (string|null)
     */
    protected Closure $callback;

    /**
     * @param callable(): (string|null) $callback Returns the active locale, or null if unknown.
     */
    public function __construct(callable $callback)
    {
        $this->callback = Closure::fromCallable($callback);
    }

    /**
     * Returns the active locale, or null when the callable yields nothing usable.
     *
     * @return string|null
     */
    public function resolve(): ?string
    {
        $locale = ($this->callback)();

        return is_string($locale) && $locale !== '' ? $locale : null;
    }
}
