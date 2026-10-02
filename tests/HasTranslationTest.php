<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Juksgraphic\EloquentModelTranslator\Exceptions\ModelNotSavedException;
use Juksgraphic\EloquentModelTranslator\Exceptions\NotTranslatableException;
use Juksgraphic\EloquentModelTranslator\Exceptions\UnsupportedLocaleException;
use Juksgraphic\EloquentModelTranslator\Models\Translation;
use Juksgraphic\EloquentModelTranslator\Tests\Fixtures\Article;
use Juksgraphic\EloquentModelTranslator\Tests\Fixtures\SoftArticle;

beforeEach(function () {
    $this->article = Article::create(['title' => 'Bonjour', 'body' => 'Le monde', 'slug' => 'bonjour']);
});

it('keeps the source value in the model column', function () {
    expect($this->article->title)->toBe('Bonjour')
        ->and($this->article->getAttributes()['title'])->toBe('Bonjour');
});

it('stores a translation on save and reads it back', function () {
    $this->article->setTranslation('title', 'en', 'Hello')->save();

    expect($this->article->setLocale('en')->title)->toBe('Hello')
        ->and(Translation::count())->toBe(1);
});

it('follows the resolved locale when none is forced', function () {
    $this->article->setTranslation('title', 'en', 'Hello')->save();

    $this->useLocale('en');

    expect($this->article->title)->toBe('Hello');
});

it('lets a forced locale win over the resolved one', function () {
    $this->article->setTranslation('title', 'en', 'Hello')->save();

    $this->useLocale('es');

    expect($this->article->setLocale('en')->title)->toBe('Hello')
        ->and($this->article->setLocale(null)->title)->toBe('Bonjour');
});

it('falls back to the source value when a translation is missing', function () {
    expect($this->article->setLocale('es')->title)->toBe('Bonjour');
});

it('falls back to the source value when a translation is empty', function () {
    $this->article->setTranslation('title', 'en', '')->save();

    expect($this->article->setLocale('en')->title)->toBe('Bonjour');
});

it('returns null without fallback', function () {
    expect($this->article->getTranslation('title', 'es', false))->toBeNull();
});

it('falls back to the fallback locale when the resolved locale is unsupported', function () {
    $this->useLocale('de');

    expect($this->article->title)->toBe('Bonjour');
});

it('lists every locale without fallback', function () {
    $this->article->setTranslation('title', 'en', 'Hello')->save();

    expect($this->article->getTranslations('title'))->toBe([
        'fr' => 'Bonjour',
        'en' => 'Hello',
        'es' => null,
        'ht' => null,
    ]);
});

it('reports whether a non-empty translation exists', function () {
    $this->article->setTranslation('title', 'en', 'Hello')->save();

    expect($this->article->hasTranslation('title', 'en'))->toBeTrue()
        ->and($this->article->hasTranslation('title', 'es'))->toBeFalse()
        ->and($this->article->hasTranslation('title', 'fr'))->toBeTrue();
});

it('sets the model attribute when the locale is the source', function () {
    $this->article->setTranslation('title', 'fr', 'Salut');

    expect($this->article->getAttributes()['title'])->toBe('Salut')
        ->and(Translation::count())->toBe(0);
});

it('reads staged translations before they are saved', function () {
    $this->article->setTranslation('title', 'en', 'Draft');

    expect($this->article->setLocale('en')->title)->toBe('Draft')
        ->and(Translation::count())->toBe(0);
});

it('merges new attributes with existing content of the same locale', function () {
    $this->article->setTranslation('title', 'en', 'Hello')->save();
    $this->article->setTranslation('body', 'en', 'The world')->save();

    expect(Translation::count())->toBe(1)
        ->and(Translation::first()->content)->toEqual(['title' => 'Hello', 'body' => 'The world']);
});

it('stages several attributes at once', function () {
    $this->article->setTranslations('en', ['title' => 'Hello', 'body' => 'The world'])->save();

    expect($this->article->setLocale('en')->body)->toBe('The world');
});

it('writes translations of a new model once it is created', function () {
    $article = new Article(['title' => 'Salut', 'slug' => 'salut']);
    $article->setTranslation('title', 'en', 'Hi');
    $article->save();

    expect($article->exists)->toBeTrue()
        ->and($article->setLocale('en')->title)->toBe('Hi')
        ->and(Translation::count())->toBe(1);
});

it('refuses to flush translations of an unsaved model', function () {
    $article = new Article(['title' => 'Salut']);
    $article->setTranslation('title', 'en', 'Hi');

    expect(fn () => $article->flushTranslations())->toThrow(ModelNotSavedException::class);
});

it('rejects non translatable attributes', function () {
    expect(fn () => $this->article->setTranslation('slug', 'en', 'x'))
        ->toThrow(NotTranslatableException::class)
        ->and(fn () => $this->article->getTranslation('slug'))
        ->toThrow(NotTranslatableException::class);
});

it('rejects unsupported locales', function () {
    expect(fn () => $this->article->setLocale('de'))->toThrow(UnsupportedLocaleException::class)
        ->and(fn () => $this->article->setTranslation('title', 'de', 'x'))->toThrow(UnsupportedLocaleException::class)
        ->and(fn () => $this->article->getTranslation('title', 'de'))->toThrow(UnsupportedLocaleException::class);
});

it('applies the active locale in toArray()', function () {
    $this->article->setTranslation('title', 'en', 'Hello')->save();

    expect($this->article->setLocale('en')->toArray()['title'])->toBe('Hello')
        ->and($this->article->setLocale('fr')->toArray()['title'])->toBe('Bonjour');
});

it('deletes translations with the model', function () {
    $this->article->setTranslation('title', 'en', 'Hello')->save();
    $this->article->delete();

    expect(Translation::count())->toBe(0);
});

it('keeps translations on soft delete and removes them on force delete', function () {
    $soft = SoftArticle::create(['title' => 'Bonjour']);
    $soft->setTranslation('title', 'en', 'Hello')->save();

    $soft->delete();
    expect(Translation::count())->toBe(1);

    $soft->forceDelete();
    expect(Translation::count())->toBe(0);
});

it('eager loads translations without N+1 queries', function () {
    foreach (range(1, 3) as $i) {
        Article::create(['title' => "Titre {$i}"])->setTranslation('title', 'en', "Title {$i}")->save();
    }

    $this->useLocale('en');

    $connection = Capsule::connection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();

    $titles = Article::withTranslations()->get()->map(fn (Article $a) => $a->title)->all();

    expect($connection->getQueryLog())->toHaveCount(2)
        ->and($titles)->toContain('Title 1', 'Title 2', 'Title 3');
});
