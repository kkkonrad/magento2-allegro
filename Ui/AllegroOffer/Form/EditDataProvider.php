<?php

namespace Macopedia\Allegro\Ui\AllegroOffer\Form;

use Macopedia\Allegro\Api\Data\OfferInterface;
use Macopedia\Allegro\Api\Data\ParameterInterface;
use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Macopedia\Allegro\Model\Configuration;
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

    /** @var Configuration */
    private $configuration;

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
        Configuration $configuration,
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
        $this->configuration = $configuration;
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

        /** @var OfferInterface|ProductOfferInterface|null $offer */
        $offer = $this->registry->registry('offer');

        /** @var ProductInterface|null $product */
        $product = $this->registry->registry('product');

        if (!$offer || !$product) {
            \Magento\Framework\Profiler::stop(__CLASS__ . '::' . __METHOD__);
            return $this->_loadedData;
        }

        if ($offer instanceof ProductOfferInterface) {
            $data = $this->productOfferData($offer, $product);
        } else {
            $data = $this->legacyOfferData($offer, $product);
        }

        $this->_loadedData[$offer->getId()] = [
            'allegro' => $data,
        ];

        \Magento\Framework\Profiler::stop(__CLASS__ . '::' . __METHOD__);
        return $this->_loadedData;
    }

    private function productOfferData(ProductOfferInterface $offer, ProductInterface $product): array
    {
        $afterSales = $offer->getAfterSalesServices();
        $producer = $offer->getResponsibleProducer();
        $person = $offer->getResponsiblePerson();
        $safety = $offer->getSafetyInformation();
        $tax = $offer->getTaxSettings();
        $delivery = $offer->getDeliveryOptions();

        return [
            'id' => $offer->getId(),
            'product' => $product->getId(),
            'product_id' => $offer->getProductId(),
            'name' => $offer->getName(),
            'ean' => $this->productEan($product),
            'description' => (string)($offer->getDescription()['sections'][0]['items'][0]['content'] ?? ''),
            'images' => $this->normalizeImages($offer->getImages()),
            'delivery_shipping_rates_id' => $delivery['shipping_rates_id'] ?? null,
            'implied_warranty' => $afterSales['impliedWarranty']['id'] ?? null,
            'return_policy' => $afterSales['returnPolicy']['id'] ?? null,
            'warranty' => $afterSales['warranty']['id'] ?? null,
            'delivery_handling_time' => $delivery['handling_time'] ?? null,
            'payments_invoice' => $offer->getPayments()['invoice'] ?? null,
            'price' => $offer->getPrice(),
            'qty' => $offer->getQuantity(),
            'category' => $offer->getCategory(),
            'parameters' => $this->productOfferParameters($offer->getParameters()),
            'responsible_producer_id' => $producer['id'] ?? null,
            'responsible_producer_name' => $producer['name'] ?? null,
            'responsible_person_id' => $person['id'] ?? null,
            'responsible_person_name' => $person['name'] ?? null,
            'safety_information' => $safety['description'] ?? null,
            'tax_rate' => $tax['rates'][0]['rate'] ?? null,
            'tax_subject' => $tax['subject'] ?? 'GOODS',
            'tax_exemption' => $tax['exemption'] ?? null,
        ];
    }

    private function legacyOfferData(OfferInterface $offer, ProductInterface $product): array
    {
        $parameters = [];
        foreach ($offer->getParameters() ?: [] as $parameter) {
            if ($parameter instanceof ParameterInterface && !$parameter->isValueEmpty()) {
                $parameters[$parameter->getId()] = $parameter->getRawData()['value'] ?? [];
            }
        }

        return [
            'id' => $offer->getId(),
            'product' => $product->getId(),
            'name' => $offer->getName(),
            'ean' => $offer->getEan(),
            'description' => $offer->getDescription(),
            'images' => $offer->getImages(),
            'delivery_shipping_rates_id' => $offer->getDeliveryShippingRatesId(),
            'implied_warranty' => $offer->getAfterSalesServices()->getImpliedWarrantyId(),
            'return_policy' => $offer->getAfterSalesServices()->getReturnPolicyId(),
            'warranty' => $offer->getAfterSalesServices()->getWarrantyId(),
            'delivery_handling_time' => $offer->getDeliveryHandlingTime(),
            'payments_invoice' => $offer->getPaymentsInvoice(),
            'price' => $offer->getPrice(),
            'qty' => $offer->getQty(),
            'category' => $offer->getCategory(),
            'parameters' => $parameters,
        ];
    }

    private function productOfferParameters(array $parameters): array
    {
        $result = [];
        foreach ($parameters as $parameter) {
            if (!is_array($parameter) || empty($parameter['id'])) {
                continue;
            }
            if (!empty($parameter['valuesIds'])) {
                $result[(string)$parameter['id']] = $parameter['valuesIds'];
            } elseif (!empty($parameter['values'])) {
                $result[(string)$parameter['id']] = $parameter['values'];
            } elseif (is_array($parameter['rangeValue'] ?? null)) {
                $result[(string)$parameter['id']] = [
                    'minValue' => $parameter['rangeValue']['from'] ?? '',
                    'maxValue' => $parameter['rangeValue']['to'] ?? '',
                ];
            }
        }
        return $result;
    }

    private function normalizeImages(array $images): array
    {
        $result = [];
        foreach ($images as $image) {
            $url = is_string($image) ? $image : (is_array($image) ? ($image['url'] ?? '') : '');
            if ($url !== '') {
                $result[] = ['url' => $url];
            }
        }
        return $result;
    }

    private function productEan(ProductInterface $product): string
    {
        $attributeCode = $this->configuration->getEanAttributeCode();
        return $attributeCode ? (string)$product->getData($attributeCode) : '';
    }
}
