<?php

namespace Macopedia\Allegro\Model\Api;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Model\Api\Client\ClientException;
use Macopedia\Allegro\Model\ResourceModel\AbstractResource;

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
     * @param AbstractResource $resource
     * @param ProductOfferFactory $productOfferFactory
     */
    public function __construct(
        AbstractResource $resource,
        ProductOfferFactory $productOfferFactory
    ) {
        $this->resource = $resource;
        $this->productOfferFactory = $productOfferFactory;
    }

    /**
     * @inheritDoc
     */
    public function save(ProductOfferInterface $productOffer): string
    {
        try {
            $data = $this->prepareData($productOffer);
            
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

            return $response['id'];
        } catch (ClientException $e) {
            throw new ClientException(__('Could not save product offer: %1', $e->getMessage()));
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
     */
    private function prepareData(ProductOfferInterface $productOffer): array
    {
        return [
            'product' => [
                'id' => $productOffer->getProductId()
            ],
            'seller' => [
                'id' => $productOffer->getSellerId()
            ],
            'price' => [
                'amount' => $productOffer->getPrice(),
                'currency' => 'PLN'
            ],
            'quantity' => $productOffer->getQuantity(),
            'status' => $productOffer->getStatus(),
            'parameters' => $productOffer->getParameters(),
            'delivery' => [
                'options' => $productOffer->getDeliveryOptions()
            ],
            'payments' => $productOffer->getPayments()
        ];
    }

    /**
     * @param array $data
     * @return ProductOfferInterface
     */
    private function createProductOffer(array $data): ProductOfferInterface
    {
        /** @var ProductOfferInterface $productOffer */
        $productOffer = $this->productOfferFactory->create();
        
        $productOffer->setId($data['id'] ?? null);
        $productOffer->setProductId($data['product']['id']);
        $productOffer->setSellerId($data['seller']['id']);
        $productOffer->setPrice($data['price']['amount']);
        $productOffer->setQuantity($data['quantity']);
        $productOffer->setStatus($data['status']);
        $productOffer->setParameters($data['parameters'] ?? []);
        $productOffer->setDeliveryOptions($data['delivery']['options'] ?? []);
        $productOffer->setPayments($data['payments'] ?? []);

        return $productOffer;
    }
} 