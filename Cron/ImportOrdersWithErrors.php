<?php

declare(strict_types = 1);

namespace Macopedia\Allegro\Cron;

use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\OrderWithErrorImporter;
use Macopedia\Allegro\Model\Configuration;

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

    /**
     * @param Logger $logger
     * @param OrderWithErrorImporter $orderImporter
     * @param Configuration $configuration
     */
    public function __construct(
        Logger $logger,
        OrderWithErrorImporter $orderImporter,
        Configuration $configuration
    ) {
        $this->logger = $logger;
        $this->orderImporter = $orderImporter;
        $this->configuration = $configuration;
    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        if ($this->configuration->isOrderRetryCronEnabled()) {
            $this->logger->addInfo("Cronjob imported orders with errors is executed.");
            $this->orderImporter->execute();
        }
    }
}
