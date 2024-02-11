<?php

require '../vendor/autoload.php';
require_once __DIR__ . '/../includes/autoload.php';

use Includes\RedisConnection;

$redis = RedisConnection::getInstance();
$table_name = "webhook_log";
$pattern = "*$table_name*";
$sortedSetKey = $table_name . '_sorted_by_fail_time';
$round = 0;

do {
    // Use SCAN to find keys matching the pattern
    $keys = $redis->scan($iterator, $pattern);
    $round++;
    if ($keys !== false) {
        foreach ($keys as $key) {
            $hashValues = $redis->hGetAll($key);
            if (is_array($hashValues)) {
                checkSaveFailSort($sortedSetKey, $redis, $hashValues, $key, $round);
            } else {
                echo $key . " is not array";
            }
        }
    }
} while ($iterator > 0); // Continue until SCAN returns an iterator of 0

function checkSaveFailSort($sortedSetKey, $redis, array $hashValues, string $key, int $round)
{
    if (
        !empty($hashValues['created_at'])
        && isset($hashValues['status'])
        && isset($hashValues['next_retry_time'])
    ) {
        $nextTryTime = (int)$hashValues['next_retry_time'];
        $now = time();
        if ($nextTryTime < $now) {
            $tenMinutesLater = (int)strtotime("+10 minutes", $now); // Add 10 minutes
            if ($nextTryTime < 1) {
                //try to make first time fail not save as same score
                $tenMinutesLater += $round;
            }
            $redis->zAdd($sortedSetKey, $tenMinutesLater, $key);
        }
    }
}