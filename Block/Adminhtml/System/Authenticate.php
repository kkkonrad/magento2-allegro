<?php

namespace Macopedia\Allegro\Block\Adminhtml\System;

use Macopedia\Allegro\Model\Api\Auth;
use Macopedia\Allegro\Model\Api\ClientException;
use Macopedia\Allegro\Model\Api\TokenProvider;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Class responsible for authentication with Allegro API
 */
class Authenticate extends Field
{
    /** @var TokenProvider */
    private $tokenProvider;

    /** @var Auth */
    private $auth;

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
        array $data = []
    ) {
        $this->tokenProvider = $tokenProvider;
        $this->auth = $auth;
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
            $disconnectHtml = '<form method="post" action="'
                . $this->getUrl('allegro/system/disconnect') . '" style="display:inline-block;margin-left:8px">'
                . '<input type="hidden" name="form_key" value="' . $this->getFormKey() . '"/>'
                . '<button type="submit">' . __('Disconnect Allegro account') . '</button></form>';

        } catch (ClientException $e) {

            $statusLabel = '<span style="color: red;">' . __('Not active') . '</span>';
            $buttonLabel = __('Connect with Allegro account');
            $color = '#fae5e5';

        }

        $html = '<div style="background-color: ' . $color . '; padding: 20px 30px;">' . __('Connection status:') . ' ' . $statusLabel . '</div>'//phpcs:ignore
            . '<a href="' . $this->auth->getAuthUrl() . '"><button type="button">' . $buttonLabel . '</button></a>'
            . $disconnectHtml;

        return $html;
    }
}
