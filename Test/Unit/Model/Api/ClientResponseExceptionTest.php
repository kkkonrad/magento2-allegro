<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Test\Unit\Model\Api;

use Macopedia\Allegro\Model\Api\ClientException;
use Macopedia\Allegro\Model\Api\ClientResponseException;
use PHPUnit\Framework\TestCase;

class ClientResponseExceptionTest extends TestCase
{
    public function testCarriesSafeApiMetadataAndUsesCommonBaseException(): void
    {
        $errors = [[
            'code' => 'VALIDATION_ERROR',
            'path' => 'name',
            'message' => 'Name is required',
        ]];

        $exception = new ClientResponseException(
            __('Request failed'),
            null,
            0,
            422,
            'trace-123',
            $errors
        );

        self::assertInstanceOf(ClientException::class, $exception);
        self::assertSame(422, $exception->getHttpStatusCode());
        self::assertSame('trace-123', $exception->getRequestId());
        self::assertSame($errors, $exception->getApiErrors());
    }
}
