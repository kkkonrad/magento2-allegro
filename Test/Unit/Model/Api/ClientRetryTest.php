<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Api;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Api\ApiErrorResponseParser;
use Macopedia\Allegro\Model\Api\Client;
use Macopedia\Allegro\Model\Configuration;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class ClientRetryTest extends TestCase
{
    public function testRetriesPatchAfterTransientServerError(): void
    {
        $history = [];
        $guzzle = $this->guzzle([
            new Response(500, ['Retry-After' => '0'], '{"errors":[]}'),
            new Response(200, ['X-Request-Id' => 'request-2'], '{"id":"offer-1"}'),
        ], $history);

        $body = $this->client()->getResponse($guzzle, 'PATCH', '/sale/product-offers/offer-1', []);

        self::assertSame('{"id":"offer-1"}', $body);
        self::assertCount(2, $history);
    }

    public function testDoesNotRetryPostAfterServerError(): void
    {
        $history = [];
        $guzzle = $this->guzzle([
            new Response(500, ['Retry-After' => '0'], '{"errors":[]}'),
            new Response(200, [], '{"id":"unexpected"}'),
        ], $history);

        try {
            $this->client()->getResponse($guzzle, 'POST', '/sale/product-offers', []);
            self::fail('POST should not be retried.');
        } catch (\GuzzleHttp\Exception\ServerException $exception) {
            self::assertCount(1, $history);
        }
    }

    private function client(): Client
    {
        return new Client(
            new Json(),
            $this->createMock(Logger::class),
            $this->createMock(Configuration::class),
            $this->createMock(\GuzzleHttp\ClientFactory::class),
            new ApiErrorResponseParser()
        );
    }

    private function guzzle(array $responses, array &$history): GuzzleClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));
        return new GuzzleClient(['handler' => $stack, 'http_errors' => true]);
    }
}
