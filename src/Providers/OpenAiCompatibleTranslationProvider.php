<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Providers;

use JsonException;
use Juksgraphic\EloquentModelTranslator\Contracts\TranslationPrompt;
use Juksgraphic\EloquentModelTranslator\Contracts\TranslationProvider;
use Juksgraphic\EloquentModelTranslator\Exceptions\InvalidConfigurationException;
use Juksgraphic\EloquentModelTranslator\Exceptions\TranslationFailedException;
use Juksgraphic\EloquentModelTranslator\Prompts\DefaultTranslationPrompt;
use Juksgraphic\EloquentModelTranslator\Support\HttpDefaults;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Generic provider for any OpenAI-style /chat/completions API (OpenAI, Cerebras, Groq, Mistral, Ollama...).
 * HTTP goes through PSR-18, the prompt through TranslationPrompt.
 */
class OpenAiCompatibleTranslationProvider implements TranslationProvider
{
    protected string $name = 'openai-compatible';

    protected ClientInterface $client;

    protected RequestFactoryInterface $requestFactory;

    protected StreamFactoryInterface $streamFactory;

    protected TranslationPrompt $prompt;

    /**
     * @param string $apiKey
     * @param string $model Model id (check the provider documentation).
     * @param ClientInterface|null $client PSR-18 client. Defaults to an installed one (Guzzle, Symfony HttpClient).
     * @param RequestFactoryInterface|null $requestFactory PSR-17 factory. Defaults to the client if it is one, else an installed one.
     * @param StreamFactoryInterface|null $streamFactory PSR-17 factory. Defaults to the client if it is one, else an installed one.
     * @param string $baseUri
     * @param TranslationPrompt|null $prompt Defaults to DefaultTranslationPrompt.
     * @param int $maxRetries Retries on network errors, 429 and 5xx.
     * @param int $retryDelayMs Base delay, doubled on each retry.
     * @param float $temperature
     *
     * @throws InvalidConfigurationException When a default HTTP implementation is needed but none is installed.
     */
    public function __construct(
        protected string $apiKey,
        protected string $model,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        protected string $baseUri = 'https://api.openai.com/v1/',
        ?TranslationPrompt $prompt = null,
        protected int $maxRetries = 2,
        protected int $retryDelayMs = 500,
        protected float $temperature = 0.2,
    ) {
        $this->client = $client ?? HttpDefaults::client();
        $this->requestFactory = $requestFactory
            ?? ($this->client instanceof RequestFactoryInterface ? $this->client : HttpDefaults::requestFactory());
        $this->streamFactory = $streamFactory
            ?? ($this->client instanceof StreamFactoryInterface ? $this->client : HttpDefaults::streamFactory());
        $this->prompt = $prompt ?? new DefaultTranslationPrompt();
    }

    public function translate(array $fields, string $sourceLocale, string $targetLocale): array
    {
        if ($fields === []) {
            return [];
        }

        $response = $this->send($this->buildRequest($fields, $sourceLocale, $targetLocale));

        return $this->parseResponse($response);
    }

    /**
     * @param array<string, string> $fields
     */
    protected function buildRequest(array $fields, string $sourceLocale, string $targetLocale): RequestInterface
    {
        try {
            $body = json_encode([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->prompt->system($sourceLocale, $targetLocale)],
                    ['role' => 'user', 'content' => $this->prompt->user($fields)],
                ],
                'temperature' => $this->temperature,
                'response_format' => ['type' => 'json_object'],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw TranslationFailedException::requestFailed($this->name, $e);
        }

        return $this->requestFactory
            ->createRequest('POST', rtrim($this->baseUri, '/') . '/chat/completions')
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($body));
    }

    protected function send(RequestInterface $request): ResponseInterface
    {
        $attempt = 0;

        while (true) {
            try {
                $response = $this->client->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                if ($attempt >= $this->maxRetries) {
                    throw TranslationFailedException::requestFailed($this->name, $e);
                }

                $this->backoff(++$attempt);

                continue;
            }

            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                return $response;
            }

            if (($status === 429 || $status >= 500) && $attempt < $this->maxRetries) {
                $this->backoff(++$attempt);

                continue;
            }

            throw TranslationFailedException::invalidResponse($this->name, "HTTP {$status}");
        }
    }

    /**
     * Exponential backoff. Override in tests.
     */
    protected function backoff(int $attempt): void
    {
        usleep($this->retryDelayMs * (2 ** ($attempt - 1)) * 1000);
    }

    /**
     * @return array<string, string>
     */
    protected function parseResponse(ResponseInterface $response): array
    {
        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw TranslationFailedException::invalidResponse($this->name, 'malformed JSON');
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (! is_string($content) || $content === '') {
            throw TranslationFailedException::invalidResponse($this->name, 'empty content');
        }

        return $this->prompt->parse($content, $this->name);
    }
}
