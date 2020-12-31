<?php

declare(strict_types=1);

namespace DoclerLabs\ApiClientGenerator\Output\Copy\Serializer\ContentType;

use DoclerLabs\ApiClientGenerator\Output\Copy\Schema\SerializableInterface;
use DOMDocument;
use DOMElement;
use DOMNode;
use Psr\Http\Message\StreamInterface;

class XmlContentTypeSerializer implements ContentTypeSerializerInterface
{
    const MIME_TYPE = 'application/xml';
    const ATTRIBUTE_NAMESPACE = 'xmlns';
    const ATTRIBUTE_NAMESPACE_SEPARATOR = ':';

    protected array       $config     = [
        'version'          => '1.0',
        'encoding'         => 'UTF-8',
        'attributesKey'    => '@attributes',
        'cdataKey'         => '@cdata',
        'valueKey'         => '@value',
        'namespacesOnRoot' => true
    ];
    protected DOMDocument $xml;
    protected array       $namespaces = [];
    protected array       $array      = [];

    public function decode(StreamInterface $body): array
    {
        $body->rewind();
        $this->loadXml($body->getContents());

        // Convert the XML to an array, omitting the root node, as it is the name of the entity
        $child         = $this->xml->documentElement->firstChild;
        $childValue    = $this->parseNode($child);
        $childNodeName = $child->nodeName;

        $this->array[$childNodeName] = $childValue;

        // Add namespacing information to the root node
        if (!empty($this->namespaces) && $this->config['namespacesOnRoot']) {
            if (!isset($this->array[$childNodeName][$this->config['attributesKey']])) {
                $this->array[$childNodeName][$this->config['attributesKey']] = [];
            }

            foreach ($this->namespaces as $uri => $prefix) {
                if ($prefix) {
                    $prefix = self::ATTRIBUTE_NAMESPACE_SEPARATOR . $prefix;
                }

                $this->array[$childNodeName][$this->config['attributesKey']][self::ATTRIBUTE_NAMESPACE . $prefix] = $uri;
            }
        }

        return $this->array;
    }

    public function encode(SerializableInterface $body): string
    {
        $this->xml = new DOMDocument($this->config['version'], $this->config['encoding']);
        if (strrpos(get_class($body), '\\') === false) {
            $rootKey = get_class($body);
        } else {
            $rootKey = substr(get_class($body), strrpos(get_class($body), '\\') + 1);
        }
        $this->xml->appendChild($this->buildNode($rootKey, $body->toArray()));

        return $this->xml->saveXML();
    }

    public function getMimeType(): string
    {
        return self::MIME_TYPE;
    }

    protected function loadXml(string $inputXml)
    {
        $this->xml = new DOMDocument($this->config['version'], $this->config['encoding']);

        $parse = @$this->xml->loadXML($inputXml);

        if ($parse === false) {
            throw new SerializeException('Error parsing XML string, input is not a well-formed XML string.');
        }
    }

    protected function parseNode(DOMNode $node)
    {
        $output = [];
        $output = $this->collectNodeNamespaces($node, $output);

        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
                $output[$this->config['cdataKey']] = $this->normalizeTextContent($node->textContent);
                break;

            case XML_TEXT_NODE:
                $output = $this->normalizeTextContent($node->textContent);
                break;

            case XML_ELEMENT_NODE:
                $output = $this->parseChildNodes($node, $output);
                $output = $this->normalizeNodeValues($output);
                $output = $this->collectAttributes($node, $output);
                break;
        }

        return $output;
    }

    protected function parseChildNodes(DOMNode $node, $output)
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_CDATA_SECTION_NODE) {
                if (!is_array($output)) {
                    if (!empty($output)) {
                        $output = [$this->config['valueKey'] => $output];
                    } else {
                        $output = [];
                    }
                }

                $output[$this->config['cdataKey']] = $this->normalizeTextContent($child->textContent);
            } else {
                $value = $this->parseNode($child);

                if ($child->nodeType == XML_TEXT_NODE) {
                    if ($value != '') {
                        if (!empty($output)) {
                            $output[$this->config['valueKey']] = $value;
                        } else {
                            $output = $value;
                        }
                    }
                } elseif ($child->nodeType !== XML_COMMENT_NODE) {
                    $nodeName = $child->nodeName;

                    if (!isset($output[$nodeName])) {
                        $output[$nodeName] = [];
                    }

                    $output[$nodeName][] = $value;
                }
            }
        }

        return $output;
    }

    protected function normalizeTextContent($textContent): string
    {
        return trim(
            preg_replace(
                [
                    '/\n+\s+/',
                    '/\r+\s+/',
                    '/\n+\t+/',
                    '/\r+\t+/'
                ],
                ' ',
                $textContent
            )
        );
    }

    protected function normalizeNodeValues($values)
    {
        if (is_array($values)) {
            // if only one node of its kind, assign it directly instead if array($value);
            foreach ($values as $key => $value) {
                if (is_array($value) && count($value) === 1) {
                    $keyName = array_keys($value)[0];

                    if (is_numeric($keyName)) {
                        $values[$key] = $value[$keyName];
                    }
                }
            }

            if (empty($values)) {
                $values = '';
            }
        }

        return $values;
    }

    protected function collectAttributes(DOMNode $node, $output)
    {
        if (!$node->attributes->length) {
            return $output;
        }

        $attributes = [];
        $namespaces = [];

        foreach ($node->attributes as $attributeName => $attributeNode) {
            $attributeName              = $attributeNode->nodeName;
            $attributes[$attributeName] = (string)$attributeNode->value;

            if ($attributeNode->namespaceURI) {
                $nsUri    = $attributeNode->namespaceURI;
                $nsPrefix = $attributeNode->lookupPrefix($nsUri);

                $namespaces = $this->collectNamespaces($attributeNode);
            }
        }

        // if its a leaf node, store the value in @value instead of directly it.
        if (!is_array($output)) {
            if (!empty($output)) {
                $output = [$this->config['valueKey'] => $output];
            } else {
                $output = [];
            }
        }

        foreach (array_merge($attributes, $namespaces) as $key => $value) {
            $output[$this->config['attributesKey']][$key] = $value;
        }

        return $output;
    }

    protected function collectNodeNamespaces(DOMNode $node, array $output): array
    {
        $namespaces = $this->collectNamespaces($node);

        if (!empty($namespaces)) {
            $output[$this->config['attributesKey']] = $namespaces;
        }

        return $output;
    }

    protected function collectNamespaces(DOMNode $node): array
    {
        $namespaces = [];

        if ($node->namespaceURI) {
            $nsUri    = $node->namespaceURI;
            $nsPrefix = $node->lookupPrefix($nsUri);

            if (!array_key_exists($nsUri, $this->namespaces)) {
                $this->namespaces[$nsUri] = $nsPrefix;

                if (!$this->config['namespacesOnRoot']) {
                    if ($nsPrefix) {
                        $nsPrefix = self::ATTRIBUTE_NAMESPACE_SEPARATOR . $nsPrefix;
                    }

                    $namespaces[self::ATTRIBUTE_NAMESPACE . $nsPrefix] = $nsUri;
                }
            }
        }

        return $namespaces;
    }

    protected function buildNode($nodeName, $data)
    {
        if (!$this->isValidTagName($nodeName)) {
            throw new SerializeException('Invalid character in the tag name being generated: ' . $nodeName);
        }

        $node = $this->xml->createElement($nodeName);

        if (is_array($data)) {
            $this->parseArray($node, $nodeName, $data);
        } else {
            $node->appendChild($this->xml->createTextNode($this->normalizeValues($data)));
        }

        return $node;
    }

    protected function parseArray(DOMElement $node, $nodeName, array $array)
    {
        // get the attributes first.;
        $array = $this->parseAttributes($node, $nodeName, $array);

        // get value stored in @value
        $array = $this->parseValue($node, $array);

        // get value stored in @cdata
        $array = $this->parseCdata($node, $array);

        // recurse to build child nodes for this node
        foreach ($array as $key => $value) {
            if (!$this->isValidTagName($key)) {
                throw new SerializeException('Invalid character in the tag name being generated: ' . $key);
            }

            if (is_array($value) && is_numeric(key($value))) {
                // MORE THAN ONE NODE OF ITS KIND;
                // if the new array is numeric index, means it is array of nodes of the same kind
                // it should follow the parent key name
                foreach ($value as $v) {
                    $node->appendChild($this->buildNode($key, $v));
                }
            } else {
                // ONLY ONE NODE OF ITS KIND
                $node->appendChild($this->buildNode($key, $value));
            }

            unset($array[$key]);
        }
    }

    protected function parseAttributes(DOMElement $node, $nodeName, array $array)
    {
        $attributesKey = $this->config['attributesKey'];

        if (array_key_exists($attributesKey, $array) && is_array($array[$attributesKey])) {
            foreach ($array[$attributesKey] as $key => $value) {
                if (!$this->isValidTagName($key)) {
                    throw new SerializeException('Invalid character in the attribute name being generated: ' . $key);
                }

                $node->setAttribute($key, $this->normalizeValues($value));
            }

            unset($array[$attributesKey]);
        }

        return $array;
    }

    protected function parseValue(DOMElement $node, array $array)
    {
        $valueKey = $this->config['valueKey'];

        if (array_key_exists($valueKey, $array)) {
            $node->appendChild($this->xml->createTextNode($this->normalizeValues($array[$valueKey])));

            unset($array[$valueKey]);
        }

        return $array;
    }

    protected function parseCdata(DOMElement $node, array $array)
    {
        $cdataKey = $this->config['cdataKey'];

        if (array_key_exists($cdataKey, $array)) {
            $node->appendChild($this->xml->createCDATASection($this->normalizeValues($array[$cdataKey])));

            unset($array[$cdataKey]);
        }

        return $array;
    }

    protected function normalizeValues($value)
    {
        $value = $value === true ? 'true' : $value;
        $value = $value === false ? 'false' : $value;
        $value = $value === null ? '' : $value;

        return (string)$value;
    }

    protected function isValidTagName(string $tag): bool
    {
        $pattern = '/^[a-zA-Z_][\w\:\-\.]*$/';

        return preg_match($pattern, $tag, $matches) && $matches[0] === $tag && $tag[strlen($tag) - 1] !== ':';
    }
}
