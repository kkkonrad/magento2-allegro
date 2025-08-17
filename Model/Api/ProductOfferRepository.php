<?php

namespace Macopedia\Allegro\Model\Api;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Model\Api\Client\ClientException;
use Macopedia\Allegro\Model\ResourceModel\AbstractResource;
use Macopedia\Allegro\Logger\Logger;

class ProductOfferRepository implements ProductOfferRepositoryInterface
{
    private const API_ENDPOINT = '/sale/product-offers';
    private const API_ENDPOINT_GET = '/sale/product-offers/{offerId}';
    private const API_ENDPOINT_PRODUCT = '/sale/product-offers?product.id={productId}';

    /**
     * @var AbstractResource
     */
    private $resource;

    /**
     * @var ProductOfferFactory
     */
    private $productOfferFactory;
    
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param AbstractResource $resource
     * @param ProductOfferFactory $productOfferFactory
     * @param Logger $logger
     */
    public function __construct(
        AbstractResource $resource,
        ProductOfferFactory $productOfferFactory,
        Logger $logger
    ) {
        $this->resource = $resource;
        $this->productOfferFactory = $productOfferFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function save(ProductOfferInterface $productOffer): string
    {
        try {
            $data = $this->prepareData($productOffer);
            
            // Log przesyłanych danych dla debugowania
            $this->logger->debug('Product offer data to send: ' . json_encode($data, JSON_PRETTY_PRINT));
            
            if ($productOffer->getId()) {
                $response = $this->resource->requestPut(
                    str_replace('{offerId}', $productOffer->getId(), self::API_ENDPOINT_GET),
                    $data
                );
            } else {
                $response = $this->resource->requestPost(
                    self::API_ENDPOINT,
                    $data
                );
            }

            if (!isset($response['id'])) {
                throw new ClientException(__('Invalid API response - missing offer ID'));
            }
            
            return $response['id'];
        } catch (ClientException $e) {
            // Lepsze formatowanie błędów API
            $errorMessage = $this->parseApiError($e->getMessage());
            throw new ClientException(__('Could not save product offer: %1', $errorMessage));
        } catch (\Exception $e) {
            throw new ClientException(__('Unexpected error while saving product offer: %1', $e->getMessage()));
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $offerId): ProductOfferInterface
    {
        try {
            $response = $this->resource->requestGet(
                str_replace('{offerId}', $offerId, self::API_ENDPOINT_GET)
            );

            return $this->createProductOffer($response);
        } catch (ClientException $e) {
            throw new ClientException(__('Product offer with ID "%1" does not exist.', $offerId));
        }
    }

    /**
     * @inheritDoc
     */
    public function getByProductId(string $productId): array
    {
        try {
            $response = $this->resource->requestGet(
                str_replace('{productId}', $productId, self::API_ENDPOINT_PRODUCT)
            );

            $offers = [];
            foreach ($response['offers'] as $offerData) {
                $offers[] = $this->createProductOffer($offerData);
            }

            return $offers;
        } catch (ClientException $e) {
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $offerId): bool
    {
        try {
            $this->resource->requestDelete(
                str_replace('{offerId}', $offerId, self::API_ENDPOINT_GET)
            );
            return true;
        } catch (ClientException $e) {
            return false;
        }
    }

    /**
     * @param ProductOfferInterface $productOffer
     * @return array
     * @throws ClientException
     */
    private function prepareData(ProductOfferInterface $productOffer): array
    {
        // Walidacja wymaganych pól
        $this->validateRequiredFields($productOffer);
        
        // Zgodnie z dokumentacją API Allegro
        $data = [
            // Wymagane pola
            'name' => $productOffer->getName(),
            'sellingMode' => $productOffer->getSellingMode(),
            'location' => $productOffer->getLocation(),
            'language' => 'pl-PL'
        ];

        // Stock - jeśli dostępny
        if ($productOffer->getQuantity() > 0) {
            $data['stock'] = [
                'available' => $productOffer->getQuantity(),
                'unit' => 'UNIT'
            ];
        }

        // Product set - struktura zgodna z API (opcjonalne)
        $productId = $productOffer->getProductId();
        if (!empty($productId)) {
            $data['productSet'] = [
                [
                    'product' => [
                        'id' => $productId
                    ]
                ]
            ];
        }

        // Publication status
        if (!empty($productOffer->getStatus())) {
            $data['publication'] = [
                'status' => $productOffer->getStatus()
            ];
        }

        // Delivery options
        $deliveryOptions = $productOffer->getDeliveryOptions();
        if (!empty($deliveryOptions)) {
            $delivery = [];
            
            if (!empty($deliveryOptions['shipping_rates_id'])) {
                $delivery['shippingRates'] = [
                    'id' => $deliveryOptions['shipping_rates_id']
                ];
                if (!empty($deliveryOptions['shipping_rates_name'])) {
                    $delivery['shippingRates']['name'] = $deliveryOptions['shipping_rates_name'];
                }
            }
            
            if (!empty($deliveryOptions['handling_time'])) {
                $delivery['handlingTime'] = $deliveryOptions['handling_time'];
            } else {
                $delivery['handlingTime'] = 'PT24H'; // domyślny czas
            }
            
            if (!empty($deliveryOptions['additional_info'])) {
                $delivery['additionalInfo'] = $deliveryOptions['additional_info'];
            }
            
            if (!empty($delivery)) {
                $data['delivery'] = $delivery;
            }
        }

        // Payments
        $payments = $productOffer->getPayments();
        if (!empty($payments)) {
            $data['payments'] = [
                'invoice' => $payments['invoice'] ?? 'VAT'
            ];
        }

        // Category
        if (!empty($productOffer->getCategory())) {
            $data['category'] = [
                'id' => $productOffer->getCategory()
            ];
        }

        // Parameters - poprawna struktura dla API
        if (!empty($productOffer->getParameters())) {
            $formattedParams = $this->formatParameters($productOffer->getParameters());
            
            // Waliduj parametry dla konkretnej kategorii
            $categoryId = $productOffer->getCategory();
            if ($categoryId) {
                $formattedParams = $this->validateParametersForCategory($formattedParams, $categoryId);
            }
            
            if (!empty($formattedParams)) {
                $data['parameters'] = $formattedParams;
            }
        }

        // Images
        if (!empty($productOffer->getImages())) {
            $data['images'] = $productOffer->getImages();
        }

        // Description
        if (!empty($productOffer->getDescription())) {
            $data['description'] = $productOffer->getDescription();
        }

        // External ID
        if (!empty($productOffer->getExternalId())) {
            $data['external'] = [
                'id' => $productOffer->getExternalId()
            ];
        }

        // After sales services (opcjonalne - pomijamy jeśli brak)
        $afterSalesServices = $productOffer->getAfterSalesServices();
        if (!empty($afterSalesServices)) {
            $data['afterSalesServices'] = $afterSalesServices;
        }
        // Pomijamy domyślną politykę - endpoint nie działa w sandbox

        // Attachments
        if (!empty($productOffer->getAttachments())) {
            $data['attachments'] = $productOffer->getAttachments();
        }

        return $data;
    }

    /**
     * @param array $data
     * @return ProductOfferInterface
     */
    private function createProductOffer(array $data): ProductOfferInterface
    {
        /** @var ProductOfferInterface $productOffer */
        $productOffer = $this->productOfferFactory->create();
        
        // Podstawowe informacje
        $productOffer->setId($data['id'] ?? null);
        
        // Nazwa oferty
        if (!empty($data['name'])) {
            $productOffer->setName($data['name']);
        }
        
        // Product ID z productSet
        if (!empty($data['productSet'][0]['product']['id'])) {
            $productOffer->setProductId($data['productSet'][0]['product']['id']);
        }
        
        // Seller ID
        if (!empty($data['seller']['id'])) {
            $productOffer->setSellerId($data['seller']['id']);
        }
        
        // Cena z sellingMode
        if (!empty($data['sellingMode']['price']['amount'])) {
            $productOffer->setPrice((float)$data['sellingMode']['price']['amount']);
            $productOffer->setSellingMode($data['sellingMode']);
        }
        
        // Ilość ze stock
        if (!empty($data['stock']['available'])) {
            $productOffer->setQuantity((int)$data['stock']['available']);
        }
        
        // Status publikacji
        if (!empty($data['publication']['status'])) {
            $productOffer->setStatus($data['publication']['status']);
        }
        
        // Kategoria
        if (!empty($data['category']['id'])) {
            $productOffer->setCategory($data['category']['id']);
        }
        
        // Parametry
        if (!empty($data['parameters'])) {
            $productOffer->setParameters($data['parameters']);
        }
        
        // Opcje dostawy
        if (!empty($data['delivery'])) {
            $deliveryOptions = [];
            if (!empty($data['delivery']['shippingRates']['id'])) {
                $deliveryOptions['shipping_rates_id'] = $data['delivery']['shippingRates']['id'];
            }
            if (!empty($data['delivery']['shippingRates']['name'])) {
                $deliveryOptions['shipping_rates_name'] = $data['delivery']['shippingRates']['name'];
            }
            if (!empty($data['delivery']['handlingTime'])) {
                $deliveryOptions['handling_time'] = $data['delivery']['handlingTime'];
            }
            if (!empty($data['delivery']['additionalInfo'])) {
                $deliveryOptions['additional_info'] = $data['delivery']['additionalInfo'];
            }
            $productOffer->setDeliveryOptions($deliveryOptions);
        }
        
        // Płatności
        if (!empty($data['payments'])) {
            $productOffer->setPayments($data['payments']);
        }
        
        // Lokalizacja
        if (!empty($data['location'])) {
            $productOffer->setLocation($data['location']);
        }
        
        // Zdjęcia
        if (!empty($data['images'])) {
            $productOffer->setImages($data['images']);
        }
        
        // Opis
        if (!empty($data['description'])) {
            $productOffer->setDescription($data['description']);
        }
        
        // External ID
        if (!empty($data['external']['id'])) {
            $productOffer->setExternalId($data['external']['id']);
        }
        
        // Usługi posprzedażowe
        if (!empty($data['afterSalesServices'])) {
            $productOffer->setAfterSalesServices($data['afterSalesServices']);
        }
        
        // Załączniki
        if (!empty($data['attachments'])) {
            $productOffer->setAttachments($data['attachments']);
        }

        return $productOffer;
    }

    /**
     * Validate required fields for API
     * 
     * @param ProductOfferInterface $productOffer
     * @throws ClientException
     */
    private function validateRequiredFields(ProductOfferInterface $productOffer): void
    {
        $errors = [];
        
        // Sprawdź wymagane pola
        if (empty($productOffer->getName())) {
            $errors[] = 'Name is required';
        }
        
        $sellingMode = $productOffer->getSellingMode();
        if (empty($sellingMode) || !isset($sellingMode['price']['amount'])) {
            $errors[] = 'SellingMode with price is required';
        }
        
        $location = $productOffer->getLocation();
        if (empty($location) || empty($location['city']) || empty($location['countryCode'])) {
            $errors[] = 'Location with city and countryCode is required';
        }
        
        if (!empty($errors)) {
            throw new ClientException(__('Validation failed: %1', implode(', ', $errors)));
        }
    }
    
    /**
     * Format parameters to correct API structure
     * 
     * @param array $parameters
     * @return array
     */
    private function formatParameters(array $parameters): array
    {
        $formatted = [];
        
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                // Jeśli to już poprawna struktura (tablica obiektów)
                if (isset($value['id']) || (isset($value[0]) && is_array($value[0]) && isset($value[0]['id']))) {
                    // Filtruj puste wartości w już poprawnej strukturze
                    foreach ($parameters as $param) {
                        if (isset($param['valuesIds']) && !empty($param['valuesIds'])) {
                            // Filtruj puste strings z valuesIds
                            $cleanValuesIds = array_filter($param['valuesIds'], function($v) {
                                return $v !== '' && $v !== null;
                            });
                            
                            if (!empty($cleanValuesIds)) {
                                $param['valuesIds'] = array_values($cleanValuesIds);
                                $formatted[] = $param;
                            }
                        }
                    }
                    break;
                }
                
                // Konwersja ze starej struktury klucz => wartości
                // Pomiń puste wartości
                $cleanValues = array_filter($value, function($v) {
                    return $v !== '' && $v !== null;
                });
                
                if (!empty($cleanValues)) {
                    $formatted[] = [
                        'id' => (string)$key,
                        'valuesIds' => array_values($cleanValues)
                    ];
                }
            } else {
                // Pojedyncza wartość - pomiń jeśli pusta
                if ($value !== '' && $value !== null) {
                    $formatted[] = [
                        'id' => (string)$key,
                        'valuesIds' => [$value]
                    ];
                }
            }
        }
        
        return $formatted;
    }
    
    /**
     * Parse API error message to human readable format
     * 
     * @param string $errorMessage
     * @return string
     */
    private function parseApiError(string $errorMessage): string
    {
        // Próba wyodrębnienia szczegółów błędu z odpowiedzi JSON
        if (preg_match('/\{"errors":\[(.*?)\]\}/', $errorMessage, $matches)) {
            $errorsJson = '{"errors":[' . $matches[1] . ']}';
            $decoded = json_decode($errorsJson, true);
            
            if ($decoded && isset($decoded['errors'])) {
                $errorDetails = [];
                foreach ($decoded['errors'] as $error) {
                    $message = $error['message'] ?? 'Unknown error';
                    $path = $error['path'] ?? null;
                    $code = $error['code'] ?? null;
                    
                    $detail = $message;
                    if ($path) {
                        $detail .= " (field: $path)";
                    }
                    if ($code) {
                        $detail .= " [code: $code]";
                    }
                    
                    $errorDetails[] = $detail;
                }
                
                return implode(', ', $errorDetails);
            }
        }
        
        return $errorMessage;
    }
    
    /**
     * Get default return policy ID
     * 
     * @return string|null
     */
    private function getDefaultReturnPolicy(): ?string
    {
        // Endpoint /sale/return-policies nie istnieje w sandbox
        // Pomijamy pole afterSalesServices dla teraz
        return null;
    }
    
    /**
     * Validate and filter parameters for specific category
     * 
     * @param array $parameters
     * @param string $categoryId
     * @return array
     */
    private function validateParametersForCategory(array $parameters, string $categoryId): array
    {
        // Lista parametrów, które są ogólne i działają dla większości kategorii
        $commonParameters = [
            '217', // Brand
            '219', // Color  
            '224017', // Manufacturer code
            '225693' // EAN
        ];
        
        $validParameters = [];
        
        foreach ($parameters as $param) {
            $paramId = $param['id'] ?? null;
            
            // Zachowaj tylko ogólne parametry lub sprawdzone dla tej kategorii
            if (in_array($paramId, $commonParameters)) {
                $validParameters[] = $param;
            } else {
                // Log parametrów, które są pomijane
                $this->logger->info("Skipping parameter {$paramId} for category {$categoryId}");
            }
        }
        
        return $validParameters;
    }
} 