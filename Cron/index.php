<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once  __DIR__ .'../Includes/autoload.php';

use Cron\Webhook\UpdateFailHook;
use Cron\Lnd\CheckInvoiceStatusCron;

$cron = new UpdateFailHook();
$cronCheckLnd = new CheckInvoiceStatusCron();
$now = date("Y-m-d H:i:s P");
if ($cron->handel()) {
    echo "$now:done call UpdateFailHook" . PHP_EOL;
}
if ($cronCheckLnd->handel()) {
    echo "$now:done call check invoice status with LND" . PHP_EOL;
}
