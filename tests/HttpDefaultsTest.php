<?php

use Juksgraphic\EloquentModelTranslator\Providers\CerebrasTranslationProvider;
use Juksgraphic\EloquentModelTranslator\Providers\OpenAiCompatibleTranslationProvider;
use Juksgraphic\EloquentModelTranslator\Support\HttpDefaults;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

it('finds installed PSR-18 and PSR-17 implementations', function () {
    expect(HttpDefaults::client())->toBeInstanceOf(ClientInterface::class)
        ->and(HttpDefaults::requestFactory())->toBeInstanceOf(RequestFactoryInterface::class)
        ->and(HttpDefaults::streamFactory())->toBeInstanceOf(StreamFactoryInterface::class);
});

it('builds providers without client or factories', function () {
    expect(new OpenAiCompatibleTranslationProvider('key', 'model'))->toBeInstanceOf(OpenAiCompatibleTranslationProvider::class)
        ->and(new CerebrasTranslationProvider('key', 'model'))->toBeInstanceOf(CerebrasTranslationProvider::class);
});
