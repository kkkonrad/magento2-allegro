<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Offer;

use Macopedia\Allegro\Api\Data\ParameterDefinition\DictionaryItemInterface;
use Macopedia\Allegro\Api\Data\ParameterDefinitionInterface;
use Macopedia\Allegro\Api\ParameterDefinitionRepositoryInterface;
use Macopedia\Allegro\Model\Configuration;
use Macopedia\Allegro\Model\Offer\ProductBrandParameterResolver;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ProductBrandParameterResolverTest extends TestCase
{
    public function testMapsConfiguredAttributeLabelToAllegroDictionaryValue(): void
    {
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getBrandAttributeCode')->willReturn('manufacturer');

        $product = $this->createMock(Product::class);
        $product->method('getAttributeText')->with('manufacturer')->willReturn('Test Brand');
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('getById')->with(42, false, 0, true)->willReturn($product);

        $item = $this->createMock(DictionaryItemInterface::class);
        $item->method('getLabel')->willReturn('test brand');
        $item->method('getValue')->willReturn('248811_123');
        $definition = $this->createMock(ParameterDefinitionInterface::class);
        $definition->method('getDescribesProduct')->willReturn(true);
        $definition->method('getName')->willReturn('Marka');
        $definition->method('getType')->willReturn(ParameterDefinitionInterface::TYPE_DICTIONARY);
        $definition->method('getId')->willReturn('248811');
        $definition->method('getDictionary')->willReturn([$item]);
        $definitions = $this->createMock(ParameterDefinitionRepositoryInterface::class);
        $definitions->method('getListByCategoryId')->with(123)->willReturn([$definition]);

        $resolver = new ProductBrandParameterResolver($configuration, $products, $definitions);

        self::assertSame(
            [['id' => '248811', 'valuesIds' => ['248811_123']]],
            $resolver->resolve(42, 123)
        );
    }

    public function testRejectsBrandMissingFromAllegroDictionary(): void
    {
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getBrandAttributeCode')->willReturn('manufacturer');
        $product = $this->createMock(Product::class);
        $product->method('getAttributeText')->willReturn('Unknown Brand');
        $products = $this->createMock(ProductRepositoryInterface::class);
        $products->method('getById')->willReturn($product);
        $definition = $this->createMock(ParameterDefinitionInterface::class);
        $definition->method('getDescribesProduct')->willReturn(true);
        $definition->method('getName')->willReturn('Marka');
        $definition->method('getType')->willReturn(ParameterDefinitionInterface::TYPE_DICTIONARY);
        $definition->method('getId')->willReturn('248811');
        $definition->method('getDictionary')->willReturn([]);
        $definitions = $this->createMock(ParameterDefinitionRepositoryInterface::class);
        $definitions->method('getListByCategoryId')->willReturn([$definition]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown Brand');

        (new ProductBrandParameterResolver($configuration, $products, $definitions))->resolve(42, 123);
    }
}
