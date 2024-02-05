<?php

namespace Api\V1\Config;

use API\V1\BaseAPI;
use Includes\Database\Config;

class WebhookConfig extends BaseAPI
{
    public function save($data)
    {
        try {
            if (!$this->validation($data)) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format. need to add http:// in url format too ']);
                return;
            }
            $model = new Config();
            if (!$model->saveAppConfig($data)) {
                throw new \Exception('unable to save app config');
            }
            $this->returnData(200, ['message' => 'config save successfully', 'config_data' => $model->getConfigAll()]);

        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to connect to update config.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    public function get()
    {
        try {
            $model = new Config();
            $config = $model->getConfigAll();
            $return['config_data'] = $config;
            $this->returnData(200, $return);
        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to read config.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    /**
     * Validates the input data.
     *
     * @param array $data
     * @return bool
     */
    private function validation(array $data): bool
    {
//        $requiredKeys = ['web_hook_url_start', 'web_hook_url_end', 'web_hook_url_newaction'];
        $requiredKeys = ['web_hook_url_update_invoice'];
        $keysPresent = [];
        foreach ($requiredKeys as $key) {
            if (!empty($data[$key])) {
                $keysPresent[] = $key;
            }
        }
        if (empty($keysPresent)) {
            return false;
        }

        foreach ($data as $input) {
            if (is_string($input) && ($input !== null) && (strpos($input, 'http://') === 0 || strpos($input, 'https://') === 0)) {
                return true;
            }
        }
        return false;
    }
}