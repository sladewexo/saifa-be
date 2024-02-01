<?php

namespace Includes\Database;

class Session extends BaseModel
{
    const SESSION_TABLE = "session_table";

    /**
     * @param string $key
     * @return array
     */
    public function getOneSession(string $key): array
    {
        return $this->getOne(self::SESSION_TABLE, $key);
    }

    private function getOne(string $tableName, string $key)
    {
        $result = $this->select($tableName, $key);
        if (empty($result)) return [];
        return $result;
    }

    /**
     * @param array $SessionData
     * @param string $sessionId
     * @param int $expireTime
     * @return bool
     */
    public function saveNewSession(array $SessionData, string $sessionId, int $expireTime): bool
    {
        $insertResult = $this->insertNewRow(self::SESSION_TABLE, $sessionId, $SessionData, false, true);
        if ($insertResult) {
            return $this->setExpireKey(self::SESSION_TABLE, $sessionId, $expireTime);
        }
    }

    /**
     * @param array $SessionData
     * @param string $sessionId
     * @param int $expireTime
     * @return bool
     */
    public function updateSessionTimeOut(array $SessionData, string $sessionId, int $expireTime): bool
    {
        $update = $this->update(self::SESSION_TABLE, $sessionId, $SessionData);
        if ($update) {
            return $this->setExpireKey(self::SESSION_TABLE, $sessionId, $expireTime);
        }
    }

    public function removeSession(string $sessionId): bool
    {
        return $this->deleteOne(self::SESSION_TABLE, $sessionId);
    }


}