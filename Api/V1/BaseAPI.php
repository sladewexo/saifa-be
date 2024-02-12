<?php

namespace API\V1;

class BaseAPI
{
    /**
     * @param int $httpStatus
     * @param array $dataReturn
     * @return void
     */
    protected function returnData(int $httpStatus, array $dataReturn)
    {
        header('Content-Type: application/json');
        http_response_code($httpStatus);
        if ($httpStatus != 200) {
            echo json_encode(['status' => 'ERROR', 'data' => $dataReturn]);
        } else {
            echo json_encode(['status' => 'ok', 'data' => $dataReturn]);
        }
    }

}
