<?php

namespace Macopedia\Allegro\Controller\Adminhtml\Offer;

use Macopedia\Allegro\Controller\Adminhtml\Offer;
use Macopedia\Allegro\Controller\Adminhtml\Offer\Context as OfferContext;
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

    /** @var ProductOfferSaveService */
    private $productOfferSaveService;

    /**
     * Save constructor.
     * @param Context $context
     * @param \Macopedia\Allegro\Controller\Adminhtml\Offer\Context $offerContext
     * @param ProductOfferSaveService $productOfferSaveService
     */
    public function __construct(
        Context $context,
        OfferContext $offerContext,
        ProductOfferSaveService $productOfferSaveService
    ) {
        parent::__construct($context, $offerContext);
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

            $this->hydrateCatalogProductIdForEdit($data);
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

}
