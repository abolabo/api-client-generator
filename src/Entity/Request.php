<?php declare(strict_types=1);

namespace DoclerLabs\ApiClientGenerator\Entity;

class Request
{
    public const GET     = 'GET';
    public const POST    = 'POST';
    public const PUT     = 'PUT';
    public const PATCH   = 'PATCH';
    public const OPTIONS = 'OPTIONS';
    public const DELETE  = 'DELETE';
    public const HEAD    = 'HEAD';
    private string               $path;
    private string               $method;
    private RequestFieldRegistry $fields;

    public function __construct(string $path, string $method, RequestFieldRegistry $fieldCollection)
    {
        $this->path   = $this->toRelativePath($path);
        $this->method = $method;
        $this->fields = $fieldCollection;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getFields(): RequestFieldRegistry
    {
        return $this->fields;
    }

    private function toRelativePath(string $path): string
    {
        return ltrim($path, '/');
    }
}
