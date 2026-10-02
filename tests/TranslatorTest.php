<?php

use Juksgraphic\EloquentModelTranslator\Resolvers\CallableLocaleResolver;
use Juksgraphic\EloquentModelTranslator\Support\TranslatorConfig;
use Juksgraphic\EloquentModelTranslator\Translator;

it('returns default config when none was set', function () {
    Translator::reset();

    expect(Translator::config())->toBeInstanceOf(TranslatorConfig::class);
});

it('uses the configured config', function () {
    Translator::configure(new TranslatorConfig(['en', 'fr'], 'en'));

    expect(Translator::config()->sourceLocale)->toBe('en');
});

it('uses the source locale when no resolver is set', function () {
    Translator::reset();

    expect(Translator::currentLocale())->toBe('fr');
});

it('accepts a callable or a resolver', function () {
    Translator::resolveLocaleUsing(fn () => 'en');
    expect(Translator::currentLocale())->toBe('en');

    Translator::resolveLocaleUsing(new CallableLocaleResolver(fn () => 'es'));
    expect(Translator::currentLocale())->toBe('es');
});

it('falls back to the source locale when the resolver returns nothing', function () {
    Translator::resolveLocaleUsing(fn () => null);
    expect(Translator::currentLocale())->toBe('fr');

    Translator::resolveLocaleUsing(fn () => '');
    expect(Translator::currentLocale())->toBe('fr');
});

it('resets configuration and resolver', function () {
    Translator::configure(new TranslatorConfig(['en'], 'en'));
    Translator::resolveLocaleUsing(fn () => 'en');

    Translator::reset();

    expect(Translator::config()->sourceLocale)->toBe('fr')
        ->and(Translator::currentLocale())->toBe('fr');
});
