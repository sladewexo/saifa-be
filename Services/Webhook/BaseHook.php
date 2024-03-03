<?php

namespace Services\Webhook;

use Includes\Database\Config;
use Includes\Database\WebHookLogsStorage;

class BaseHook
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

        if (!empty($data['call_id'])) {
            [$insert, $uuid] = $logModel->insertNewLogs($result, $eventName, $data['call_id'], $data);
        }

        if (!$result && !empty($data['call_id'])) {
            //save sort for retry case webhook send fail.
            $config = new Config();
            $arrayRetry = $config->getRetryArray();
            $now = time();
            $timeAddForThisTime = (int)$arrayRetry[0];
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

    /**
     * @param string $url
     * @param array $data
     * @return bool
     */
    protected function sendDataToWebhook(string $url, array $data)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }
        return false;
    }
}