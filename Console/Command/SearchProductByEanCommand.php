<?php

namespace Macopedia\Allegro\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Macopedia\Allegro\Api\ProductCatalogRepositoryInterface;
use Magento\Framework\App\State;

class SearchProductByEanCommand extends Command
{
    const EAN_ARGUMENT = 'ean';

    /** @var ProductCatalogRepositoryInterface */
    private $productCatalogRepository;

    /** @var State */
    private $state;

    /**
     * @param ProductCatalogRepositoryInterface $productCatalogRepository
     * @param State $state
     */
    public function __construct(
        ProductCatalogRepositoryInterface $productCatalogRepository,
        State $state
    ) {
        parent::__construct();
        $this->productCatalogRepository = $productCatalogRepository;
        $this->state = $state;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('allegro:product:search')
            ->setDescription('Search for a product in Allegro Catalog by EAN')
            ->addArgument(
                self::EAN_ARGUMENT,
                InputArgument::REQUIRED,
                'EAN of the product to search'
            );

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

        $ean = $input->getArgument(self::EAN_ARGUMENT);
        $output->writeln("<info>Searching for product with EAN: {$ean}</info>");

        try {
            $products = $this->productCatalogRepository->search(['ean' => $ean]);

            if (empty($products)) {
                $output->writeln("<error>No product found for EAN: {$ean}</error>");
                return \Magento\Framework\Console\Cli::RETURN_FAILURE;
            }

            dd($products);

            $product = array_shift($products);
            $output->writeln("<info>Product found!</info>");
            $output->writeln("  ID:   <comment>{$product->getId()}</comment>");
            $output->writeln("  Name: <comment>{$product->getName()}</comment>");

            return \Magento\Framework\Console\Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>An error occurred: {$e->getMessage()}</error>");
            return \Magento\Framework\Console\Cli::RETURN_FAILURE;
        }
    }
} 