<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The only database the suite is allowed to touch.
     *
     * RefreshDatabase drops every table it runs against, so this must never
     * resolve to the development database.
     */
    private const TEST_DATABASE = 'ims_restaurant_test';

    /**
     * Boot the app with the connection pinned to the test database.
     *
     * phpunit.xml's <env> entries cannot be relied on here: docker-compose
     * injects a real DB_DATABASE variable into the container, and Laravel's
     * immutable env repository keeps that value even when phpunit declares its
     * own (force="true" included). Overriding the config at boot — before
     * RefreshDatabase runs in setUpTraits — keeps the seeded dev data safe.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');

        if ($connection !== 'sqlite') {
            $app['config']->set("database.connections.{$connection}.database", self::TEST_DATABASE);
        }

        return $app;
    }
}
