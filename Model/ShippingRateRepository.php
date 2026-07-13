<?php

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Api\Data\ShippingRateInterface;
use Macopedia\Allegro\Api\Data\ShippingRateInterfaceFactory;
use Macopedia\Allegro\Api\ShippingRateRepositoryInterface;
use Macopedia\Allegro\Model\Api\ClientException;
use Macopedia\Allegro\Model\ResourceModel\Sale\ShippingRates;

class ShippingRateRepository implements ShippingRateRepositoryInterface
{
    /** @var ShippingRates */
    private $shippingRates;

    /** @var ShippingRateInterfaceFactory */
    private $shippingRateFactory;

    /**
     * ShippingRatesRepository constructor.
     * @param ShippingRates $shippingRates
     */
    public function __construct(
        ShippingRates $shippingRates,
        ShippingRateInterfaceFactory $shippingRateFactory
    ) {
        $this->shippingRates = $shippingRates;
        $this->shippingRateFactory = $shippingRateFactory;
    }

    /**
     * @return ShippingRateInterface[]
     * @throws ClientException
     */
    public function getList(): array
    {
        $shippingRatesData = $this->shippingRates->getList();

        $shippingRates = [];
        foreach ($shippingRatesData as $shippingRateData) {
            /** @var ShippingRateInterface $shippingRate */
            $shippingRate = $this->shippingRateFactory->create();
            $shippingRate->setRawData($shippingRateData);
            $shippingRates[] = $shippingRate;
        }

        return $shippingRates;
    }
}
