<?php

declare(strict_types=1);

/*
 * This file was generated by docler-labs/api-client-generator.
 *
 * Do not edit it manually.
 */

namespace OpenApi\PetStoreClient\Response;

use DoclerLabs\ApiClientException\Factory\ResponseExceptionFactory;
use DoclerLabs\ApiClientException\UnexpectedResponseException;
use OpenApi\PetStoreClient\Serializer\BodySerializer;
use Psr\Http\Message\ResponseInterface;

class ResponseHandler
{
    /** @var BodySerializer */
    private $bodySerializer;

    /** @var ResponseExceptionFactory */
    private $responseExceptionFactory;

    public function __construct(BodySerializer $bodySerializer, ResponseExceptionFactory $exceptionFactory)
    {
        $this->bodySerializer           = $bodySerializer;
        $this->responseExceptionFactory = $exceptionFactory;
    }

    /**
     * @throws UnexpectedResponseException
     */
    public function handle(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 200 && $statusCode < 300) {
            return $this->bodySerializer->unserializeResponse($response);
        }
        \var_dump($response);
        throw $this->responseExceptionFactory->create(\sprintf('Server replied with a non-200 status code: %s', $response->getStatusCode()), $response);
    }
}
