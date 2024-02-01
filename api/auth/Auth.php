<?php

namespace Api\Auth;

use Includes\Database\Session;
use Includes\Database\AccessToken;
use Includes\Database\AccessTokenLog;
use Includes\Http\getRequestInfo;
use Includes\Http\returnData;

class Auth
{
    private $accessToken = '';

    public function isAuthenticated(string $httpMethod): bool
    {
        //todo think about should used JWT token instant of UUID or not.
        $token = $this->getTokenFromRequest();
        //for access token is can be used only read data only
        if (!empty($token) && $httpMethod == 'GET') {
            $workToken = $this->checkTokenStillActive($token);
            if ($workToken) $this->updateTimeUsedToken();
            return $workToken;
        } else {
            $sessionId = session_id();
            $model = new Session();
            $sessionData = $model->getOneSession($sessionId);
            if (empty($sessionData['user_id'])) return false;
            return true;
        }
    }

    public function checkAuth()
    {
        $sessionId = session_id();
        if (empty($sessionId)) {
            die("session issue contact admin");
        }
        $model = new Session();
        $sessionData = $model->getOneSession($sessionId);

        if (!empty($sessionData['user_id'])) {
            $returnAuthData = [
                'userUid' => $sessionData['user_id'],
                'lastLoginTime' => $sessionData['last_login_time'] ?? null
            ];
            returnData::returnJsonFormat('ok', $returnAuthData, null, 'auth');
        } else {
            if ($this->checkToken()) {
                $returnAuthData = [
                    'userUid' => $this->accessToken,
                    'lastLoginTime' => time()
                ];
                $this->updateTimeUsedToken();
                returnData::returnJsonFormat('ok', $returnAuthData, null, 'auth');
            }

            returnData::returnJsonFormat('ok', ['userUid' => -1], null, 'auth');
        }
    }

    private function checkToken()
    {
        $this->accessToken = $this->getTokenFromRequest();
        return $this->checkTokenStillActive($this->accessToken);
    }

    /**
     * @return bool
     */
    private function updateTimeUsedToken(): bool
    {
        try {
            if (empty($this->accessToken)) return false;
            $accessTokenModel = new AccessToken();
            $accessTokenLogModel = new AccessTokenLog();
            if (!$accessTokenModel->updateTimeUsedToken($this->accessToken)) {
                return false;
            }

            $json = json_encode($_SERVER, true);
            if (!$accessTokenLogModel->addLog($this->accessToken, getRequestInfo::getClientIP(), getRequestInfo::getServerData())) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            //todo add to fail log system but still let user be able to call
        }
    }

    private function getTokenFromRequest(): string
    {
        $headers = getallheaders();
        $accessToken = "";

        if (isset($headers['Authorization'])) {
            $accessToken = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            // Sometimes headers can be in lowercase
            $accessToken = $headers['authorization'];
        }

        if (preg_match('/Bearer\s(\S+)/', $accessToken, $matches)) {
            $accessToken = $matches[1];
        }
        return $accessToken;
    }

    /**
     * @param string $accessToken
     * @return bool
     */
    private function checkTokenStillActive(string $accessToken): bool
    {
        if (empty($accessToken)) {
            return false;
        }
        $accessTokenModel = new AccessToken();
        $accessTokenData = $accessTokenModel->getConfig($accessToken);
        if (empty($accessTokenData)) {
            return false;
        }
        $this->accessToken = $accessToken;

        if (empty($accessTokenData['expire'])) {
            return false;
        }
        if ($accessTokenData['expire'] > 1 && $accessTokenData['expire'] < time()) {
            return false;
        }
        if (empty($accessTokenData['status']) || ($accessTokenData['status'] != 'open')) {
            return false;
        }

        if (!empty($accessTokenData['allow']) || (strtolower($accessTokenData['allow']) != 'all')) {
            $allowList = json_decode($accessTokenData['allow']);
            if (is_array($allowList)) {
                if (in_array(getRequestInfo::getClientIP(), $allowList)) {
                    return true;
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    public function reLoadAuth()
    {
        $sessionId = session_id();
        if (empty($sessionId)) {
            die("session issue contact admin");
        }
        $model = new Session();
        $sessionData = $model->getOneSession($sessionId);
        if (empty($sessionData)) {
            returnData::returnJsonFormat('ok', ['userUid' => -1], null, 'auth');
        }
        $oneHrAsSec = (60 * 60);
        $newExpireTime = $sessionData['time_out'] + $oneHrAsSec;
        if (!empty($sessionData['user_id'])) {
            $this->updateSessionInBase($model, $sessionId, $sessionData, $oneHrAsSec);
            $returnAuthData = [
                'userUid' => $sessionData['user_id'],
                'newExpireTime' => $newExpireTime
            ];
            returnData::returnJsonFormat('ok', $returnAuthData, null, 'auth');
        } else {
            returnData::returnJsonFormat('ok', ['userUid' => -1], null, 'auth');
        }
    }

    /**
     * @param $model
     * @param string $sessionId
     * @param array $sessionData
     * @param int $addTime
     * @return bool
     */
    private function updateSessionInBase($model, string $sessionId, array $sessionData, int $addTime): bool
    {
        $expireTime = $sessionData['time_out'] + $addTime;
        $SessionData = [
            'time_out' => $expireTime
        ];
        $expireTimeRedis = ($expireTime - time());
        return $model->updateSessionTimeOut($SessionData, $sessionId, $expireTimeRedis);
    }
}
