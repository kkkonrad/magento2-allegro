<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Cron;

use Macopedia\Allegro\Api\Consumer\EntityMessageInterfaceFactory;
use Macopedia\Allegro\Api\Consumer\MessageInterfaceFactory;
use Macopedia\Allegro\Model\AsyncFailureRepository;
use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Model\Consumer;
use Macopedia\Allegro\Model\Consumer\OrderStatusConsumer;
use Macopedia\Allegro\Model\Consumer\ShipmentConsumer;
use Macopedia\Allegro\Model\Operations\CronJobRunner;

class RetryAsyncOperations
{
    private $failures;
    private $configuration;
    private $stockConsumer;
    private $orderStatusConsumer;
    private $shipmentConsumer;
    private $stockMessageFactory;
    private $entityMessageFactory;
    private $jobRunner;

    public function __construct(
        AsyncFailureRepository $failures,
        Configuration $configuration,
        Consumer $stockConsumer,
        OrderStatusConsumer $orderStatusConsumer,
        ShipmentConsumer $shipmentConsumer,
        MessageInterfaceFactory $stockMessageFactory,
        EntityMessageInterfaceFactory $entityMessageFactory,
        CronJobRunner $jobRunner
    ) {
        $this->failures = $failures;
        $this->configuration = $configuration;
        $this->stockConsumer = $stockConsumer;
        $this->orderStatusConsumer = $orderStatusConsumer;
        $this->shipmentConsumer = $shipmentConsumer;
        $this->stockMessageFactory = $stockMessageFactory;
        $this->entityMessageFactory = $entityMessageFactory;
        $this->jobRunner = $jobRunner;
    }

    public function execute(): void
    {
        if (!$this->configuration->isAsyncRetryCronEnabled()) {
            return;
        }
        $this->jobRunner->run('retry_async_operations', function (): array {
            return $this->retryDue();
        });
    }

    private function retryDue(): array
    {
        $metrics = ['processed' => 0, 'resolved' => 0, 'failed' => 0];
        foreach ($this->failures->getDue() as $failure) {
            $metrics['processed']++;
            $operation = (string)$failure['operation'];
            $sourceId = (int)$failure['source_id'];
            try {
                if ($operation === AsyncFailureRepository::OPERATION_STOCK) {
                    $message = $this->stockMessageFactory->create();
                    $message->setProductId($sourceId);
                    $this->stockConsumer->processMessage($message);
                } else {
                    $message = $this->entityMessageFactory->create();
                    $message->setEntityId($sourceId);
                    if ($operation === AsyncFailureRepository::OPERATION_ORDER_STATUS) {
                        $this->orderStatusConsumer->process($message);
                    } elseif ($operation === AsyncFailureRepository::OPERATION_SHIPMENT) {
                        $this->shipmentConsumer->process($message);
                    } else {
                        continue;
                    }
                }
                $this->failures->markResolved($operation, $sourceId);
                $metrics['resolved']++;
            } catch (\Throwable $exception) {
                // Consumers record the failed attempt with sanitized context.
                $metrics['failed']++;
            }
        }

        return $metrics;
    }
}
