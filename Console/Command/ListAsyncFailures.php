<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Console\Command;

use Macopedia\Allegro\Model\AsyncFailureRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListAsyncFailures extends Command
{
    /** @var AsyncFailureRepository */
    private $failures;

    public function __construct(AsyncFailureRepository $failures)
    {
        parent::__construct();
        $this->failures = $failures;
    }

    protected function configure(): void
    {
        $this->setName('macopedia:allegro:async-failures')
            ->setDescription('List Allegro asynchronous operations that exhausted retries')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum rows (1-1000)', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = $input->getOption('limit');
        if (!is_numeric($limit) || (int)$limit < 1 || (int)$limit > 1000) {
            $output->writeln('<error>Option --limit must be an integer between 1 and 1000.</error>');
            return Command::INVALID;
        }

        $rows = $this->failures->getDead((int)$limit);
        if (!$rows) {
            $output->writeln('<info>No dead Allegro asynchronous operations.</info>');
            return Command::SUCCESS;
        }

        (new Table($output))->setHeaders(['Operation', 'Source ID', 'Attempts', 'Last error', 'Updated at'])
            ->setRows(array_map(static function (array $row): array {
                return [
                    $row['operation'],
                    $row['source_id'],
                    $row['attempts'],
                    $row['last_error'],
                    $row['updated_at'],
                ];
            }, $rows))->render();

        return Command::FAILURE;
    }
}
