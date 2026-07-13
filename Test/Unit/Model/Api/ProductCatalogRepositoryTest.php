<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Api;

use Macopedia\Allegro\Model\Api\ClientException;
use Macopedia\Allegro\Model\Api\ProductCatalogRepository;
use Macopedia\Allegro\Model\Api\ProductFactory;
use Macopedia\Allegro\Model\Data\Product;
use Macopedia\Allegro\Model\ResourceModel\AbstractResource;
use PHPUnit\Framework\TestCase;

class ProductCatalogRepositoryTest extends TestCase
{
    public function testSearchConvertsLegacyEanArgumentToGtinQuery(): void
    {
        $resource = $this->createMock(AbstractResource::class);
        $resource->expects(self::once())
            ->method('requestGet')
            ->with('/sale/products?phrase=5901234123457&mode=GTIN')
            ->willReturn([
                'products' => [[
                    'id' => 'catalog-id',
                    'name' => 'Catalog product',
                    'category' => ['id' => '123'],
                ]],
            ]);
        $factory = $this->createMock(ProductFactory::class);
        $factory->method('create')->willReturn(new Product());

        $products = (new ProductCatalogRepository($resource, $factory))->search([
            'ean' => '5901234123457',
        ]);

        self::assertCount(1, $products);
        self::assertSame('catalog-id', $products[0]->getId());
    }

    public function testSearchDoesNotHideApiFailureAsEmptyResult(): void
    {
        $resource = $this->createMock(AbstractResource::class);
        $resource->method('requestGet')->willThrowException(new ClientException(__('API failure')));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('API failure');

        (new ProductCatalogRepository($resource, $this->createMock(ProductFactory::class)))
            ->search(['ean' => '5901234123457']);
    }

    public function testSearchRejectsInvalidGtinBeforeRequest(): void
    {
        $resource = $this->createMock(AbstractResource::class);
        $resource->expects(self::never())->method('requestGet');

        $this->expectException(ClientException::class);

        (new ProductCatalogRepository($resource, $this->createMock(ProductFactory::class)))
            ->search(['ean' => '5901234123456']);
    }
}
