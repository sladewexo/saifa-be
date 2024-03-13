<?php

namespace Includes\Database;

class WebHookLogsStorage extends BaseModel
{
    const table_name = "_webhook_log";
    const table_name_cut_first = "webhook_log";
    const sorted_fail_key = self::table_name_cut_first . '_sorted_by_fail_time';
    //count key
    const COUNT_KEY = "webhook_log_all_count";
    const COUNT_KEY_FAIL = "webhook_log_fail_count";

    /**
     * @param bool $status
     * @param string $eventName
     * @param string $uuid
     * @param array $logData
     * @return array
     * @throws \Exception
     */
    public function insertNewLogs(bool $status, string $eventName, string $uuid = '', array $logData): array
    {
        if (!empty($uuid) && !$this->isUUID($uuid)) {
            throw new \Exception('id is not uuid format');
        } else {
            $uuid = $this->makeNewUUID();
        }
        $logDataSave = [];
        $logDataSave['created_at'] = time();
        $logDataSave['sent_data'] = json_encode($logData, true);
        $logDataSave['status'] = ($status) ? 'ok' : '';
        $logDataSave['retry'] = 0;
        $logDataSave['next_retry_time'] = '';
        $logDataSave['retry_time'] = json_encode([time()], true);
        $insert = $this->insertNewRow($eventName . self::table_name, $uuid, $logDataSave);
        return [$insert, $eventName . self::table_name . ":" . $uuid];
    }

    /**
     * @param bool $fail
     * @return bool|\Redis
     */
    public function updateCountSendEvent(bool $fail = false)
    {
        $keyCount = self::COUNT_KEY;
        if ($fail) $keyCount = self::COUNT_KEY_FAIL;
        $count = (empty($this->redis->get($keyCount))) ? 0 : $this->redis->get($keyCount);
        $count++;
        return $this->redis->set($keyCount, $count);
    }

    public function getCount(): array
    {
        $count = (empty($this->redis->get(self::COUNT_KEY))) ? 0 : $this->redis->get(self::COUNT_KEY);
        $countFail = (empty($this->redis->get(self::COUNT_KEY_FAIL))) ? 0 : $this->redis->get(self::COUNT_KEY_FAIL);
        return ['all_send' => (int)$count, 'fail_send' => (int)$countFail];
    }

    /**
     * @param string $eventName
     * @param string $callId
     * @return array
     */
    public function getWebHookLogs(string $eventName, string $callId): array
    {
        return $this->select($eventName . self::table_name, $callId);
    }

    public function getWebHookLog(string $key): array
    {
        return $this->select('', $key);
    }

    /**
     * @param $debug
     * @return array
     */
    public function getWebHookFailToSendList($debug = false): array
    {
        $sortKey = $this->getSortKey(self::sorted_fail_key, 0, 0, true);
        $eventLogs = [];
        $now = time();
        //$now = '1671849108';
        foreach ($sortKey as $keyId => $score) {
            if ($score < $now) {
                if($debug) echo PHP_EOL."skip this key ($keyId) is not time yet : $score ";
                continue;
            }
            $eventLogs[$keyId] = $this->redis->hGetAll($keyId);
        }
        return $eventLogs;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function removeWebHookFailToSendList(string $key): bool
    {
        return $this->redis->zRem(self::sorted_fail_key, $key);
    }

    /**
     * @param bool $fail
     * @return bool|\Redis
     */
    public function updateCountAfterRetryNotFail()
    {
        $count = (empty($this->redis->get( self::COUNT_KEY))) ? 0 : $this->redis->get(self::COUNT_KEY);
        $count++;
        $this->redis->set(self::COUNT_KEY, $count);

        $count = (empty($this->redis->get( self::COUNT_KEY_FAIL))) ? 0 : $this->redis->get(self::COUNT_KEY_FAIL);
        $count--;
        if ($count < 1) $count = 0;
        $this->redis->set(self::COUNT_KEY_FAIL, $count);

        return true;
    }

    /**
     * @param string $key
     * @param int $score
     * @return bool
     */
    public function addNewSort(string $key, int $score): bool
    {
        return $this->redis->zAdd(self::sorted_fail_key, $score, $key);
    }

    /**
     * @param string $key
     * @param array $updateData
     * @return bool
     */
    public function updateWebHook(string $key, array $updateData): bool
    {
        return $this->update('', $key, $updateData);
    }
}