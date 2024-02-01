<?php

namespace Includes\Database;

class AccessToken extends BaseModel
{
    const APP_CONFIG_TABLE = "access_token";
    const SORT_KEY = "access_token_sort";

    /**
     * @param string $uuid
     * @return array
     */
    public function getConfig(string $uuid): array
    {
        return $this->select(self::APP_CONFIG_TABLE, $uuid);
    }

    public function getConfigAll(): array
    {
        $resultSort = $this->redis->zRevRange(self::SORT_KEY, 0, -1);
        $results = [];
        foreach ($resultSort as $key) {
            $results[] = $this->redis->hGetAll($key);
        }
        return $results;
    }

    /**
     * @param array $config
     * @return array
     */
    public function insertToken(array $config): array
    {
        $newUUID = $this->makeNewUUID();
        $config['token'] = $newUUID;
        $config['create_at'] = time();
        $config['update_at'] = time();
        $config['last_used'] = -1;
        $config['is_delete'] = false;

        $status = $this->insertNewRow(self::APP_CONFIG_TABLE, $newUUID, $config);

        if (!$this->addOrUpdateSort(self::APP_CONFIG_TABLE, self::SORT_KEY, $newUUID, $config['update_at'])) {
            return [false, $newUUID];
        }

        return [$status, $newUUID];
    }

    /**
     * @param string $uuid
     * @param array $config
     * @return bool
     */
    public function updateToken(string $uuid, array $config): bool
    {
        unset($config['token']);
        $config['update_at'] = time();
        $update = $this->update(self::APP_CONFIG_TABLE, $uuid, $config);
        if (!$this->addOrUpdateSort(self::APP_CONFIG_TABLE, self::SORT_KEY, $uuid, $config['update_at'])) {
            return false;
        }
        return $update;
    }

    /**
     * @param string $uuid
     * @return bool
     */
    public function updateTimeUsedToken(string $uuid): bool
    {
        $config['last_used'] = time();
        return $this->update(self::APP_CONFIG_TABLE, $uuid, $config);
    }

    public function removeSort(string $tokenID): bool
    {
        return $this->softDelete(self::APP_CONFIG_TABLE, self::SORT_KEY, $tokenID);
    }
}