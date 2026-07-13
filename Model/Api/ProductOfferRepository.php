<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Api;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\ResourceModel\AbstractResource;

class ProductOfferRepository implements ProductOfferRepositoryInterface
{
    private const API_ENDPOINT = '/sale/product-offers';
    private const API_ENDPOINT_GET = '/sale/product-offers/{offerId}';
    private const API_ENDPOINT_PRODUCT = '/sale/product-offers?product.id={productId}';

    /** @var AbstractResource */
    private $resource;

    /** @var ProductOfferFactory */
    private $productOfferFactory;

    /** @var ProductOfferPayloadBuilder */
    private $payloadBuilder;

    /** @var Logger */
    private $logger;

    public function __construct(
        AbstractResource $resource,
        ProductOfferFactory $productOfferFactory,
        ProductOfferPayloadBuilder $payloadBuilder,
        Logger $logger
    ) {
        $this->resource = $resource;
        $this->productOfferFactory = $productOfferFactory;
        $this->payloadBuilder = $payloadBuilder;
        $this->logger = $logger;
    }

    public function save(ProductOfferInterface $productOffer): string
    {
        $data = $this->payloadBuilder->build($productOffer);

        $this->logger->debug('Prepared Allegro product offer payload', [
            'operation' => $productOffer->getId() ? 'update' : 'create',
            'payload_fields' => array_keys($data),
            'product_id' => $productOffer->getProductId(),
        ]);

        try {
            if ($productOffer->getId()) {
                $response = $this->resource->requestPut(
                    $this->offerUri($productOffer->getId()),
                    $data
                );
            } else {
                $response = $this->resource->requestPost(self::API_ENDPOINT, $data);
            }
        } catch (ClientException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->apiFailure('Unexpected product offer repository failure', [
                'operation' => $productOffer->getId() ? 'update' : 'create',
                'exception_type' => get_class($e),
            ]);
            throw new ClientException(__('Could not save Allegro product offer.'), $e);
        }

        if (!is_array($response) || empty($response['id'])) {
            throw new ClientException(__('Allegro API response does not contain an offer ID.'));
        }

        return (string)$response['id'];
    }

    public function get(string $offerId): ProductOfferInterface
    {
        try {
            $response = $this->resource->requestGet($this->offerUri($offerId));
        } catch (ClientResponseException $e) {
            if ($e->getHttpStatusCode() === 404) {
                throw new ClientException(__('Product offer with ID "%1" does not exist.', $offerId), $e);
            }
            throw $e;
        }

        return $this->createProductOffer($response);
    }

    public function getByProductId(string $productId): array
    {
        try {
            $response = $this->resource->requestGet(
                str_replace('{productId}', rawurlencode($productId), self::API_ENDPOINT_PRODUCT)
            );
        } catch (ClientResponseException $e) {
            if ($e->getHttpStatusCode() === 404) {
                return [];
            }
            throw $e;
        }

        $offers = [];
        foreach ((array)($response['offers'] ?? []) as $offerData) {
            if (is_array($offerData)) {
                $offers[] = $this->createProductOffer($offerData);
            }
        }

        return $offers;
    }

    public function delete(string $offerId): bool
    {
        try {
            $this->resource->requestDelete($this->offerUri($offerId));
            return true;
        } catch (ClientResponseException $e) {
            if ($e->getHttpStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    private function createProductOffer(array $data): ProductOfferInterface
    {
        /** @var ProductOfferInterface $productOffer */
        $productOffer = $this->productOfferFactory->create();

        if (!empty($data['id'])) {
            $productOffer->setId((string)$data['id']);
        }
        if (!empty($data['name'])) {
            $productOffer->setName((string)$data['name']);
        }
        if (!empty($data['productSet'][0]['product']['id'])) {
            $productOffer->setProductId((string)$data['productSet'][0]['product']['id']);
        }
        if (!empty($data['seller']['id'])) {
            $productOffer->setSellerId((string)$data['seller']['id']);
        }
        if (isset($data['sellingMode']) && is_array($data['sellingMode'])) {
            $productOffer->setSellingMode($data['sellingMode']);
            if (isset($data['sellingMode']['price']['amount'])) {
                $productOffer->setPrice((float)$data['sellingMode']['price']['amount']);
            }
        }
        if (isset($data['stock']['available'])) {
            $productOffer->setQuantity((int)$data['stock']['available']);
        }
        if (!empty($data['publication']['status'])) {
            $productOffer->setStatus((string)$data['publication']['status']);
        }
        if (!empty($data['category']['id'])) {
            $productOffer->setCategory((string)$data['category']['id']);
        }
        if (!empty($data['parameters']) && is_array($data['parameters'])) {
            $productOffer->setParameters($data['parameters']);
        }
        if (!empty($data['delivery']) && is_array($data['delivery'])) {
            $productOffer->setDeliveryOptions($this->mapDeliveryOptions($data['delivery']));
        }
        if (!empty($data['payments']) && is_array($data['payments'])) {
            $productOffer->setPayments($data['payments']);
        }
        if (!empty($data['location']) && is_array($data['location'])) {
            $productOffer->setLocation($data['location']);
        }
        if (!empty($data['images']) && is_array($data['images'])) {
            $productOffer->setImages($data['images']);
        }
        if (!empty($data['description']) && is_array($data['description'])) {
            $productOffer->setDescription($data['description']);
        }
        if (!empty($data['external']['id'])) {
            $productOffer->setExternalId((string)$data['external']['id']);
        }
        if (!empty($data['afterSalesServices']) && is_array($data['afterSalesServices'])) {
            $productOffer->setAfterSalesServices($data['afterSalesServices']);
        }
        if (!empty($data['attachments']) && is_array($data['attachments'])) {
            $productOffer->setAttachments($data['attachments']);
        }

        return $productOffer;
    }

    private function mapDeliveryOptions(array $delivery): array
    {
        $options = [];
        if (!empty($delivery['shippingRates']['id'])) {
            $options['shipping_rates_id'] = (string)$delivery['shippingRates']['id'];
        }
        if (!empty($delivery['shippingRates']['name'])) {
            $options['shipping_rates_name'] = (string)$delivery['shippingRates']['name'];
        }
        if (!empty($delivery['handlingTime'])) {
            $options['handling_time'] = (string)$delivery['handlingTime'];
        }
        if (!empty($delivery['additionalInfo'])) {
            $options['additional_info'] = (string)$delivery['additionalInfo'];
        }

        return $options;
    }

    private function offerUri(string $offerId): string
    {
        return str_replace('{offerId}', rawurlencode($offerId), self::API_ENDPOINT_GET);
    }
}
