<?php

namespace Api\V1\Config;

use Api\V1\BaseAPI;
use Includes\Database\AccessToken as tokenModel;
use Includes\Database\AccessTokenLog as tokenLogModel;

class AccessToken extends BaseAPI
{
    public function new($data)
    {
        try {
            if (!$this->validation($data)) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format.']);
                return;
            }
            $model = new tokenModel();
            if ($data['allow'] != 'all') {
                $data['allow'] = json_encode($data['allow'], true);
            }

            [$status, $token] = $model->insertToken($data);
            if (!$status) {
                throw new \Exception('unable to save app config');
            }
            $this->returnData(200, ['message' => 'config save successfully', 'config_data' => $model->getConfig($token)]);

        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to connect to update config.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    public function update($data)
    {
        try {
            if (!$this->validation($data)) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format.']);
                return;
            }
            $model = new tokenModel();
            if (empty($data['token']) || !$model->isUUID($data['token'])) {
                $this->returnData(400, ['message' => 'Your request uuid is not in the correct format.']);
                return;
            }

            $token = $data['token'];
            $dataInBase = $model->getConfig($token);
            if (empty($dataInBase)) {
                $this->returnData(400, ['message' => 'not found this token for update ' . $token]);
                return;
            }

            if (!$model->updateToken($token, $data)) {
                throw new \Exception('unable to save app config');
            }
            $this->returnData(200, ['message' => 'config save successfully', 'config_data' => $model->getConfig($token)]);

        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to connect to update config.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    public function getAll($data)
    {
        $model = new tokenModel();
        try {
            $this->returnData(200, ['config_data' => $model->getConfigAll()]);
        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to get data.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    public function getOne($token)
    {
        $model = new tokenModel();
        $modelLog = new tokenLogModel();
        $logToken = $modelLog->getLogFromTokenID($token);
        try {
            $this->returnData(200, [
                'config_data' => $model->getConfig($token),
                'token_used_log' => $logToken
            ]);
        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to connect to update config.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    public function remove($data)
    {
        $model = new tokenModel();
        try {
            if (empty($data['token']) || !$model->isUUID($data['token'])) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format.']);
            }
            $token = $data['token'];
            $updateData = [];
            $updateData['is_delete'] = true;

            if (!$model->updateToken($token, $updateData)) {
                throw new \Exception('unable to remove token');
            }
            if (!$model->removeSort($token)) {
                throw new \Exception('unable to removeSort');
            }
            $this->returnData(200, ['message' => 'token have been remove successfully']);
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
        $requiredKeys = ['expire', 'allow', 'status'];
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