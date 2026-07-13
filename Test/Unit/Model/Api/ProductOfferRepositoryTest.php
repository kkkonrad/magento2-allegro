<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Api;

use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Api\ProductOfferFactory;
use Macopedia\Allegro\Model\Api\ProductOfferPayloadBuilder;
use Macopedia\Allegro\Model\Api\ProductOfferRepository;
use Macopedia\Allegro\Model\Data\ProductOffer;
use Macopedia\Allegro\Model\ResourceModel\AbstractResource;
use PHPUnit\Framework\TestCase;

class ProductOfferRepositoryTest extends TestCase
{
    public function testExistingOfferIsEditedWithPatch(): void
    {
        $resource = $this->createMock(AbstractResource::class);
        $resource->expects(self::once())
            ->method('requestPatch')
            ->with('/sale/product-offers/offer-1', ['name' => 'Updated'])
            ->willReturn(['id' => 'offer-1']);
        $resource->method('getLastResponseStatusCode')->willReturn(200);

        $offer = new ProductOffer();
        $offer->setId('offer-1');

        self::assertSame('offer-1', $this->repository($resource)->save($offer));
    }

    public function testAcceptedPatchOperationIsFollowedUntilCompletion(): void
    {
        $resource = $this->createMock(AbstractResource::class);
        $resource->method('requestPatch')->willReturn(['id' => 'offer-1']);
        $resource->expects(self::once())
            ->method('requestGet')
            ->with('sale/product-offers/offer-1/operations/operation-1')
            ->willReturn(['id' => 'offer-1']);
        $resource->method('getLastResponseStatusCode')->willReturnOnConsecutiveCalls(202, 200);
        $resource->method('getLastResponseHeader')->willReturnCallback(
            static function (string $name): string {
                if (strtolower($name) === 'location') {
                    return 'https://api.allegro.pl/sale/product-offers/offer-1/operations/operation-1';
                }
                return '0';
            }
        );

        $offer = new ProductOffer();
        $offer->setId('offer-1');

        self::assertSame('offer-1', $this->repository($resource)->save($offer));
    }

    private function repository(AbstractResource $resource): ProductOfferRepository
    {
        $builder = $this->createMock(ProductOfferPayloadBuilder::class);
        $builder->method('build')->willReturn(['name' => 'Updated']);

        return new ProductOfferRepository(
            $resource,
            $this->createMock(ProductOfferFactory::class),
            $builder,
            $this->createMock(Logger::class)
        );
    }
}
