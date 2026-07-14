<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Operations;

use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Operations\CronJobRunner;
use Macopedia\Allegro\Model\Operations\OperationalStatus;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;

class CronJobRunnerTest extends TestCase
{
    public function testRecordsMetricsAndAlwaysUnlocksSuccessfulJob(): void
    {
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->expects(self::once())->method('lock')->willReturn(true);
        $lock->expects(self::once())->method('unlock');
        $status = $this->createMock(OperationalStatus::class);
        $status->expects(self::once())->method('record')->with(
            'import_orders',
            'success',
            self::callback(static function (array $metrics): bool {
                return $metrics['processed'] === 2 && isset($metrics['duration_ms']);
            })
        );

        $result = (new CronJobRunner($lock, $status, $this->createMock(Logger::class)))
            ->run('import_orders', static function (): array {
                return ['processed' => 2];
            });

        self::assertSame(['processed' => 2], $result);
    }

    public function testSkipsOverlappingJobWithoutExecutingCallback(): void
    {
        $lock = $this->createMock(LockManagerInterface::class);
        $lock->method('lock')->willReturn(false);
        $lock->expects(self::never())->method('unlock');
        $status = $this->createMock(OperationalStatus::class);
        $status->expects(self::once())->method('record')->with('refresh_token', 'skipped');
        $executed = false;

        $result = (new CronJobRunner($lock, $status, $this->createMock(Logger::class)))
            ->run('refresh_token', static function () use (&$executed): void {
                $executed = true;
            });

        self::assertNull($result);
        self::assertFalse($executed);
    }
}
