<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Offer;

use Macopedia\Allegro\Api\Data\ParameterDefinitionInterface;
use Macopedia\Allegro\Api\Data\ShippingRateInterface;
use Macopedia\Allegro\Api\ParameterDefinitionRepositoryInterface;
use Macopedia\Allegro\Api\ShippingRateRepositoryInterface;
use Macopedia\Allegro\Model\Offer\OfferSaveRequest;
use Macopedia\Allegro\Model\Offer\OfferSaveRequestValidator;
use Macopedia\Allegro\Model\ResourceModel\Sale\OfferConfiguration;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class OfferSaveRequestValidatorTest extends TestCase
{
    public function testFiltersProductParametersAndAcceptsSupportedTaxRate(): void
    {
        $offerParameter = $this->parameter('11323', false);
        $productParameter = $this->parameter('248811', true);
        $definitions = $this->createMock(ParameterDefinitionRepositoryInterface::class);
        $definitions->method('getListByCategoryId')->willReturn([$offerParameter, $productParameter]);

        $shippingRates = $this->createMock(ShippingRateRepositoryInterface::class);
        $shippingRates->method('getList')->willReturn([$this->shippingRate('rate-id', 'Standard')]);

        $configuration = $this->createMock(OfferConfiguration::class);
        $configuration->method('getTaxSettings')->willReturn([
            'subjects' => [['value' => 'GOODS']],
            'rates' => [[
                'countryCode' => 'PL',
                'values' => [['value' => '23.00']],
            ]],
        ]);

        $request = $this->request();
        $request->parameters = [
            ['id' => '11323', 'valuesIds' => ['11323_1']],
            ['id' => '248811', 'valuesIds' => ['brand-id']],
        ];

        (new OfferSaveRequestValidator($definitions, $shippingRates, $configuration))->validate($request);

        self::assertSame([['id' => '11323', 'valuesIds' => ['11323_1']]], $request->parameters);
    }

    public function testRejectsOneFulfillmentWithMerchantManagedStock(): void
    {
        $definitions = $this->createMock(ParameterDefinitionRepositoryInterface::class);
        $definitions->method('getListByCategoryId')->willReturn([]);
        $shippingRates = $this->createMock(ShippingRateRepositoryInterface::class);
        $shippingRates->method('getList')->willReturn([
            $this->shippingRate('rate-id', 'One Fulfillment PL'),
        ]);

        $request = $this->request();
        $request->quantity = 5;
        $request->invoice = 'VAT';

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('quantity to 0');

        (new OfferSaveRequestValidator(
            $definitions,
            $shippingRates,
            $this->createMock(OfferConfiguration::class)
        ))->validate($request);
    }

    private function request(): OfferSaveRequest
    {
        return new OfferSaveRequest(
            42,
            'catalog-id',
            'Offer',
            10.0,
            2,
            '64509',
            [],
            'rate-id',
            'PT24H',
            'VAT',
            ['countryCode' => 'PL', 'city' => 'Poznań', 'postCode' => '60-001', 'province' => 'WIELKOPOLSKIE'],
            [],
            null,
            [],
            [],
            [],
            [],
            ['subject' => 'GOODS', 'rates' => [['rate' => '23.00', 'countryCode' => 'PL']]]
        );
    }

    private function parameter(string $id, bool $describesProduct): ParameterDefinitionInterface
    {
        $parameter = $this->createMock(ParameterDefinitionInterface::class);
        $parameter->method('getId')->willReturn($id);
        $parameter->method('getDescribesProduct')->willReturn($describesProduct);
        return $parameter;
    }

    private function shippingRate(string $id, string $name): ShippingRateInterface
    {
        $shippingRate = $this->createMock(ShippingRateInterface::class);
        $shippingRate->method('getId')->willReturn($id);
        $shippingRate->method('getName')->willReturn($name);
        return $shippingRate;
    }
}
