<?php

namespace Macopedia\Allegro\Ui\AllegroOffer\Form;

use Macopedia\Allegro\Api\Data\OfferInterface;
use Macopedia\Allegro\Api\Data\ParameterInterface;
use Macopedia\Allegro\Model\ResourceModel\Sale\Categories;
use Macopedia\Allegro\Model\ResourceModel\Sale\Offers;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

class EditDataProvider extends DataProvider
{
    /**
     * @var array
     */
    protected $_loadedData = [];

    /** @var Categories */
    private $categories;

    /** @var Offers */
    private $offers;

    /** @var Registry */
    private $registry;

    /**
     * EditDataProvider constructor.
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param Categories $categories
     * @param Offers $offers
     * @param Registry $registry
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        Categories $categories,
        Offers $offers,
        Registry $registry,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
        $this->categories = $categories;
        $this->offers = $offers;
        $this->registry = $registry;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        \Magento\Framework\Profiler::start(__CLASS__ . '::' . __METHOD__);
        if (isset($this->_loadedData) && !empty($this->_loadedData)) {
            \Magento\Framework\Profiler::stop(__CLASS__ . '::' . __METHOD__);
            return $this->_loadedData;
        }

        /** @var OfferInterface|null $offer */
        $offer = $this->registry->registry('offer');

        /** @var ProductInterface|null $product */
        $product = $this->registry->registry('product');

        if (!$offer || !$product) {
            \Magento\Framework\Profiler::stop(__CLASS__ . '::' . __METHOD__);
            return $this->_loadedData;
        }

        $parameters = [];
        $offerParameters = $offer->getParameters();
        if ($offerParameters && count($offerParameters) > 0) {
            foreach ($offerParameters as $parameter) {
                if ($parameter instanceof ParameterInterface) {
                    $parameterId = $parameter->getId();
                    if ($parameterId !== null && !$parameter->isValueEmpty()) {
                        $rawData = $parameter->getRawData();
                        if (isset($rawData['value'])) {
                            $parameters[$parameterId] = $rawData['value'];
                        }
                    }
                }
            }
        }

        $this->_loadedData[$offer->getId()] = [
            'allegro' => [
                'id' => $offer->getId(),
                'product' => $product->getId(),
                'name' => $offer->getName(),
                'ean' => $offer->getEan(),
                'description' => $offer->getDescription(),
                'images' => $offer->getImages(),
                'delivery_shipping_rates_id' => $offer->getDeliveryShippingRatesId(),
                'implied_warranty' => $offer->getAfterSalesServices() ? $offer->getAfterSalesServices()->getImpliedWarrantyId() : null,
                'return_policy' => $offer->getAfterSalesServices() ? $offer->getAfterSalesServices()->getReturnPolicyId() : null,
                'warranty' => $offer->getAfterSalesServices() ? $offer->getAfterSalesServices()->getWarrantyId() : null,
                'delivery_handling_time' => $offer->getDeliveryHandlingTime(),
                'payments_invoice' => $offer->getPaymentsInvoice(),
                'price' => $offer->getPrice(),
                'qty' => $offer->getQty(),
                'category' => $offer->getCategory(),
                'parameters' => $parameters,
            ],
        ];

        \Magento\Framework\Profiler::stop(__CLASS__ . '::' . __METHOD__);
        return $this->_loadedData;
    }
}
