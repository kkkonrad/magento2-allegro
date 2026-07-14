<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Setup\Patch\Data;

use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddEanAttribute implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /** @var EavSetupFactory */
    private $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $entityTypeId = $eavSetup->getEntityTypeId('catalog_product');
        $groupName = 'Allegro';

        if (!$eavSetup->getAttributeId($entityTypeId, 'ean')) {
            $eavSetup->addAttribute($entityTypeId, 'ean', [
                'type' => 'varchar',
                'label' => 'EAN / GTIN',
                'input' => 'text',
                'required' => false,
                'user_defined' => true,
                'searchable' => true,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => true,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            ]);
        }

        $attributeId = $eavSetup->getAttributeId($entityTypeId, 'ean');
        foreach ($eavSetup->getAllAttributeSetIds($entityTypeId) as $attributeSetId) {
            $eavSetup->addAttributeGroup($entityTypeId, $attributeSetId, $groupName, 10);
            $attributeGroupId = $eavSetup->getAttributeGroupId(
                $entityTypeId,
                $attributeSetId,
                $groupName
            );
            $eavSetup->addAttributeToGroup(
                $entityTypeId,
                $attributeSetId,
                $attributeGroupId,
                $attributeId,
                null
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
