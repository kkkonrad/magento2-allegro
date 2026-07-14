<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\OrderImporter;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;

class AddressRegionResolver
{
    /** @var CountryInformationAcquirerInterface */
    private $countryInformationAcquirer;

    /** @var DirectoryHelper */
    private $directoryHelper;

    public function __construct(
        CountryInformationAcquirerInterface $countryInformationAcquirer,
        DirectoryHelper $directoryHelper
    )
    {
        $this->countryInformationAcquirer = $countryInformationAcquirer;
        $this->directoryHelper = $directoryHelper;
    }

    public function complete(AddressInterface $address): void
    {
        if ((int)$address->getRegionId() > 0) {
            return;
        }

        $countryCode = strtoupper(trim((string)$address->getCountryId()));
        if ($countryCode === '') {
            return;
        }

        $regionCode = $countryCode === 'PL'
            ? $this->polishRegionCode((string)$address->getPostcode())
            : null;
        $city = trim((string)$address->getCity());
        $region = null;
        foreach ((array)$this->countryInformationAcquirer->getCountryInfo($countryCode)->getAvailableRegions() as $item) {
            if (($regionCode !== null && $item->getCode() === $regionCode)
                || ($city !== '' && strcasecmp((string)$item->getName(), $city) === 0)
            ) {
                $region = $item;
                break;
            }
        }

        if ($region !== null && $region->getId()) {
            $address->setRegionId((int)$region->getId());
            $address->setRegion((string)$region->getName());
            return;
        }

        if ($this->directoryHelper->isRegionRequired($countryCode)) {
            throw new LocalizedException(
                __('Could not determine a required region for shipping country %1.', $countryCode)
            );
        }
    }

    private function polishRegionCode(string $postcode): ?string
    {
        if (!preg_match('/^(\d{2})-?\d{3}$/', trim($postcode), $matches)) {
            return null;
        }

        $prefix = (int)$matches[1];
        if ($prefix <= 9) {
            return 'PL-14';
        }
        if ($prefix <= 14) {
            return 'PL-28';
        }
        if ($prefix <= 19) {
            return 'PL-20';
        }
        if ($prefix <= 24) {
            return 'PL-06';
        }
        if ($prefix <= 29) {
            return 'PL-26';
        }
        if ($prefix <= 34) {
            return 'PL-12';
        }
        if ($prefix <= 39) {
            return 'PL-18';
        }
        if ($prefix <= 44) {
            return 'PL-24';
        }
        if ($prefix <= 49) {
            return 'PL-16';
        }
        if ($prefix <= 59) {
            return 'PL-02';
        }
        if ($prefix <= 64) {
            return 'PL-30';
        }
        if ($prefix <= 69) {
            return 'PL-08';
        }
        if ($prefix <= 79) {
            return 'PL-32';
        }
        if ($prefix <= 84) {
            return 'PL-22';
        }
        if ($prefix <= 89) {
            return 'PL-04';
        }

        return 'PL-10';
    }
}
