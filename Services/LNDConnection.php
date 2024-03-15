<?php

namespace Services;

require_once '../vendor/autoload.php';
require_once  '../Includes/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Services\Webhook\UpdateInvoiceStatus as WebHookService;
use Includes\Database\Invoice as InvoiceModel;

class LNDConnection
{
    private $hostname, $macaroon, $debugMode;

    /**
     * @param $hostname
     * @param $macaroon
     * @param $debugMode
     */
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
            $this->logDebug('debug', 'before call ' . $invoiceRHashSafe . 'will wait ' . $timeOut );

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
            $this->logDebug('debug', 'hash:' . $lastResultArray['result']['r_hash'] . ',state:' . $lastResultArray['result']['state']);

            $model = new InvoiceModel();
            $invoiceData = $model->getInvoiceFromRHash($invoiceRHash);
            if (!empty($invoiceData['invoice_id'])) {
                $this->logDebug('debug', 'state:' . $lastResultArray['result']['state'] . $invoiceData['invoice_id']);
                if ($model->updateInvoiceStatus($invoiceData['invoice_id'], $lastResultArray['result']['state'])) {
                    $this->logDebug('debug', 'done save status ' . $invoiceData['invoice_id']);
                } else {
                    $this->logDebug('debug', 'update invoice status not work ');
                }

                sleep(2);
                $invoiceData = $model->getInvoiceFromRHash($invoiceRHash);
                if(empty($invoiceData['is_manual_cancel']) && !$invoiceData['is_manual_cancel']) {
                    $lastUpdateInvoice = $model->getInvoice($invoiceData['invoice_id']);
                    $webhookService = new WebHookService();
                    $webhookService->sendHook('', 'invoice_status', $lastUpdateInvoice);
                }

            } else {
                throw new \Exception('unable to found invoice id from hash ' . $invoiceRHash);
            }

        } catch (GuzzleException $e) {
            $this->logDebug('error', print_r($e->getMessage(), true));
            echo "Error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * @param string $mode
     * @param string $textLog
     * @return void
     */
    private function logDebug(string $mode = 'debug', string $textLog = '')
    {
        if ($mode == 'debug' && !$this->debugMode) return;
        $logFile = 'error.log';
        if ($mode == 'debug') $logFile = 'debug.log';
        if (!file_exists($logFile) || !is_writable($logFile)) {
            @touch($logFile);
            @chmod($logFile, 0666);
        }
        file_put_contents($logFile, $textLog . PHP_EOL, FILE_APPEND);
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

$hostname = $_ENV['LND_HOST'];
$macaroon = $_ENV['LND_MARCAROON'];
$debugMode = $_ENV['DEBUG_MODE'] ?? false;

$listener = new \Services\LNDConnection($hostname, $macaroon, $debugMode); // Pass the attempt variable by reference.
$listener->listenInvoice($invoiceRHash);