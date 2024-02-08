<?php

use Includes\Database\Users;

require_once __DIR__ . '/../vendor/autoload.php';
require_once '../includes/autoload.php';

if ($argc > 1) {
    $password = $argv[1];
} else {
    echo "No parameter provided for add user \n";
    die();
}

//todo check if have admin or migration version then not do this.
$model = new Users();
$adminUser = ['username' => 'admin', 'password' => $password];
[$status, $userID] = $model->insertUser($adminUser);
if (!$status) {
    throw new \Exception('unable to save user');
}