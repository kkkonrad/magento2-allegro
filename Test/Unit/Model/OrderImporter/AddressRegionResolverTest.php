<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\OrderImporter;

use Macopedia\Allegro\Model\OrderImporter\AddressRegionResolver;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Api\Data\CountryInformationInterface;
use Magento\Directory\Api\Data\RegionInformationInterface;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;
use PHPUnit\Framework\TestCase;

class AddressRegionResolverTest extends TestCase
{
    public function testCompletesPolishRegionFromPostcode(): void
    {
        $region = $this->createMock(RegionInformationInterface::class);
        $region->method('getId')->willReturn('1024');
        $region->method('getCode')->willReturn('PL-14');
        $region->method('getName')->willReturn('mazowieckie');
        $country = $this->createMock(CountryInformationInterface::class);
        $country->method('getAvailableRegions')->willReturn([$region]);
        $acquirer = $this->createMock(CountryInformationAcquirerInterface::class);
        $acquirer->expects(self::once())->method('getCountryInfo')->with('PL')->willReturn($country);

        $address = $this->createMock(AddressInterface::class);
        $address->method('getRegionId')->willReturn(null);
        $address->method('getCountryId')->willReturn('PL');
        $address->method('getPostcode')->willReturn('00-001');
        $address->method('getCity')->willReturn('Warszawa');
        $address->expects(self::once())->method('setRegionId')->with(1024);
        $address->expects(self::once())->method('setRegion')->with('mazowieckie');

        $resolver = new AddressRegionResolver($acquirer, $this->createMock(DirectoryHelper::class));
        $resolver->complete($address);
    }

    public function testThrowsWhenRequiredRegionCannotBeResolved(): void
    {
        $country = $this->createMock(CountryInformationInterface::class);
        $country->method('getAvailableRegions')->willReturn([]);
        $acquirer = $this->createMock(CountryInformationAcquirerInterface::class);
        $acquirer->method('getCountryInfo')->willReturn($country);
        $directoryHelper = $this->createMock(DirectoryHelper::class);
        $directoryHelper->method('isRegionRequired')->with('US')->willReturn(true);
        $address = $this->createMock(AddressInterface::class);
        $address->method('getRegionId')->willReturn(null);
        $address->method('getCountryId')->willReturn('US');
        $address->method('getCity')->willReturn('Unknown');

        $this->expectException(LocalizedException::class);
        (new AddressRegionResolver($acquirer, $directoryHelper))->complete($address);
    }
}
