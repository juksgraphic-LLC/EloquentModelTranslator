<?php

use Juksgraphic\EloquentModelTranslator\Exceptions\TranslationFailedException;
use Juksgraphic\EloquentModelTranslator\Prompts\DefaultTranslationPrompt;

it('names the locales in the system prompt', function () {
    $system = (new DefaultTranslationPrompt())->system('fr', 'ht');

    expect($system)->toContain('French (fr)')->toContain('Haitian Creole (ht)');
});

it('appends extra instructions', function () {
    $system = (new DefaultTranslationPrompt(extraInstructions: 'Never translate "Juksgraphic".'))->system('fr', 'en');

    expect($system)->toEndWith('Never translate "Juksgraphic".');
});

it('keeps unknown locale codes as is', function () {
    expect((new DefaultTranslationPrompt())->system('fr', 'de'))->toContain(' de.');
});

it('serialises fields as unescaped JSON', function () {
    expect((new DefaultTranslationPrompt())->user(['title' => 'Été']))->toBe('{"title":"Été"}');
});

it('parses plain and fenced JSON', function () {
    $prompt = new DefaultTranslationPrompt();

    expect($prompt->parse('{"title":"Hello"}', 'test'))->toBe(['title' => 'Hello'])
        ->and($prompt->parse("```json\n{\"title\":\"Hello\"}\n```", 'test'))->toBe(['title' => 'Hello']);
});

it('rejects malformed or non-object content', function () {
    $prompt = new DefaultTranslationPrompt();

    expect(fn () => $prompt->parse('not json', 'test'))->toThrow(TranslationFailedException::class, 'malformed JSON')
        ->and(fn () => $prompt->parse('"just a string"', 'test'))->toThrow(TranslationFailedException::class, 'not a JSON object');
});
