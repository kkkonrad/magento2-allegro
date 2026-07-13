<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Api;

use Macopedia\Allegro\Api\Data\TokenInterface;
use Macopedia\Allegro\Model\Api\Auth\Data\TokenSerializer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\FlagManager;

/**
 * Credentials data class
 */
class Credentials
{
    const API_KEY_CONFIG_KEY = 'allegro/credentials/api_key';
    const CLIENT_ID_CONFIG_KEY = 'allegro/credentials/client_id';
    const CLIENT_SECRET_CONFIG_KEY = 'allegro/credentials/client_secret';
    const SANDBOX_CONFIG_KEY = 'allegro/general/sandbox';
    const TOKEN_DATA_FLAG_NAME = 'allegro_credentials_token_data';

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var TokenSerializer */
    private $tokenSerializer;

    /** @var FlagManager */
    private $flagManager;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param TokenSerializer $tokenSerializer
     * @param FlagManager $flagManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        TokenSerializer $tokenSerializer,
        FlagManager $flagManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->tokenSerializer = $tokenSerializer;
        $this->flagManager = $flagManager;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->scopeConfig->getValue(self::API_KEY_CONFIG_KEY);
    }

    /**
     * @return string
     */
    public function getClientId()
    {
        return $this->scopeConfig->getValue(self::CLIENT_ID_CONFIG_KEY);
    }

    /**
     * @return string
     */
    public function getClientSecret()
    {
        return $this->scopeConfig->getValue(self::CLIENT_SECRET_CONFIG_KEY);
    }

    /**
     * @return bool
     */
    public function isSandbox()
    {
        return (bool)$this->scopeConfig->getValue(self::SANDBOX_CONFIG_KEY);
    }

    /**
     * @param TokenInterface $token
     */
    public function saveToken(TokenInterface $token)
    {
        $this->flagManager->saveFlag(
            $this->getTokenFlagName(),
            $this->tokenSerializer->encode($token)
        );
    }

    /**
     * @return void
     */
    public function deleteToken()
    {
        $this->flagManager->deleteFlag($this->getTokenFlagName());
    }

    /**
     * @return TokenInterface
     * @throws ClientException
     */
    public function getToken()
    {
        $tokenString = $this->flagManager->getFlagData($this->getTokenFlagName());
        if (!$tokenString) {
            throw new ClientException(__('Allegro account is not connected. Connect to Allegro account and try again'));
        }
        try {
            return $this->tokenSerializer->decode($tokenString);
        } catch (Auth\Data\TokenSerializerException $e) {
            throw new ClientException(__('Something went wrong while decoding Allegro Api token'));
        }
    }

    private function getTokenFlagName(): string
    {
        return self::TOKEN_DATA_FLAG_NAME . ($this->isSandbox() ? '_sandbox' : '_production');
    }
}
