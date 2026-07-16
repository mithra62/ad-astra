<?php

namespace AdAstra\Doctor;

use Illuminate\Support\Str;

/**
 * Convenience base for doctor checks. A minimal check declares $id and
 * $name and implements run() — category derives from the id prefix and
 * dependencies default to none:
 *
 *     class MyCheck extends AbstractDoctorCheck
 *     {
 *         protected string $id = 'shop.payment-gateway';
 *         protected string $name = 'Payment gateway configured';
 *
 *         public function run(): iterable
 *         {
 *             if (config('shop.gateway') === null) {
 *                 yield $this->fail('No payment gateway configured');
 *             }
 *         }
 *     }
 */
abstract class AbstractDoctorCheck implements DoctorCheck
{
    protected string $id;

    protected string $name;

    public function id(): string
    {
        return $this->id;
    }

    public function category(): string
    {
        return Str::before($this->id, '.');
    }

    public function name(): string
    {
        return $this->name;
    }

    public function dependsOn(): array
    {
        return [];
    }

    protected function pass(string $message): DoctorResult
    {
        return DoctorResult::pass($message);
    }

    protected function warn(string $message, ?string $details = null, ?string $fixCommand = null, ?string $docsUrl = null): DoctorResult
    {
        return DoctorResult::warn($message, $details, $fixCommand, $docsUrl);
    }

    protected function fail(string $message, ?string $details = null, ?string $fixCommand = null, ?string $docsUrl = null): DoctorResult
    {
        return DoctorResult::fail($message, $details, $fixCommand, $docsUrl);
    }

    protected function skip(string $message): DoctorResult
    {
        return DoctorResult::skip($message);
    }
}
