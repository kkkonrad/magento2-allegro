<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Offer;

use Macopedia\Allegro\Api\Data\ImageInterfaceFactory;
use Macopedia\Allegro\Api\ImageRepositoryInterface;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Logger\Logger;
use Macopedia\Allegro\Model\Api\Credentials;
use Macopedia\Allegro\Model\Api\ProductOfferFactory;
use Macopedia\Allegro\Model\Data\ProductOffer;
use Macopedia\Allegro\Model\Offer\OfferFormDataMapper;
use Macopedia\Allegro\Model\Offer\OfferSaveRequest;
use Macopedia\Allegro\Model\Offer\ProductOfferSaveService;
use Macopedia\Allegro\Model\OfferMappingService;
use PHPUnit\Framework\TestCase;

class ProductOfferSaveServiceTest extends TestCase
{
    public function testReturnsValidationResultAfterSavingDraft(): void
    {
        $mapper = $this->createMock(OfferFormDataMapper::class);
        $mapper->method('map')->willReturn(new OfferSaveRequest(
            42,
            'catalog-product-id',
            'Test offer',
            10.0,
            2,
            '123',
            [],
            'shipping-rate-id',
            'PT24H',
            'VAT',
            ['city' => 'Poznań', 'countryCode' => 'PL', 'postCode' => '60-001'],
            [],
            null,
            []
        ));

        $factory = $this->createMock(ProductOfferFactory::class);
        $factory->method('create')->willReturn(new ProductOffer());

        $savedOffer = (new ProductOffer())
            ->setId('offer-1')
            ->setValidationErrors(['[parameters] Uzupełnij markę.'])
            ->setValidationWarnings(['[description] Sprawdź opis.']);
        $repository = $this->createMock(ProductOfferRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->willReturn('offer-1');
        $repository->expects(self::once())->method('get')->with('offer-1')->willReturn($savedOffer);

        $mapping = $this->createMock(OfferMappingService::class);
        $mapping->method('saveMapping')->willReturn(true);
        $credentials = $this->createMock(Credentials::class);
        $credentials->method('getClientId')->willReturn('seller-id');

        $service = new ProductOfferSaveService(
            $mapper,
            $factory,
            $this->createMock(ImageInterfaceFactory::class),
            $this->createMock(ImageRepositoryInterface::class),
            $repository,
            $mapping,
            $credentials,
            $this->createMock(Logger::class)
        );

        $result = $service->execute([]);

        self::assertSame('offer-1', $result['offer_id']);
        self::assertTrue($result['mapping_saved']);
        self::assertTrue($result['validation_checked']);
        self::assertSame(['[parameters] Uzupełnij markę.'], $result['validation_errors']);
        self::assertSame(['[description] Sprawdź opis.'], $result['validation_warnings']);
    }
}
