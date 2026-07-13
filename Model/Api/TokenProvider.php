<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Api;

use Macopedia\Allegro\Api\Data\TokenInterface;
use Magento\Framework\Lock\LockManagerInterface;

/**
 * Class to get current access token received from Allegro API
 */
class TokenProvider
{
    private const REFRESH_LOCK_PREFIX = 'macopedia_allegro_token_refresh_';

    /** @var Auth */
    private $auth;

    /** @var Credentials */
    private $credentials;

    /** @var LockManagerInterface */
    private $lockManager;

    /**
     * @param Auth $auth
     * @param Credentials $credentials
     * @param LockManagerInterface $lockManager
     */
    public function __construct(
        Auth $auth,
        Credentials $credentials,
        LockManagerInterface $lockManager
    ) {
        $this->auth = $auth;
        $this->credentials = $credentials;
        $this->lockManager = $lockManager;
    }

    /**
     * @return TokenInterface
     * @throws ClientException
     * @throws \Exception
     */
    public function getCurrent()
    {
        $token = $this->credentials->getToken();

        if ($token->isExpired()) {
            $token = $this->refreshTokenWithLock($token);
        }

        return $token;
    }

    /**
     * @return void
     * @throws ClientException
     * @throws \Exception
     */
    public function forceRefreshToken()
    {
        $this->refreshTokenWithLock($this->credentials->getToken(), true);
    }

    /**
     * @param TokenInterface $token
     * @return TokenInterface
     * @throws \Exception
     */
    private function refreshToken(TokenInterface $token)
    {
        $token = $this->auth->refreshToken($token);
        $this->credentials->saveToken($token);
        return $token;
    }

    /**
     * @throws ClientException
     * @throws \Exception
     */
    private function refreshTokenWithLock(TokenInterface $token, bool $force = false): TokenInterface
    {
        $lockName = self::REFRESH_LOCK_PREFIX . ($this->credentials->isSandbox() ? 'sandbox' : 'production');
        if (!$this->lockManager->lock($lockName, 5)) {
            throw new ClientException(__('Allegro token refresh is already in progress. Please try again.'));
        }

        try {
            if (!$force) {
                $latestToken = $this->credentials->getToken();
                if (!$latestToken->isExpired()) {
                    return $latestToken;
                }
                $token = $latestToken;
            }

            return $this->refreshToken($token);
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }
}
