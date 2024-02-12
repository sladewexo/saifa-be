<?php

namespace Api\V1\Invoice;

use Api\V1\BaseAPI;
use Includes\Database\Invoice as InvoiceModel;

use Includes\Http\getRequestInfo;
use Services\LND\InvoiceService;

class Invoice extends BaseAPI
{
    public function new($data)
    {
        try {
            if (!$this->validation($data)) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format.']);
                return;
            }
            $lndService = new InvoiceService();
            $invoicesQRCode = $lndService->makeNewInvoices($data['amount']);
            if (!empty($invoicesQRCode)) {
                $data['qr_code'] = $invoicesQRCode['payment_request'];
                $data['r_hash'] = $invoicesQRCode['r_hash'];
                $data['payment_addr'] = $invoicesQRCode['payment_addr'];
            } else {
                throw new \Exception('enable to make qr code');
            }

            $model = new InvoiceModel();
            [$status, $InvoiceID] = $model->insertInvoice($data);
            if (!$status) {
                throw new \Exception('unable to save app config');
            }
            $invoiceDataFromBase = $model->getInvoiceDataById($InvoiceID);
            $lndService->runCheckingInvoiceRealTime($invoiceDataFromBase['r_hash']);
            $this->returnData(200, ['message' => 'Invoice save successfully', 'Invoice' => $invoiceDataFromBase]);

        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to connect to update config.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    public function update($data)
    {
        try {
            if (!$this->validation($data)) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format.']);
                return;
            }
            $model = new tokenModel();
            if (empty($data['token']) || !$model->isUUID($data['token'])) {
                $this->returnData(400, ['message' => 'Your request uuid is not in the correct format.']);
                return;
            }

            $token = $data['token'];
            $dataInBase = $model->getConfig($token);
            if (empty($dataInBase)) {
                $this->returnData(400, ['message' => 'not found this token for update ' . $token]);
                return;
            }

            if (!$model->updateToken($token, $data)) {
                throw new \Exception('unable to save app config');
            }
            $this->returnData(200, ['message' => 'config save successfully', 'config_data' => $model->getConfig($token)]);

        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to connect to update config.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    public function getAll($data)
    {
        $model = new InvoiceModel();
        try {
            [$maxPerPage, $page, $mode] = getRequestInfo::getBasicPage();
            $invoiceData = $model->getInvoiceAll($maxPerPage, $page, $mode);
            $count = count($invoiceData);
            if (empty($readParam['search'])) {
                $count = $model->countInvoice();
            }
            $this->returnData(200, ['count' => $count, 'invoices' => $invoiceData]);
        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to get data.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    public function getOne($uuid)
    {
        $model = new InvoiceModel();
        try {
            $this->returnData(200, [
                'invoice' => $model->getInvoice($uuid),
                //todo add payment log event here
                'invoice_payment_log' => []
            ]);
        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to connect to update config.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    public function cancel($data)
    {
        $model = new InvoiceModel();
        try {
            if (empty($data['invoice_id']) || !$model->isUUID($data['invoice_id'])) {
                $this->returnData(400, ['message' => 'Your request is not in the correct format.']);
            }

            $invoiceId = $data['invoice_id'];
            $invoiceData = $model->getInvoice($invoiceId);
            if (empty($invoiceData['r_hash'])) {
                $this->returnData(400, ['message' => 'not found hash of invoice id : ' . $invoiceId . ' unable to cancel invoice']);
                return;
            }
            $paymentHash = $invoiceData['r_hash'];
            $lndService = new InvoiceService();
            $sendCancelRequestOK = $lndService->cancelInvoice($paymentHash);
            if (!$sendCancelRequestOK) {
                throw new \Exception('unable to cancel Invoice issue from lnd');
            }
            $updateData['is_manual_cancel'] = true;
            if (!$model->updateInvoice($invoiceId, $updateData)) {
                throw new \Exception('unable to cancel Invoice issue from redis');
            }

            $this->returnData(200, ['message' => 'invoice have been cancel successfully']);

        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to connect to update config.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }

    /**
     * Validates the input data.
     *
     * @param array $data
     * @return bool
     */
    private function validation(array $data): bool
    {
        $requiredKeys = ['address_name', 'amount'];
        $keysPresent = [];
        foreach ($requiredKeys as $key) {
            if (empty($data[$key])) {
                return false;
            }
        }
        if (empty($keysPresent)) {
            return true;
        }
    }
}