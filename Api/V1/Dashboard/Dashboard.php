<?php

namespace Api\V1\Dashboard;

use Api\V1\BaseAPI;
use Includes\Database\Invoice as InvoiceModel;
use Includes\Database\WebHookLogsStorage as webHookLog;

class Dashboard extends BaseAPI
{
    public function getOne()
    {
        try {
            $webHookModel = new webHookLog();
            $invoiceModel = new InvoiceModel();

            $count = $invoiceModel->countInvoiceTotal();
            $activeCount = $invoiceModel->countInvoiceActive();
            $successCount = $invoiceModel->countInvoiceSuccess();

            $invoice_count = [
                'count' => $count,
                'active_count' => $activeCount,
                'success_count' => $successCount,
            ];

            $this->returnData(200, [
                'webhook_count' => $webHookModel->getCount(),
                'invoice_count' => $invoice_count
            ]);
        } catch (\Exception $e) {
            $error = [
                'status' => 'ERROR',
                'message' => 'Unable to get Dashboard data.',
                'error_details' => $e->getMessage()
            ];
            $this->returnData(500, $error);
        }
    }
}