<?php
namespace App\Field;
abstract class AbstractField
{
    protected array $settings = [];

    protected string $type = 'string';

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }
}
