<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Macopedia\Allegro\Logger\Logger;
use Monolog\LogRecord;

/**
 * HttpRequestHandler for logger class
 */
class HttpRequestHandler extends BaseHandler
{
    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * @var string
     */
    protected $fileName = '/var/log/allegro-http-request.log';

    /**
     * @param LogRecord $record
     * @return bool
     */
    public function isHandling(LogRecord $record): bool
    {
        return parent::isHandling($record) && !($record->context[Logger::IS_EXCEPTION_KEY] ?? false);
    }
}