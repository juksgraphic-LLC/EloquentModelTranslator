<?php

declare(strict_types=1);

namespace Juksgraphic\EloquentModelTranslator\Tests\Fixtures;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

final class FakeClientException extends RuntimeException implements ClientExceptionInterface {}
