<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Macopedia\Allegro\Api\Data\TokenInterface;
use Macopedia\Allegro\Api\Data\TokenInterfaceFactory;
use Macopedia\Allegro\Logger\Logger;
use Magento\Backend\Model\Url;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Http\Message\ResponseInterface;
use Macopedia\Allegro\Model\Configuration;

/**
 * Class responsible for authentication with Allegro API
 */
class Auth
{
    const OAUTH_URL_CONFIG_KEY = 'allegro/general/authentication_url';
    const SANDBOX_OAUTH_URL_CONFIG_KEY = 'allegro/general/sandbox_authentication_url';

    /** @var Credentials */
    private $credentials;

    /** @var Client */
    private $client;

    /** @var TokenInterfaceFactory */
    private $tokenFactory;

    /** @var Logger */
    private $logger;

    /** @var Url */
    private $url;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var Json */
    private $json;

    /** @var ApiErrorResponseParser */
    private $errorResponseParser;

    /** @var OAuthStateManager */
    private $stateManager;

    /** @var Configuration */
    private $configuration;

    /**
     * @param Credentials $credentials
     * @param Client $client
     * @param TokenInterfaceFactory $tokenFactory
     * @param Logger $logger
     * @param Url $url
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Credentials $credentials,
        Client $client,
        TokenInterfaceFactory $tokenFactory,
        Logger $logger,
        Url $url,
        ScopeConfigInterface $scopeConfig,
        Json $json,
        ApiErrorResponseParser $errorResponseParser,
        OAuthStateManager $stateManager,
        Configuration $configuration
    ) {
        $this->credentials = $credentials;
        $this->client = $client;
        $this->tokenFactory = $tokenFactory;
        $this->logger = $logger;
        $this->url = $url;
        $this->scopeConfig = $scopeConfig;
        $this->json = $json;
        $this->errorResponseParser = $errorResponseParser;
        $this->stateManager = $stateManager;
        $this->configuration = $configuration;
    }

    /**
     * @param $authCode
     * @return TokenInterface
     * @throws ClientException
     */
    public function getNewToken($authCode)
    {
        try {
            $response = $this->client->post(
                $this->getOauthUrl() . '/token',
                $this->getNewTokenData($authCode)
            );

        } catch (GuzzleException $e) {
            throw $this->createAuthenticationException($e, 'authorization_code');
        }

        return $this->createTokenFromResponse($response);
    }

    /**
     * @param TokenInterface $token
     * @return TokenInterface
     * @throws \Exception
     */
    public function refreshToken(TokenInterface $token)
    {
        try {
            $response = $this->client->post(
                $this->getOauthUrl() . '/token',
                $this->getRefreshTokenData($token)
            );

        } catch (GuzzleException $e) {
            throw $this->createAuthenticationException($e, 'refresh_token');
        }

        return $this->createTokenFromResponse($response);
    }

    /**
     * @param ResponseInterface $response
     * @return TokenInterface
     * @throws ClientException
     * @throws \Exception
     */
    private function createTokenFromResponse(ResponseInterface $response): TokenInterface
    {
        $data = $this->json->unserialize(
            $response->getBody()->getContents()
        );

        if (!isset($data['access_token']) || !isset($data['refresh_token']) || !isset($data['expires_in'])) {
            throw new ClientException(
                __('Wrong response structure received')
            );
        }

        /** @var TokenInterface $token */
        $token = $this->tokenFactory->create();
        $token->setAccessToken($data['access_token']);
        $token->setRefreshToken($data['refresh_token']);
        $token->setExpirationTime($this->getExpirationTimestamp($data['expires_in']));

        return $token;
    }

    /**
     * @param $expiresIn
     * @return int
     * @throws \Exception
     */
    private function getExpirationTimestamp($expiresIn)
    {
        $expirationDate = new \DateTime();
        $expirationDate->add(new \DateInterval("PT{$expiresIn}S"));

        return $expirationDate->getTimestamp();
    }

    /**
     * @return string
     */
    public function getAuthUrl()
    {
        $query = [
            'response_type' => 'code',
            'client_id' => $this->credentials->getClientId(),
            'redirect_uri' => $this->getRedirectUrl(),
            'state' => $this->stateManager->generate(),
        ];

        return $this->getOauthUrl() . '/authorize?' . http_build_query($query);
    }

    /**
     * @param TokenInterface $token
     * @return array
     */
    private function getRefreshTokenData(TokenInterface $token)
    {
        return $this->createData([
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->getRefreshToken(),
            'redirect_uri' => $this->getRedirectUrl(),
        ]);
    }

    /**
     * @param string $authCode
     * @return array
     */
    public function getNewTokenData(string $authCode)
    {
        return $this->createData([
            'grant_type' => 'authorization_code',
            'code' => $authCode,
            'redirect_uri' => $this->getRedirectUrl(),
        ]);
    }

    /**
     * @param array $query
     * @return array
     */
    public function createData(array $query)
    {
        return [
            'auth' => [
                $this->credentials->getClientId(),
                $this->credentials->getClientSecret(),
            ],
            'query' => $query,
            'connect_timeout' => $this->configuration->getConnectTimeout(),
            'timeout' => $this->configuration->getRequestTimeout(),
            'http_errors' => true,
        ];
    }

    /**
     * @return string
     */
    private function getRedirectUrl()
    {
        return $this->url->getUrl(
            'allegro/system/authenticate',
            [
                'key' => false
            ]
        );
    }

    /**
     * @return string
     */
    private function getOauthUrl()
    {
        return $this->credentials->isSandbox() ?
            $this->scopeConfig->getValue(self::SANDBOX_OAUTH_URL_CONFIG_KEY) :
            $this->scopeConfig->getValue(self::OAUTH_URL_CONFIG_KEY);
    }

    private function createAuthenticationException(
        GuzzleException $exception,
        string $grantType
    ): AuthenticationException {
        $response = $exception instanceof RequestException ? $exception->getResponse() : null;
        $statusCode = $response ? $response->getStatusCode() : null;
        $errors = $response
            ? $this->errorResponseParser->parse((string)$response->getBody())
            : [];

        $this->logger->apiFailure('Allegro OAuth request failed', [
            'grant_type' => $grantType,
            'status_code' => $statusCode,
            'error_codes' => array_values(array_filter(array_column($errors, 'code'))),
            'exception_type' => get_class($exception),
        ]);

        $message = $this->errorResponseParser->format($errors);
        $cause = $exception instanceof \Exception ? $exception : null;
        $requestId = null;
        if ($response) {
            foreach (['Trace-Id', 'X-Request-Id', 'Request-Id'] as $header) {
                $value = trim($response->getHeaderLine($header));
                if ($value !== '') {
                    $requestId = $value;
                    break;
                }
            }
        }

        return new AuthenticationException(
            __(
                'Could not authenticate with Allegro.%1',
                $message !== '' ? ' ' . $message : ''
            ),
            $cause,
            (int)$exception->getCode(),
            $statusCode,
            $requestId,
            $errors
        );
    }
}
