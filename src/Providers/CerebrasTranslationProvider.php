<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Providers;

use Juksgraphic\EloquentModelTranslator\Contracts\TranslationPrompt;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Cerebras is OpenAI-compatible: only the name and the base URI differ.
 * The client and factories are optional: installed implementations are detected.
 */
class CerebrasTranslationProvider extends OpenAiCompatibleTranslationProvider
{
    protected string $name = 'cerebras';

    public function __construct(
        string $apiKey,
        string $model,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        string $baseUri = 'https://api.cerebras.ai/v1/',
        ?TranslationPrompt $prompt = null,
        int $maxRetries = 2,
        int $retryDelayMs = 500,
        float $temperature = 0.2,
    ) {
        parent::__construct(
            $apiKey,
            $model,
            $client,
            $requestFactory,
            $streamFactory,
            $baseUri,
            $prompt,
            $maxRetries,
            $retryDelayMs,
            $temperature
        );
    }
}
