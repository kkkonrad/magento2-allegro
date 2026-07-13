<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Api;

use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Api\ProductOfferFactory;
use Macopedia\Allegro\Model\Api\ProductOfferPayloadBuilder;
use Macopedia\Allegro\Model\Api\ProductOfferRepository;
use Macopedia\Allegro\Model\Api\ClientResponseException;
use Macopedia\Allegro\Model\Data\ProductOffer;
use Macopedia\Allegro\Model\ResourceModel\AbstractResource;
use Magento\Framework\Exception\NoSuchEntityException;
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

    public function testGetMapsReadableValidationErrorsAndWarnings(): void
    {
        $resource = $this->createMock(AbstractResource::class);
        $resource->method('requestGet')->willReturn([
            'id' => 'offer-1',
            'productSet' => [[
                'product' => ['id' => 'catalog-id'],
                'responsibleProducer' => ['id' => 'producer-id'],
                'responsiblePerson' => ['id' => 'person-id'],
                'safetyInformation' => ['type' => 'TEXT', 'description' => 'Safety text'],
            ]],
            'taxSettings' => [
                'subject' => 'GOODS',
                'rates' => [['countryCode' => 'PL', 'rate' => '23.00']],
            ],
            'validation' => [
                'errors' => [
                    [
                        'message' => 'Technical message',
                        'userMessage' => 'Uzupełnij markę.',
                        'path' => 'parameters',
                    ],
                    ['message' => 'Configure return policy.', 'path' => 'null'],
                    ['message' => ''],
                ],
                'warnings' => [
                    ['userMessage' => 'Sprawdź opis.', 'path' => 'description'],
                ],
            ],
        ]);

        $offer = $this->repository($resource)->get('offer-1');

        self::assertSame(
            ['[parameters] Uzupełnij markę.', 'Configure return policy.'],
            $offer->getValidationErrors()
        );
        self::assertSame(['[description] Sprawdź opis.'], $offer->getValidationWarnings());
        self::assertSame('producer-id', $offer->getResponsibleProducer()['id']);
        self::assertSame('person-id', $offer->getResponsiblePerson()['id']);
        self::assertSame('Safety text', $offer->getSafetyInformation()['description']);
        self::assertSame('23.00', $offer->getTaxSettings()['rates'][0]['rate']);
    }

    public function testGetMapsNotFoundResponseToNoSuchEntityException(): void
    {
        $resource = $this->createMock(AbstractResource::class);
        $resource->method('requestGet')->willThrowException(
            new ClientResponseException(__('Not found'), null, 0, 404)
        );

        $this->expectException(NoSuchEntityException::class);

        $this->repository($resource)->get('missing-offer');
    }

    private function repository(AbstractResource $resource): ProductOfferRepository
    {
        $builder = $this->createMock(ProductOfferPayloadBuilder::class);
        $builder->method('build')->willReturn(['name' => 'Updated']);
        $factory = $this->createMock(ProductOfferFactory::class);
        $factory->method('create')->willReturnCallback(
            static function (): ProductOffer {
                return new ProductOffer();
            }
        );

        return new ProductOfferRepository(
            $resource,
            $factory,
            $builder,
            $this->createMock(Logger::class)
        );
    }
}
