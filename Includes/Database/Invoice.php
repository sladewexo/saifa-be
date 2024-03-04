<?php

namespace Includes\Database;

class Invoice extends BaseModel
{
    const TABLE_NAME = "invoice";
    const SORT_KEY = "invoice_sort";
    const SORT_KEY_ACTIVE_ONLY = "invoice_sort_active";
    const SORT_KEY_SUCCESS_INVOICE = "invoice_sort_success";
    const INDEX_KEY = "invoice_rhash_index";

    /**
     * @param string $uuid
     * @return array
     */
    public function getInvoiceDataById(string $uuid): array
    {
        return $this->select(self::TABLE_NAME, $uuid);
    }

    public function getInvoiceAll($maxPerPage, $page, $mode): array
    {
        $start = $page - 1;
        $end = $start + ($maxPerPage - 1);
        $results = [];

        if ($mode == 'desc') {
            $resultSort = $this->redis->zRevRange(self::SORT_KEY, $start, $end);
        } else {
            $resultSort = $this->redis->zRange(self::SORT_KEY, $start, $end);
        }

        foreach ($resultSort as $key) {
            $results[] = $this->redis->hGetAll($key);
        }
        return $results;
    }

    public function getActiveInvoice($maxPerPage, $page, $mode): array
    {
        $start = $page - 1;
        $end = $start + ($maxPerPage - 1);
        $results = [];

        if ($mode == 'desc') {
            $resultSort = $this->redis->zRevRange(self::SORT_KEY_ACTIVE_ONLY, $start, $end);
        } else {
            $resultSort = $this->redis->zRange(self::SORT_KEY_ACTIVE_ONLY, $start, $end);
        }

        foreach ($resultSort as $key) {
            $results[] = $this->redis->hGetAll($key);
        }
        return $results;
    }

    private function removeActiveInvoice(string $invoiceId): bool
    {
        return $this->softDelete(self::TABLE_NAME, self::SORT_KEY_ACTIVE_ONLY, $invoiceId);
    }

    public function getInvoice($uuid): array
    {
        return $this->select(self::TABLE_NAME, $uuid);
    }

    public function countInvoiceTotal(): int
    {
        return $this->redis->zCount(self::SORT_KEY, '-inf', 'inf');
    }

    public function countInvoiceActive(): int
    {
        return $this->redis->zCount(self::SORT_KEY_ACTIVE_ONLY, '-inf', 'inf');
    }

    public function countInvoiceSuccess(): int
    {
        return $this->redis->zCount(self::SORT_KEY_SUCCESS_INVOICE, '-inf', 'inf');
    }

    /**
     * @param array $requestData
     * @return array
     */
    public function insertInvoice(array $requestData): array
    {
        $newUUID = $this->makeNewUUID();
        $updateData = [];
        $updateData['invoice_id'] = $newUUID;
        $updateData['create_at'] = time();
        $updateData['update_at'] = null;
        $updateData['is_delete'] = false;
        //todo map this Invoice with real lnd address in db. ex dog_shop -> 12c6DSiU4Rq3P4ZxziKxzrL5LmMBrzjrJX
        $updateData['address_name'] = $requestData['address_name'];
        //todo amount need to check is any
        $updateData['amount'] = $requestData['amount'];
        $updateData['description_note'] = $requestData['description_note'];
        $updateData['qr_code'] = $requestData['qr_code'];
        $updateData['r_hash'] = $requestData['r_hash'];
        $updateData['payment_addr'] = $requestData['payment_addr'];
        $updateData['status'] = 'wait';
        if (!empty($requestData['meta_data'])) {
            $updateData['meta_data'] = json_encode($requestData['meta_data'], true);
        }

        $status = $this->insertNewRow(self::TABLE_NAME, $newUUID, $updateData);
        if (!$this->addOrUpdateSort(self::TABLE_NAME, self::SORT_KEY, $newUUID, $updateData['create_at'])) {
            return [false, $newUUID];
        }
        $this->addOrUpdateSort(self::TABLE_NAME, self::SORT_KEY_ACTIVE_ONLY, $newUUID, $updateData['create_at']);
        $this->insertIndex(self::INDEX_KEY, $updateData['r_hash'], $newUUID);
        return [$status, $newUUID];
    }

    public function getInvoiceFromRHash(string $rHash): array
    {
        $invoiceID = $this->selectIndex(self::INDEX_KEY, $rHash);
        return $this->select(self::TABLE_NAME, $invoiceID);
    }

    public function updateInvoiceStatus(string $uuid, string $status): bool
    {
        //debug
//        $random = rand(0,1);
//        if($status == 'CANCELED' && $random == 0){
//            $status = 'SETTLED';
//        }

        $nonActiveStatus = ['SETTLED', 'CANCELED', 'ACCEPTED'];
        if (in_array($status, $nonActiveStatus)) {
            $this->removeActiveInvoice($uuid);
            if ($status != 'CANCELED') {
                $this->addOrUpdateSort(self::TABLE_NAME, self::SORT_KEY_SUCCESS_INVOICE, $uuid, time());
            }
        }
        return $this->update(self::TABLE_NAME, $uuid, ['status' => $status]);
    }

    public function updateInvoice(string $uuid, array $dataUpdate): bool
    {
        if (isset($dataUpdate['is_manual_cancel']) && $dataUpdate['is_manual_cancel']) {
            $this->softDelete(self::TABLE_NAME, self::SORT_KEY_ACTIVE_ONLY, $uuid);
        }
        return $this->update(self::TABLE_NAME, $uuid, $dataUpdate);
    }

}