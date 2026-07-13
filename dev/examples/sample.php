<?php

namespace Macopedia\Allegro;

use Macopedia\Allegro\Api\ProductCatalogRepositoryInterface;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Macopedia\Allegro\Model\Api\ProductOfferFactory;

class Sample
{
    private $productCatalogRepository;
    private $productOfferRepository;
    private $productOfferFactory;

    public function __construct(
        ProductCatalogRepositoryInterface $productCatalogRepository,
        ProductOfferRepositoryInterface $productOfferRepository,
        ProductOfferFactory $productOfferFactory
    ) {
        $this->productCatalogRepository = $productCatalogRepository;
        $this->productOfferRepository = $productOfferRepository;
        $this->productOfferFactory = $productOfferFactory;
    }

    public function searchProduct($searchPhrase)
    {
        /** @var ProductCatalogRepositoryInterface $productCatalogRepository */
        $productCatalogRepository = $this->productCatalogRepository;

        // Wyszukaj produkty
        $products = $productCatalogRepository->search($searchPhrase);

        // Jeśli znalazłeś odpowiedni produkt, możesz pobrać jego szczegóły
        if (!empty($products)) {
            $product = $products[0];
            $productId = $product->getId();

            // Pobierz szczegóły produktu
            $productDetails = $productCatalogRepository->get($productId);

            // Teraz możesz użyć tego ID do utworzenia oferty
            /** @var ProductOfferInterface $productOffer */
            $productOffer = $this->productOfferFactory->create();
            $productOffer->setProductId($productId);
            // ... ustaw pozostałe parametry oferty ...

            // Zapisz ofertę
            $offerId = $this->productOfferRepository->save($productOffer);
            return $offerId;
        }
    }

    public function getSellerId()
    {
        return 1;
    }   

    public function createProductOffer($productId, $price, $quantity)
    {
        /** @var ProductOfferInterface $productOffer */
        $productOffer = $this->productOfferFactory->create();

        $productOffer->setProductId($productId);
        $productOffer->setSellerId($this->getSellerId()); // musisz zaimplementować pobieranie ID sprzedawcy
        $productOffer->setPrice($price);
        $productOffer->setQuantity($quantity);
        $productOffer->setStatus('ACTIVE');

        // Opcjonalnie możesz dodać parametry, opcje dostawy i płatności
        $productOffer->setParameters([
            // parametry produktu
        ]);

        $productOffer->setDeliveryOptions([
            // opcje dostawy
        ]);

        $productOffer->setPayments([
            // metody płatności
        ]);

        // Zapisz ofertę
        $offerId = $this->productOfferRepository->save($productOffer);

        return $offerId;
    }
}
