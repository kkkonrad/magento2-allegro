<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Operations;

use Macopedia\Allegro\Model\Api\Credentials;
use Magento\Framework\FlagManager;
use Magento\Framework\Serialize\Serializer\Json;

class OperationalStatus
{
    private const FLAG_PREFIX = 'macopedia_allegro_operation_';

    /** @var FlagManager */
    private $flagManager;

    /** @var Json */
    private $json;

    /** @var Credentials */
    private $credentials;

    public function __construct(FlagManager $flagManager, Json $json, Credentials $credentials)
    {
        $this->flagManager = $flagManager;
        $this->json = $json;
        $this->credentials = $credentials;
    }

    public function record(string $operation, string $status, array $metrics = [], ?\Throwable $error = null): void
    {
        $current = $this->get($operation) ?? [];
        $now = gmdate('c');
        $data = array_merge($current, [
            'operation' => $operation,
            'environment' => $this->environment(),
            'last_run_at' => $now,
            'last_run_status' => $status,
            'metrics' => $this->safeMetrics($metrics),
            'last_error_type' => $error ? get_class($error) : null,
        ]);
        if ($status === 'success') {
            $data['last_success_at'] = $now;
        }

        $this->flagManager->saveFlag($this->flagName($operation), $this->json->serialize($data));
    }

    public function get(string $operation): ?array
    {
        $raw = $this->flagManager->getFlagData($this->flagName($operation));
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        try {
            $data = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException $exception) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private function safeMetrics(array $metrics): array
    {
        $safe = [];
        foreach ($metrics as $key => $value) {
            if (is_int($value) || is_float($value) || is_bool($value)) {
                $safe[(string)$key] = $value;
            }
        }
        return $safe;
    }

    private function flagName(string $operation): string
    {
        return self::FLAG_PREFIX . $this->environment() . '_' . preg_replace('/[^a-z0-9_]+/', '_', strtolower($operation));
    }

    private function environment(): string
    {
        return $this->credentials->isSandbox() ? 'sandbox' : 'production';
    }
}
