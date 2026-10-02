<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Exceptions;

use Throwable;

/**
 * Thrown by providers when a translation could not be produced.
 * Providers must throw instead of returning untranslated content.
 */
class TranslationFailedException extends TranslationException
{

    /**
     * Send an exception when a request has faild
     * @param string $provider
     * @param mixed $previous
     * @return TranslationFailedException
     */
    public static function requestFailed(string $provider, ?Throwable $previous = null): self
    {
        return new self("Provider [{$provider}] request failed.", 0, $previous);
    }

    /**
     * Send an exception when the provider returns an invalid response
     * @param string $provider
     * @param string $reason
     * @return TranslationFailedException
     */
    public static function invalidResponse(string $provider, string $reason): self
    {
        return new self("Provider [{$provider}] returned an invalid response: {$reason}.");
    }

 
    /**
     * Send an exception where the provoder retunrs a response with missing keys
     * @param string $provider
     * @param list<string> $keys
     * @return TranslationFailedException
     */
    public static function missingKeys(string $provider, array $keys): self
    {
        return new self(sprintf(
            'Provider [%s] response is missing keys: %s.',
            $provider,
            implode(', ', $keys)
        ));
    }
}
