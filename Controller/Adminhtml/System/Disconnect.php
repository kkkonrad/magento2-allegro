<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Controller\Adminhtml\System;

use Macopedia\Allegro\Model\Api\Credentials;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Disconnect extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Macopedia_Allegro::config_macopedia_allegro';

    /** @var Credentials */
    private $credentials;

    public function __construct(Context $context, Credentials $credentials)
    {
        parent::__construct($context);
        $this->credentials = $credentials;
    }

    public function execute(): Redirect
    {
        $this->credentials->deleteToken();
        $this->messageManager->addSuccessMessage(__('The current Allegro account has been disconnected.'));

        /** @var Redirect $result */
        $result = $this->resultRedirectFactory->create();
        return $result->setPath('adminhtml/system_config/edit', ['section' => 'allegro']);
    }
}
