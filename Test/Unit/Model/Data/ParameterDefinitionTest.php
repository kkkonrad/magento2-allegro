<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Data;

use Macopedia\Allegro\Api\Data\ParameterDefinition\DictionaryItemInterfaceFactory;
use Macopedia\Allegro\Api\Data\ParameterDefinition\RestrictionInterfaceFactory;
use Macopedia\Allegro\Model\Data\ParameterDefinition;
use PHPUnit\Framework\TestCase;

class ParameterDefinitionTest extends TestCase
{
    public function testMapsDescribesProductOption(): void
    {
        $definition = new ParameterDefinition(
            $this->createMock(DictionaryItemInterfaceFactory::class),
            $this->createMock(RestrictionInterfaceFactory::class)
        );

        $definition->setRawData([
            'id' => '235201',
            'name' => 'Rodzaj',
            'type' => 'dictionary',
            'options' => ['describesProduct' => true],
        ]);

        self::assertTrue($definition->getDescribesProduct());
    }

    public function testDefaultsToOfferParameter(): void
    {
        $definition = new ParameterDefinition(
            $this->createMock(DictionaryItemInterfaceFactory::class),
            $this->createMock(RestrictionInterfaceFactory::class)
        );

        $definition->setRawData(['id' => '11323', 'name' => 'Stan', 'type' => 'dictionary']);

        self::assertFalse($definition->getDescribesProduct());
    }
}
