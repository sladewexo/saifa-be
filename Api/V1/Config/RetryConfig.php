<?php

namespace Api\V1\Config;

use Api\V1\BaseAPI;
use Includes\Database\Config;

class RetryConfig extends BaseAPI
{
    public function save($data)
    {
        try {
            if (!$this->validation($data)) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format. need to add http:// in url format too ']);
                return;
            }
            $model = new Config();
            $data['arrayRetry'] = json_encode($data['arrayRetry'],true);
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

    /**
     * Validates the input data.
     *
     * @param array $data
     * @return bool
     */
    private function validation(array $data): bool
    {
        $requiredKeys = ['numRetry', 'arrayRetry', 'status'];
        $keysPresent = [];
        foreach ($requiredKeys as $key) {
            if (empty($data[$key])) {
                return false;
            }
        }
        if (empty($keysPresent)) {
            return true;
        }
    }
}