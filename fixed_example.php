<?php

/**
 * NAPRAWIONY PRZYKŁAD - rozwiązuje problemy z błędami API
 * 
 * Pokazuje jak poprawnie przygotować dane dla API Allegro
 * aby uniknąć błędów typu 422 Unprocessable Entity
 */

namespace Macopedia\Allegro;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Model\Api\ProductOfferFactory;

class FixedExample
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
     * ✅ POPRAWNY przykład tworzenia oferty
     * Naprawia wszystkie błędy z logów
     */
    public function createValidOffer(): string
    {
        /** @var ProductOfferInterface $productOffer */
        $productOffer = $this->productOfferFactory->create();

        // ✅ 1. NAZWA OFERTY (wymagane - nie może być null)
        $productOffer->setName('Samsung Galaxy A02s 32GB Biały');
        
        // ✅ 2. SELLING MODE (wymagane - nie może być pustą tablicą)
        $productOffer->setSellingMode([
            'format' => 'BUY_NOW',
            'price' => [
                'amount' => '599.00',
                'currency' => 'PLN'
            ]
        ]);
        
        // ✅ 3. LOCATION (wymagane - nie może być pustą tablicą)
        $productOffer->setLocation([
            'city' => 'Warszawa',
            'countryCode' => 'PL',
            'postCode' => '00-001',
            'province' => 'MAZOWIECKIE'
        ]);

        // ✅ 4. PARAMETRY (poprawna struktura)
        // STARA STRUKTURA (błędna):
        // "parameters": {"217": ["217_2048"], "219": ["219_64"]}
        
        // NOWA STRUKTURA (poprawna):
        $productOffer->setParameters([
            [
                'id' => '217',      // Brand
                'valuesIds' => ['217_2048']  // Samsung
            ],
            [
                'id' => '219',      // Color
                'valuesIds' => ['219_64']    // Biały
            ],
            [
                'id' => '224017',   // Manufacturer code
                'values' => ['SM-A025GZWEEUE']
            ],
            [
                'id' => '225693',   // EAN
                'values' => ['8806090873287']
            ]
        ]);

        // Opcjonalne pola
        $productOffer->setProductId('040a536d-1af0-4dd6-bdbf-6ed25b73c2d6');
        $productOffer->setQuantity(100);
        $productOffer->setStatus('ACTIVE');
        $productOffer->setCategory('165');

        // Opcje dostawy
        $productOffer->setDeliveryOptions([
            'shipping_rates_id' => 'some-shipping-rate-id',
            'handling_time' => 'PT24H'
        ]);

        // Płatności
        $productOffer->setPayments([
            'invoice' => 'VAT'
        ]);

        try {
            $offerId = $this->productOfferRepository->save($productOffer);
            echo "✅ Oferta została utworzona pomyślnie! ID: " . $offerId . "\n";
            return $offerId;
        } catch (\Exception $e) {
            echo "❌ Błąd podczas tworzenia oferty: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * ❌ BŁĘDNY przykład - pokazuje co powodowało błędy
     */
    public function createInvalidOffer()
    {
        /** @var ProductOfferInterface $productOffer */
        $productOffer = $this->productOfferFactory->create();

        // ❌ BŁĘDY z logów:
        $productOffer->setName(null);           // BŁĄD: name nie może być null
        $productOffer->setSellingMode([]);      // BŁĄD: sellingMode nie może być pustą tablicą
        $productOffer->setLocation([]);         // BŁĄD: location nie może być pustą tablicą
        
        // ❌ BŁĘDNA struktura parameters:
        $productOffer->setParameters([
            '217' => ['217_2048'],              // BŁĄD: powinna być tablica obiektów
            '219' => ['219_64', '219_256']      // BŁĄD: niepoprawna struktura
        ]);

        // To spowoduje błąd 422 Unprocessable Entity
        // return $this->productOfferRepository->save($productOffer);
    }

    /**
     * Przykład konwersji starych parametrów do nowej struktury
     */
    public function convertOldParametersStructure()
    {
        // STARA STRUKTURA (z logów):
        $oldParameters = [
            '217' => ['217_2048'],
            '219' => ['219_64', '219_256', '219_128'],
            '4388' => ['4388_1'],
            '224017' => ['SM-A025GZWEEUE'],
            '225693' => ['8806090873287']
        ];

        // KONWERSJA DO NOWEJ STRUKTURY:
        $newParameters = [];
        foreach ($oldParameters as $id => $values) {
            $newParameters[] = [
                'id' => (string)$id,
                'valuesIds' => is_array($values) ? $values : [$values]
            ];
        }

        // WYNIK:
        /*
        [
            ['id' => '217', 'valuesIds' => ['217_2048']],
            ['id' => '219', 'valuesIds' => ['219_64', '219_256', '219_128']],
            ['id' => '4388', 'valuesIds' => ['4388_1']],
            ['id' => '224017', 'valuesIds' => ['SM-A025GZWEEUE']],
            ['id' => '225693', 'valuesIds' => ['8806090873287']]
        ]
        */

        return $newParameters;
    }
}

/**
 * PODSUMOWANIE POPRAWEK:
 * 
 * 1. ✅ WALIDACJA WYMAGANYCH PÓL
 *    - Sprawdzanie czy name, sellingMode, location nie są puste
 * 
 * 2. ✅ POPRAWKA STRUKTURY PARAMETERS  
 *    - Konwersja z associative array na tablicę obiektów
 *    - Automatyczne formatowanie w metodzie formatParameters()
 * 
 * 3. ✅ LEPSZE OBSŁUGIWANIE BŁĘDÓW
 *    - Szczegółowe komunikaty błędów z API
 *    - Logowanie przesyłanych danych dla debugowania
 * 
 * 4. ✅ DODATKOWE ZABEZPIECZENIA
 *    - Walidacja odpowiedzi API
 *    - Obsługa różnych typów wyjątków
 */
