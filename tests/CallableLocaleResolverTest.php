<?php

use Juksgraphic\EloquentModelTranslator\Resolvers\CallableLocaleResolver;

it('returns the locale given by the callable', function () {
    expect((new CallableLocaleResolver(fn () => 'en'))->resolve())->toBe('en');
});

it('returns null for empty or non-string results', function () {
    expect((new CallableLocaleResolver(fn () => ''))->resolve())->toBeNull()
        ->and((new CallableLocaleResolver(fn () => null))->resolve())->toBeNull()
        ->and((new CallableLocaleResolver(fn () => 42))->resolve())->toBeNull();
});