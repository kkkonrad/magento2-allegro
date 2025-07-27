<?php

namespace Macopedia\Allegro\Controller\Adminhtml\Offer;

use Macopedia\Allegro\Api\Data\ImageInterface;
use Macopedia\Allegro\Api\Data\Offer\AfterSalesServicesInterface;
use Macopedia\Allegro\Api\Data\Offer\AfterSalesServicesInterfaceFactory;
use Macopedia\Allegro\Api\Data\Offer\LocationInterface;
use Macopedia\Allegro\Api\Data\OfferInterface;
use Macopedia\Allegro\Api\Data\ParameterInterface;
use Macopedia\Allegro\Api\Data\ParameterInterfaceFactoryInterface;
use Macopedia\Allegro\Controller\Adminhtml\Offer;
use Macopedia\Allegro\Controller\Adminhtml\Offer\Context as OfferContext;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Api\Data\ProductOfferInterfaceFactory;

/**
 * Save controller class
 */
class Save extends Offer
{

    /** @var AfterSalesServicesInterfaceFactory */
    private $afterSalesServicesFactory;

    /** @var ProductOfferRepositoryInterface */
    private $productOfferRepository;

    /** @var ProductOfferInterfaceFactory */
    private $productOfferFactory;

    /**
     * Save constructor.
     * @param Context $context
     * @param \Macopedia\Allegro\Controller\Adminhtml\Offer\Context $offerContext
     * @param AfterSalesServicesInterfaceFactory $afterSalesServicesFactory
     * @param ProductOfferRepositoryInterface $productOfferRepository
     * @param ProductOfferInterfaceFactory $productOfferFactory
     */
    public function __construct(
        Context $context,
        OfferContext $offerContext,
        AfterSalesServicesInterfaceFactory $afterSalesServicesFactory,
        ProductOfferRepositoryInterface $productOfferRepository,
        ProductOfferInterfaceFactory $productOfferFactory,
    ) {
        parent::__construct($context, $offerContext);
        $this->afterSalesServicesFactory = $afterSalesServicesFactory;
        $this->productOfferRepository = $productOfferRepository;
        $this->productOfferFactory = $productOfferFactory;
    }

    /**
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
   
        try {
            $data = $this->getRequest()->getParam('allegro');
            $data['seller_id'] = $this->credentials->getClientId();
            if (!empty($data['product_id'])) {
                $offerId = $this->saveProductOffer($data);
                $this->messageManager->addSuccessMessage(__('Product offer saved successfully'));
                return $this->createRedirectEditResult($offerId);
            }

            // Fallback to old logic if product_id is not present
            $offer = $this->initializeOffer($data);
            $this->offerRepository->save($offer);
            $offerId = $offer->getId();

            $this->handleProductLink($offerId, $data);

            if ($offer->isValid()) {
                $this->messageManager->addSuccessMessage(__('Offer saved successfully'));
            } else {
                $this->messageManager
                    ->addWarningMessage(
                        __(
                            'Offer saved successfully but contains invalid data. Validation errors: %1',
                            sprintf(implode(' ', $offer->getValidationErrors()))
                        )
                    );
            }

            return $this->createRedirectEditResult($offerId);

        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            $this->messageManager->addExceptionMessage($e);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__('Something went wrong'));
        }

        return $this->createRedirectIndexResult();
    }

    /**
     * @param array $data
     * @return string
     */
    private function saveProductOffer(array $data)
    {
        $productOffer = $this->productOfferFactory->create();
        $productOffer->setProductId($data['product_id']);
        $productOffer->setPrice($data['price']);
        $productOffer->setQuantity($data['qty']);
        $productOffer->setStatus('ACTIVE');
        $productOffer->setSellerId($this->credentials->getClientId());
        // Or determine status based on form data
        // TODO: map other fields like delivery, payments etc.

        $productOffer->setParameters($data['parameters'] ?? []);
        $productOffer->setDeliveryOptions($data['delivery']['options'] ?? []);
        $productOffer->setPayments($data['payments'] ?? []);

        return $this->productOfferRepository->save($productOffer);
    }

    /**
     * @param string $offerId
     * @param array $data
     */
    private function handleProductLink(string $offerId, array $data)
    {
        $productId = $data['product'] ?? '';
        if (!$productId) {
            return;
        }

        try {
            $product = $this->productRepository->getById($productId);
            $product->setData('allegro_offer_id', $offerId);
            if (!empty($data['product_id'])) {
                $product->setData('allegro_product_id', $data['product_id']);
            }
            $this->productRepository->save($product);
        } catch (\Exception $e) {
            $this->messageManager->addWarningMessage(
                __('Could not assign offer id to product. Please update product data with proper offer ID manually')
            );
        }
    }

    /**
     * @param array $data
     * @return OfferInterface
     * @throws \Macopedia\Allegro\Model\Api\ClientException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function initializeOffer(array $data): OfferInterface
    {
        if (isset($data['id'])) {
            $offer = $this->offerRepository->get($data['id']);
        } else {
            /** @var OfferInterface $offer */
            $offer = $this->offerFactory->create();
        }

        $location = $offer->getLocation();
        $location->setCountryCode($this->scopeConfig->getValue('allegro/origin/country_id'));
        $location->setProvince($this->scopeConfig->getValue('allegro/origin/province'));
        $location->setCity($this->scopeConfig->getValue('allegro/origin/city'));
        $location->setPostCode($this->scopeConfig->getValue('allegro/origin/post_code'));
        $offer->setLocation($location);

        if (isset($data['images'])) {
            $offer->setImages($this->initializeImages($data['images']));
        }

        $offer->setName($data['name']);
        $offer->setEan($data['ean']);
        $offer->setDescription($data['description']);
        $offer->setPrice($data['price']);
        $offer->setQty($data['qty']);
        $offer->setDeliveryShippingRatesId($data['delivery_shipping_rates_id']);
        $offer->setAfterSalesServices($this->initializeAfterSalesServices($data));
        $offer->setDeliveryHandlingTime($data['delivery_handling_time']);
        $offer->setPaymentsInvoice($data['payments_invoice']);
        $offer->setCategory($data['category']);
        $offer->setParameters($this->initializeParameters($data));

        return $offer;
    }

    /**
     * @param array $data
     * @return array
     * @throws \Macopedia\Allegro\Model\Api\ClientException
     */
    private function initializeParameters(array $data): array
    {
        if (!$data || !isset($data['category']) || !isset($data['parameters'])) {
            return [];
        }

        $result = [];
        foreach ($this->parameterDefinitionRepository->createParametersByCategoryId($data['category']) as $parameter) {
            if (!isset($data['parameters'][$parameter->getId()])) {
                continue;
            }

            $parameter->setValue($data['parameters'][$parameter->getId()]);
            $result[] = $parameter;
        }

        return $result;
    }

    /**
     * @param array $data
     * @return ImageInterface[]
     */
    private function initializeImages(array $data): array
    {
        $images = [];
        foreach ($data as $imageData) {
            /** @var ImageInterface $image */
            $image = $this->imageFactory->create();
            $image->setRawData($imageData);
            $image->setStatus(ImageInterface::STATUS_LOCAL);
            $images[] = $image;
        }
        return $images;
    }

    /**
     * @param array $data
     * @return AfterSalesServicesInterface
     */
    private function initializeAfterSalesServices(array $data): AfterSalesServicesInterface
    {
        /** @var AfterSalesServicesInterface $afterSalesServices */
        $afterSalesServices = $this->afterSalesServicesFactory->create();

        if (!empty($data['implied_warranty'])) {
            $afterSalesServices->setImpliedWarrantyId($data['implied_warranty']);
        }
        if (!empty($data['return_policy'])) {
            $afterSalesServices->setReturnPolicyId($data['return_policy']);
        }
        if (!empty($data['warranty'])) {
            $afterSalesServices->setWarrantyId($data['warranty']);
        }

        return $afterSalesServices;
    }
}
