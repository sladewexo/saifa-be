<?php

namespace Cron\WebHook;

use Includes\Database\CallEventStorage;
use Includes\Database\Config;
use Includes\Database\WebHookLogsStorage;
use Services\Webhook\NewCallHook;

class UpdateFailHook
{

    public function handel(): bool
    {
        //todo will back to work on this.
        return true;

        $config = new Config();
        $configData = $config->getConfigAll();
        $webHookLogModel = new WebHookLogsStorage();
        $webhook = new NewCallHook();
        $callModel = new CallEventStorage();

        if (!isset($configData['numRetry']) || empty($configData['status'])) {
            echo "number of retry config did not set. will send retry only 1 time 10 mins after fail.";
        }

        $numRetry = (int)$configData['numRetry'];
        $status = empty($configData['status']) ? 'open' : $configData['status'];
        if ($status === 'close' || $numRetry < 1) {
            echo 'this cron retry fail webhook is disable';
            return true;
        }
        $arrayRetry = $config->getRetryArray($configData['arrayRetry']);
        $failList = $webHookLogModel->getFailSorted();

        $round = 0;
        foreach ($failList as $key => $row) {
            $round++;
            //retry send webhook
            $eventTypeArray = explode("_", $key);
            $callIdArray = explode(":", $key);
            if (empty($callIdArray[1]) && !$webHookLogModel->isUUID($callIdArray[1])) {
                echo $key . "is not have call id for process!";
                continue;
            }
            $callId = $callIdArray[1];

            if (empty($eventTypeArray[0])) {
                echo "event type is not correct format!";
                var_dump($key);
                var_dump($row);
                continue;
            }
            $nextTimeShouldTry = (int)$row['next_retry_time'];
            $now = time();
            if ($nextTimeShouldTry > $now) {
                echo $key . " this key is not time to retry yet " . PHP_EOL;
                //continue;
            }
            if (empty($row['retry'])) {
                $row['retry'] = 1;
            } else {
                $row['retry'] = $row['retry']+1;
            }
            if($row['retry'] > $numRetry){
                //todo think if over retry time in config should remove out of sort key or not
                echo $key . " this key is retry over the config time skip this " . PHP_EOL;
                continue;
            }

            $timeAddForThisTime = $arrayRetry[$row['retry']];

            $eventType = $eventTypeArray[0];
            $sentData = json_decode($row['sent_data'], true);
            $eventCheck = "";
            switch ($eventType) {
                case 'start':
                    $eventCheck = 'start';
                    break;
                case 'end':
                    $eventCheck = 'end';
                    break;
                default:
                    echo $eventType . " is not support yet !";
                    break;
            }

            //echo $eventType . PHP_EOL;*/
            if (!empty($eventCheck)) {
                $urlSendToHook = $configData['web_hook_url_' . $eventCheck] ?? null;
                if (empty($urlSendToHook)) {
                    echo 'urlSendToHook is null skip the process retry';
                    continue;
                }

                if ($webhook->sendHook($urlSendToHook, $eventCheck, $sentData)) {
                    $updateCallTable = $callModel->updateCallTable($callId, ["web_hook_" . $eventCheck . "_already_send" => true]);
                    if (!$updateCallTable) {
                        echo "unable to update to  call table" . PHP_EOL;
                    }
                } else {
                    $timeAddForThisTime = (int)$timeAddForThisTime;
                    $newNextTimeTry = (int)strtotime("+" . $timeAddForThisTime . " minutes", $now); // Add 10 minutes
                    $newNextTimeTry += $round;

                    if (!$this->updateRetryTimeSort($key, $newNextTimeTry, $webHookLogModel)) {
                        break;
                    }
                    if (!$this->updateWebHookLog($row, $key, $newNextTimeTry, $webHookLogModel)) {
                        break;
                    }
                }
            }
        }
        return true;
    }

    /**
     * @param string $key
     * @param int $nextTryTime
     * @param $webHookLogModel
     * @return bool
     */
    private function updateRetryTimeSort(string $key, int $nextTryTime, $webHookLogModel): bool
    {
        if (!$webHookLogModel->removeSort($key)) {
            echo "issue remove sort " . $key;
            return false;
        }
        if (!$webHookLogModel->addNewSort($key, $nextTryTime)) {
            echo "issue add new sort " . $key;
            return false;
        }
        return true;
    }

    /**
     * @param array $row
     * @param string $key
     * @param int $newNextTimeTry
     * @param $webHookLogModel
     * @return bool
     */
    private function updateWebHookLog(array $row, string $key, int $newNextTimeTry, $webHookLogModel): bool
    {
        if (!empty($row['retry_time'])) {
            $retryArray = json_decode($row['retry_time'], true);
            $retryArray[] = $newNextTimeTry;
        } else {
            $retryArray[] = $newNextTimeTry;
        }
        $updateWebHook = $row;
        $updateWebHook['next_retry_time'] = $newNextTimeTry;
        $updateWebHook['retry_time'] = json_encode($retryArray);
//        $updateWebHook['retry'] = $updateWebHook['retry'];
        return $webHookLogModel->updateWebHook($key, $updateWebHook);
    }
}