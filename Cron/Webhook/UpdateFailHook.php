<?php

namespace Cron\Webhook;

use Includes\Database\Config;
use Includes\Database\WebHookLogsStorage;
use Services\Webhook\UpdateInvoiceStatus;

class UpdateFailHook
{
    /**
     * @param $debug
     * @return bool
     * @throws \Exception
     */
    public function handel($debug = false): bool
    {
        $config = new Config();
        $configData = $config->getConfigAll();
        $webHookLogModel = new WebHookLogsStorage();
        $webhook = new UpdateInvoiceStatus();
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
        $failList = $webHookLogModel->getWebHookFailToSendList($debug);

        if (count($failList) < 1) {
            echo PHP_EOL . 'no fail list need to retry as this moment. time now is ' . time() . PHP_EOL;
            return true;
        }
        $round = 0;
        foreach ($failList as $key => $row) {
            $round++;
            //retry send webhook
            $eventTypeArray = explode("_", $key);
            $uuidArray = explode(":", $key);
            if (empty($uuidArray[1]) && !$webHookLogModel->isUUID($uuidArray[1])) {
                echo $key . "is not have call id for process!";
                continue;
            }
            $uuidOfWebHookLog = $uuidArray[1];
            if (empty($eventTypeArray[0])) {
                echo "event type is not correct format!" . PHP_EOL;
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
                $row['retry'] = $row['retry'] + 1;
            }
            if ($row['retry'] > $numRetry) {
                //todo think if over retry time in config should remove out of sort key or not
                //if($debug) echo $key . " this key is retry over the config time skip this " . PHP_EOL;
                continue;
            }

            $timeAddForThisTime = $arrayRetry[$row['retry']];
            //$eventType = $eventTypeArray[0];
            $sentData = json_decode($row['sent_data'], true);
            $urlSendToHook = '';
            $eventCheck = 'invoice_status';

            $retrySendWebHookSuccess = $webhook->sendHook($urlSendToHook, $eventCheck, $sentData, true);

            if ($retrySendWebHookSuccess) {
                if ($debug) {
                    echo "retry sent Success : $key";
                }
                //todo may add webhook send fail or not on invoice table too
                //remove out of webhook fail list
                $removeFailHook = $webHookLogModel->removeWebHookFailToSendList($key);
                if (!$removeFailHook) {
                    echo "unable to update fail hook key $key" . PHP_EOL;
                }
                //update count since webhook sent is not fail anymore
                $updateCount = $webHookLogModel->updateCountAfterRetryNotFail();
                if (!$updateCount) {
                    echo "unable to update count fail hook key $key" . PHP_EOL;
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
        if (!$webHookLogModel->removeWebHookFailToSendList($key)) {
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