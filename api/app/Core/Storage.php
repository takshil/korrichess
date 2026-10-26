<?php

declare(strict_types=1);

namespace Korrichess\Api\Core;

final class Storage
{
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? (string) getenv('KORRI_STORAGE_ROOT') ?: __DIR__ . '/../../data/storage', '/');
        if (!is_dir($this->root)) {
            mkdir($this->root, 0775, true);
        }
    }

    /** Écrit un fichier et renvoie sa clé (chemin relatif). */
    public function put(string $key, string $contents): string
    {
        $path = $this->path($key);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, $contents);
        return $key;
    }

    public function get(string $key): ?string
    {
        $path = $this->path($key);
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function path(string $key): string
    {
        $realRoot = realpath($this->root) ?: $this->root;
        $path = $this->root . '/' . ltrim($key, '/');
        $real = realpath($path) ?: $path;
        if (!str_starts_with($real, $realRoot)) {
            throw new \InvalidArgumentException('Clé de stockage invalide (traversée hors racine)');
        }
        return $path;
    }
}
