<?php

namespace Includes;

use Dotenv\Dotenv;
use Redis;

class RedisConnection
{
    private static $instance;
    private $redis;

    private function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        $this->redis = new Redis();
        $this->redis->connect($_ENV['REDIS_HOST'], $_ENV['REDIS_PORT']);
        // You can also include authentication and other configurations if needed
    }

    public static function getInstance(): Redis
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        return static::$instance->redis;
    }
}
