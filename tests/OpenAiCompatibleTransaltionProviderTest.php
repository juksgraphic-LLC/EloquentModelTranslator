<?php

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Juksgraphic\EloquentModelTranslator\Contracts\TranslationPrompt;
use Juksgraphic\EloquentModelTranslator\Exceptions\TranslationFailedException;
use Juksgraphic\EloquentModelTranslator\Providers\CerebrasTranslationProvider;
use Juksgraphic\EloquentModelTranslator\Providers\OpenAiCompatibleTranslationProvider;
use Juksgraphic\EloquentModelTranslator\Tests\Fixtures\FakeClient;
use Juksgraphic\EloquentModelTranslator\Tests\Fixtures\FakeClientException;

function makeProvider(FakeClient $client, ?TranslationPrompt $prompt = null): OpenAiCompatibleTranslationProvider
{
    $factory = new HttpFactory();

    return new OpenAiCompatibleTranslationProvider(
        'secret',
        'test-model',
        $client,
        $factory,
        $factory,
        baseUri: 'https://llm.test/v1/',
        prompt: $prompt,
        maxRetries: 2,
        retryDelayMs: 0,
    );
}

it('translates and sends a well formed request', function () {
    $client = new FakeClient([FakeClient::chat('{"title":"Hello"}')]);
    $result = makeProvider($client)->translate(['title' => 'Bonjour'], 'fr', 'en');
    $request = $client->requests[0];
    $payload = json_decode((string) $request->getBody(), true);

    expect($result)->toBe(['title' => 'Hello'])
        ->and((string) $request->getUri())->toBe('https://llm.test/v1/chat/completions')
        ->and($request->getMethod())->toBe('POST')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer secret')
        ->and($payload['model'])->toBe('test-model')
        ->and($payload['messages'][1]['content'])->toBe('{"title":"Bonjour"}');
});

it('accepts markdown fenced answers', function () {
    $client = new FakeClient([FakeClient::chat("```json\n{\"title\":\"Hello\"}\n```")]);

    expect(makeProvider($client)->translate(['title' => 'Bonjour'], 'fr', 'en'))->toBe(['title' => 'Hello']);
});

it('does not call the API for empty fields', function () {
    $client = new FakeClient([]);

    expect(makeProvider($client)->translate([], 'fr', 'en'))->toBe([])
        ->and($client->requests)->toBe([]);
});

it('retries on 5xx and 429 then succeeds', function () {
    $client = new FakeClient([new Response(500), new Response(429), FakeClient::chat('{"a":"b"}')]);

    expect(makeProvider($client)->translate(['a' => 'x'], 'fr', 'en'))->toBe(['a' => 'b'])
        ->and($client->requests)->toHaveCount(3);
});

it('gives up after the maximum number of retries', function () {
    $client = new FakeClient([new Response(500), new Response(500), new Response(500)]);

    expect(fn () => makeProvider($client)->translate(['a' => 'x'], 'fr', 'en'))
        ->toThrow(TranslationFailedException::class, 'HTTP 500')
        ->and($client->requests)->toHaveCount(3);
});

it('does not retry on 4xx', function () {
    $client = new FakeClient([new Response(401)]);

    expect(fn () => makeProvider($client)->translate(['a' => 'x'], 'fr', 'en'))
        ->toThrow(TranslationFailedException::class, 'HTTP 401')
        ->and($client->requests)->toHaveCount(1);
});

it('retries on network errors then throws', function () {
    $e = new FakeClientException('boom');
    $client = new FakeClient([$e, $e, $e]);

    expect(fn () => makeProvider($client)->translate(['a' => 'x'], 'fr', 'en'))
        ->toThrow(TranslationFailedException::class, 'request failed')
        ->and($client->requests)->toHaveCount(3);
});

it('throws on empty or malformed API answers', function () {
    expect(fn () => makeProvider(new FakeClient([new Response(200, [], 'not json')]))->translate(['a' => 'x'], 'fr', 'en'))
        ->toThrow(TranslationFailedException::class, 'malformed JSON')
        ->and(fn () => makeProvider(new FakeClient([FakeClient::chat('')]))->translate(['a' => 'x'], 'fr', 'en'))
        ->toThrow(TranslationFailedException::class, 'empty content')
        ->and(fn () => makeProvider(new FakeClient([FakeClient::chat('oops')]))->translate(['a' => 'x'], 'fr', 'en'))
        ->toThrow(TranslationFailedException::class, 'malformed JSON');
});

it('uses an injected prompt', function () {
    $prompt = new class () implements TranslationPrompt {
        public function system(string $sourceLocale, string $targetLocale): string
        {
            return "SYS {$sourceLocale}>{$targetLocale}";
        }

        public function user(array $fields): string
        {
            return 'USER';
        }

        public function parse(string $content, string $provider): array
        {
            return ['parsed' => $content];
        }
    };

    $client = new FakeClient([FakeClient::chat('raw')]);
    $result = makeProvider($client, $prompt)->translate(['a' => 'x'], 'fr', 'en');
    $payload = json_decode((string) $client->requests[0]->getBody(), true);

    expect($result)->toBe(['parsed' => 'raw'])
        ->and($payload['messages'][0]['content'])->toBe('SYS fr>en')
        ->and($payload['messages'][1]['content'])->toBe('USER');
});

it('points Cerebras to its own endpoint by default', function () {
    $client = new FakeClient([FakeClient::chat('{"a":"b"}')]);
    $factory = new HttpFactory();

    (new CerebrasTranslationProvider('k', 'm', $client, $factory, $factory))->translate(['a' => 'x'], 'fr', 'en');

    expect((string) $client->requests[0]->getUri())->toBe('https://api.cerebras.ai/v1/chat/completions');
});
