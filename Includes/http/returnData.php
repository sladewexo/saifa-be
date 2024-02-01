<?php

namespace Includes\Http;

class returnData
{
    /**
     * @param string $status
     * @param $message
     * @param string|null $errorCode
     * @param string $messageName
     * @return void
     */
    public static function returnJsonFormat(string $status, $message = null, string $errorCode = null, string $messageName = 'message')
    {
        echo json_encode(['status' => $status, $messageName => $message, 'error_code' => $errorCode]);
        exit();
    }

    /**
     * @param array $datKey
     * @param array $otherKey
     * @return void
     */
    public static function returnJsonFormatManyKey(array $datKey, array $otherKey)
    {
        $returnData = ['status' => 'ok', 'data' => $datKey, 'error_code' => null];
        $returnData += $otherKey;
        echo json_encode($returnData);
        exit();
    }

}