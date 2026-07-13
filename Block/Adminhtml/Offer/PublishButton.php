<?php

namespace Macopedia\Allegro\Block\Adminhtml\Offer;

use Macopedia\Allegro\Api\Data\OfferInterface;
use Macopedia\Allegro\Api\Data\ProductOfferInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Publish button configuration provider
 */
class PublishButton implements ButtonProviderInterface
{
    /**
     * Url Builder
     *
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * Registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Widget\Context $context
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Magento\Framework\Registry $registry
    ) {
        $this->urlBuilder = $context->getUrlBuilder();
        $this->registry = $registry;
    }

    /**
     * Return button attributes array
     */
    public function getButtonData()
    {
        $offer = $this->getOffer();
        $canPublish = $offer instanceof ProductOfferInterface
            ? !$offer->getValidationErrors() && in_array($offer->getStatus(), ['INACTIVE', 'ENDED'], true)
            : $offer->canBePublished() && $offer->isValid();

        return [
            'label' => $this->getLabel(),
            'disabled' => !$canPublish,
            'class' => 'action-secondary',
            'on_click' => $this->getOnclick(),
            'sort_order' => 10,
        ];
    }

    /**
     * @return string
     */
    protected function getLabel()
    {
        $offer = $this->getOffer();
        $status = $offer instanceof ProductOfferInterface
            ? $offer->getStatus()
            : $offer->getPublicationStatus();
        if ($status === OfferInterface::PUBLICATION_STATUS_ENDED) {
            return __('Resume offer');
        }
        return __('Publish offer');
    }

    /**
     * @return string
     */
    protected function getOnClick()
    {
        return sprintf(
            "location.href = '%s';",
            $this->urlBuilder->getUrl('allegro/offer/publish/', ['id' => $this->getOffer()->getId()])
        );
    }

    /**
     * @return OfferInterface|ProductOfferInterface
     */
    private function getOffer()
    {
        return $this->registry->registry('offer');
    }
}
