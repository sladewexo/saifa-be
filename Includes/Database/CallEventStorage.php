<?php

namespace Includes\Database;

class CallEventStorage extends BaseModel
{
    const TABLE_NAME = 'call_table';
    const SORT_KEY = self::TABLE_NAME . '_sorted_by_create_at';

    public function getCallTable(string $callId): array
    {
        return $this->select(self::TABLE_NAME, $callId);
    }

    /**
     * @param int $maxPerPage
     * @param int $page
     * @param string $mode
     * @param string $search
     * @return array
     */
    public function getCallTables(int $maxPerPage, int $page, string $mode = 'desc', string $search = ''): array
    {
        $start = $page - 1;
        $end = $start + ($maxPerPage - 1);
        $resultSort = [];

        if (!empty($search)) {
            //todo check this should still used redis->keys or not.
            $pattern = 'index:call_table:uniqueid:' . $search . '*';
            $allKeys = $this->redis->keys($pattern);
            foreach ($allKeys as $key) {
                $uuid = $this->redis->get($key);
                if ($this->isUUID($uuid)) {
                    $resultSort[] = "call_table:" . $uuid;
                }
            }
        } else {
            if ($mode == 'desc') {
                $resultSort = $this->redis->zRevRange(self::SORT_KEY, $start, $end);
            } else {
                $resultSort = $this->redis->zRange(self::SORT_KEY, $start, $end);
            }
        }

        $results = [];
        foreach ($resultSort as $key) {
            $results[] = $this->redis->hGetAll($key);
        }
        return $results;
    }

    public function countCallTables(): int
    {
        return $this->redis->zCount(self::SORT_KEY, '-inf', 'inf');
    }

    public function getCallTableByUniqueid(string $uniqueid): array
    {
        $callId = $this->selectIndex("call_table:uniqueid", $uniqueid);
        return $this->select("call_table", $callId);
    }

    /**
     * @param array $keys
     * @param string $eventNameStr
     * @param $noDetail
     * @return void
     */
    public function printLog(array $keys, string $eventNameStr = "", $noDetail = false)
    {
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
     * @param array $callData
     * @param string $fromEvent
     * @return string
     */
    public function saveNewCallTable(array $callData,string $fromEvent = 'VarSet'): string
    {
        $uuid = $callData['value'] ?? '';
        if ($fromEvent === 'OriginateResponse'){
            $uuid = $callData['actionid'] ?? '';
        }
        if (!$this->isUUID($uuid)) {
            echo "is no uuid format!";
            return "";
        }

        $callDataTable = [
            "call_id" => $uuid,
            "ask_id" => $callData['uniqueid'],
            "channel" => $callData['channel'],
            "create_at" => date("Y-m-d H:i:s P"),
            "start_time" => date("Y-m-d H:i:s P"),
            "end_time" => null,
            "call_status" => "initialize",
            "end_call_reason" => null,
            "from_event" => $fromEvent,
            "web_hook_start_already_send" => false,
            "web_hook_end_already_send" => false
        ];

        if (!$this->insertNewRow("call_table", $uuid, $callDataTable)) {
            echo "unable to add call_table";
        }

        if (!empty($callData['uniqueid'])) {
            if (!$this->insertIndex("call_table:uniqueid", $callData['uniqueid'], $uuid)) {
                echo "unable to add insertIndex";
            }
        }

        return $uuid;
    }

    /**
     * @param string $uuid
     * @param string $uniqueid
     * @return void
     */
    public function removeTestCall(string $uuid, string $uniqueid)
    {
        if (!$this->deleteAll($uuid)) {
            echo "unable to delete key" . $uuid;
        }
        if (!$this->deleteAll($uniqueid)) {
            echo "unable to delete key" . $uniqueid;
        }
    }

    /**
     * @param string $updateId
     * @param array $updateData
     * @return bool
     */
    public function updateCallTable(string $updateId, array $updateData): bool
    {
        if (!$this->isUUID($updateId)) {
            return false;
        }
        return $this->update("call_table", $updateId, $updateData);
    }

    /**
     * @param string $callID
     * @param int $score
     * @return bool
     */
    public function addNewSort(string $callID, int $score): bool
    {
        return $this->redis->zAdd(self::SORT_KEY, $score, self::TABLE_NAME . ":" . $callID);
    }

}