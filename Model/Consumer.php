<?php

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Api\Consumer\MessageInterface;
use Macopedia\Allegro\Api\ConsumerInterface;
use Macopedia\Allegro\Api\Data\PublicationCommandInterface;
use Macopedia\Allegro\Api\Data\PublicationCommandInterfaceFactory;
use Macopedia\Allegro\Api\OfferRepositoryInterface;
use Macopedia\Allegro\Api\PriceCommandInterface;
use Macopedia\Allegro\Api\PublicationCommandRepositoryInterface;
use Macopedia\Allegro\Api\QuantityCommandInterface;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Api\ClientException;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Model\Indexer\Stock\Processor;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;

/**
 * Consumes messages from Allegro queue
 */
class Consumer implements ConsumerInterface
{
    /**
     * @var QuantityCommandInterface
     */
    protected $quantityCommand;
    /**
     * @var Processor
     */
    protected $indexerProcessor;
    /** @var Logger */
    private $logger;

    /** @var ProductRepository */
    private $productRepository;

    /** @var GetSalableQuantityDataBySku */
    private $getSalableQuantityDataBySku;

    /** @var OfferRepositoryInterface */
    private $offerRepository;

    /** @var PublicationCommandRepositoryInterface */
    private $publicationCommandRepository;

    /** @var PublicationCommandInterfaceFactory */
    private $publicationCommandFactory;

    /** @var Configuration */
    private $config;

    /** @var PriceCommandInterface */
    private $priceCommand;

    /** @var AllegroPrice */
    protected $allegroPrice;

    /** @var LockManagerInterface */
    private $lockManager;

    /** @var OfferSyncState */
    private $offerSyncState;

    /** @var AsyncFailureRepository */
    private $asyncFailureRepository;

    /**
     * Consumer constructor.
     * @param Logger $logger
     * @param ProductRepository $productRepository
     * @param GetSalableQuantityDataBySku $getSalableQuantityDataBySku
     * @param OfferRepositoryInterface $offerRepository
     * @param PublicationCommandRepositoryInterface $publicationCommandRepository
     * @param PublicationCommandInterfaceFactory $publicationCommandFactory
     * @param QuantityCommandInterface $quantityCommand
     * @param Configuration $config
     * @param Processor $indexerProcessor
     * @param PriceCommandInterface $priceCommand
     * @param AllegroPrice $allegroPrice
     * @param LockManagerInterface $lockManager
     * @param OfferSyncState $offerSyncState
     * @param AsyncFailureRepository $asyncFailureRepository
     */
    public function __construct(
        Logger $logger,
        ProductRepository $productRepository,
        GetSalableQuantityDataBySku $getSalableQuantityDataBySku,
        OfferRepositoryInterface $offerRepository,
        PublicationCommandRepositoryInterface $publicationCommandRepository,
        PublicationCommandInterfaceFactory $publicationCommandFactory,
        QuantityCommandInterface $quantityCommand,
        Configuration $config,
        Processor $indexerProcessor,
        PriceCommandInterface $priceCommand,
        AllegroPrice $allegroPrice,
        LockManagerInterface $lockManager,
        OfferSyncState $offerSyncState,
        AsyncFailureRepository $asyncFailureRepository
    ) {
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->getSalableQuantityDataBySku = $getSalableQuantityDataBySku;
        $this->offerRepository = $offerRepository;
        $this->publicationCommandRepository = $publicationCommandRepository;
        $this->publicationCommandFactory = $publicationCommandFactory;
        $this->config = $config;
        $this->quantityCommand = $quantityCommand;
        $this->indexerProcessor = $indexerProcessor;
        $this->priceCommand = $priceCommand;
        $this->allegroPrice = $allegroPrice;
        $this->lockManager = $lockManager;
        $this->offerSyncState = $offerSyncState;
        $this->asyncFailureRepository = $asyncFailureRepository;
    }

    /**
     * @param MessageInterface $message
     */
    public function processMessage(MessageInterface $message)
    {
        $productId = $message->getProductId();
        if (!$productId) {
            $this->logger->warning('Error while receiving product id from observer');
            return;
        }

        if (!$this->config->isStockSynchronizationEnabled()) {
            return;
        }

        $lockName = 'macopedia_allegro_stock_sync_' . (int)$productId;
        if (!$this->lockManager->lock($lockName, 0)) {
            throw new \RuntimeException('A stock synchronization for this product is already running.');
        }

        try {
            if ($product = $this->productRepository->getMinProductWithAllegro($productId)) {
                $allegroOfferId = $product->getData('allegro_offer_id');
                if (!$allegroOfferId) {
                    $this->logger->debug('Error while receiving product id from observer');
                    return;
                }
                // refresh stock index to have current stock data
                try {
                    $this->indexerProcessor->reindexList([$product->getId()], true);
                } catch (\Exception $exception) {
                    $this->logger->apiFailure('Could not refresh stock index before Allegro synchronization', [
                        'product_id' => (int)$product->getId(),
                        'exception_type' => get_class($exception),
                    ]);
                }

                $productStock = $this->getSalableQuantityDataBySku->execute($product->getSku());
                if (!isset($productStock[0]['qty'])) {
                    return;
                }

                $qty = max(0, (int)floor((float)$productStock[0]['qty']));
                $price = $this->getOfferPrice($product);
                $stateHash = $this->offerSyncState->createHash($allegroOfferId, $qty, $price);
                if ($this->offerSyncState->isCurrent((int)$product->getId(), $stateHash)) {
                    return;
                }

                if ($price !== null) {
                    $this->priceCommand->change($allegroOfferId, $price);
                }

                $offer = $this->offerRepository->get($allegroOfferId);
                if ($qty > 0) {
                    $this->quantityCommand->change($allegroOfferId, $qty);
                    if (!$offer->isDraft()) {
                        $this->savePublicationCommand(
                            $allegroOfferId,
                            PublicationCommandInterface::ACTION_ACTIVATE
                        );
                    }
                } else {
                    $this->savePublicationCommand(
                        $allegroOfferId,
                        PublicationCommandInterface::ACTION_END
                    );

                }

                $this->offerSyncState->markCurrent(
                    (int)$product->getId(),
                    $allegroOfferId,
                    $stateHash
                );

                $this->logger->info(
                    sprintf(
                        'Quantity and price of offer with external id %s have been successfully updated',
                        $allegroOfferId
                    )
                );
            }

        } catch (\Throwable $e) {
            $this->asyncFailureRepository->recordFailure(
                AsyncFailureRepository::OPERATION_STOCK,
                (int)$productId,
                $e
            );
            $this->logger->apiFailure('Allegro stock synchronization failed', [
                'product_id' => (int)$productId,
                'exception_type' => get_class($e),
            ]);
            throw $e;
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }

    /**
     * @param string $offerId
     * @param string $action
     * @throws ClientException
     * @throws CouldNotSaveException
     */
    private function savePublicationCommand(string $offerId, string $action)
    {
        $publicationCommand = $this->publicationCommandFactory->create();
        $publicationCommand->setOfferId($offerId);
        $publicationCommand->setAction($action);

        $this->publicationCommandRepository->save($publicationCommand);
    }

    /**
     * @param ProductInterface $product
     * @param string $offerId
     * @throws AllegroPriceGettingException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getOfferPrice(ProductInterface $product): ?float
    {
        if (!$this->config->isPricePolicyEnabled()) {
            return null;
        }

        $price = $this->allegroPrice->getByProductId($product->getId());
        return $price > 0 ? (float)$price : null;
    }
}
