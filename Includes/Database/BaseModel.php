<?php

namespace Includes\Database;

use Includes\RedisConnection;

class BaseModel
{
    protected $redis;
    private $oldJsonKeymd5;
    const max_get_in_one_time = 2000;

    public function __construct($debug = false)
    {
        $this->redis = RedisConnection::getInstance();
        $this->debug = $debug;
    }

    /**
     * @param array $keys
     * @param string $eventNameStr
     * @param $noDetail
     * @return void
     */
    public function printLog(array $keys, string $eventNameStr = "", $noDetail = false)
    {
        echo "$eventNameStr ja !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!" . PHP_EOL;

        $jsonKey = json_encode($keys, true);
        $jsonKeymd5 = md5($jsonKey);
        if ($jsonKeymd5 != $this->oldJsonKeymd5) {
            $this->oldJsonKeymd5 = $jsonKeymd5;
            if (!$noDetail) {
                print_r($keys);
                echo $jsonKeymd5 . PHP_EOL;
            }
        }
    }

    /**
     * @param string $tableName
     * @param string $primaryKey
     * @param array $saveData
     * @param bool $noPrimaryKey
     * @param bool $noUUidFormat
     * @return bool
     */
    protected function insertNewRow(string $tableName, string $primaryKey, array $saveData, bool $noPrimaryKey = false, bool $noUUidFormat = false): bool
    {
        $redis = $this->redis;
        if (!$noUUidFormat) {
            if (!$noPrimaryKey && !$this->isUUID($primaryKey)) {
                echo "is no uuid format!";
                return false;
            }
        }
        // Key for Redis
        try {
            $redisKey = "$tableName:" . $primaryKey;
            if ($noPrimaryKey) {
                $redisKey = "$tableName";
            }
            $md5Data = md5(json_encode($saveData, true));

            // Check if the key already exists
            if (!$redis->exists($redisKey)) {
                // Start a transaction
                $redis->multi();

                // Saving the array to Redis hash set
                foreach ($saveData as $key => $value) {
                    $redis->hSet($redisKey, $key, $value);
                }

                // Execute the transaction
                if ($this->debug) echo "Data saved to Redis with key: $redisKey" . ":md5_data:" . $md5Data . PHP_EOL;

                $redis->exec();
                return true;
            } else {
                if ($this->debug) echo "Key already exists. Data not saved: " . $redisKey . ":md5_data:" . $md5Data . PHP_EOL;
                return false;
            }
        } catch (\RedisException $e) {
            echo "Redis error: " . $e->getMessage();
        }
        return false;
    }

    protected function insertOrUpdate(string $tableName, string $primaryKey, array $saveData, bool $noPrimaryKey = false, bool $noUUidFormat = false): bool
    {
        $redis = $this->redis;
        if (!$noUUidFormat) {
            if (!$noPrimaryKey && !$this->isUUID($primaryKey)) {
                echo "is no uuid format!";
                return false;
            }
        }
        // Key for Redis
        try {
            $redisKey = "$tableName:" . $primaryKey;
            if ($noPrimaryKey) {
                $redisKey = "$tableName";
            }
            // Start a transaction
            $redis->multi();

            // Saving the array to Redis hash set
            foreach ($saveData as $key => $value) {
                $redis->hSet($redisKey, $key, $value);
            }

            // Execute the transaction
            if ($this->debug) echo "Data saved to Redis with key: $redisKey" . ":md5_data:"  . PHP_EOL;

            $redis->exec();
            return true;

        } catch (\RedisException $e) {
            echo "Redis error: " . $e->getMessage();
        }
        return false;
    }

    /**
     * @param string $tableName
     * @param string $primaryKey
     * @param int $intTime
     * @return bool|\Redis
     */
    protected function setExpireKey(string $tableName, string $primaryKey, int $intTime)
    {
        $redisKey = "$tableName:" . $primaryKey;
        return $this->redis->expire($redisKey, $intTime);
    }

    /**
     * @param string $indexName
     * @param string $indexKey
     * @param string $uuid
     * @return bool
     */
    protected function insertIndex(string $indexName, string $indexKey, string $uuid): bool
    {
        try {
            $redis = $this->redis;
            $redisKey = "index:" . $indexName . ":$indexKey";
            return $redis->set($redisKey, $uuid);
        } catch (\RedisException $e) {
            echo "Redis error: " . $e->getMessage();
        } catch (\Exception $e) {
            echo "error: " . $e->getMessage();
        }
        return false;

    }

    /**
     * @param string $tableName
     * @param string $primaryKey
     * @return array
     */
    protected function select(string $tableName, string $primaryKey): array
    {
        $redis = $this->redis;
        $key = "$tableName:$primaryKey";
        if (empty($primaryKey)) {
            $key = $tableName;
        }

        if ($redis->exists($key)) {
            try {
                return $redis->hGetAll($key);
            } catch (\RedisException $e) {
                echo "Redis error: " . $e->getMessage();
            } catch (\Exception $e) {
                echo "error: " . $e->getMessage();
            }
        } else {
            return [];
        }
    }

    protected function selectFromIndex(string $tableName, string $primaryKey): array
    {
        $redis = $this->redis;
        $key = "index:$tableName:$primaryKey";
        if (empty($primaryKey)) {
            $key = $tableName;
        }

        if ($redis->exists($key)) {
            try {
                $keyFromIndex = $this->redis->get($key);
                return $redis->hGetAll($keyFromIndex);
            } catch (\RedisException $e) {
                echo "Redis error: " . $e->getMessage();
            } catch (\Exception $e) {
                echo "error: " . $e->getMessage();
            }
        } else {
            return [];
        }
        return [];
    }

    /**
     * @param string $indexName
     * @param string $indexKey
     * @return string
     */
    protected function selectIndex(string $indexName, string $indexKey): string
    {
        $redis = $this->redis;
        $keyString = "index:" . $indexName . ":" . $indexKey;
        return $redis->get($keyString);
    }

    /**
     * @param string $tableName
     * @param string $primaryKey
     * @param array $updateArray
     * @return bool
     */
    protected function update(string $tableName = '', string $primaryKey, array $updateArray): bool
    {
        $redis = $this->redis;

        $key = "$tableName:$primaryKey";
        if (empty($primaryKey)) {
            $key = $tableName;
        }
        if (empty($tableName)) {
            $key = $primaryKey;
        }

        if ($redis->exists($key)) {
            try {
                $redis->multi();
                foreach ($updateArray as $field => $value) {
                    $redis->hSet($key, $field, $value);
                }
                $redis->exec();
                return true;
            } catch (\RedisException $e) {
                die("dd");
                echo "Redis error: " . $e->getMessage();
                return false;
            } catch (\Exception $e) {
                die("ss");
                echo "error: " . $e->getMessage();
                return false;
            }
        } else {
            echo("not found key for update key is :" . $key);
            return false;
        }
    }

    /**
     * @param string $key
     * @param string $value
     * @return bool
     */
    protected function updateKeyString(string $key, string $value): bool
    {
        try {
            $redis = $this->redis;
            return $redis->set($key, $value);
        } catch (\RedisException $e) {
            echo "Redis error: " . $e->getMessage();
            return false;
        } catch (\Exception $e) {
            echo "error: " . $e->getMessage();
            return false;
        }
    }

    /**
     * @param string $table
     * @param string $id
     * @return bool
     */
    protected function deleteOne(string $table, string $id): bool
    {
        try {
            return $this->redis->del($table . ":" . $id);
        } catch (\RedisException $e) {
            echo "Redis error: " . $e->getMessage();
            return false;
        } catch (\Exception $e) {
            echo "error: " . $e->getMessage();
            return false;
        }
    }

    /**
     * @param string $keyPattern
     * @return bool
     */
    protected function deleteAll(string $keyPattern): bool
    {
        try {
            $keys = $this->redis->keys("*$keyPattern*");
            // Check if there are any matching keys
            if (!empty($keys)) {
                // Delete all matching keys
                foreach ($keys as $key) {
                    $this->redis->del($key);
                }
                return true;
            } else {
                return false;
            }
        } catch (\RedisException $e) {
            echo "Redis error: " . $e->getMessage();
            return false;
        } catch (\Exception $e) {
            echo "error: " . $e->getMessage();
            return false;
        }
    }

    /**
     * @param string $uuid
     * @return false|int
     */
    public function isUUID(string $uuid)
    {
        if (empty($uuid)) return false;
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }

    /**
     * @return string
     */
    protected function makeNewUUID(): string
    {
        try {
            $data = openssl_random_pseudo_bytes(16);
            assert(strlen($data) == 16);

            // Set version to 0100
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            // Set bits 6-7 to 10
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

            // Output the 36 character UUID.
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        } catch (\Exception $e) {
            die('openssl_random_pseudo_bytes Caught exception: ' . $e->getMessage() . "\n");
        }
    }

    /**
     * @param $key
     * @param int $start
     * @param int $end
     * @param bool $withScores
     * @return array
     */
    protected function getSortKey($key, int $start = 0, int $end = 0, bool $withScores = false): array
    {
        if ($end === 0) {
            $end = self::max_get_in_one_time;
        }

        $sortKey = $this->redis->zRange($key, $start, $end, $withScores);
        if (empty($sortKey)) return [];
        return $sortKey;
    }

    /**
     * @param string $tableName
     * @param string $sortName
     * @param string $uuid
     * @param int $newScore
     * @param int $count
     * @return bool
     */
    protected function addOrUpdateSort(string $tableName, string $sortName, string $uuid, int $newScore, int $count = -1): bool
    {
        $memberKey = $tableName . ":" . $uuid;
        $sortedSetKey = $sortName;
        if ($count !== -1) {
            $memberKey = $tableName . ":" . $uuid . '_' . $count;
            $sortedSetKey = $sortName . "_" . $uuid;
        }

        $result = $this->redis->zAdd($sortedSetKey , $newScore, $memberKey);
        if ($result !== false) {
            $currentScore = $this->redis->zScore($sortedSetKey, $memberKey);
            if ($currentScore == $newScore) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @param string $tableName
     * @param string $sortName
     * @param string $uuid
     * @return bool
     */
    protected function softDelete (string $tableName, string $sortName, string $uuid): bool
    {
        $memberKey = $tableName . ":" . $uuid;
        $removedCount = $this->redis->zRem($sortName, $memberKey);
        if ($removedCount > 0) {
            return true;
        }
        return false;
    }

    public function getLogFromID(string $sortKey,string $uuid): array
    {
        if (!$this->isUUID($uuid)) {
            return [];
        }

        $resultSort = $this->redis->zRevRange($sortKey . "_" . $uuid, 0, -1);
        $results = [];
        foreach ($resultSort as $key) {
            $results[] = $this->redis->hGetAll($key);
        }
        return $results;
    }
}