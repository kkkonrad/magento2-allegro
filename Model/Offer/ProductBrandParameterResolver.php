<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Offer;

use Macopedia\Allegro\Api\Data\ParameterDefinitionInterface;
use Macopedia\Allegro\Api\ParameterDefinitionRepositoryInterface;
use Macopedia\Allegro\Model\Configuration;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

class ProductBrandParameterResolver
{
    /** @var Configuration */
    private $configuration;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var ParameterDefinitionRepositoryInterface */
    private $parameterDefinitionRepository;

    public function __construct(
        Configuration $configuration,
        ProductRepositoryInterface $productRepository,
        ParameterDefinitionRepositoryInterface $parameterDefinitionRepository
    ) {
        $this->configuration = $configuration;
        $this->productRepository = $productRepository;
        $this->parameterDefinitionRepository = $parameterDefinitionRepository;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
     */
    public function resolve(int $magentoProductId, int $categoryId): array
    {
        $attributeCode = trim((string)$this->configuration->getBrandAttributeCode());
        if ($attributeCode === '') {
            return [];
        }

        $product = $this->productRepository->getById($magentoProductId, false, 0, true);
        $brand = $product->getAttributeText($attributeCode);
        if (is_array($brand)) {
            $brand = reset($brand);
        }
        if (!is_scalar($brand) || trim((string)$brand) === '') {
            $brand = $product->getData($attributeCode);
        }
        if (!is_scalar($brand) || trim((string)$brand) === '') {
            return [];
        }
        $brand = trim((string)$brand);

        $definition = $this->findBrandDefinition($categoryId);
        if ($definition === null) {
            return [];
        }

        if ($definition->getType() !== ParameterDefinitionInterface::TYPE_DICTIONARY) {
            return [['id' => $definition->getId(), 'values' => [$brand]]];
        }

        foreach ($definition->getDictionary() as $item) {
            if ($this->normalize((string)$item->getLabel()) === $this->normalize($brand)) {
                return [['id' => $definition->getId(), 'valuesIds' => [(string)$item->getValue()]]];
            }
        }

        throw new LocalizedException(
            __(
                'Magento brand "%1" is not available in the Allegro brand dictionary for category %2.',
                $brand,
                $categoryId
            )
        );
    }

    private function findBrandDefinition(int $categoryId): ?ParameterDefinitionInterface
    {
        $fallback = null;
        foreach ($this->parameterDefinitionRepository->getListByCategoryId($categoryId) as $definition) {
            if (!$definition->getDescribesProduct()) {
                continue;
            }
            $name = $this->normalize((string)$definition->getName());
            if ($name === 'marka') {
                return $definition;
            }
            if ($fallback === null
                && ($name === 'producent' || strpos($name, 'marka ') === 0 || strpos($name, 'marka /') === 0)
            ) {
                $fallback = $definition;
            }
        }

        return $fallback;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?: $value), 'UTF-8');
    }
}
