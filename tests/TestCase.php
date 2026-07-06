<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'cache.public_store' => 'array',
            'cache.public_fallback_store' => 'array',
            'queue.default' => 'sync',
        ]);
    }
}
