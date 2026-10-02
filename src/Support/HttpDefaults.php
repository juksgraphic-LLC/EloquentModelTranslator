<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Support;

use Juksgraphic\EloquentModelTranslator\Exceptions\InvalidConfigurationException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Finds PSR-18 / PSR-17 implementations already installed in the application.
 * Used by providers when the caller does not pass a client or factories.
 *
 * Detection order: Guzzle, Symfony HttpClient, Nyholm, Laminas.
 */
final class HttpDefaults
{
    /**
     * Timeout (seconds) applied to the default client: LLM calls can be slow.
     */
    public const TIMEOUT = 30;

    /**
     * Classes implementing PSR-17 request factories.
     *
     * @var list<class-string>
     */
    private const REQUEST_FACTORIES = [
        'GuzzleHttp\\Psr7\\HttpFactory',
        'Nyholm\\Psr7\\Factory\\Psr17Factory',
        'Symfony\\Component\\HttpClient\\Psr18Client',
        'Laminas\\Diactoros\\RequestFactory',
    ];

    /**
     * Classes implementing PSR-17 stream factories.
     *
     * @var list<class-string>
     */
    private const STREAM_FACTORIES = [
        'GuzzleHttp\\Psr7\\HttpFactory',
        'Nyholm\\Psr7\\Factory\\Psr17Factory',
        'Symfony\\Component\\HttpClient\\Psr18Client',
        'Laminas\\Diactoros\\StreamFactory',
    ];

    /**
     * Static helper: not instantiable.
     */
    private function __construct()
    {
    }

    /**
     * Returns a PSR-18 client.
     *
     * @return ClientInterface
     *
     * @throws InvalidConfigurationException When no supported client is installed.
     */
    public static function client(): ClientInterface
    {
        if (class_exists('GuzzleHttp\\Client')) {
            $client = new \GuzzleHttp\Client(['timeout' => self::TIMEOUT]);

            if ($client instanceof ClientInterface) {
                return $client;
            }
        }

        if (class_exists('Symfony\\Component\\HttpClient\\Psr18Client')) {
            $client = new \Symfony\Component\HttpClient\Psr18Client();

            if ($client instanceof ClientInterface) {
                return $client;
            }
        }

        throw InvalidConfigurationException::missingHttpImplementation(
            'PSR-18 HTTP client',
            'guzzlehttp/guzzle or symfony/http-client'
        );
    }

    /**
     * Returns a PSR-17 request factory.
     *
     * @return RequestFactoryInterface
     *
     * @throws InvalidConfigurationException When no supported factory is installed.
     */
    public static function requestFactory(): RequestFactoryInterface
    {
        return self::first(self::REQUEST_FACTORIES, RequestFactoryInterface::class)
            ?? throw InvalidConfigurationException::missingHttpImplementation(
                'PSR-17 request factory',
                'guzzlehttp/psr7 or nyholm/psr7'
            );
    }

    /**
     * Returns a PSR-17 stream factory.
     *
     * @return StreamFactoryInterface
     *
     * @throws InvalidConfigurationException When no supported factory is installed.
     */
    public static function streamFactory(): StreamFactoryInterface
    {
        return self::first(self::STREAM_FACTORIES, StreamFactoryInterface::class)
            ?? throw InvalidConfigurationException::missingHttpImplementation(
                'PSR-17 stream factory',
                'guzzlehttp/psr7 or nyholm/psr7'
            );
    }

    /**
     * Instantiates the first installed class implementing the interface.
     *
     * @template T of object
     *
     * @param list<class-string> $candidates
     * @param class-string<T> $interface
     * @return T|null
     */
    private static function first(array $candidates, string $interface): ?object
    {
        foreach ($candidates as $class) {
            if (! class_exists($class)) {
                continue;
            }

            $instance = new $class();

            if ($instance instanceof $interface) {
                return $instance;
            }
        }

        return null;
    }
}
