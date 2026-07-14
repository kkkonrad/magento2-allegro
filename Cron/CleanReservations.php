<?php

declare(strict_types = 1);

namespace Macopedia\Allegro\Cron;

use Macopedia\Allegro\Logger\Logger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Macopedia\Allegro\Model\OrderImporter\AllegroReservation;
use Macopedia\Allegro\Model\Operations\CronJobRunner;

/**
 * Class responsible for cleaning old reservations
 */
class CleanReservations
{
    const RESERVATIONS_CRON_CONFIG_KEY = 'allegro/order/reservations_cron_enabled';

    /** @var Logger */
    private $logger;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var AllegroReservation */
    private $allegroReservation;

    /** @var CronJobRunner */
    private $jobRunner;

    /**
     * @param Logger $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param AllegroReservation $allegroReservation
     */
    public function __construct(
        Logger $logger,
        ScopeConfigInterface $scopeConfig,
        AllegroReservation $allegroReservation,
        CronJobRunner $jobRunner
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->allegroReservation = $allegroReservation;
        $this->jobRunner = $jobRunner;
    }

    public function execute()
    {
        if ($this->scopeConfig->getValue(self::RESERVATIONS_CRON_CONFIG_KEY)) {
            $this->logger->info('Cronjob clean reservations is executed.');
            $this->jobRunner->run('clean_reservations', function (): array {
                $this->allegroReservation->cleanOldReservations();
                return [];
            });
        }
    }
}
