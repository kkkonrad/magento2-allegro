<?php

namespace Macopedia\Allegro\Controller\Adminhtml\Offer;

use Macopedia\Allegro\Api\Data\PublicationCommandInterface;
use Macopedia\Allegro\Api\ProductOfferRepositoryInterface;
use Macopedia\Allegro\Controller\Adminhtml\Offer;
use Macopedia\Allegro\Controller\Adminhtml\Offer\Context as OfferContext;
use Macopedia\Allegro\Model\Offer\ProductOfferPublicationValidator;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Save controller class
 */
class Publish extends Offer
{
    public const ADMIN_RESOURCE = 'Macopedia_Allegro::offer_publish';

    /** @var ProductOfferRepositoryInterface */
    private $productOfferRepository;

    /** @var ProductOfferPublicationValidator */
    private $productOfferPublicationValidator;

    public function __construct(
        Context $context,
        OfferContext $offerContext,
        ProductOfferRepositoryInterface $productOfferRepository,
        ProductOfferPublicationValidator $productOfferPublicationValidator
    ) {
        parent::__construct($context, $offerContext);
        $this->productOfferRepository = $productOfferRepository;
        $this->productOfferPublicationValidator = $productOfferPublicationValidator;
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
                $this->productOfferPublicationValidator->validate($offer);
            } catch (NoSuchEntityException $exception) {
                // Offers created through the legacy /sale/offers flow are not
                // guaranteed to be available through Product Offer API.
                $offer = $this->offerRepository->get($offerId);
                if (!$offer->isValid()) {
                    throw $this->validationException($offer->getValidationErrors());
                }
                if (!$offer->canBePublished()) {
                    throw new LocalizedException(__('Can not publish active or activating offers'));
                }
            }

            /** @var PublicationCommandInterface $publicationCommand */
            $publicationCommand = $this->publicationCommandFactory->create();
            $publicationCommand->setOfferId($offerId);
            $publicationCommand->setAction(PublicationCommandInterface::ACTION_ACTIVATE);
            $this->publicationCommandRepository->save($publicationCommand);

            $this->messageManager->addSuccessMessage(__('Offer publication request sent successfully'));
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

    private function summarizeValidationMessages(array $messages): string
    {
        $messages = array_values(array_filter(array_map('strval', $messages)));
        $visible = array_slice($messages, 0, 5);
        $summary = implode(' ', $visible);
        if (count($messages) > count($visible)) {
            $summary .= ' ' . (string)__('And %1 more error(s).', count($messages) - count($visible));
        }

        return $summary !== '' ? $summary : (string)__('The offer contains invalid data.');
    }

    private function validationException(array $messages): LocalizedException
    {
        return new LocalizedException(
            __(
                'The offer cannot be published because Allegro validation reported: %1',
                $this->summarizeValidationMessages($messages)
            )
        );
    }
}
