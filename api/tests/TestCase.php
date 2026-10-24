<?php

namespace Tests;

use App\Redis\Contracts\RedisClienteInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RedisClienteFalso;

abstract class TestCase extends BaseTestCase
{
    protected RedisClienteFalso $redis;

    /** La suite nunca toca un Redis real (salvo el grupo `redis`) ni encola jobs de verdad. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->redis = new RedisClienteFalso;
        $this->app->instance(RedisClienteInterface::class, $this->redis);

        Queue::fake();
    }
}
