<?php

namespace Tests\Unit\Field\Traits;

use App\Traits\Field\ValidatesAgainstOptions;
use Tests\TestCase;

class ValidatesAgainstOptionsTest extends TestCase
{
    private function makeType(array $settings = []): object
    {
        return new class($settings) {
            use ValidatesAgainstOptions;

            public function __construct(private array $settings) {}

            public function getSetting(string $key, mixed $default = null): mixed
            {
                return $this->settings[$key] ?? $default;
            }
        };
    }

    private function sampleOptions(): array
    {
        return [
            ['key' => 'foo', 'label' => 'Foo'],
            ['key' => 'bar', 'label' => 'Bar'],
            ['key' => 'baz', 'label' => 'Baz'],
        ];
    }

    // -------------------------------------------------------------------------
    // isValidOption()
    // -------------------------------------------------------------------------

    public function test_is_valid_option_returns_true_for_existing_key(): void
    {
        $type = $this->makeType();
        $this->assertTrue($type->isValidOption('foo', $this->sampleOptions()));
    }

    public function test_is_valid_option_returns_false_for_missing_key(): void
    {
        $type = $this->makeType();
        $this->assertFalse($type->isValidOption('missing', $this->sampleOptions()));
    }

    public function test_is_valid_option_returns_false_for_empty_string(): void
    {
        $type = $this->makeType();
        $this->assertFalse($type->isValidOption('', $this->sampleOptions()));
    }

    // -------------------------------------------------------------------------
    // validateAgainstOptions()
    // -------------------------------------------------------------------------

    public function test_validate_against_options_returns_true_for_null_value(): void
    {
        $type = $this->makeType(['options' => $this->sampleOptions()]);
        $this->assertTrue($type->validateAgainstOptions(null));
    }

    public function test_validate_against_options_returns_true_for_empty_string(): void
    {
        $type = $this->makeType(['options' => $this->sampleOptions()]);
        $this->assertTrue($type->validateAgainstOptions(''));
    }

    public function test_validate_against_options_returns_true_for_valid_value_when_not_strict(): void
    {
        $type = $this->makeType(['options' => $this->sampleOptions(), 'strict_options' => false]);
        $this->assertTrue($type->validateAgainstOptions('foo'));
    }

    public function test_validate_against_options_returns_true_for_orphaned_value_when_not_strict(): void
    {
        $type = $this->makeType(['options' => $this->sampleOptions(), 'strict_options' => false]);
        $this->assertTrue($type->validateAgainstOptions('orphaned'));
    }

    public function test_validate_against_options_returns_error_for_orphaned_value_when_strict(): void
    {
        $type = $this->makeType(['options' => $this->sampleOptions(), 'strict_options' => true]);
        $result = $type->validateAgainstOptions('orphaned');
        $this->assertIsString($result);
        $this->assertStringContainsString('orphaned', $result);
    }

    public function test_validate_against_options_checks_each_array_value_when_strict(): void
    {
        $type   = $this->makeType(['options' => $this->sampleOptions(), 'strict_options' => true]);
        $result = $type->validateAgainstOptions(['foo', 'invalid']);
        $this->assertIsString($result);
        $this->assertStringContainsString('invalid', $result);
    }

    public function test_validate_against_options_returns_true_when_options_empty(): void
    {
        $type = $this->makeType(['options' => [], 'strict_options' => true]);
        $this->assertTrue($type->validateAgainstOptions('anything'));
    }

    // -------------------------------------------------------------------------
    // renderOrphanedValue()
    // -------------------------------------------------------------------------

    public function test_render_orphaned_value_returns_empty_string_for_valid_value(): void
    {
        $type = $this->makeType();
        $this->assertSame('', $type->renderOrphanedValue('foo', $this->sampleOptions()));
    }

    public function test_render_orphaned_value_returns_html_for_invalid_value(): void
    {
        $type   = $this->makeType();
        $result = $type->renderOrphanedValue('gone', $this->sampleOptions());
        $this->assertStringContainsString('data-orphaned="true"', $result);
        $this->assertStringContainsString('[orphaned:', $result);
    }
}
