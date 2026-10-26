<?php

declare(strict_types=1);

namespace Korrichess\Api\Core;

final class Request
{
    /** @param array<string,mixed> $query @param array<string,mixed> $body @param array<string,string> $headers */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public array $pathParams = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        $body = is_array($decoded) ? $decoded : $_POST;
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string) $key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['authorization'] = (string) $_SERVER['HTTP_AUTHORIZATION'];
        }
        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . trim((string) ($_SERVER['REQUEST_URI'] ?? '/'), '/'),
            $_GET,
            $body,
            $headers,
        );
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->headers['authorization'] ?? '';
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }
}
