<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Tests\Fixtures;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * PSR-18 client replaying a queue of responses (or exceptions) and recording requests.
 */
final class FakeClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param list<ResponseInterface|Throwable> $queue
     */
    public function __construct(private array $queue)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        $next = array_shift($this->queue);

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next ?? new Response(500);
    }

    /**
     * Builds a chat-completions response whose message content is $content.
     */
    public static function chat(string $content, int $status = 200): Response
    {
        return new Response($status, [], (string) json_encode([
            'choices' => [['message' => ['content' => $content]]],
        ]));
    }
}
