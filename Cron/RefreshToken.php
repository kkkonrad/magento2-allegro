<?php

namespace Macopedia\Allegro\Cron;

use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Model\Api\TokenProvider;

/**
 * Class responsible for importing orders from Allegro API
 */
class RefreshToken
{
    /**
     * @var \Macopedia\Allegro\Model\Api\TokenProvider
     */
    protected $tokenProvider;

    /** @var Configuration */
    private $configuration;

    /**
     * RefreshToken constructor.
     * @param TokenProvider $tokenProvider
     * @param Configuration $configuration
     */
    public function __construct(
        TokenProvider $tokenProvider,
        Configuration $configuration
    ) {
        $this->tokenProvider = $tokenProvider;
        $this->configuration = $configuration;
    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        if (!$this->configuration->isTokenRefreshCronEnabled()) {
            return;
        }

        $this->tokenProvider->forceRefreshToken();
    }
}
