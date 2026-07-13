<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Api;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Macopedia\Allegro\Api\Data\TokenInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Configuration;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientFactory;
use Magento\Framework\Webapi\Rest\Request as MagentoRequest;
use Psr\Http\Message\ResponseInterface;

/**
 * Processes API requests and responses
 */
class Client
{
    public const API_URL = 'https://api.allegro.pl/';
    public const SANDBOX_API_URL = 'https://api.allegro.pl.allegrosandbox.pl/';

    private const MAX_REQUEST_ATTEMPTS = 3;
    private const CONNECT_TIMEOUT_SECONDS = 10;
    private const REQUEST_TIMEOUT_SECONDS = 120;
    private const RETRY_BASE_DELAY_MICROSECONDS = 250000;

    /** @var Json */
    private $json;

    /** @var Logger */
    private $logger;

    /** @var Configuration */
    private $config;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /** @var ApiErrorResponseParser */
    private $errorResponseParser;

    /** @var int|null */
    private $lastResponseStatusCode;

    /** @var array<string, string> */
    private $lastResponseHeaders = [];

    /**
     * Client constructor.
     * @param Json $json
     * @param Logger $logger
     * @param Configuration $config
     * @param ClientFactory $clientFactory
     * @param ApiErrorResponseParser $errorResponseParser
     */
    public function __construct(
        Json $json,
        Logger $logger,
        Configuration $config,
        ClientFactory $clientFactory,
        ApiErrorResponseParser $errorResponseParser
    ) {
        $this->json = $json;
        $this->logger = $logger;
        $this->config = $config;
        $this->clientFactory = $clientFactory;
        $this->errorResponseParser = $errorResponseParser;
    }

    /**
     * @param TokenInterface $token
     * @param Request $request
     * @return mixed
     * @throws ClientResponseErrorException
     * @throws ClientResponseException
     */
    public function sendRequest(TokenInterface $token, Request $request)
    {
        $this->lastResponseStatusCode = null;
        $this->lastResponseHeaders = [];

        try {
            $json = $this->sendHttpRequest($token, $request);
        } catch (GuzzleException $e) {
            throw $this->createTransportException($e, $request);
        } catch (\Exception $e) {
            $this->logger->apiFailure('Unexpected Allegro API client failure', [
                'method' => $request->getMethod(),
                'uri' => $request->getUri(),
                'exception_type' => get_class($e),
            ]);

            throw new ClientResponseException(
                __('Unexpected error while communicating with Allegro API.'),
                $e,
                (int)$e->getCode()
            );
        }

        try {
            $response = $this->json->unserialize($json);
        } catch (\InvalidArgumentException $e) {
            $this->logger->apiFailure('Invalid JSON returned by Allegro API', [
                'method' => $request->getMethod(),
                'uri' => $request->getUri(),
            ]);

            throw new ClientResponseException(
                __('Allegro API returned an invalid response.'),
                $e
            );
        }

        if (!is_array($response)) {
            throw new ClientResponseException(__('Allegro API returned an invalid response structure.'));
        }

        if (isset($response['errors'])) {
            $errors = $this->errorResponseParser->parse($json);
            $message = $this->errorResponseParser->format($errors);
            throw new ClientResponseErrorException(
                __(
                    'Allegro API rejected the request: %1',
                    $message ?: (string)__('Unknown validation error')
                ),
                null,
                0,
                null,
                null,
                $errors
            );
        }

        return $response;
    }

    /**
     * @param TokenInterface $token
     * @param Request $request
     * @return array
     */
    private function prepareHeaders(TokenInterface $token, Request $request)
    {
        return [
            'Authorization' => 'Bearer ' . $token->getAccessToken(),
            'Accept' => $request->getAcceptType() ?: Request::TYPE_BETA,
            'Content-Type' => $request->getContentType() ?: Request::TYPE_BETA
        ];
    }

    /**
     * @param Request $request
     * @return string
     */
    private function getApiUrl(Request $request)
    {
        if ($request->isSandbox()) {
            return self::SANDBOX_API_URL;
        }

        return self::API_URL;
    }

    /**
     * @param TokenInterface $token
     * @param Request $request
     * @return string
     * @throws GuzzleException
     */
    private function sendHttpRequest(TokenInterface $token, Request $request)
    {
        $method = $request->getMethod();
        $uri = $request->getUri();
        $body = $request->getBody();

        $params = [];
        $params['headers'] = $this->prepareHeaders($token, $request);

        $content = preg_match('/application\/.*json/', $request->getContentType())
            ? 'json'
            : 'body';

        if ($method !== MagentoRequest::HTTP_METHOD_GET) {
            $params[$content] = $body;
        }
        $client = $this->clientFactory->create([
            'config' => [
                'base_uri' => $this->getApiUrl($request),
                'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                'http_errors' => true,
            ],
        ]);

        if (!$this->config->isDebugModeEnabled()) {
            return $this->getResponse($client, $method, $uri, $params);
        }

        $requestId = bin2hex(random_bytes(8));
        $this->logger->debug('Allegro API HTTP request', [
            'request_id' => $requestId,
            'method' => $method,
            'uri' => $uri,
            'sandbox' => $request->isSandbox(),
            'body_fields' => is_array($body) ? array_keys($body) : [],
        ]);
        $response = $this->getResponse($client, $method, $uri, $params);
        $this->logger->debug('Allegro API HTTP response', [
            'request_id' => $requestId,
            'method' => $method,
            'uri' => $uri,
            'response_bytes' => strlen($response),
        ]);

        return $response;
    }

    /**
     * @param GuzzleClient $client
     * @param string $method
     * @param string $uri
     * @param array $params
     * @return string
     * @throws GuzzleException
     */
    public function getResponse(GuzzleClient $client, string $method, string $uri, array $params)
    {
        $attempt = 0;
        do {
            $attempt++;
            try {
                $response = $client->request($method, $uri, $params);
                $this->lastResponseStatusCode = $response->getStatusCode();
                $this->lastResponseHeaders = [];
                foreach ($response->getHeaders() as $name => $values) {
                    $this->lastResponseHeaders[strtolower($name)] = implode(', ', $values);
                }
                break;
            } catch (GuzzleException $e) {
                if (!$this->shouldRetry($e, $method, $attempt)) {
                    throw $e;
                }

                usleep($this->retryDelayMicroseconds($e, $attempt));
            }
        } while ($attempt < self::MAX_REQUEST_ATTEMPTS);

        $contents = $response->getBody()->getContents();

        if (empty($contents)) {
            return $this->json->serialize(
                ['statusCode' => $response->getStatusCode(), 'reasonPhrase' => $response->getReasonPhrase()]
            );
        }

        return $contents;
    }

    public function getLastResponseStatusCode(): ?int
    {
        return $this->lastResponseStatusCode;
    }

    public function getLastResponseHeader(string $name): string
    {
        return $this->lastResponseHeaders[strtolower($name)] ?? '';
    }

    private function createTransportException(
        GuzzleException $exception,
        Request $request
    ): ClientResponseException {
        $response = $exception instanceof RequestException ? $exception->getResponse() : null;
        $statusCode = $response ? $response->getStatusCode() : null;
        $requestId = $this->extractRequestId($response);
        $errors = $response
            ? $this->errorResponseParser->parse((string)$response->getBody())
            : [];
        $apiMessage = $this->errorResponseParser->format($errors);

        $this->logger->apiFailure('Allegro API request failed', [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'status_code' => $statusCode,
            'request_id' => $requestId,
            'error_codes' => array_values(array_filter(array_column($errors, 'code'))),
            'error_paths' => array_values(array_filter(array_column($errors, 'path'))),
            'exception_type' => get_class($exception),
        ]);

        $message = $apiMessage ?: ($statusCode
            ? (string)__('Allegro API request failed with HTTP status %1.', $statusCode)
            : (string)__('Could not connect to Allegro API.'));

        $cause = $exception instanceof \Exception ? $exception : null;

        return new ClientResponseException(
            __($message),
            $cause,
            (int)$exception->getCode(),
            $statusCode,
            $requestId,
            $errors
        );
    }

    private function extractRequestId(?ResponseInterface $response): ?string
    {
        if (!$response) {
            return null;
        }

        foreach (['Trace-Id', 'X-Request-Id', 'Request-Id'] as $header) {
            $value = trim($response->getHeaderLine($header));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function shouldRetry(GuzzleException $exception, string $method, int $attempt): bool
    {
        if ($attempt >= self::MAX_REQUEST_ATTEMPTS) {
            return false;
        }

        if (!in_array(strtoupper($method), ['GET', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        if (!$exception instanceof RequestException || !$exception->getResponse()) {
            return true;
        }

        $statusCode = $exception->getResponse()->getStatusCode();
        return $statusCode === 429 || $statusCode >= 500;
    }

    private function retryDelayMicroseconds(GuzzleException $exception, int $attempt): int
    {
        if ($exception instanceof RequestException && $exception->getResponse()) {
            $retryAfter = trim($exception->getResponse()->getHeaderLine('Retry-After'));
            if (ctype_digit($retryAfter)) {
                return min((int)$retryAfter, 5) * 1000000;
            }
        }

        $exponentialDelay = self::RETRY_BASE_DELAY_MICROSECONDS * (2 ** ($attempt - 1));
        return $exponentialDelay + random_int(0, 100000);
    }
}
