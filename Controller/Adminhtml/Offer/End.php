<?php

namespace Macopedia\Allegro\Controller\Adminhtml\Offer;

use Macopedia\Allegro\Api\Data\PublicationCommandInterface;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Controller\Adminhtml\Offer;
use Macopedia\Allegro\Controller\Adminhtml\Offer\Context as OfferContext;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Save controller class
 */
class End extends Offer
{
    public const ADMIN_RESOURCE = 'Macopedia_Allegro::offer_publish';

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
        $offer = null;

        try {
            $offerId = $this->getRequest()->getParam('id');
            if (!$offerId) {
                throw new LocalizedException(__('Requested offer does not exists'));
            }

            try {
                $offer = $this->productOfferRepository->get((string)$offerId);
                if ($offer->getStatus() !== 'ACTIVE') {
                    throw new LocalizedException(__('Can not end inactive or ended offers'));
                }
            } catch (NoSuchEntityException $exception) {
                $offer = $this->offerRepository->get($offerId);
                if (!$offer->canBeEnded()) {
                    throw new LocalizedException(__('Can not end inactive or ended offers'));
                }
            }

            /** @var PublicationCommandInterface $publicationCommand */
            $publicationCommand = $this->publicationCommandFactory->create();
            $publicationCommand->setOfferId($offerId);
            $publicationCommand->setAction(PublicationCommandInterface::ACTION_END);
            $this->publicationCommandRepository->save($publicationCommand);

            $this->messageManager->addSuccessMessage(__('Offer ended successfully'));
            return $this->createRedirectEditResult($offerId);

        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            $this->messageManager->addExceptionMessage($e);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__('Something went wrong'));
        }

        if ($offer !== null && $offer->getId() !== null) {
            return $this->createRedirectEditResult($offer->getId());
        }

        return $this->createRedirectIndexResult();
    }
}
