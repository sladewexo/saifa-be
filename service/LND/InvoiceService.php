<?php

namespace Services\LND;

use GuzzleHttp\Client;
use Dotenv\Dotenv;

class InvoiceService
{
    protected $debug = false;
    private $ฺnewInvoicesEndPoint = '/v1/invoices';
    private $cancelInvoicesEndPoint = '/v2/invoices/cancel';
    private $host;
    private $macaroon;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
        $dotenv->load();
        $this->host = $_ENV['LND_HOST'];;
        $this->macaroon = $_ENV['LND_MARCAROON'];
        $this->debug = (bool)$_ENV['DEBUG_MODE'];
        $this->expiry = $_ENV['LND_EXPIRY_INVOICE_TIME_SECS'] ?? ((60 * 60) * 24);
        if (empty($this->host) || empty($this->macaroon)) {
            die('not setup lnd config in env stop program now');
        }
    }

    /**
     * @param string $invoiceRHash
     * @return bool
     */
    public function runCheckingInvoiceRealTime(string $invoiceRHash): bool
    {
        try {
            $fullCommandPHP = '/usr/local/opt/php@7.4/bin/php';
            $command = "cd ../services && $fullCommandPHP LNDConnection.php " . $invoiceRHash . " > /dev/null 2>&1 &";
            //shell_exec($command);

            $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin
                1 => array("pipe", "w"),  // stdout
                2 => array("file", "/dev/null", "a") // stderr
            );
            $process = proc_open($command, $descriptorspec, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                fclose($pipes[1]);
                proc_close($process);
            }
        } catch (\Exception $e) {
            echo "Request Exception: " . $e->getMessage();
            return false;
        }
        return true;
    }

    /**
     * @param string $invoiceRHash
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function cancelInvoice(string $invoiceRHash): bool
    {
        if (empty($invoiceRHash)) return false;
        $client = new Client();
        $data = [
            'payment_hash' => $invoiceRHash
        ];
        try {
            $response = $client->post($this->host . $this->cancelInvoicesEndPoint, [
                'headers' => [
                    'Grpc-Metadata-macaroon' => $this->macaroon,
                ],
                'json' => $data,
                'verify' => false,
                'debug' => $this->debug,
            ]);
            if ($response->getStatusCode() == 200) {
//                var_dump($response->getBody()->getContents());
                return true;
            }
            return false;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            echo "Request Exception: " . $e->getMessage();
            if ($e->hasResponse()) {
                echo "\nResponse: " . $e->getResponse()->getBody();
            }
            return false;
        }
        return false;
    }

    /**
     * @param int $amount
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function makeNewInvoices(int $amount): array
    {
        $client = new Client();
        $data = [
            'value' => $amount, // Amount in satoshis
            'expiry' => $this->expiry
        ];
        try {
            $response = $client->post($this->host . $this->ฺnewInvoicesEndPoint, [
                'headers' => [
                    'Grpc-Metadata-macaroon' => $this->macaroon,
                ],
                'json' => $data,
                'verify' => false,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            echo "Request Exception: " . $e->getMessage();
            if ($e->hasResponse()) {
                echo "\nResponse: " . $e->getResponse()->getBody();
            }
            exit;
        }

        $body = $response->getBody();
        $result = json_decode($body, true);
        if (empty($result['payment_request']) || empty($result['r_hash'])) return [];
        return $result;
    }
}