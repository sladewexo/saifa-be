<?php

namespace Api\Auth;

use Includes\Database\Session;
use Includes\http\returnData;

class Logout
{
    public function handle($logoutData): bool
    {
        try {
            $model = new Session();
            $sessionId = session_id();

            if (empty($sessionId)) {
                die("can't get session id!");
            }
            if (empty($logoutData['user_id'])) {
                returnData::returnJsonFormat('error', null, 'validation_fail_no_user_id_in_request');
            }
            $userIdFromDB = $this->getUserIDFromSessionDB($model, $sessionId);
            if (empty($userIdFromDB)) {
                returnData::returnJsonFormat('error', null, 'user_already_logout');
            }
            if ($userIdFromDB != $logoutData['user_id']) {
                returnData::returnJsonFormat('error', null, 'validation_fail_user_id_not_correct');
            }
            //step 1 remove this session data out of php app
            session_destroy();
            //step 2 remove this session out of db of app
            if ($this->removeSessionFromBase($model, $sessionId)) {
                returnData::returnJsonFormat('ok', 'logout_ok', null);
            } else {
                throw new \ErrorException("unable to save new key!");
            }
//
            return true;
        } catch (\Exception $e) {
            $this->returnJsonFormat('error', null, $e->getMessage());
            return false;
        }
    }

    /**
     * @param $model
     * @param string $id
     * @return string
     */
    private function getUserIDFromSessionDB($model, string $id): string
    {
        $sessionData = $model->getOneSession($id);
        if (empty($sessionData['user_id'])) return '';
        return $sessionData['user_id'];
    }

    /**
     * @param $model
     * @param string $sessionId
     * @param array $userData
     * @return bool
     */
    private function removeSessionFromBase($model, string $sessionId): bool
    {
        return $model->removeSession($sessionId);
    }
}
