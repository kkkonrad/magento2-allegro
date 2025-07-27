<?php

namespace Macopedia\Allegro\Model\Api;

use Macopedia\Allegro\Api\Data\ProductInterface;
use Macopedia\Allegro\Api\ProductCatalogRepositoryInterface;
use Macopedia\Allegro\Model\Api\Client\ClientException;
use Macopedia\Allegro\Model\ResourceModel\AbstractResource;
use Macopedia\Allegro\Api\Data\TokenInterface;

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
     * @var TokenInterface
     */
    private $token;

    /**
     * @param AbstractResource $resource
     * @param ProductFactory $productFactory
     * @param TokenInterface $token
     */
    public function __construct(
        AbstractResource $resource,
        ProductFactory $productFactory,
        TokenInterface $token
    ) {
        $this->resource = $resource;
        $this->productFactory = $productFactory;
        $this->token = $token;
    }

    /**
     * @inheritDoc
     */
    public function search(array $parameters = []): array
    {
        if(empty($parameters)) {
            $parameters = $_GET;
        }
        
         try {
            $queryParams = http_build_query($parameters);

         

            $response = $this->resource->requestGet(
                self::API_ENDPOINT_SEARCH . '?' . $queryParams,
            );


            $products = [];
            foreach ($response['products'] as $productData) {
                $products[] = $this->createProduct($productData);
            }

            return $products;
        } catch (ClientException $e) {
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $productId): ProductInterface
    {
        try {
            $response = $this->resource->requestGet(
                str_replace('{productId}', $productId, self::API_ENDPOINT_GET)
            );

            return $this->createProduct($response);
        } catch (ClientException $e) {
            throw new ClientException(__('Could not get product: %1', $e->getMessage()));
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
            throw new ClientException(__('Could not create product: %1', $e->getMessage()));
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

        $product->setId($data['id'] ?? null);
        $product->setName($data['name']);
        $product->setCategory($data['category']['id']);
        $product->setImages($data['images'] ?? []);
        $product->setParameters($data['parameters'] ?? []);
        $product->setDescription($data['description'] ?? []);

        return $product;
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