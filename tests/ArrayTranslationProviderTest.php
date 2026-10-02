<?php

use Juksgraphic\EloquentModelTranslator\Providers\ArrayTranslationProvider;

it('prefixes values with the target locale and records calls', function () {
    $provider = new ArrayTranslationProvider();

    $result = $provider->translate(['title' => 'Bonjour'], 'fr', 'en');

    expect($result)->toBe(['title' => '[en] Bonjour'])
        ->and($provider->calls())->toBe([['fields' => ['title' => 'Bonjour'], 'source' => 'fr', 'target' => 'en']]);
});

it('supports a custom format', function () {
    expect((new ArrayTranslationProvider('%s:%s'))->translate(['a' => 'b'], 'fr', 'en'))->toBe(['a' => 'en:b']);
});