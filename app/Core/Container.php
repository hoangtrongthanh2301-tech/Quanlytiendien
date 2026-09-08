<?php

namespace App\Core;

class Container
{
    private array $services = [];

    public function set(string $key, $service): void
    {
        $this->services[$key] = $service;
    }

    public function get(string $key)
    {
        if (!isset($this->services[$key])) {
            throw new \RuntimeException("Service '$key' is not registered in the container.");
        }

        return $this->services[$key];
    }
}
