<?php

namespace Api\V1\Users;

use API\V1\BaseAPI;
use Includes\Database\Users;
use Includes\Database\UsersLog;

class UserHandler extends BaseAPI
{

    public function getAll()
    {
        $model = new Users();
        try {
            $this->returnData(200, ['users' => $model->getAll()]);
        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to get data.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    public function getOne($userId)
    {
        $model = new Users();
        $modelLog = new UsersLog();
        $userLog = $modelLog->getLogFromUserID($userId);
        try {
            $this->returnData(200, [
                'user' => $model->getUserByID($userId),
                'used_log' => $userLog
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

    public function new($data)
    {
        try {
            if (!$this->validation(['username', 'status', 'password'], $data)) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format.']);
                return;
            }
            $model = new Users();
            $checkUser = $model->getUserByUserName($data['username']);
             if (!empty($checkUser)) {
                 $this->returnData(400, ['message' => 'this username already exists in system.']);
                 return;
             }
            [$status, $userID] = $model->insertUser($data);
            if (!$status) {
                throw new \Exception('unable to save user');
            }
            $this->returnData(200, ['message' => 'config save successfully', 'config_data' => $model->getUserByID($userID)]);

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
            if (!$this->validation(['user_id'], $data)) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format.']);
                return;
            }
            $userID = $data['user_id'];
            $model = new Users();
            if (!$model->updateUser($userID, $data)) {
                throw new \Exception('unable to save user');
            }
            $this->returnData(200, ['message' => 'config save successfully', 'user' => $model->getUserByID($userID)]);

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
        $model = new Users();
        try {
            if (empty($data['user_id']) || !$model->isUUID($data['user_id'])) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format.']);
            }

            $userId = $data['user_id'];
            $userData = $model->getUserByID($userId);
            if (!empty($userData['is_admin']) && $userData['is_admin']) {
                $this->returnData(400, ['message' => 'unable to remove admin user with normal flow.']);
            }
            $updateData = [];
            $updateData['is_delete'] = true;

            if (!$model->updateUser($userId, $updateData)) {
                throw new \Exception('unable to remove user');
            }
            if (!$model->removeSort($userId)) {
                throw new \Exception('unable to removeSort');
            }
            $this->returnData(200, ['message' => 'user have been remove successfully']);
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
     * @param array $requiredKeys
     * @param array $data
     * @return bool
     */
    private function validation(array $requiredKeys, array $data): bool
    {
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
