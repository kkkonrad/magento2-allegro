<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Console\Command;

use Macopedia\Allegro\Model\OffersMapping;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CleanOffersMapping command class
 */
class CleanOffersMapping extends Command
{
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_LIMIT = 'limit';

    /** @var State */
    protected $state;

    /** @var OffersMapping */
    protected $offersMapping;

    /**
     * CleanOffersMapping constructor.
     * @param State $state
     * @param OffersMapping $offersMapping
     */
    public function __construct(
        State $state,
        OffersMapping $offersMapping
    ) {
        parent::__construct();
        $this->state = $state;
        $this->offersMapping = $offersMapping;
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('macopedia:allegro:clean-offers-mapping')
            ->setDescription('Clean mappings only for offers confirmed missing by Allegro')
            ->addOption(self::OPTION_DRY_RUN, null, InputOption::VALUE_NONE, 'Report without changing products')
            ->addOption(self::OPTION_LIMIT, null, InputOption::VALUE_REQUIRED, 'Maximum products (1-10000)', '1000');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->getAreaCode();
        } catch (LocalizedException $exception) {
            $this->state->setAreaCode(Area::AREA_GLOBAL);
        }

        $limit = $input->getOption(self::OPTION_LIMIT);
        if (!is_numeric($limit) || (int)$limit < 1 || (int)$limit > 10000) {
            $output->writeln('<error>Option --limit must be an integer between 1 and 10000.</error>');
            return Command::INVALID;
        }

        try {
            $result = $this->offersMapping->clean((bool)$input->getOption(self::OPTION_DRY_RUN), (int)$limit);
        } catch (\Throwable $e) {
            $output->writeln('<error>Offer mapping cleanup failed. Check the Allegro logs.</error>');
            return Command::FAILURE;
        }

        if ($output->isVerbose()) {
            foreach ($result['details'] as $detail) {
                $output->writeln(sprintf(
                    'Product %d, offer %s: %s',
                    $detail['product_id'],
                    $detail['offer_id'],
                    $detail['result']
                ));
            }
        }

        $output->writeln(sprintf(
            '<info>Checked: %d, missing: %d, removed: %d, failed: %d.</info>',
            $result['checked'],
            $result['missing'],
            $result['removed'],
            $result['failed']
        ));

        return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
