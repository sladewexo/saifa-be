<?php

namespace Includes\Database;

class Users extends BaseModel
{
    const APP_CONFIG_TABLE = "users_table";
    const INDEX_KEY = self::APP_CONFIG_TABLE . ":userId";
    const SORT_KEY = "users_table_sort";
    const ALLOW_COLUMN = ['username', 'name', 'user_id', 'last_login_time', 'create_at', 'update_at', 'status', 'is_admin', 'is_verified'];

    private function formatPublicData(array $user): array
    {
        $formatUser = [];
        foreach ($user as $key => $row) {
            if (in_array($key, self::ALLOW_COLUMN)) {
                $formatUser[$key] = $row;
            }
        }
        return $formatUser;
    }

    public function getUserByUserName(string $userName): array
    {
        return $this->selectFromIndex(self::INDEX_KEY, $userName);
    }

    public function getUserByID(string $userUUId): array
    {
        $user = $this->select(self::APP_CONFIG_TABLE, $userUUId);
        return $this->formatPublicData($user);
    }

    public function getAll(): array
    {
        $resultSort = $this->redis->zRevRange(self::SORT_KEY, 0, -1);
        $results = [];
        foreach ($resultSort as $key) {
            $result = [];
            $user = $this->redis->hGetAll($key);
            $results[] = $this->formatPublicData($user);
        }
        return $results;
    }

    /**
     * @param array $requestData
     * @return array
     */
    public function insertUser(array $requestData): array
    {
        $newUUID = $this->makeNewUUID();
        $saveData['user_id'] = $newUUID;
        $saveData['username'] = $requestData['username'];
        $saveData['status'] = $requestData['status'];
        $saveData['create_at'] = time();
        $saveData['update_at'] = time();
        $saveData['hash_password'] = hash('sha256', $requestData['password']);
        $saveData['last_login_time'] = -1;
        $saveData['is_delete'] = false;
        $saveData['is_admin'] = $requestData['is_admin'] ?? false;
        //todo make verified process url send to email
        $saveData['is_verified'] = 'no';

        if (!$this->insertNewRow(self::APP_CONFIG_TABLE, $newUUID, $saveData)) {
            return [false, $newUUID];
        }

        if (!$this->addOrUpdateSort(self::APP_CONFIG_TABLE, self::SORT_KEY, $newUUID, $saveData['update_at'])) {
            return [false, $newUUID];
        }

        if (!$this->insertIndex(self::INDEX_KEY, $saveData['username'], self::APP_CONFIG_TABLE . ":" . $newUUID)) {
            return [false, $newUUID];
        }

        return [true, $newUUID];
    }

    /**
     * @param string $userId
     * @param array $userFromRequest
     * @return bool
     */
    public function updateUser(string $userId, array $userFromRequest): bool
    {
        $updateData = [];
        if (!$this->isUUID($userId)) {
            return false;
        }
        if (!empty($userFromRequest['new_password'])) {
            $updateData['hash_password'] = hash('sha256', $userFromRequest['new_password']);
        }
        if (!empty($userFromRequest['status'])) {
            $updateData['status'] = ($userFromRequest['status'] === 'active') ? 'active' : 'inactive';
        }
        if (!empty($userFromRequest['is_delete'])) {
            $updateData['is_delete'] = ($userFromRequest['is_delete'] === true) ? true : false;
        }
        if (empty($updateData)) return false;
        $updateData['update_at'] = time();

        $update = $this->update(self::APP_CONFIG_TABLE, $userId, $updateData);
        if (!$this->addOrUpdateSort(self::APP_CONFIG_TABLE, self::SORT_KEY, $userId, $updateData['update_at'])) {
            return false;
        }
        return $update;
    }

    /**
     * @param string $userId
     * @return bool
     */
    public function updateTimeLoginTime(string $userId): bool
    {
        if (!$this->isUUID($userId)) {
            return false;
        }
        $user['last_login_time'] = time();
        return $this->update(self::APP_CONFIG_TABLE, $userId, $user);
    }

    public function removeSort(string $userId): bool
    {
        return $this->softDelete(self::APP_CONFIG_TABLE, self::SORT_KEY, $userId);
    }

}