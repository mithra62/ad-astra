<?php

namespace AdAstra\Traits;

trait Errors
{
    /**
     * @param array $payload
     * @return bool
     */
    public function hasErrors(array $payload): bool
    {
        return array_key_exists('error', $payload);
    }
}
