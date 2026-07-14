<?php

namespace Macopedia\Allegro\Block\Adminhtml\System;

use Macopedia\Allegro\Model\Api\Auth;
use Macopedia\Allegro\Model\Api\ClientException;
use Macopedia\Allegro\Model\Api\TokenProvider;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Macopedia\Allegro\Model\Api\Credentials;
use Macopedia\Allegro\Model\Operations\OperationalStatus;
use Macopedia\Allegro\Model\ResourceModel\ProductOffer as ProductOfferResource;

/**
 * Class responsible for authentication with Allegro API
 */
class Authenticate extends Field
{
    /** @var TokenProvider */
    private $tokenProvider;

    /** @var Auth */
    private $auth;

    /** @var Credentials */
    private $credentials;

    /** @var ProductOfferResource */
    private $productOfferResource;

    /** @var OperationalStatus */
    private $operationalStatus;

    /**
     * @param Context $context
     * @param TokenProvider $tokenProvider
     * @param Auth $auth
     * @param array $data
     */
    public function __construct(
        Context $context,
        TokenProvider $tokenProvider,
        Auth $auth,
        Credentials $credentials,
        ProductOfferResource $productOfferResource,
        OperationalStatus $operationalStatus,
        array $data = []
    ) {
        $this->tokenProvider = $tokenProvider;
        $this->auth = $auth;
        $this->credentials = $credentials;
        $this->productOfferResource = $productOfferResource;
        $this->operationalStatus = $operationalStatus;
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     * @return string
     * @throws \Exception
     */
    public function render(AbstractElement $element)
    {
        $disconnectHtml = '';
        try {

            $token = $this->tokenProvider->getCurrent();
            $statusLabel = '<span style="color: green;">' . __('Active') . '</span>';
            $buttonLabel = __('Connect with another Allegro account');
            $color = '#e5efe5';
            $accountId = $this->productOfferResource->getCurrentUserId() ?: '-';
            $details = sprintf(
                '<div>%s: %s | %s: %s | %s: %s | %s: %s</div>',
                __('Environment'),
                $this->credentials->isSandbox() ? __('Sandbox') : __('Production'),
                __('Account ID'),
                $this->escapeHtml($accountId),
                __('Token expires'),
                $this->escapeHtml(gmdate('Y-m-d H:i:s T', (int)$token->getExpirationTime())),
                __('Last successful synchronization'),
                $this->escapeHtml($this->lastSuccessfulSynchronization())
            );
            $disconnectHtml = '<form method="post" action="'
                . $this->getUrl('allegro/system/disconnect') . '" style="display:inline-block;margin-left:8px">'
                . '<input type="hidden" name="form_key" value="' . $this->getFormKey() . '"/>'
                . '<button type="submit">' . __('Disconnect Allegro account') . '</button></form>';

        } catch (ClientException $e) {

            $statusLabel = '<span style="color: red;">' . __('Not active') . '</span>';
            $buttonLabel = __('Connect with Allegro account');
            $color = '#fae5e5';
            $details = '<div>' . __('Environment') . ': '
                . ($this->credentials->isSandbox() ? __('Sandbox') : __('Production')) . '</div>';

        }

        $html = '<div style="background-color: ' . $color . '; padding: 20px 30px;">' . __('Connection status:') . ' ' . $statusLabel . $details . '</div>'//phpcs:ignore
            . '<a href="' . $this->auth->getAuthUrl() . '"><button type="button">' . $buttonLabel . '</button></a>'
            . $disconnectHtml;

        return $html;
    }

    private function lastSuccessfulSynchronization(): string
    {
        $latest = '';
        foreach (['import_orders', 'reconcile_offer_mappings', 'retry_async_operations'] as $operation) {
            $status = $this->operationalStatus->get($operation);
            $candidate = (string)($status['last_success_at'] ?? '');
            if ($candidate > $latest) {
                $latest = $candidate;
            }
        }

        return $latest !== '' ? $latest : '-';
    }
}
