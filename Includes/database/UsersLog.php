<?php

namespace Includes\Database;

class UsersLog extends BaseModel
{
    const APP_CONFIG_TABLE = "user_log";
    const SORT_KEY = "user_sort_log";
    const COUNT_KEY = "count_user_log";
    const MAX_LOG_SAVE = 10;

    public function addLog(string $userId, string $userIP, string $json, bool $loginFail): bool
    {
        $log = [];
        $log['user_id'] = $userId;
        $log['create_at'] = time();
        $log['user_ip'] = $userIP;
        $log['json'] = $json;
        $log['login_fail'] = $loginFail;
        $keyCount = self::COUNT_KEY . $userId;

        $count = (empty($this->redis->get($keyCount))) ? 0 : $this->redis->get($keyCount);
        $count++;
        //keep log only 10 time last call if over let override the old log logic like cctv on car.
        if ($count > self::MAX_LOG_SAVE) $count = 1;
        $this->redis->set($keyCount, $count);

        $tokenKey = $userId . '_' . $count;
        if (!$this->insertOrUpdate(self::APP_CONFIG_TABLE . ":" . $tokenKey, '', $log, true)) {
            return false;
        }
        if (!$this->addOrUpdateSort(self::APP_CONFIG_TABLE, self::SORT_KEY, $userId, $log['create_at'], $count)) {
            return false;
        }
        return true;
    }

    public function getLogFromUserID(string $userId): array
    {
        return $this->getLogFromID(self::SORT_KEY, $userId);
    }

}