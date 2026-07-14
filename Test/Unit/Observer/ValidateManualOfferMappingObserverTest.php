<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Observer;

use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Model\ResourceModel\Product as ProductResource;
use Macopedia\Allegro\Observer\ValidateManualOfferMappingObserver;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ValidateManualOfferMappingObserverTest extends TestCase
{
    public function testVerifiesChangedMappingAndCopiesCatalogProductId(): void
    {
        $offer = $this->createMock(ProductOfferInterface::class);
        $offer->method('getProductId')->willReturn('catalog-product-id');
        $repository = $this->createMock(ProductOfferRepositoryInterface::class);
        $repository->expects(self::once())->method('get')->with('7781864283')->willReturn($offer);
        $resource = $this->createMock(ProductResource::class);
        $resource->method('getIdByAllegroOfferId')->willReturn(false);
        $product = $this->product('7781864283', null);
        $product->expects(self::once())->method('setData')->with('allegro_product_id', 'catalog-product-id');

        (new ValidateManualOfferMappingObserver($repository, $resource))->execute($this->observer($product));
    }

    public function testRejectsOfferAlreadyMappedToDifferentProduct(): void
    {
        $repository = $this->createMock(ProductOfferRepositoryInterface::class);
        $repository->expects(self::never())->method('get');
        $resource = $this->createMock(ProductResource::class);
        $resource->method('getIdByAllegroOfferId')->willReturn('99');

        $this->expectException(LocalizedException::class);
        (new ValidateManualOfferMappingObserver($repository, $resource))
            ->execute($this->observer($this->product('7781864283', null)));
    }

    private function product(string $offerId, ?string $original): Product
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getData', 'getOrigData', 'setData'])
            ->getMock();
        $product->method('getId')->willReturn(2041);
        $product->method('getData')->with('allegro_offer_id')->willReturn($offerId);
        $product->method('getOrigData')->with('allegro_offer_id')->willReturn($original);
        return $product;
    }

    private function observer(Product $product): Observer
    {
        return new Observer(['event' => new DataObject(['product' => $product])]);
    }
}
