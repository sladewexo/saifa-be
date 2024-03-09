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
     *
     * now this webhook have been call with 3 possible way
     * 1 manual cancel on UI
     * 2 auto cancel by every 5min cron
     * 3 auto cancel by sync process real time update process.
     */
    public function sendHook(string $url = '', string $eventName, array $data): bool
    {
        if (empty($data)) {
            throw new \Exception('unable to send webhook no url data or send data');
        }
        $config = new Config;

        if (empty($url)) {
            $url = $config->getConfig("web_hook_url_update_invoice");
        }

        if (empty($url)) {
            return false;
        }

        $header = [];
        $authOption = $config->getConfig("auth_option") ?? '';
        if ($authOption == 'customized') {
            $headerKey = $config->getConfig("header_key") ?? '';
            $customizedApiKey = $config->getConfig("customized_api_key") ?? '';
            if(!empty($headerKey)) {
                $header[$headerKey] = $customizedApiKey;
            }
        } else if ($authOption == 'bearer') {
            $bearer = $config->getConfig("bearer") ?? '';
            if(!empty($bearer)) {
                $header['Authorization'] = 'Bearer ' . $bearer;
            }
        }

        $result = $this->sendDataToWebhook($url, $data, $header);
        $logModel = new WebHookLogsStorage();
        [$insert, $uuid] = $logModel->insertNewLogs($result, $eventName, '', $data);
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