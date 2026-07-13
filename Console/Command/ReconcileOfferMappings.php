<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Console\Command;

use Macopedia\Allegro\Model\OfferMappingService;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReconcileOfferMappings extends Command
{
    private const OPTION_LIMIT = 'limit';

    /** @var State */
    private $state;

    /** @var OfferMappingService */
    private $offerMappingService;

    public function __construct(State $state, OfferMappingService $offerMappingService)
    {
        parent::__construct();
        $this->state = $state;
        $this->offerMappingService = $offerMappingService;
    }

    protected function configure(): void
    {
        $this->setName('macopedia:allegro:reconcile-offer-mappings')
            ->setDescription('Retry pending local mappings for offers created in Allegro')
            ->addOption(
                self::OPTION_LIMIT,
                null,
                InputOption::VALUE_REQUIRED,
                'Maximum number of records (1-1000)',
                '100'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->getAreaCode();
        } catch (LocalizedException $exception) {
            $this->state->setAreaCode(Area::AREA_GLOBAL);
        }

        $rawLimit = $input->getOption(self::OPTION_LIMIT);
        if (!is_numeric($rawLimit) || (int)$rawLimit < 1 || (int)$rawLimit > 1000) {
            $output->writeln('<error>Option --limit must be an integer between 1 and 1000.</error>');
            return Command::INVALID;
        }

        try {
            $result = $this->offerMappingService->reconcilePending((int)$rawLimit);
        } catch (\Throwable $exception) {
            $output->writeln('<error>Offer mapping reconciliation failed. Check the Allegro logs.</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Processed: %d, resolved: %d, failed: %d.</info>',
            $result['processed'],
            $result['resolved'],
            $result['failed']
        ));

        return Command::SUCCESS;
    }
}
