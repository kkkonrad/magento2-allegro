<?php

declare(strict_types = 1);

namespace Macopedia\Allegro\Cron;

use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\OrderWithErrorImporter;
use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Model\Operations\CronJobRunner;

/**
 * Class responsible for importing orders with errors from Allegro API
 */
class ImportOrdersWithErrors
{
    /** @var Logger */
    private $logger;

    /** @var OrderWithErrorImporter */
    private $orderImporter;

    /** @var Configuration */
    private $configuration;

    /** @var CronJobRunner */
    private $jobRunner;

    /**
     * @param Logger $logger
     * @param OrderWithErrorImporter $orderImporter
     * @param Configuration $configuration
     */
    public function __construct(
        Logger $logger,
        OrderWithErrorImporter $orderImporter,
        Configuration $configuration,
        CronJobRunner $jobRunner
    ) {
        $this->logger = $logger;
        $this->orderImporter = $orderImporter;
        $this->configuration = $configuration;
        $this->jobRunner = $jobRunner;
    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        if ($this->configuration->isOrderRetryCronEnabled()) {
            $this->logger->info('Cronjob imported orders with errors is executed.');
            $this->jobRunner->run('retry_failed_orders', function (): array {
                return $this->orderImporter->execute()->getMetrics();
            });
        }
    }
}
