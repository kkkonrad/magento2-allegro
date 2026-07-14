<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model;

use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Api\ClientResponseException;
use Macopedia\Allegro\Model\Api\TokenProvider;
use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Model\OffersMapping;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OffersMappingTest extends TestCase
{
    public function testRemovesMappingOnlyAfterConfirmedNotFound(): void
    {
        [$service, $offers, $resource, $product] = $this->fixture();
        $offers->method('get')->willThrowException(new NoSuchEntityException(__('Not found')));
        $resource->expects(self::once())->method('saveAttribute')->with($product, 'allegro_offer_id');

        $result = $service->clean(false, 10);

        self::assertSame(1, $result['checked']);
        self::assertSame(1, $result['missing']);
        self::assertSame(1, $result['removed']);
        self::assertNull($product->getData('allegro_offer_id'));
    }

    public function testDryRunDoesNotRemoveConfirmedMissingMapping(): void
    {
        [$service, $offers, $resource, $product] = $this->fixture();
        $offers->method('get')->willThrowException(new NoSuchEntityException(__('Not found')));
        $resource->expects(self::never())->method('saveAttribute');

        $result = $service->clean(true, 10);

        self::assertSame(1, $result['missing']);
        self::assertSame(0, $result['removed']);
        self::assertSame('7781864283', $product->getData('allegro_offer_id'));
        self::assertSame('would_remove', $result['details'][0]['result']);
    }

    public function testTransportFailureNeverRemovesMapping(): void
    {
        [$service, $offers, $resource, $product] = $this->fixture();
        $offers->method('get')->willThrowException(
            new ClientResponseException(__('Temporary failure'), null, 0, 503)
        );
        $resource->expects(self::never())->method('saveAttribute');

        $result = $service->clean(false, 10);

        self::assertSame(1, $result['failed']);
        self::assertSame(0, $result['removed']);
        self::assertSame('7781864283', $product->getData('allegro_offer_id'));
    }

    /**
     * @return array{OffersMapping, ProductOfferRepositoryInterface&MockObject, ProductResource&MockObject, Product&MockObject}
     */
    private function fixture(): array
    {
        $offerId = '7781864283';
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getData', 'setData'])
            ->getMock();
        $product->method('getId')->willReturn(2041);
        $product->method('getData')->willReturnCallback(
            static function (string $key) use (&$offerId) {
                return $key === 'allegro_offer_id' ? $offerId : null;
            }
        );
        $product->method('setData')->willReturnCallback(
            static function (string $key, $value = null) use (&$offerId, &$product) {
                if ($key === 'allegro_offer_id') {
                    $offerId = $value;
                }
                return $product;
            }
        );

        $collection = $this->createMock(Collection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addStoreFilter')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('setCurPage')->willReturnSelf();
        $collection->method('getItems')->willReturn([$product]);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $offers = $this->createMock(ProductOfferRepositoryInterface::class);
        $resource = $this->createMock(ProductResource::class);
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getStoreId')->willReturn(0);
        $tokenProvider = $this->createMock(TokenProvider::class);
        $tokenProvider->expects(self::once())->method('getCurrent');

        return [
            new OffersMapping(
                $offers,
                $this->createMock(Logger::class),
                $configuration,
                $collectionFactory,
                $tokenProvider,
                $resource
            ),
            $offers,
            $resource,
            $product,
        ];
    }
}
