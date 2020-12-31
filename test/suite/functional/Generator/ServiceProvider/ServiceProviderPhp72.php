<?php declare(strict_types=1);

/*
 * This file was generated by docler-labs/api-client-generator.
 *
 * Do not edit it manually.
 */

namespace Test;

use DoclerLabs\ApiClientException\Factory\ResponseExceptionFactory;
use Pimple\Container;
use Test\Request\Mapper\GuzzleRequestMapper;
use Test\Request\Mapper\RequestMapperInterface;
use Test\Response\ResponseHandler;
use Test\Schema\Mapper\FoodMapper;
use Test\Schema\Mapper\PetCollectionMapper;
use Test\Schema\Mapper\PetMapper;
use Test\Serializer\BodySerializer;
use Test\Serializer\ContentType\FormUrlencodedContentTypeSerializer;
use Test\Serializer\ContentType\JsonContentTypeSerializer;
use Test\Serializer\ContentType\XmlContentTypeSerializer;
use Test\Serializer\QuerySerializer;

class ServiceProvider
{
    /**
     * @param Container $container
     */
    public function register(Container $container): void
    {
        $container[BodySerializer::class] = static function (): BodySerializer {
            return (new BodySerializer())->add(new JsonContentTypeSerializer())->add(new FormUrlencodedContentTypeSerializer())->add(new XmlContentTypeSerializer());
        };
        $container[QuerySerializer::class] = static function (): QuerySerializer {
            return new QuerySerializer();
        };
        $container[ResponseHandler::class] = static function () use ($container): ResponseHandler {
            return new ResponseHandler($container[BodySerializer::class], new ResponseExceptionFactory());
        };
        $container[RequestMapperInterface::class] = static function () use ($container): RequestMapperInterface {
            return new GuzzleRequestMapper($container[BodySerializer::class], $container[QuerySerializer::class]);
        };
        $container[PetCollectionMapper::class] = static function () use ($container): PetCollectionMapper {
            return new PetCollectionMapper($container[PetMapper::class]);
        };
        $container[PetMapper::class] = static function () use ($container): PetMapper {
            return new PetMapper($container[FoodMapper::class]);
        };
        $container[FoodMapper::class] = static function () use ($container): FoodMapper {
            return new FoodMapper();
        };
    }
}
