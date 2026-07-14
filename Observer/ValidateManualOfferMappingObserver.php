<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Observer;

use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

class ValidateManualOfferMappingObserver implements ObserverInterface
{
    /** @var ProductOfferRepositoryInterface */
    private $productOfferRepository;

    /** @var ProductResource */
    private $productResource;

    public function __construct(
        ProductOfferRepositoryInterface $productOfferRepository,
        ProductResource $productResource
    ) {
        $this->productOfferRepository = $productOfferRepository;
        $this->productResource = $productResource;
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        $offerId = trim((string)$product->getData('allegro_offer_id'));
        $originalOfferId = trim((string)$product->getOrigData('allegro_offer_id'));
        if ($offerId === '' || $offerId === $originalOfferId) {
            return;
        }
        if (!ctype_digit($offerId)) {
            throw new LocalizedException(__('Allegro offer ID must contain digits only.'));
        }

        $mappedProductId = (int)$this->productResource->getIdByAllegroOfferId($offerId);
        if ($mappedProductId > 0 && $mappedProductId !== (int)$product->getId()) {
            throw new LocalizedException(__('This Allegro offer is already mapped to another Magento product.'));
        }

        $offer = $this->productOfferRepository->get($offerId);
        if ($offer->getProductId()) {
            $product->setData('allegro_product_id', $offer->getProductId());
        }
    }
}
