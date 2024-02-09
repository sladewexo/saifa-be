<?php

namespace Api\V1\Config;

use API\V1\BaseAPI;
use Includes\Database\Config;

class ShopConfig extends BaseAPI
{
    public function save($data)
    {
        try {
            if (!$this->validation($data)) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format.']);
                return;
            }
            $model = new Config();
            $data['arrayStop'] = json_encode($data['arrayStop'],true);

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
        $requiredKeys = ['arrayStop'];
        $keysPresent = [];
        foreach ($requiredKeys as $key) {
            if (!empty($data[$key])) {
                $keysPresent[] = $key;
            }
        }
        if (empty($keysPresent)) {
            return false;
        }
        return true;
    }
}