<?php

namespace Api\Auth;

use Includes\Database\Session;
use Includes\Database\Users;
use Includes\Database\UsersLog;
use Includes\Http\returnData;
use Includes\Http\getRequestInfo;

class Login
{
    public function handle($loginData): bool
    {
        try {
            $model = new Session();
            $usersModel = new Users();
            $sessionId = session_id();
            $modelLog = new UsersLog();

            if (empty($sessionId)) {
                die("can't get session id!");
            }

            $userId = $this->getUserIDFromSessionDB($model, $sessionId);
            if (!empty($userId)) {
                returnData::returnJsonFormat('ok', 'already_login');
            } else {
                if (empty($loginData['username']) || empty($loginData['password'])) {
                    returnData::returnJsonFormat('error', null, 'validation_fail');
                }

                $user = $usersModel->getUserByUserName($loginData['username']);
                if (empty($user)) {
                    returnData::returnJsonFormat('error', null, 'not_found_user');
                }
                if (hash('sha256', $loginData['password']) != $user['hash_password']) {
                    $modelLog->addLog($user['user_id'], getRequestInfo::getClientIP(), getRequestInfo::getServerData(),true);
                    returnData::returnJsonFormat('error', null, 'password_not_correct');
                }
                if ($this->saveSessionToBase($model, $sessionId, $user)) {
                    $updateLastLoginTime = $usersModel->updateTimeLoginTime($user['user_id']);
                    if ($updateLastLoginTime) {
                        $modelLog->addLog($user['user_id'], getRequestInfo::getClientIP(), getRequestInfo::getServerData(),false);
                    }
                    returnData::returnJsonFormat('ok', 'password_correct', null);
                } else {
                    throw new \ErrorException("unable to save new key!");
                }
            }
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
    private function saveSessionToBase($model, string $sessionId, array $userData): bool
    {
        $secsAdded = (24 * 60 * 60);
        $expireTime = time() + $secsAdded;
        $SessionData = [
            'user_id' => $userData['user_id'],
            'last_login_time' => time(),
            'time_out' => $expireTime
        ];

        return $model->saveNewSession($SessionData, $sessionId, $secsAdded);
    }
}
