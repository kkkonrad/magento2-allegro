<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Operations;

use Macopedia\Allegro\Logger\Logger;
use Magento\Framework\Lock\LockManagerInterface;

class CronJobRunner
{
    /** @var LockManagerInterface */
    private $lockManager;

    /** @var OperationalStatus */
    private $status;

    /** @var Logger */
    private $logger;

    public function __construct(
        LockManagerInterface $lockManager,
        OperationalStatus $status,
        Logger $logger
    ) {
        $this->lockManager = $lockManager;
        $this->status = $status;
        $this->logger = $logger;
    }

    /**
     * @return mixed|null
     */
    public function run(string $operation, callable $callback)
    {
        $lockName = 'macopedia_allegro_cron_' . hash('sha256', $operation);
        if (!$this->lockManager->lock($lockName, 0)) {
            $this->status->record($operation, 'skipped');
            $this->logger->warning('Skipped overlapping Allegro cron job', ['operation' => $operation]);
            return null;
        }

        $startedAt = microtime(true);
        try {
            $result = $callback();
            $metrics = is_array($result) ? $result : [];
            $metrics['duration_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
            $this->status->record($operation, 'success', $metrics);
            return $result;
        } catch (\Throwable $exception) {
            $this->status->record($operation, 'failure', [
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ], $exception);
            $this->logger->apiFailure('Allegro cron job failed', [
                'operation' => $operation,
                'exception_type' => get_class($exception),
            ]);
            throw $exception;
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }
}
