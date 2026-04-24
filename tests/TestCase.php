<?php

namespace Tests;

use App\Models\UserSchema;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        UserSchema::flushResolved();
    }
}
