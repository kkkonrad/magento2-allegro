<?php

declare(strict_types = 1);

namespace Macopedia\Allegro\Cron;

use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\OrderImporter;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Macopedia\Allegro\Model\Operations\CronJobRunner;

/**
 * Class responsible for importing orders from Allegro API
 */
class ImportOrders
{
    const ORDER_IMPORT_CONFIG_KEY = 'allegro/order/enabled';

    /** @var Logger */
    private $logger;

    /** @var OrderImporter */
    private $orderImporter;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var CronJobRunner */
    private $jobRunner;

    /**
     * @param Logger $logger
     * @param OrderImporter $orderImporter
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Logger $logger,
        OrderImporter $orderImporter,
        ScopeConfigInterface $scopeConfig,
        CronJobRunner $jobRunner
    ) {
        $this->logger = $logger;
        $this->orderImporter = $orderImporter;
        $this->scopeConfig = $scopeConfig;
        $this->jobRunner = $jobRunner;
    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        if ($this->scopeConfig->getValue(self::ORDER_IMPORT_CONFIG_KEY)) {
            $this->logger->info('Cronjob imported orders is executed.');
            $this->jobRunner->run('import_orders', function (): array {
                return $this->orderImporter->execute()->getMetrics();
            });
        }
    }
}
