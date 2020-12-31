<?php declare(strict_types=1);

/*
 * This file was generated by docler-labs/api-client-generator.
 *
 * Do not edit it manually.
 */

namespace OpenApi\PetStoreClient\Schema\Mapper;

use DateTimeImmutable;
use OpenApi\PetStoreClient\Schema\Order;

class OrderMapper implements SchemaMapperInterface
{
    /**
     * @param array $payload
     *
     * @return Order
     */
    public function toSchema(array $payload): Order
    {
        $schema = new Order();
        if (isset($payload['id'])) {
            $schema->setId($payload['id']);
        }
        if (isset($payload['petId'])) {
            $schema->setPetId($payload['petId']);
        }
        if (isset($payload['quantity'])) {
            $schema->setQuantity($payload['quantity']);
        }
        if (isset($payload['shipDate'])) {
            $schema->setShipDate(new DateTimeImmutable($payload['shipDate']));
        }
        if (isset($payload['status'])) {
            $schema->setStatus($payload['status']);
        }
        if (isset($payload['complete'])) {
            $schema->setComplete($payload['complete']);
        }

        return $schema;
    }
}
