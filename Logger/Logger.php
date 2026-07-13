<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Logger;

use Macopedia\Allegro\Model\Api\ClientResponseException;
/**
 * Logger for allegro integration debugging
 */
class Logger extends \Monolog\Logger
{
    const IS_EXCEPTION_KEY = 'exception';

    public function exception(\Throwable $exception, $message = false): void
    {
        $context = [
            self::IS_EXCEPTION_KEY => true,
            'exception_type' => get_class($exception),
            'exception_code' => $exception->getCode(),
        ];
        if ($exception instanceof ClientResponseException) {
            $context['http_status'] = $exception->getHttpStatusCode();
            $context['request_id'] = $exception->getRequestId();
        }

        $this->error($this->safeMessage($message ?: 'Allegro integration exception'), $context);
    }

    /**
     * Log a sanitized API failure. Context must not contain request headers,
     * tokens, secrets or response bodies.
     */
    public function apiFailure(string $message, array $context = []): void
    {
        $context = $this->sanitizeContext($context);
        $context[self::IS_EXCEPTION_KEY] = true;
        $this->error($this->safeMessage($message), $context);
    }

    private function sanitizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/token|secret|authorization|password|cookie|body/i', (string)$key)) {
                $context[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $context[$key] = $this->sanitizeContext($value);
                continue;
            }
            if (is_string($value)) {
                $context[$key] = $this->safeMessage($value);
            }
        }

        return $context;
    }

    private function safeMessage($message): string
    {
        $message = is_scalar($message) ? (string)$message : 'Allegro integration exception';
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $message);
        $message = preg_replace('/\b(?:Bearer\s+)?[A-Za-z0-9+\/_=-]{32,}\b/i', '[secret]', (string)$message);
        return mb_substr((string)$message, 0, 1000);
    }
}
