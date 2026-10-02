<?php

use Juksgraphic\EloquentModelTranslator\Exceptions\NotTranslatableException;
use Juksgraphic\EloquentModelTranslator\Exceptions\TranslationFailedException;
use Juksgraphic\EloquentModelTranslator\Models\Translation;
use Juksgraphic\EloquentModelTranslator\Providers\ArrayTranslationProvider;
use Juksgraphic\EloquentModelTranslator\Services\TranslationService;
use Juksgraphic\EloquentModelTranslator\Tests\Fixtures\Article;
use Juksgraphic\EloquentModelTranslator\Tests\Fixtures\CallbackProvider;

beforeEach(function () {
    $this->article = Article::create(['title' => 'Bonjour', 'body' => 'Le monde', 'slug' => 'bonjour']);
    $this->provider = new ArrayTranslationProvider();
    $this->service = new TranslationService($this->provider);
});

it('translates into every target locale', function () {
    $this->service->translate($this->article);

    expect($this->provider->calls())->toHaveCount(3)
        ->and($this->article->getTranslation('title', 'en'))->toBe('[en] Bonjour')
        ->and($this->article->getTranslation('body', 'ht'))->toBe('[ht] Le monde')
        ->and(Translation::count())->toBe(3);
});

it('keeps existing manual translations by default', function () {
    $this->service->saveTranslations($this->article, ['en' => ['title' => 'My own title']]);

    $this->service->translate($this->article);

    $enCall = collect($this->provider->calls())->firstWhere('target', 'en');

    expect(array_keys($enCall['fields']))->toBe(['body'])
        ->and($this->article->getTranslation('title', 'en'))->toBe('My own title')
        ->and($this->article->getTranslation('body', 'en'))->toBe('[en] Le monde');
});

it('replaces existing translations when overwrite is true', function () {
    $this->service->saveTranslations($this->article, ['en' => ['title' => 'My own title']]);

    $this->service->translate($this->article, overwrite: true);

    expect($this->article->getTranslation('title', 'en'))->toBe('[en] Bonjour');
});

it('limits translation to the given attributes', function () {
    $this->service->translate($this->article, ['title']);

    expect($this->article->hasTranslation('title', 'en'))->toBeTrue()
        ->and($this->article->hasTranslation('body', 'en'))->toBeFalse();
});

it('skips empty source values and does not call the provider when nothing is left', function () {
    $empty = Article::create(['title' => '  ', 'body' => null]);

    $this->service->translate($empty);

    expect($this->provider->calls())->toBe([])
        ->and(Translation::count())->toBe(0);
});

it('rejects non translatable attributes', function () {
    expect(fn () => $this->service->translate($this->article, ['slug']))
        ->toThrow(NotTranslatableException::class);
});

it('writes nothing when one locale fails', function () {
    $provider = new CallbackProvider(function (array $fields, string $source, string $target): array {
        if ($target === 'ht') {
            throw TranslationFailedException::requestFailed('test');
        }

        return array_map(fn (string $v): string => "[{$target}] {$v}", $fields);
    });

    expect(fn () => (new TranslationService($provider))->translate($this->article))
        ->toThrow(TranslationFailedException::class)
        ->and(Translation::count())->toBe(0);
});

it('rejects a response with missing keys', function () {
    $provider = new CallbackProvider(fn (array $fields): array => ['title' => 'x']);

    expect(fn () => (new TranslationService($provider))->translate($this->article))
        ->toThrow(TranslationFailedException::class, 'missing keys');
});

it('rejects a response with an empty value', function () {
    $provider = new CallbackProvider(fn (array $fields): array => array_map(fn () => '', $fields));

    expect(fn () => (new TranslationService($provider))->translate($this->article))
        ->toThrow(TranslationFailedException::class);
});
