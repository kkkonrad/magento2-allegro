<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Data;

use Macopedia\Allegro\Api\Data\ImageInterfaceFactory;
use Macopedia\Allegro\Api\Data\Offer\AfterSalesServicesInterface;
use Macopedia\Allegro\Api\Data\Offer\AfterSalesServicesInterfaceFactory;
use Macopedia\Allegro\Api\Data\Offer\LocationInterface;
use Macopedia\Allegro\Api\Data\Offer\LocationInterfaceFactory;
use Macopedia\Allegro\Api\Data\OfferInterface;
use Macopedia\Allegro\Api\ParameterDefinitionRepositoryInterface;
use Macopedia\Allegro\Model\Data\Offer;
use PHPUnit\Framework\TestCase;

class OfferTest extends TestCase
{
    public function testInvalidInactiveOfferCannotBePublished(): void
    {
        $locationFactory = $this->createMock(LocationInterfaceFactory::class);
        $locationFactory->method('create')->willReturn($this->createMock(LocationInterface::class));
        $afterSalesFactory = $this->createMock(AfterSalesServicesInterfaceFactory::class);
        $afterSalesFactory->method('create')->willReturn(
            $this->createMock(AfterSalesServicesInterface::class)
        );

        $offer = new Offer(
            $this->createMock(ParameterDefinitionRepositoryInterface::class),
            $this->createMock(ImageInterfaceFactory::class),
            $locationFactory,
            $afterSalesFactory
        );
        $offer->setPublicationStatus(OfferInterface::PUBLICATION_STATUS_INACTIVE);
        $offer->setValidationErrors(['Missing return policy.']);

        self::assertFalse($offer->canBePublished());
    }
}
