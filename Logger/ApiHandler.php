<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Logger;

use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Macopedia\Allegro\Logger\Logger;
use Monolog\LogRecord;

/**
 * ApiHandler for logger class
 */
class ApiHandler extends BaseHandler
{
    /**
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * @var string
     */
    protected $fileName = '/var/log/allegro-api.log';

    /**
     * @param LogRecord $record
     * @return bool
     */
    public function isHandling(LogRecord $record): bool
    {
        return parent::isHandling($record) && !($record->context[Logger::IS_EXCEPTION_KEY] ?? false);
    }
}