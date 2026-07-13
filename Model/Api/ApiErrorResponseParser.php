<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Api;

/**
 * Parses Allegro error responses without exposing transport-level secrets.
 */
class ApiErrorResponseParser
{
    /**
     * @return array<int, array{code:string,path:string,message:string}>
     */
    public function parse(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [];
        }

        $rawErrors = $data['errors'] ?? [];
        if (!is_array($rawErrors)) {
            return [];
        }

        $errors = [];
        foreach ($rawErrors as $rawError) {
            if (!is_array($rawError)) {
                continue;
            }

            $message = $rawError['userMessage'] ?? $rawError['message'] ?? '';
            if (!is_scalar($message) || trim((string)$message) === '') {
                $message = 'Unknown Allegro API error';
            }

            $errors[] = [
                'code' => $this->scalarToString($rawError['code'] ?? ''),
                'path' => $this->scalarToString($rawError['path'] ?? ''),
                'message' => trim((string)$message),
            ];
        }

        return $errors;
    }

    public function format(array $errors): string
    {
        $messages = [];
        foreach ($errors as $error) {
            if (!is_array($error)) {
                continue;
            }

            $message = trim((string)($error['message'] ?? ''));
            if ($message === '') {
                continue;
            }

            $context = [];
            if (!empty($error['code'])) {
                $context[] = (string)$error['code'];
            }
            if (!empty($error['path'])) {
                $context[] = (string)$error['path'];
            }

            $messages[] = $context
                ? sprintf('%s (%s)', $message, implode(', ', $context))
                : $message;
        }

        return implode(' ', $messages);
    }

    /**
     * @param mixed $value
     */
    private function scalarToString($value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
