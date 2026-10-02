<?php

use Juksgraphic\EloquentModelTranslator\Exceptions\InvalidConfigurationException;
use Juksgraphic\EloquentModelTranslator\Exceptions\UnsupportedLocaleException;
use Juksgraphic\EloquentModelTranslator\Support\TranslatorConfig;

it('has sensible defaults', function () {
    $config = new TranslatorConfig();

    expect($config->locales)->toBe(['fr', 'en', 'es', 'ht'])
        ->and($config->sourceLocale)->toBe('fr')
        ->and($config->fallbackLocale)->toBe('fr')
        ->and($config->table)->toBe('translations');
});

it('deduplicates and reindexes locales', function () {
    $config = new TranslatorConfig(['fr', 'en', 'fr'], 'fr');

    expect($config->locales)->toBe(['fr', 'en']);
});

it('lists target locales without the source', function () {
    expect((new TranslatorConfig())->targetLocales())->toBe(['en', 'es', 'ht']);
});

it('validates locales, source and fallback', function () {
    expect(fn() => new TranslatorConfig([], 'fr'))->toThrow(InvalidConfigurationException::class)
        ->and(fn() => new TranslatorConfig(['en'], 'fr'))->toThrow(InvalidConfigurationException::class)
        ->and(fn() => new TranslatorConfig(['fr', 'en'], 'fr', 'de'))->toThrow(InvalidConfigurationException::class);
});

it('checks allowed and source locales', function () {
    $config = new TranslatorConfig();

    expect($config->isAllowed('en'))->toBeTrue()
        ->and($config->isAllowed('de'))->toBeFalse()
        ->and($config->isSource('fr'))->toBeTrue()
        ->and($config->isSource('en'))->toBeFalse()
        ->and(fn() => $config->assertAllowed('de'))->toThrow(UnsupportedLocaleException::class);
});
