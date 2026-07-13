<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Offer;

use Macopedia\Allegro\Model\Offer\OfferFormDataMapper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class OfferFormDataMapperTest extends TestCase
{
    public function testMapsFlatFormFieldsWithoutMixingProductIdentifiers(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $values = [
            'allegro/origin/city' => 'Poznań',
            'allegro/origin/country_id' => 'PL',
            'allegro/origin/post_code' => '60-001',
            'allegro/origin/province' => 'WIELKOPOLSKIE',
        ];
        $scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path) use ($values): ?string {
                return $values[$path] ?? null;
            }
        );

        $request = (new OfferFormDataMapper($scopeConfig))->map([
            'product' => '42',
            'product_id' => 'catalog-id',
            'ean' => '5901234123457',
            'name' => 'Test offer',
            'price' => '123.45',
            'qty' => '7',
            'category' => '123',
            'parameters' => ['1' => '2'],
            'delivery_shipping_rates_id' => 'rate-id',
            'delivery_handling_time' => 'PT24H',
            'payments_invoice' => 'VAT',
            'return_policy' => 'return-id',
            'responsible_producer_id' => 'producer-id',
            'responsible_person_name' => 'EU representative',
            'safety_information' => "Keep away from fire.\nRead the manual.",
            'tax_rate' => '23',
            'description' => 'Description',
        ]);

        self::assertSame(42, $request->magentoProductId);
        self::assertSame('catalog-id', $request->catalogProductId);
        self::assertSame(123.45, $request->price);
        self::assertSame(7, $request->quantity);
        self::assertSame('rate-id', $request->shippingRateId);
        self::assertSame('PT24H', $request->handlingTime);
        self::assertSame(['returnPolicy' => ['id' => 'return-id']], $request->afterSalesServices);
        self::assertSame(['type' => 'ID', 'id' => 'producer-id'], $request->responsibleProducer);
        self::assertSame(['name' => 'EU representative'], $request->responsiblePerson);
        self::assertSame('TEXT', $request->safetyInformation['type']);
        self::assertSame('23.00', $request->taxSettings['rates'][0]['rate']);
        self::assertSame('Poznań', $request->location['city']);
    }

    /**
     * @dataProvider invalidFormDataProvider
     */
    public function testRejectsInvalidBoundaryData(array $changes): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $mapper = new OfferFormDataMapper($scopeConfig);
        $data = array_merge([
            'product' => 42,
            'product_id' => 'catalog-id',
            'ean' => '5901234123457',
            'price' => '10.00',
            'qty' => 1,
            'category' => '123',
        ], $changes);

        $this->expectException(LocalizedException::class);
        $mapper->map($data);
    }

    public function testAllowsMissingEanWhenUpdatingAnExistingProductOffer(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $request = (new OfferFormDataMapper($scopeConfig))->map([
            'id' => 'offer-id',
            'product' => 42,
            'product_id' => 'catalog-id',
            'ean' => '',
            'price' => '10.00',
            'qty' => 1,
            'category' => '123',
        ]);

        self::assertSame('catalog-id', $request->catalogProductId);
    }

    public static function invalidFormDataProvider(): array
    {
        return [
            'missing Magento product ID' => [['product' => null]],
            'missing catalog product ID' => [['product_id' => '']],
            'non-positive price' => [['price' => '0']],
            'negative quantity' => [['qty' => '-1']],
            'fractional quantity' => [['qty' => '1.5']],
            'invalid EAN' => [['ean' => '1234567890123']],
        ];
    }
}
