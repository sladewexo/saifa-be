<?php

namespace Services\Webhook;

use Includes\Database\Config;
use Includes\Database\WebHookLogsStorage;

class UpdateInvoiceStatus extends BaseHook
{
    /**
     * @param string $url
     * @param string $eventName
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function sendHook(string $url, string $eventName, array $data): bool
    {
        if (empty($url) && empty($data)) {
            throw new \Exception('unable to send webhook no url data or send data');
        }

        $result = $this->sendDataToWebhook($url, $data);
        $logModel = new WebHookLogsStorage();
        [$insert, $uuid] = $logModel->insertNewLogs($result, $eventName,'', $data);
        if (!$insert) {
            throw new \Exception('unable to save log webhook');
        }
        $logModel->updateCountSendEvent();
        if (!$result) {
            $config = new Config();
            $arrayRetry = $config->getRetryArray();
            $now = time();
            $timeAddForThisTime = (int)$arrayRetry[0];
            $logModel->updateCountSendEvent(true);
            $tenMinutesLater = (int)strtotime("+" . $timeAddForThisTime . " minutes", $now); // Add 10 minutes
            if ($logModel->addNewSort($uuid, $tenMinutesLater)) {
                $webHookData = $logModel->getWebHookLog($uuid);
                $webHookData['next_retry_time'] = $tenMinutesLater;
                $logModel->updateWebHook($uuid, $webHookData);
            }
        }

        if ($result) {
            return true;
        }
        return false;
    }
}