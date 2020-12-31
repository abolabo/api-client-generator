<?php

declare(strict_types=1);

/*
 * This file was generated by docler-labs/api-client-generator.
 *
 * Do not edit it manually.
 */

namespace OpenApi\PetStoreClient\Serializer\ContentType;

use OpenApi\PetStoreClient\Schema\SerializableInterface;
use Psr\Http\Message\StreamInterface;

interface ContentTypeSerializerInterface
{
    public const LITERAL_VALUE_KEY = '__literalResponseValue';

    public function encode(SerializableInterface $body): string;

    public function decode(StreamInterface $body): array;

    public function getMimeType(): string;
}
