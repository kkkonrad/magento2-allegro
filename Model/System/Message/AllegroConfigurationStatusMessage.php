<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\System\Message;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\UrlInterface;

class AllegroConfigurationStatusMessage implements MessageInterface
{
    private const IDENTITY = 'allegro_configuration_status_message';

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var UrlInterface */
    private $urlBuilder;

    public function __construct(ScopeConfigInterface $scopeConfig, UrlInterface $urlBuilder)
    {
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
    }

    public function getIdentity()
    {
        return self::IDENTITY;
    }

    public function isDisplayed()
    {
        return (bool)$this->getMissingFields();
    }

    public function getText()
    {
        return __('Allegro offer configuration is incomplete: %1. <a href="%2">Open configuration</a>.',
            implode(', ', $this->getMissingFields()),
            $this->urlBuilder->getUrl('adminhtml/system_config/edit', ['section' => 'allegro'])
        );
    }

    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }

    private function getMissingFields(): array
    {
        $fields = [
            'allegro/credentials/client_id' => 'Client ID',
            'allegro/origin/country_id' => 'origin country',
            'allegro/origin/city' => 'origin city',
            'allegro/origin/post_code' => 'origin post code',
        ];
        $missing = [];
        foreach ($fields as $path => $label) {
            if (trim((string)$this->scopeConfig->getValue($path)) === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }
}
