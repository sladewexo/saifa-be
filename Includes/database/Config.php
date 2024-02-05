<?php

namespace Includes\Database;

class Config extends BaseModel
{
    const APP_CONFIG_TABLE = "app_config";
    const default_retry_time = [10, 20, 30, 40, 50, 60];

    /**
     * @param string $key
     * @return string
     */
    public function getConfig(string $key): string
    {
        if (empty($key))
            return "";
        $config = $this->select(self::APP_CONFIG_TABLE, "");
        return $config[$key];
    }

    public function getConfigAll(): array
    {
        return $config = $this->select(self::APP_CONFIG_TABLE, "");
    }

    /**
     * @param array $config
     * @return bool
     */
    public function saveAppConfig(array $config): bool
    {
        $configDB = $this->select(self::APP_CONFIG_TABLE, "");
        if (!empty($configDB)) {
            $configDB = array_merge($configDB, $config);
            return $this->update(self::APP_CONFIG_TABLE, "", $configDB);
        }
        return $this->insertNewRow(self::APP_CONFIG_TABLE, "", $config, true);
    }

    /**
     * @param string $arrayRetryStr
     * @return array
     */
    public function getRetryArray(string $arrayRetryStr = ''): array
    {
        if (empty($arrayRetryStr)) {
            $configData = $this->getConfigAll();
            $arrayRetryStr = $configData['arrayRetry'];
        }

        try {
            $arrayRetry = json_decode($arrayRetryStr, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($arrayRetry)) {
                throw new \Exception("JSON decode error: " . json_last_error_msg());
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            $arrayRetry = self::default_retry_time;
        }
        return $arrayRetry;
    }

}