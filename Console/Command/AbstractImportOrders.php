<?php

declare(strict_types = 1);

namespace Macopedia\Allegro\Console\Command;

use Macopedia\Allegro\Model\AbstractOrderImporter;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractImportOrders extends Command
{
    /** @var State */
    protected $state;

    /**
     * AbstractImportOrders constructor.
     * @param State $state
     */
    public function __construct(State $state)
    {
        $this->state = $state;
        parent::__construct();
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

        $output->writeln('Order import start');

        try {
            $info = $this->createOrderImporter()->execute();
        } catch (\Throwable $exception) {
            $output->writeln('<error>Order import failed. Check the Allegro logs.</error>');
            return Command::FAILURE;
        }

        $output->writeln($info->getMessage());

        return $info->hasErrors() ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return AbstractOrderImporter
     */
    abstract protected function createOrderImporter(): AbstractOrderImporter;
}
