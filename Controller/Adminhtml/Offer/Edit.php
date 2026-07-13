<?php

namespace Macopedia\Allegro\Controller\Adminhtml\Offer;

use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Controller\Adminhtml\Offer;
use Macopedia\Allegro\Controller\Adminhtml\Offer\Context as OfferContext;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Edit controller class
 */
class Edit extends Offer
{
    /** @var ProductOfferRepositoryInterface */
    private $productOfferRepository;

    public function __construct(
        Context $context,
        OfferContext $offerContext,
        ProductOfferRepositoryInterface $productOfferRepository
    ) {
        parent::__construct($context, $offerContext);
        $this->productOfferRepository = $productOfferRepository;
    }

    /**
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
   
        try {

            $offerId = $this->getRequest()->getParam('id');
            if (!$offerId) {
                throw new LocalizedException(__('Requested offer does not exists'));
            }

            $this->credentials->getToken();

            try {
                $offer = $this->productOfferRepository->get((string)$offerId);
            } catch (NoSuchEntityException $exception) {
                $offer = $this->offerRepository->get($offerId);
            }
            $product = $this->productRepository->getByAllegroOfferId((string)$offer->getId());

            $this->registry->register('offer', $offer);
            $this->registry->register('product', $product);

            return $this->createPageResult();

        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            $this->messageManager->addExceptionMessage($e);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__('Something went wrong'));
        }

        return $this->createForwardNoRouteResult();
    }
}
