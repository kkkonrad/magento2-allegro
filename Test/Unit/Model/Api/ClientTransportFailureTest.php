<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Api;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response;
use Macopedia\Allegro\Api\Data\TokenInterface;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Api\ApiErrorResponseParser;
use Macopedia\Allegro\Model\Api\Client;
use Macopedia\Allegro\Model\Api\ClientResponseException;
use Macopedia\Allegro\Model\Api\AuthenticationException;
use Macopedia\Allegro\Model\Api\ValidationException;
use Macopedia\Allegro\Model\Api\RateLimitException;
use Macopedia\Allegro\Model\Api\ApiResponseException;
use Macopedia\Allegro\Model\Api\NotFoundException;
use Macopedia\Allegro\Model\Api\TransportException;
use Macopedia\Allegro\Model\Api\Request;
use Macopedia\Allegro\Model\Configuration;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class ClientTransportFailureTest extends TestCase
{
    /**
     * @dataProvider httpFailureProvider
     */
    public function testMapsHttpFailureToSafeDomainException(int $status, string $method): void
    {
        $responses = [];
        $attempts = in_array($status, [429, 500], true) && $method !== 'POST' ? 3 : 1;
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $responses[] = new Response(
                $status,
                ['Retry-After' => '0', 'Trace-Id' => 'trace-' . $status],
                json_encode(['errors' => [[
                    'code' => 'ERROR_' . $status,
                    'path' => 'field',
                    'userMessage' => 'Safe operator message',
                ]]])
            );
        }

        try {
            $this->client($responses)->sendRequest($this->token(), $this->request($method));
            self::fail('A transport exception was expected.');
        } catch (ClientResponseException $exception) {
            $expectedClass = [
                401 => AuthenticationException::class,
                403 => AuthenticationException::class,
                404 => NotFoundException::class,
                422 => ValidationException::class,
                429 => RateLimitException::class,
                500 => ApiResponseException::class,
            ][$status];
            self::assertInstanceOf($expectedClass, $exception);
            self::assertSame($status, $exception->getHttpStatusCode());
            self::assertSame('trace-' . $status, $exception->getRequestId());
            self::assertSame('ERROR_' . $status, $exception->getApiErrors()[0]['code']);
            self::assertStringContainsString('Safe operator message', $exception->getMessage());
        }
    }

    public function testMapsConnectionTimeoutWithoutLeakingRequestData(): void
    {
        $timeout = new ConnectException('Connection timed out with secret=hidden', new PsrRequest('POST', '/test'));

        try {
            $this->client([$timeout])->sendRequest($this->token(), $this->request('POST'));
            self::fail('A transport exception was expected.');
        } catch (ClientResponseException $exception) {
            self::assertInstanceOf(TransportException::class, $exception);
            self::assertNull($exception->getHttpStatusCode());
            self::assertStringNotContainsString('secret=hidden', $exception->getMessage());
            self::assertStringContainsString('Could not connect', $exception->getMessage());
        }
    }

    public function testUsesConfiguredTransportTimeouts(): void
    {
        $guzzle = new GuzzleClient([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(200, [], '{}'),
            ])),
            'http_errors' => true,
        ]);
        $factory = $this->createMock(ClientFactory::class);
        $factory->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (array $arguments): bool {
                return $arguments['config']['connect_timeout'] === 7
                    && $arguments['config']['timeout'] === 44;
            }))
            ->willReturn($guzzle);
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getConnectTimeout')->willReturn(7);
        $configuration->method('getRequestTimeout')->willReturn(44);
        $configuration->method('isDebugModeEnabled')->willReturn(false);

        $client = new Client(
            new Json(),
            $this->createMock(Logger::class),
            $configuration,
            $factory,
            new ApiErrorResponseParser()
        );

        self::assertSame([], $client->sendRequest($this->token(), $this->request('GET')));
    }

    public function testMapsErrorsInSuccessfulResponseToValidationException(): void
    {
        $response = new Response(200, [], json_encode(['errors' => [[
            'code' => 'VALIDATION_ERROR',
            'path' => 'name',
            'userMessage' => 'Name is invalid',
        ]]]));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Name is invalid');

        $this->client([$response])->sendRequest($this->token(), $this->request('POST'));
    }

    public static function httpFailureProvider(): array
    {
        return [
            'unauthorized' => [401, 'GET'],
            'forbidden' => [403, 'GET'],
            'not found' => [404, 'GET'],
            'validation' => [422, 'POST'],
            'rate limit' => [429, 'GET'],
            'server error' => [500, 'GET'],
        ];
    }

    private function client(array $responses): Client
    {
        $guzzle = new GuzzleClient([
            'handler' => HandlerStack::create(new MockHandler($responses)),
            'http_errors' => true,
        ]);
        $factory = $this->createMock(ClientFactory::class);
        $factory->method('create')->willReturn($guzzle);
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('isDebugModeEnabled')->willReturn(false);

        return new Client(
            new Json(),
            $this->createMock(Logger::class),
            $configuration,
            $factory,
            new ApiErrorResponseParser()
        );
    }

    private function token(): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getAccessToken')->willReturn('test-token');
        return $token;
    }

    private function request(string $method): Request
    {
        return (new Request())
            ->setUri('/test')
            ->setMethod($method)
            ->setIsSandbox(true)
            ->setIsPublic();
    }
}
