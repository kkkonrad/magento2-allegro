<?php

namespace Macopedia\Allegro\Controller\Adminhtml\Offer;

use Macopedia\Allegro\Api\Data\ImageInterface;
use Macopedia\Allegro\Api\Data\Offer\AfterSalesServicesInterface;
use Macopedia\Allegro\Api\Data\OfferInterface;
use Macopedia\Allegro\Controller\Adminhtml\Offer;
use Macopedia\Allegro\Controller\Adminhtml\Offer\Context as OfferContext;
use Macopedia\Allegro\Model\OfferMappingService;
use Macopedia\Allegro\Model\Offer\ProductOfferSaveService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Save controller class
 */
class Save extends Offer
{
    public const ADMIN_RESOURCE = 'Macopedia_Allegro::offer_manage';

    /** @var AfterSalesServicesInterface */
    private $afterSalesServicesFactory;

    /** @var OfferMappingService */
    private $offerMappingService;

    /** @var ProductOfferSaveService */
    private $productOfferSaveService;

    /**
     * Save constructor.
     * @param Context $context
     * @param \Macopedia\Allegro\Controller\Adminhtml\Offer\Context $offerContext
     * @param AfterSalesServicesInterface $afterSalesServicesFactory
     * @param OfferMappingService $offerMappingService
     * @param ProductOfferSaveService $productOfferSaveService
     */
    public function __construct(
        Context $context,
        OfferContext $offerContext,
        AfterSalesServicesInterface $afterSalesServicesFactory,
        OfferMappingService $offerMappingService,
        ProductOfferSaveService $productOfferSaveService
    ) {
        parent::__construct($context, $offerContext);
        $this->afterSalesServicesFactory = $afterSalesServicesFactory;
        $this->offerMappingService = $offerMappingService;
        $this->productOfferSaveService = $productOfferSaveService;
    }

    /**
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        try {
            $data = $this->getRequest()->getParam('allegro');
            if (!is_array($data)) {
                throw new LocalizedException(__('Offer form data is missing or invalid.'));
            }

            $data['seller_id'] = $this->credentials->getClientId();
            $this->hydrateCatalogProductIdForEdit($data);
            if (!empty($data['product_id'])) {
                $result = $this->productOfferSaveService->execute($data);
                $offerId = $result['offer_id'];
                if (!$result['mapping_saved']) {
                    $this->messageManager->addWarningMessage(
                        __('The offer was created, but its Magento mapping is pending automatic reconciliation.')
                    );
                }
                if (!$result['validation_checked']) {
                    $this->messageManager->addWarningMessage(
                        __('Product offer saved, but its Allegro validation status could not be checked. Do not publish it before refreshing the offer.')
                    );
                } elseif ($result['validation_errors']) {
                    $this->messageManager->addWarningMessage(
                        __(
                            'Product offer draft saved, but it cannot be published: %1',
                            $this->summarizeValidationMessages($result['validation_errors'])
                        )
                    );
                } else {
                    $this->messageManager->addSuccessMessage(__('Product offer saved successfully'));
                }
                if ($result['validation_warnings']) {
                    $this->messageManager->addWarningMessage(
                        __('Allegro validation warnings: %1', $this->summarizeValidationMessages($result['validation_warnings']))
                    );
                }
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
     * @param string $offerId
     * @param array $data
     */
    private function handleProductLink(string $offerId, array $data)
    {
        $productId = $data['product'] ?? '';
        if (!$productId) {
            return;
        }

        if (!$this->offerMappingService->saveMapping(
            (int)$productId,
            $offerId,
            !empty($data['product_id']) ? (string)$data['product_id'] : null
        )) {
            $this->messageManager->addWarningMessage(
                __('The offer was created, but its Magento mapping is pending automatic reconciliation.')
            );
        }
    }

    /**
     * Legacy edit UI loads the offer through the generic offers endpoint and does
     * not expose product_id. Product offers created by this module always persist
     * their catalog ID on the Magento product, so restore it before deciding which
     * API endpoint handles the save.
     */
    private function hydrateCatalogProductIdForEdit(array &$data): void
    {
        if (!empty($data['product_id']) || empty($data['id']) || empty($data['product'])) {
            return;
        }

        try {
            $product = $this->productRepository->getById((int)$data['product']);
            $catalogProductId = (string)$product->getData('allegro_product_id');
            if ($catalogProductId !== '') {
                $data['product_id'] = $catalogProductId;
            }
        } catch (\Exception $exception) {
            $this->logger->apiFailure('Could not load Allegro catalog product mapping for offer edit', [
                'product_id' => (int)$data['product'],
                'exception_type' => get_class($exception),
            ]);
        }
    }

    private function summarizeValidationMessages(array $messages): string
    {
        $messages = array_values(array_filter(array_map('strval', $messages)));
        $visible = array_slice($messages, 0, 5);
        $summary = implode(' ', $visible);
        if (count($messages) > count($visible)) {
            $summary .= ' ' . (string)__('And %1 more message(s).', count($messages) - count($visible));
        }

        return $summary;
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
        // Używamy bezpośrednio interfejsu zamiast factory
        $afterSalesServices = $this->afterSalesServicesFactory;

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
