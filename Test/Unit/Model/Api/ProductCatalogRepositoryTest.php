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

    public function testSearchByNameReturnsGtinFromIdentifyingParameter(): void
    {
        $resource = $this->createMock(AbstractResource::class);
        $resource->expects(self::once())
            ->method('requestGet')
            ->with('/sale/products?phrase=LEGO%20Technic&language=pl-PL')
            ->willReturn([
                'products' => [[
                    'id' => 'catalog-id',
                    'name' => 'LEGO Technic 42154',
                    'category' => ['id' => '123'],
                    'parameters' => [[
                        'id' => '225693',
                        'name' => 'GTIN',
                        'values' => ['5702017424735'],
                        'options' => ['isGTIN' => true],
                    ]],
                ]],
            ]);
        $factory = $this->createMock(ProductFactory::class);
        $factory->method('create')->willReturn(new Product());

        $products = (new ProductCatalogRepository($resource, $factory))->search([
            'phrase' => ' LEGO Technic ',
            'language' => 'pl-PL',
        ]);

        self::assertCount(1, $products);
        self::assertSame('5702017424735', $products[0]->getGtin());
        self::assertSame('LEGO Technic 42154', $products[0]->getName());
    }

    public function testSearchByNameCanBeRestrictedToCategory(): void
    {
        $resource = $this->createMock(AbstractResource::class);
        $resource->expects(self::once())
            ->method('requestGet')
            ->with('/sale/products?phrase=iPhone%2015&language=pl-PL&category.id=253002')
            ->willReturn(['products' => []]);

        $products = (new ProductCatalogRepository(
            $resource,
            $this->createMock(ProductFactory::class)
        ))->search([
            'phrase' => 'iPhone 15',
            'language' => 'pl-PL',
            'category.id' => '253002',
        ]);

        self::assertSame([], $products);
    }

    public function testSearchRejectsNameShorterThanThreeCharacters(): void
    {
        $resource = $this->createMock(AbstractResource::class);
        $resource->expects(self::never())->method('requestGet');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('between 3 and 1024');

        (new ProductCatalogRepository($resource, $this->createMock(ProductFactory::class)))
            ->search(['phrase' => 'TV']);
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
