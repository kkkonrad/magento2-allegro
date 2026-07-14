<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Api\TokenProvider;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Exception\NoSuchEntityException;

class OffersMapping
{
    /** @var ProductOfferRepositoryInterface */
    protected $offers;

    /** @var Logger */
    protected $logger;

    /** @var Configuration */
    protected $configuration;

    /** @var CollectionFactory */
    protected $productCollection;

    /** @var TokenProvider */
    protected $tokenProvider;

    /** @var ProductResource */
    private $productResource;

    /**
     * OffersMapping constructor.
     * @param ProductOfferRepositoryInterface $offers
     * @param Logger $logger
     * @param Configuration $configuration
     * @param CollectionFactory $productCollection
     * @param TokenProvider $tokenProvider
     * @param ProductResource $productResource
     */
    public function __construct(
        ProductOfferRepositoryInterface $offers,
        Logger $logger,
        Configuration $configuration,
        CollectionFactory $productCollection,
        TokenProvider $tokenProvider,
        ProductResource $productResource
    ) {
        $this->offers = $offers;
        $this->logger = $logger;
        $this->configuration = $configuration;
        $this->productCollection = $productCollection;
        $this->tokenProvider = $tokenProvider;
        $this->productResource = $productResource;
    }

    /**
     * @throws \Exception
     */
    public function clean(bool $dryRun = false, int $limit = 0): array
    {
        // Fail before iterating products when the account is not connected.
        $this->tokenProvider->getCurrent();

        $collection = $this->productCollection->create();
        $collection->addAttributeToSelect('allegro_offer_id')
            ->addStoreFilter($this->configuration->getStoreId())
            ->addAttributeToFilter('allegro_offer_id', ['neq' => 'NULL']);
        if ($limit > 0) {
            $collection->setPageSize($limit)->setCurPage(1);
        }

        $result = [
            'checked' => 0,
            'missing' => 0,
            'removed' => 0,
            'failed' => 0,
            'details' => [],
        ];

        /** @var \Magento\Catalog\Model\Product $product */
        foreach ($collection->getItems() as $product) {
            $result['checked']++;
            $allegroOfferId = trim((string)$product->getData('allegro_offer_id'));
            $detail = [
                'product_id' => (int)$product->getId(),
                'offer_id' => $allegroOfferId,
                'result' => 'exists',
            ];

            if ($allegroOfferId === '') {
                $result['failed']++;
                $detail['result'] = 'invalid';
                $result['details'][] = $detail;
                continue;
            }

            try {
                $this->offers->get($allegroOfferId);
            } catch (NoSuchEntityException $e) {
                $result['missing']++;
                $detail['result'] = $dryRun ? 'would_remove' : 'removed';
                if (!$dryRun) {
                    $product->setData('allegro_offer_id', null);
                    $this->productResource->saveAttribute($product, 'allegro_offer_id');
                    $result['removed']++;
                }
            } catch (Api\ClientResponseException $e) {
                $result['failed']++;
                $detail['result'] = 'api_error';
                $this->logger->apiFailure('Could not verify Allegro offer mapping', [
                    'product_id' => (int)$product->getId(),
                    'offer_id' => $allegroOfferId,
                    'status_code' => $e->getHttpStatusCode(),
                    'request_id' => $e->getRequestId(),
                ]);
            } catch (\Throwable $e) {
                $result['failed']++;
                $detail['result'] = 'error';
                $this->logger->apiFailure('Could not verify Allegro offer mapping', [
                    'product_id' => (int)$product->getId(),
                    'offer_id' => $allegroOfferId,
                    'exception_type' => get_class($e),
                ]);
            }
            $result['details'][] = $detail;
        }

        return $result;
    }
}
