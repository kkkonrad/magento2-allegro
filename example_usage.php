<?php

/**
 * Przykład użycia naprawionego ProductOfferRepository
 * 
 * Ten plik pokazuje jak korzystać z naprawionej implementacji
 * która jest zgodna z dokumentacją API Allegro
 */

namespace Macopedia\Allegro;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Model\Api\ProductOfferFactory;

class ExampleUsage
{
    private $productOfferRepository;
    private $productOfferFactory;

    public function __construct(
        ProductOfferRepositoryInterface $productOfferRepository,
        ProductOfferFactory $productOfferFactory
    ) {
        $this->productOfferRepository = $productOfferRepository;
        $this->productOfferFactory = $productOfferFactory;
    }

    /**
     * Przykład tworzenia nowej oferty zgodnej z API Allegro
     */
    public function createNewOffer(): string
    {
        /** @var ProductOfferInterface $productOffer */
        $productOffer = $this->productOfferFactory->create();

        // WYMAGANE POLA zgodnie z dokumentacją API
        
        // 1. Nazwa oferty (wymagane)
        $productOffer->setName('Przykładowa nazwa oferty');
        
        // 2. Tryb sprzedaży z ceną (wymagane)
        $productOffer->setSellingMode([
            'format' => 'BUY_NOW',
            'price' => [
                'amount' => '123.45',
                'currency' => 'PLN'
            ]
        ]);
        
        // 3. Lokalizacja (wymagane)
        $productOffer->setLocation([
            'city' => 'Poznań',
            'countryCode' => 'PL',
            'postCode' => '60-001',
            'province' => 'WIELKOPOLSKIE'
        ]);

        // OPCJONALNE POLA
        
        // Product ID (jeśli używamy istniejącego produktu z katalogu)
        $productOffer->setProductId('example-product-id');
        
        // Stock
        $productOffer->setQuantity(10);
        
        // Status publikacji
        $productOffer->setStatus('INACTIVE');
        
        // Kategoria
        $productOffer->setCategory('257931');
        
        // Parametry produktu
        $productOffer->setParameters([
            [
                'id' => '224017',
                'name' => 'Manufacturer code',
                'values' => ['4234'],
                'valuesIds' => ['129970_850936']
            ]
        ]);
        
        // Opcje dostawy
        $productOffer->setDeliveryOptions([
            'shipping_rates_id' => '5637592a-0a24-4771-b527-d89b2767d821',
            'shipping_rates_name' => 'Shipping rate 1',
            'handling_time' => 'PT24H',
            'additional_info' => 'Example additional info'
        ]);
        
        // Płatności
        $productOffer->setPayments([
            'invoice' => 'VAT'
        ]);
        
        // Zdjęcia
        $productOffer->setImages([
            'https://example.com/image1.jpg',
            'https://example.com/image2.jpg'
        ]);
        
        // Opis
        $productOffer->setDescription([
            'sections' => [
                [
                    'items' => [
                        [
                            'type' => 'TEXT',
                            'content' => 'Opis produktu'
                        ]
                    ]
                ]
            ]
        ]);
        
        // External ID
        $productOffer->setExternalId('AH-129834');
        
        // Usługi posprzedażowe
        $productOffer->setAfterSalesServices([
            'warranty' => [
                'id' => '09f0b4cc-7880-11e9-8f9e-2a86e4085a59',
                'name' => 'Warranty 1'
            ]
        ]);
        
        // Załączniki
        $productOffer->setAttachments([
            [
                'id' => '07ee5e36-afc7-41eb-af49-3df5354ef85'
            ]
        ]);

        // Zapisz ofertę
        $offerId = $this->productOfferRepository->save($productOffer);
        
        return $offerId;
    }

    /**
     * Przykład pobierania i aktualizacji istniejącej oferty
     */
    public function updateExistingOffer(string $offerId): string
    {
        // Pobierz istniejącą ofertę
        $productOffer = $this->productOfferRepository->get($offerId);
        
        // Aktualizuj cenę
        $sellingMode = $productOffer->getSellingMode();
        $sellingMode['price']['amount'] = '150.00';
        $productOffer->setSellingMode($sellingMode);
        
        // Aktualizuj ilość
        $productOffer->setQuantity(5);
        
        // Zapisz zmiany
        return $this->productOfferRepository->save($productOffer);
    }

    /**
     * Przykład pobierania ofert dla konkretnego produktu
     */
    public function getOffersForProduct(string $productId): array
    {
        return $this->productOfferRepository->getByProductId($productId);
    }

    /**
     * Przykład usuwania oferty
     */
    public function deleteOffer(string $offerId): bool
    {
        return $this->productOfferRepository->delete($offerId);
    }
}
