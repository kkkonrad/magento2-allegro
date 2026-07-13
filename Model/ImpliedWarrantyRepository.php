<?php

namespace Macopedia\Allegro\Model;

use Macopedia\Allegro\Api\Data\ImpliedWarrantyInterface;
use Macopedia\Allegro\Api\Data\ImpliedWarrantyInterfaceFactory;
use Macopedia\Allegro\Api\ImpliedWarrantyRepositoryInterface;
use Macopedia\Allegro\Model\Api\ClientException;
use Macopedia\Allegro\Model\ResourceModel\Sale\AfterSaleServices;

class ImpliedWarrantyRepository implements ImpliedWarrantyRepositoryInterface
{

    /** @var AfterSaleServices */
    private $afterSaleServices;

    /** @var ImpliedWarrantyInterfaceFactory */
    private $impliedWarrantyFactory;

    /**
     * ImpliedWarrantyRepository constructor.
     * @param AfterSaleServices $afterSaleServices
     * @param ImpliedWarrantyInterfaceFactory $impliedWarrantyFactory
     */
    public function __construct(
        AfterSaleServices $afterSaleServices,
        ImpliedWarrantyInterfaceFactory $impliedWarrantyFactory
    ) {
        $this->afterSaleServices = $afterSaleServices;
        $this->impliedWarrantyFactory = $impliedWarrantyFactory;
    }

    /**
     * @return ImpliedWarrantyInterface[]
     * @throws ClientException
     */
    public function getList(): array
    {
        $impliedWarrantiesData = $this->afterSaleServices->getImpliedWarrantiesList();

        $impliedWarranties = [];
        foreach ($impliedWarrantiesData as $impliedWarrantyData) {
            /** @var ImpliedWarrantyInterface $impliedWarranty */
            $impliedWarranty = $this->impliedWarrantyFactory->create();
            $impliedWarranty->setRawData($impliedWarrantyData);
            $impliedWarranties[] = $impliedWarranty;
        }
        return $impliedWarranties;
    }
}
