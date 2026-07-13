<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Controller\Adminhtml\System;

use Macopedia\Allegro\Model\Api\Auth;
use Macopedia\Allegro\Model\Api\Credentials;
use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Macopedia\Allegro\Model\LastEventIdInitializer;
use Magento\Framework\Exception\LocalizedException;
use Macopedia\Allegro\Model\Api\OAuthStateManager;

/**
 * Authenticate Controller class
 */
class Authenticate extends Action
{
    protected $_publicActions = ['authenticate'];

    /** @var Auth */
    protected $auth;

    /** @var Credentials */
    protected $credentials;

    /** @var LastEventIdInitializer */
    protected $lastEventIdInitializer;

    /** @var OAuthStateManager */
    private $stateManager;

    /**
     * @param Auth $auth
     * @param Credentials $credentials
     * @param Action\Context $context
     * @param LastEventIdInitializer $lastEventIdInitializer
     * @param OAuthStateManager $stateManager
     */
    public function __construct(
        Auth $auth,
        Credentials $credentials,
        Action\Context $context,
        LastEventIdInitializer $lastEventIdInitializer,
        OAuthStateManager $stateManager
    ) {
        $this->auth = $auth;
        $this->credentials = $credentials;
        $this->lastEventIdInitializer = $lastEventIdInitializer;
        $this->stateManager = $stateManager;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();
        if (isset($params['code'])) {
            try {
                if (empty($params['state']) || !$this->stateManager->validateAndConsume((string)$params['state'])) {
                    throw new LocalizedException(__('Invalid or expired Allegro OAuth state. Please try connecting again.'));
                }
                $token = $this->auth->getNewToken($params['code']);
                $this->credentials->saveToken($token);

                $this->lastEventIdInitializer->initialize();

                $this->messageManager->addSuccessMessage(__('You have successfully connected with Allegro account'));
            } catch (LocalizedException $exception) {
                $this->getMessageManager()->addErrorMessage(__('Something went wrong while authorization in Allegro.'));
                $this->getMessageManager()->addExceptionMessage($exception);
            } catch (\Exception $exception) {
                $this->getMessageManager()
                    ->addErrorMessage(
                        __('Something went wrong while authorization in Allegro. Please check credentials and try again')//phpcs:ignore
                    );
            }
        }

        return $this
            ->resultRedirectFactory->create()
            ->setPath('adminhtml/system_config/edit/section/allegro');
    }
}
