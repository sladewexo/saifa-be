<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../Includes/autoload.php';
require_once './migration.php';

use Includes\Database\Config;

$migrationVersion = 2;
if (checkRunMigration($migrationVersion)) {
    echo "skip run v $migrationVersion";
    exit();
}

$data = [
    'numShop' => 1,
    'arrayStop' => json_encode(['Default Shop Name'], true)
];

$model = new Config();
if ($model->saveAppConfig($data)) {
    updateMigration($migrationVersion, 'shop');
    echo 'run migration shop ok';
}