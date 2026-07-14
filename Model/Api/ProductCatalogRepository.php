<?php

namespace Macopedia\Allegro\Model\Api;

use Macopedia\Allegro\Api\Data\ProductInterface;
use Macopedia\Allegro\Api\ProductCatalogRepositoryInterface;
use Macopedia\Allegro\Model\Api\ClientException;
use Macopedia\Allegro\Model\ResourceModel\AbstractResource;

class ProductCatalogRepository implements ProductCatalogRepositoryInterface
{
    private const API_ENDPOINT_SEARCH = '/sale/products';
    private const API_ENDPOINT_GET = '/sale/products/{productId}';
    private const API_ENDPOINT_CREATE = '/sale/products';

    /**
     * @var AbstractResource
     */
    private $resource;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @param AbstractResource $resource
     * @param ProductFactory $productFactory
     */
    public function __construct(
        AbstractResource $resource,
        ProductFactory $productFactory
    ) {
        $this->resource = $resource;
        $this->productFactory = $productFactory;
    }

    /**
     * @inheritDoc
     */
    public function search(array $parameters = []): array
    {
        if (isset($parameters['ean'])) {
            $ean = trim((string)$parameters['ean']);
            $this->validateGtin($ean);
            unset($parameters['ean']);
            $parameters['phrase'] = $ean;
            $parameters['mode'] = 'GTIN';
        }

        if (trim((string)($parameters['phrase'] ?? '')) === '') {
            throw new ClientException(__('A product search phrase is required.'));
        }

        $phrase = trim((string)$parameters['phrase']);
        if (mb_strlen($phrase) < 3 || mb_strlen($phrase) > 1024) {
            throw new ClientException(__('Product search phrase must contain between 3 and 1024 characters.'));
        }
        $parameters['phrase'] = $phrase;

        if (isset($parameters['language']) && !in_array($parameters['language'], [
            'pl-PL', 'en-US', 'uk-UA', 'sk-SK', 'cs-CZ', 'hu-HU'
        ], true)) {
            throw new ClientException(__('Unsupported Allegro catalog language.'));
        }

        if (isset($parameters['category.id']) && !preg_match('/^\d+$/', (string)$parameters['category.id'])) {
            throw new ClientException(__('Allegro category ID must contain digits only.'));
        }

        $response = $this->resource->requestGet(
            self::API_ENDPOINT_SEARCH . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986)
        );

        $products = [];
        foreach ((array)($response['products'] ?? []) as $productData) {
            if (is_array($productData)) {
                $products[] = $this->createProduct($productData);
            }
        }

        return $products;
    }

    private function validateGtin(string $gtin): void
    {
        if (!preg_match('/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/', $gtin)) {
            throw new ClientException(__('GTIN must contain 8, 12, 13 or 14 digits.'));
        }

        $digits = str_split($gtin);
        $lastIndex = count($digits) - 1;
        $sum = 0;
        foreach (array_slice($digits, 0, -1) as $index => $digit) {
            $sum += (int)$digit * (($lastIndex - $index) % 2 === 1 ? 3 : 1);
        }
        if ((10 - ($sum % 10)) % 10 !== (int)$digits[$lastIndex]) {
            throw new ClientException(__('GTIN check digit is invalid.'));
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $productId): ProductInterface
    {
        try {
            $response = $this->resource->requestGet(
                str_replace('{productId}', rawurlencode($productId), self::API_ENDPOINT_GET)
            );

            return $this->createProduct($response);
        } catch (ClientException $e) {
            throw new ClientException(__('Could not get Allegro catalog product.'), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function create(ProductInterface $product): string
    {
        try {
            $data = $this->prepareData($product);
            
            $response = $this->resource->requestPost(
                self::API_ENDPOINT_CREATE,
                $data
            );

            return $response['id'];
        } catch (ClientException $e) {
            throw new ClientException(__('Could not create Allegro catalog product.'), $e);
        }
    }

    /**
     * @param array $data
     * @return ProductInterface
     */
    private function createProduct(array $data): ProductInterface
    {
        /** @var ProductInterface $product */
        $product = $this->productFactory->create();

        if (empty($data['id']) || empty($data['name']) || empty($data['category']['id'])) {
            throw new ClientException(__('Allegro catalog returned an incomplete product.'));
        }

        $product->setId((string)$data['id']);
        $product->setName((string)$data['name']);
        $product->setCategory((string)$data['category']['id']);
        $product->setImages($data['images'] ?? []);
        $product->setParameters($data['parameters'] ?? []);
        $product->setGtin($this->extractGtin($data['parameters'] ?? []));
        $product->setDescription($data['description'] ?? []);

        return $product;
    }

    private function extractGtin(array $parameters): ?string
    {
        foreach ($parameters as $parameter) {
            if (!is_array($parameter) || empty($parameter['options']['isGTIN'])) {
                continue;
            }

            foreach (['values', 'valuesLabels'] as $field) {
                foreach ((array)($parameter[$field] ?? []) as $value) {
                    $gtin = preg_replace('/\D+/', '', (string)$value);
                    if (preg_match('/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/', (string)$gtin)) {
                        return (string)$gtin;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param ProductInterface $product
     * @return array
     */
    private function prepareData(ProductInterface $product): array
    {
        return [
            'name' => $product->getName(),
            'category' => [
                'id' => $product->getCategory()
            ],
            'images' => $product->getImages(),
            'parameters' => $product->getParameters(),
            'description' => $product->getDescription()
        ];
    }
}
