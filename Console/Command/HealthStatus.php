<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Console\Command;

use Macopedia\Allegro\Model\Api\ClientException;
use Macopedia\Allegro\Model\Api\Credentials;
use Macopedia\Allegro\Model\AsyncFailureRepository;
use Macopedia\Allegro\Model\Operations\OperationalStatus;
use Magento\Framework\App\ResourceConnection;
use Magento\MysqlMq\Model\QueueManagement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HealthStatus extends Command
{
    private const OPERATIONS = [
        'import_orders',
        'retry_failed_orders',
        'refresh_token',
        'clean_reservations',
        'clean_offer_mappings',
        'reconcile_offer_mappings',
        'retry_async_operations',
    ];

    /** @var OperationalStatus */
    private $operationalStatus;

    /** @var AsyncFailureRepository */
    private $failures;

    /** @var Credentials */
    private $credentials;

    /** @var ResourceConnection */
    private $resourceConnection;

    public function __construct(
        OperationalStatus $operationalStatus,
        AsyncFailureRepository $failures,
        Credentials $credentials,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct();
        $this->operationalStatus = $operationalStatus;
        $this->failures = $failures;
        $this->credentials = $credentials;
        $this->resourceConnection = $resourceConnection;
    }

    protected function configure(): void
    {
        $this->setName('macopedia:allegro:health')
            ->setDescription('Show OAuth, cron, queue and failed-operation health without secrets');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];
        $failedOperation = false;
        foreach (self::OPERATIONS as $operation) {
            $status = $this->operationalStatus->get($operation);
            $runStatus = (string)($status['last_run_status'] ?? 'never');
            $failedOperation = $failedOperation || $runStatus === 'failure';
            $rows[] = [
                $operation,
                $runStatus,
                (string)($status['last_run_at'] ?? '-'),
                (string)($status['last_success_at'] ?? '-'),
                $this->formatMetrics((array)($status['metrics'] ?? [])),
            ];
        }

        (new Table($output))
            ->setHeaders(['Operation', 'Last status', 'Last run', 'Last success', 'Metrics'])
            ->setRows($rows)
            ->render();

        $connected = true;
        $expiresAt = '-';
        try {
            $token = $this->credentials->getToken();
            $expiresAt = gmdate('c', (int)$token->getExpirationTime());
        } catch (ClientException $exception) {
            $connected = false;
        }

        $dead = $this->failures->getDeadCount();
        $pending = $this->pendingAllegroMessages();
        $output->writeln(sprintf(
            'OAuth: %s; token expires: %s; pending MQ: %d; dead async operations: %d.',
            $connected ? 'connected' : 'disconnected',
            $expiresAt,
            $pending,
            $dead
        ));

        return $connected && !$failedOperation && $dead === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function pendingAllegroMessages(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $messageTable = $this->resourceConnection->getTableName('queue_message');
        $statusTable = $this->resourceConnection->getTableName('queue_message_status');
        $select = $connection->select()
            ->from(['status' => $statusTable], ['COUNT(*)'])
            ->join(['message' => $messageTable], 'message.id = status.message_id', [])
            ->where('status.status = ?', QueueManagement::MESSAGE_STATUS_NEW)
            ->where('message.topic_name LIKE ?', 'allegro.%');

        return (int)$connection->fetchOne($select);
    }

    private function formatMetrics(array $metrics): string
    {
        $parts = [];
        foreach ($metrics as $key => $value) {
            $parts[] = $key . '=' . (is_bool($value) ? ($value ? '1' : '0') : $value);
        }
        return $parts ? implode(', ', $parts) : '-';
    }
}
