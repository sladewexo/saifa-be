<?php

namespace Cron\Lnd;

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Includes\Database\Invoice as InvoiceModel;
use Services\Webhook\UpdateInvoiceStatus as WebHookService;

class CheckInvoiceStatusCron
{
    public function handel(): bool
    {
        $invoiceModel = new InvoiceModel();
        $activeInvoice = $invoiceModel->getActiveInvoice(100, 1, 'desc');
        foreach ($activeInvoice as $row) {
            if (!empty($row['r_hash'] && $invoiceModel->isUUID($row['invoice_id']))) {
                [$statusChange, $statusName] = $this->checkWithoutWaitInvoiceStatus($row);
                if ($statusChange) {
                    if (!$invoiceModel->updateInvoiceStatus($row['invoice_id'], $statusName)) {
                        echo 'unable to updateInvoiceStatus ' . $row['invoice_id'];
                    }

                    //call hook
                    $lastUpdateInvoice = $invoiceModel->getInvoice($row['invoice_id']);
                    $webhookService = new WebHookService();
                    $webhookService->sendHook('', 'invoice_status', $lastUpdateInvoice);
                }
            }
        }
        return true;
    }

    /**
     * @param array $invoiceData
     * @return array
     * @throws GuzzleException
     */
    private function checkWithoutWaitInvoiceStatus(array $invoiceData): array
    {
        $dotenv = Dotenv::createImmutable('./../');
        $dotenv->load();
        $hostname = $_ENV['LND_HOST'];;
        $macaroon = $_ENV['LND_MARCAROON'];

        $client = new Client([
            'base_uri' => $hostname,
            'verify' => false,
            'headers' => [
                'Grpc-Metadata-macaroon' => $macaroon
            ]
        ]);

        $invoiceRHashBinary = base64_decode($invoiceData['r_hash'], true);
        $invoiceRHashHex = bin2hex($invoiceRHashBinary);

        $response = $client->request('GET', "/v1/invoice/$invoiceRHashHex");

        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getBody();
            $invoiceDetails = json_decode($body, true);

            if ($statusCode == 200) {
                // Check the invoice status
                if (isset($invoiceDetails['state'])) {
//                    var_dump($invoiceDetails);
                    $nonActiveStatus = ['SETTLED', 'CANCELED', 'ACCEPTED'];
                    if (in_array($invoiceDetails['state'], $nonActiveStatus)) {
                        return [true, $invoiceDetails['state']];
                    }

                } else {
                    echo "Failed to retrieve invoice details.\n";
                }
            } else {
                echo "HTTP Request failed with status $statusCode.\n";
            }
        } catch (GuzzleException $e) {
            echo 'Request failed: ' . $e->getMessage();
            return [false, ''];
        }
        return [false, ''];
    }
}

?>