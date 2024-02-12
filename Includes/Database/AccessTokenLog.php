<?php

namespace Includes\Database;

class AccessTokenLog extends BaseModel
{
    const APP_CONFIG_TABLE = "access_token_log";
    const SORT_KEY = "access_token_sort_log";
    const COUNT_KEY = "count_token_log";

    /**
     * @param string $token
     * @param string $userIP
     * @param string $json
     * @return bool
     */
    public function addLog(string $token, string $userIP, string $json): bool
    {
        $log = [];
        $log['token'] = $token;
        $log['create_at'] = time();
        $log['user_ip'] = $userIP;
        $log['json'] = $json;
        $keyCount = self::COUNT_KEY . $token;

        $count = (empty($this->redis->get($keyCount))) ? 0 : $this->redis->get($keyCount);
        $count++;
        //keep log only 10 time last call if over let override the old log logic like cctv on car.
        if ($count > 10) $count = 1;
        $this->redis->set($keyCount, $count);

        $tokenKey = $token . '_' . $count;
        if (!$this->insertOrUpdate(self::APP_CONFIG_TABLE . ":" . $tokenKey, '', $log, true)) {
            return false;
        }
        if (!$this->addOrUpdateSort(self::APP_CONFIG_TABLE, self::SORT_KEY, $token, $log['create_at'], $count)) {
            return false;
        }
        return true;
    }

    public function getLogFromTokenID(string $token): array
    {
        return $this->getLogFromID(self::SORT_KEY, $token);
    }

}