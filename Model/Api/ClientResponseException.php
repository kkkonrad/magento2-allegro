<?php

declare(strict_types=1);

namespace Macopedia\Allegro\Model\Api;

use Magento\Framework\Phrase;

class ClientResponseException extends ClientException
{
    /** @var int|null */
    private $httpStatusCode;

    /** @var string|null */
    private $requestId;

    /** @var array */
    private $apiErrors;

    /**
     * @param Phrase $phrase
     * @param \Exception|null $cause
     * @param int $code
     * @param int|null $httpStatusCode
     * @param string|null $requestId
     * @param array $apiErrors
     */
    public function __construct(
        Phrase $phrase,
        ?\Exception $cause = null,
        int $code = 0,
        ?int $httpStatusCode = null,
        ?string $requestId = null,
        array $apiErrors = []
    ) {
        parent::__construct($phrase, $cause, $code);
        $this->httpStatusCode = $httpStatusCode;
        $this->requestId = $requestId;
        $this->apiErrors = $apiErrors;
    }

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getApiErrors(): array
    {
        return $this->apiErrors;
    }

}
