<?php

namespace Services\External;

use GuzzleHttp\Client;
use Dotenv\Dotenv;

class RateService
{
    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
        $dotenv->load();
        $this->usd_rate_url = $_ENV['USD_BTC_URL_GET_RATE'] ?? 'https://api.binance.com/api/v3/ticker/price?symbol=BTCUSDT';
        $this->thb_rate_url = $_ENV['THB_BTC_URL_GET_RATE'] ?? 'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=thb';
        $this->debug = (bool)$_ENV['DEBUG_MODE'];
    }

    /**
     * @param string $currency
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function callGetRateExternalAPI(string $currency = ''): array
    {
        try {
            $client = new Client();
            if (empty($currency)) {
                die('call callGetRateExternalAPI with null currency');
            }
            if ($currency == 'usd') {
                $url = $this->usd_rate_url;
            } elseif ($currency == 'thb') {
                $url = $this->thb_rate_url;
            } else {
                die('call callGetRateExternalAPI with null currency');
            }
            $response = $client->get($url . $this->cancelInvoicesEndPoint, [
                'headers' => [],
                'verify' => true,
            ]);
            if ($response->getStatusCode() != 200) {
                if ($this->debug === true) var_dump($response->getBody()->getContents());
                return [];
            }
            $jsonDataToArray = (array)json_decode($response->getBody()->getContents(), true);
            return $jsonDataToArray;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($this->debug === true) {
                echo "Request Exception: " . $e->getMessage();
                if ($e->hasResponse()) {
                    echo "\nResponse: " . $e->getResponse()->getBody();
                }
            }
            return [];
        }
    }

    /**
     * @param array $rate
     * @param string $mode
     * @return int
     */
    public function formatRateArrayToInt(array $rate, string $mode): int
    {
        if (empty($rate)) return -1;
        $oneBtcInSats = 100000000;
        $satsPerUnit = -1;
        if ($mode == 'coingecko') {
            if (!isset($rate['bitcoin']) || empty($rate['bitcoin'])) return -1;
            $rateInt = array_pop($rate['bitcoin']);
            $satsPerUnit = $oneBtcInSats / $rateInt;
        } else if ($mode == 'binance') {
            if (!isset($rate['price']) || empty($rate['price'])) return -1;
            $rateInt = $rate['price'];
            $satsPerUnit = $oneBtcInSats / $rateInt;
        }
        return $satsPerUnit;
    }
}