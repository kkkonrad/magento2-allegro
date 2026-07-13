<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\System\Message;

use Macopedia\Allegro\Model\AsyncFailureRepository;
use Magento\Framework\Notification\MessageInterface;

class AllegroAsyncFailuresMessage implements MessageInterface
{
    private const IDENTITY = 'allegro_async_failures_message';

    /** @var AsyncFailureRepository */
    private $failures;

    public function __construct(AsyncFailureRepository $failures)
    {
        $this->failures = $failures;
    }

    public function getIdentity()
    {
        return self::IDENTITY;
    }

    public function isDisplayed()
    {
        return $this->failures->getDeadCount() > 0;
    }

    public function getText()
    {
        return __('%1 Allegro asynchronous operation(s) reached the retry limit. Review allegro_async_failures.', $this->failures->getDeadCount());
    }

    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }
}
