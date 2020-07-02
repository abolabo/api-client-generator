<?php declare(strict_types=1);

namespace DoclerLabs\ApiClientGenerator\Input;

use cebe\openapi\exceptions\TypeErrorException;
use cebe\openapi\exceptions\UnresolvableReferenceException;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use DoclerLabs\ApiClientGenerator\Entity\Field;
use DoclerLabs\ApiClientGenerator\Entity\FieldCollection;
use DoclerLabs\ApiClientGenerator\Entity\OperationCollection;
use DoclerLabs\ApiClientGenerator\Entity\ResponseException;
use DoclerLabs\ApiClientGenerator\Entity\ResponseExceptionCollection;
use DoclerLabs\ApiClientGenerator\Input\Factory\OperationCollectionFactory;
use Symfony\Component\Yaml\Yaml;

class Parser
{
    private $operationCollectionFactory;

    public function __construct(OperationCollectionFactory $operationCollectionFactory)
    {
        $this->operationCollectionFactory = $operationCollectionFactory;
    }

    public function parse(string $fileName): Specification
    {
        try {
            $openApi = $this->parseSpecification($fileName);
        } catch (TypeErrorException | UnresolvableReferenceException $e) {
            throw new InvalidSpecificationException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
        $operations              = $this->operationCollectionFactory->create($openApi);
        $compositeRequestFields  = $this->extractCompositeRequestFields($operations);
        $compositeResponseFields = $this->extractCompositeResponseFields($operations);
        $responseExceptions      = $this->extractResponseExceptions($operations);

        return new Specification(
            $openApi,
            $operations,
            $compositeRequestFields,
            $compositeResponseFields,
            $responseExceptions
        );
    }

    private function parseSpecification(string $fileName): OpenApi
    {
        if (!is_readable($fileName)) {
            throw new InvalidSpecificationException('File does not exist or not readable: ' . $fileName);
        }

        $yaml    = file_get_contents($fileName);
        $openApi = new OpenApi(Yaml::parse($yaml));
        $openApi->setReferenceContext(new ReferenceContext($openApi, $fileName));

        if (!$openApi->validate()) {
            throw new InvalidSpecificationException(
                'OpenAPI specification validation failed: ' . json_encode($openApi->getErrors())
            );
        }

        return $openApi;
    }

    private function extractCompositeRequestFields(OperationCollection $operations): FieldCollection
    {
        $allFields = new FieldCollection();
        foreach ($operations as $operation) {
            $request = $operation->getRequest();
            foreach ($request->getFields() as $origin => $fields) {
                foreach ($fields as $field) {
                    $this->extractField($field, $allFields);
                }
            }
        }

        return $allFields;
    }

    private function extractCompositeResponseFields(OperationCollection $operations): FieldCollection
    {
        $allFields = new FieldCollection();
        foreach ($operations as $operation) {
            $responseRoot = $operation->getSuccessfulResponse()->getBody();
            if ($responseRoot !== null) {
                $this->extractField($responseRoot, $allFields);
            }
        }

        return $allFields;
    }

    private function extractField(Field $field, FieldCollection $allFields): void
    {
        if ($field->isObject()) {
            $allFields->add($field);
            $this->extractPropertyField($field, $allFields);
        }

        if ($field->isArrayOfObjects()) {
            $allFields->add($field);
            $allFields->add($field->getStructure()->getArrayItem());
            $this->extractPropertyField($field->getStructure()->getArrayItem(), $allFields);
        }
    }

    private function extractPropertyField(Field $rootObject, FieldCollection $allFields): void
    {
        foreach ($rootObject->getStructure()->getObjectProperties() as $property) {
            $this->extractField($property, $allFields);
        }
    }

    private function extractResponseExceptions(OperationCollection $operations): ResponseExceptionCollection
    {
        $errorTypes = new ResponseExceptionCollection();
        foreach ($operations as $operation) {
            foreach ($operation->getErrorResponses() as $response) {
                $errorTypes->add(new ResponseException($response->getStatusCode()));
            }
        }

        return $errorTypes;
    }
}
