<?php

namespace Services;

require '../vendor/autoload.php';
require_once __DIR__ . '/../includes/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Includes\Database\Config;
use Services\Webhook\UpdateInvoiceStatus as WebHookService;
use Includes\Database\Invoice as InvoiceModel;

class LNDConnection
{
    private $hostname, $macaroon, $debugMode;

    public function __construct($hostname, $macaroon, $debugMode = false)
    {
        $this->hostname = $hostname;
        $this->macaroon = $macaroon;
        $this->debugMode = $debugMode;
    }

    public function listenInvoice($invoiceRHash)
    {
        $client = new Client([
            'base_uri' => $this->hostname,
            'verify' => false,
            'headers' => [
                'Grpc-Metadata-macaroon' => $this->macaroon
            ]
        ]);

        try {
            $timeOut = $_ENV['LND_EXPIRY_INVOICE_TIME_SECS'];

            $invoiceRHashSafe = strtr($invoiceRHash, '+/', '-_');
            /**
             * If the r_hash contains a /, it will cause an error when using the GET method to check the invoice.
             * To prevent this, change the / in the r_hash string to _. This modification won't alter the meaning of the Base64 encoded data."
             */

            if ($this->debugMode) file_put_contents('debug.log', 'before call ' . $invoiceRHashSafe . 'will wait ' . $timeOut . PHP_EOL, FILE_APPEND);
            $response = $client->request('GET', "/v2/invoices/subscribe/$invoiceRHashSafe");
            $body = $response->getBody();
            $lines = '';
            $lastResultArray = [];
            while (!$body->eof()) {
                $lines .= $body->read(1024);
            }

            $pattern = '/(?=\{"result":)/';
            $resultAll = preg_split($pattern, $lines, -1, PREG_SPLIT_NO_EMPTY);

            if (is_array($resultAll)) {
                $lastResult = end($resultAll);
                $lastResultArray = json_decode($lastResult, true);
            }
            if ($this->debugMode) file_put_contents('debug.log', 'hash:' . $lastResultArray['result']['r_hash'] . ',state:' . $lastResultArray['result']['state'] . PHP_EOL, FILE_APPEND);

            $config = new Config;
            $urlWebhook = $config->getConfig("web_hook_url_update_invoice");

            $webhookService = new WebHookService();
            $webhookService->sendHook($urlWebhook, 'invoice_status', $lastResultArray);

            $model = new InvoiceModel();
            $invoiceData = $model->getInvoiceFromRHash($invoiceRHash);
            if (!empty($invoiceData['invoice_id'])) {
                if ($model->updateInvoiceStatus($invoiceData['invoice_id'], $lastResultArray['result']['state'])) {
                    if ($this->debugMode) file_put_contents('debug.log', 'done save status ' . $invoiceData['invoice_id'] . PHP_EOL, FILE_APPEND);
                }
            } else {
                throw new \Exception('unable to found invoice id from hash ' . $invoiceRHash);
            }


        } catch (GuzzleException $e) {
            file_put_contents('error.log', print_r($e->getMessage(), true), FILE_APPEND);
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

if ($argc > 1) {
    $invoiceRHash = $argv[1];
} else {
    echo "No parameter provided unable to check invoice.\n";
    die();
}

$hostname = $_ENV['LND_HOST'];;
$macaroon = $_ENV['LND_MARCAROON'];
$debugMode = $_ENV['DEBUG_MODE'] ?? false;

$listener = new \Services\LNDConnection($hostname, $macaroon, $debugMode); // Pass the attempt variable by reference.
$listener->listenInvoice($invoiceRHash);