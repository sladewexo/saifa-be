<?php

namespace Api\V1\Config;

use Api\V1\BaseAPI;
use Services\External\RateService;

class CurrencyRate extends BaseAPI
{
    public function get()
    {
        try {
            $rateService = new RateService();
            $usdRate = $rateService->callGetRateExternalAPI('usd');
            $return['sat_usd'] = $rateService->formatRateArrayToInt($usdRate, 'binance') ?? -1;

            $usdRate = $rateService->callGetRateExternalAPI('thb');
            $return['sat_thb'] = $rateService->formatRateArrayToInt($usdRate, 'coingecko') ?? -1;
            $return['time_load_rate'] = time();
            $return['time_load_rate_date_time'] = date("Y-m-d H:i:s");
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
}