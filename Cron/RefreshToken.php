<?php

namespace Macopedia\Allegro\Cron;

use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Model\Api\TokenProvider;
use Macopedia\Allegro\Model\Operations\CronJobRunner;

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

    /** @var CronJobRunner */
    private $jobRunner;

    /**
     * RefreshToken constructor.
     * @param TokenProvider $tokenProvider
     * @param Configuration $configuration
     */
    public function __construct(
        TokenProvider $tokenProvider,
        Configuration $configuration,
        CronJobRunner $jobRunner
    ) {
        $this->tokenProvider = $tokenProvider;
        $this->configuration = $configuration;
        $this->jobRunner = $jobRunner;
    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        if (!$this->configuration->isTokenRefreshCronEnabled()) {
            return;
        }

        $this->jobRunner->run('refresh_token', function (): array {
            $this->tokenProvider->forceRefreshToken();
            return ['refreshed' => 1];
        });
    }
}
