<?php

namespace Macopedia\Allegro\Ui\AllegroOffer\Form;

use Macopedia\Allegro\Model\Configuration;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Macopedia\Allegro\Model\AllegroPrice;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;

class CreateDataProvider extends DataProvider
{
    /**
     * @var array
     */
    protected $_loadedData = [];

    /** @var GetSalableQuantityDataBySku */
    protected $getSalableQuantityDataBySku;

    /** @var Registry */
    protected $registry;

    /** @var Configuration */
    protected $config;

    /** @var AllegroPrice */
    protected $allegroPrice;

    /**
     * CreateDataProvider constructor.
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param GetSalableQuantityDataBySku $getSalableQuantityDataBySku
     * @param Registry $registry
     * @param Configuration $config
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param AllegroPrice $allegroPrice
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        GetSalableQuantityDataBySku $getSalableQuantityDataBySku,
        Registry $registry,
        Configuration $config,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        AllegroPrice $allegroPrice,
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
        $this->getSalableQuantityDataBySku = $getSalableQuantityDataBySku;
        $this->registry = $registry;
        $this->config = $config;
        $this->allegroPrice = $allegroPrice;
    }

    /**
     * Get Allegro image for product
     *
     * @param Product $product
     * @return string
     */
    protected function getAllegroImage(Product $product): string
    {
        if ($product->getAllegroImage() && $product->getAllegroImage() !== 'no_selection') {
            return $product->getAllegroImage();
        }

        return $product->getImage() ?: '';
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

        /** @var Product|null $product */
        $product = $this->registry->registry('product');

        if (!$product) {
            \Magento\Framework\Profiler::stop(__CLASS__ . '::' . __METHOD__);
            return $this->_loadedData;
        }

        try {
            $stock = $this->getSalableQuantityDataBySku->execute($product->getSku());
            $stockQty = isset($stock[0]['qty']) ? (int)$stock[0]['qty'] : 0;
        } catch (\Exception $e) {
            $stockQty = 0;
        }

        $mediaGalleryImages = $product->getMediaGalleryImages();
        $images = [];
        $allegroImage = $this->getAllegroImage($product);

        if ($mediaGalleryImages) {
            $imagesArray = $mediaGalleryImages->toArray();
            if (isset($imagesArray['items']) && is_array($imagesArray['items'])) {
                foreach ($imagesArray['items'] as $image) {
                    if (isset($image['file']) && $image['file'] === $allegroImage) {
                        $images[] = $image;
                    }
                }
            }
        }

        $price = $this->allegroPrice->get($product);
        $eanAttributeCode = $this->config->getEanAttributeCode();
        $descriptionAttributeCode = $this->config->getDescriptionAttributeCode();

        $this->_loadedData[$product->getId()] = [
            'allegro' => [
                'product' => $product->getId(),
                'ean' => $eanAttributeCode ? (string)$product->getData($eanAttributeCode) : '',
                'name' => $product->getName() ?: '',
                'description' => $descriptionAttributeCode
                    ? (string)$product->getData($descriptionAttributeCode)
                    : (string)$product->getDescription(),
                'price' => $price,
                'images' => $images,
                'qty' => $stockQty
            ]
        ];

        \Magento\Framework\Profiler::stop(__CLASS__ . '::' . __METHOD__);
        return $this->_loadedData;
    }
}
