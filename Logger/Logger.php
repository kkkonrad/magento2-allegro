<?php

namespace Macopedia\Allegro\Logger;

/**
 * Logger for allegro integration debugging
 */
class Logger extends \Monolog\Logger
{
    const IS_EXCEPTION_KEY = 'exception';

    public function exception(\Exception $exception, $message = false)
    {
        $this->error($exception->getMessage() , [self::IS_EXCEPTION_KEY => true, 'exception' => $exception]);
        if ($message) {
            $this->error($message, [self::IS_EXCEPTION_KEY => false]);
        }
    }

    public function getFullErrorMessage(\Exception $exception)
    {
        if (!$exception instanceof \GuzzleHttp\Exception\ClientException) {
            return $exception->getMessage();
        }

        $apiMessage = $exception->getResponse()->getBody()->getContents();
        $data = json_decode($apiMessage, true);
        $message = '';
        if (isset($data['errors']) && is_array($data['errors'])) {
            foreach ($data['errors'] as $error) {
                // Możesz wykorzystać np. userMessage jako tekst dla klienta
                $code = $error['code'] ?? '';
                $field = $error['path'] ?? '';
                $userMessage = $error['userMessage'] ?? $error['message'] ?? 'Unknown error';

                $message .=  " Błąd ($code) w polu '$field': $userMessage | ";
            }
        }
        return $message;
    }
}
