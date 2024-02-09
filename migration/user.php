<?php

use Includes\Database\Users;

require_once __DIR__ . '/../vendor/autoload.php';
require_once '../includes/autoload.php';
require_once './migration.php';

$migrationVersion = 1;

if ($argc > 1) {
    $password = $argv[1];
} else {
    echo "No parameter provided for add user \n";
    die();
}

if (checkRunMigration($migrationVersion)) {
    echo "skip run v $migrationVersion";
    exit();
}
if (runMigration($password)) {
    updateMigration($migrationVersion, 'user');
    echo 'run migration user ok';
}

function runMigration(string $password): bool
{
    $model = new Users();
    $adminUser = ['username' => 'admin', 'password' => $password, 'is_admin' => true];
    try {
        [$status, $userID] = $model->insertUser($adminUser);
    } catch (\Exception $e) {
        echo 'validate exception: ', $e->getMessage(), "\n";
        return false;
    }

    if (!$status) {
        throw new \Exception('unable to save user');
    }

    if ($model->isUUID($userID) && $status) {
        return true;
    }
    return false;
}