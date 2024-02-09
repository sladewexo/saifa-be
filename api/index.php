<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../includes/autoload.php';
session_start();

use Api\Auth\Auth;
use Api\Auth\Login;
use Api\Auth\Logout;
use Api\V1\Config\WebhookConfig;
use Api\V1\Config\RetryConfig;
use Api\V1\Config\AccessToken;
use Api\V1\Users\UserHandler as User;
use Api\V1\Config\ShopConfig;

$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    '/v1/login' => [
        'handler' => [new Login(), 'handle'],
        'auth_required' => false,
        'method' => 'post'
    ],
    '/v1/logout' => [
        'handler' => [new Logout(), 'handle'],
        'auth_required' => false,
        'method' => 'post'
    ],
    '/v1/check/auth' => [
        'handler' => [new Auth(), 'checkAuth'],
        'auth_required' => false,
        'method' => 'get'
    ],
    '/v1/reload/auth' => [
        'handler' => [new Auth(), 'reLoadAuth'],
        'auth_required' => false,
        'method' => 'get'
    ],
    '/v1/config/webhook' => [
        'handler' => [new WebhookConfig(), 'get'],
        'auth_required' => true,
        'method' => 'get'
    ],
    '/v1/config/webhook/save' => [
        'handler' => [new WebhookConfig(), 'save'],
        'auth_required' => true,
        'method' => 'post'
    ],
    '/v1/config/retry/save' => [
        'handler' => [new RetryConfig(), 'save'],
        'auth_required' => true,
        'method' => 'post'
    ],
    '/v1/config/token/new' => [
        'handler' => [new AccessToken(), 'new'],
        'auth_required' => true,
        'method' => 'post'
    ],
    '/v1/config/token/update' => [
        'handler' => [new AccessToken(), 'update'],
        'auth_required' => true,
        'method' => 'post'
    ],
    '/v1/config/tokens' => [
        'handler' => [new AccessToken(), 'getAll'],
        'auth_required' => true,
        'method' => 'get'
    ],
    '/v1/config/token' => [
        'handler' => [new AccessToken(), 'getAll'],
        'auth_required' => true,
        'method' => 'get'
    ],
    '/v1/config/token/remove' => [
        'handler' => [new AccessToken(), 'remove'],
        'auth_required' => true,
        'method' => 'post'
    ],
    '/v1/config/token/${tokenId}' => [
        'handler' => [new AccessToken(), 'getOne'],
        'auth_required' => true,
        'method' => 'get'
    ],
    '/v1/users' => [
        'handler' => [new User(), 'getAll'],
        'auth_required' => true,
        'method' => 'get'
    ],
    '/v1/user/new' => [
        'handler' => [new User(), 'new'],
        'auth_required' => true,
        'method' => 'post'
    ],
    '/v1/user/update' => [
        'handler' => [new User(), 'update'],
        'auth_required' => true,
        'method' => 'post'
    ],
    '/v1/user/remove' => [
        'handler' => [new User(), 'remove'],
        'auth_required' => true,
        'method' => 'post'
    ],
    '/v1/user/${userId}' => [
        'handler' => [new User(), 'getOne'],
        'auth_required' => true,
        'method' => 'get'
    ],
    '/v1/config/shops' => [
        'handler' => [new ShopConfig(), 'get'],
        'auth_required' => true,
        'method' => 'get'
    ],
    '/v1/config/shop/save' => [
        'handler' => [new ShopConfig(), 'save'],
        'auth_required' => true,
        'method' => 'post'
    ],
];

foreach ($routes as $route => $info) {
    $parsedUrl = parse_url($requestUri);
    $path = $parsedUrl['path'];

    // Check for dynamic segments in the route
    if (strpos($route, '${') !== false) {
        // Extract the base route and the parameter name
        list($baseRoute, $paramName) = explode('/${', rtrim($route, '}'));
        $paramName = rtrim($paramName, '}');

        // Match the base route and extract the dynamic parameter
        if (strpos($path, $baseRoute) === 0) {
            $dynamicPart = substr($path, strlen($baseRoute) + 1);

            // Process the request if the dynamic part is not empty
            if ($dynamicPart !== false && $dynamicPart !== '') {
                processRequest($info, $dynamicPart);
                die();
            }
        }
    } else {
        // Process static routes
        if ($path === $route) {
            processRequest($info);
            die();
        }
    }
}

// Function to process the request
function processRequest($info, $dynamicParam = null)
{
    $authService = new Auth();
    $httpMethod = $_SERVER['REQUEST_METHOD'];
    if ($info['auth_required'] && !$authService->isAuthenticated($httpMethod)) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    $rawData = file_get_contents("php://input");
    $jsonData = json_decode($rawData, true);

    if ($info['method'] === 'post') {
        if (empty($jsonData)) {
            echo json_encode(['error' => 'call post without json data']);
            exit;
        }
    }
    header('Content-Type: application/json');

    $handler = $info['handler'];
    if (is_callable($handler)) {
        if ($dynamicParam !== null) {
            $dynamicParam = (string)$dynamicParam;
            call_user_func($handler, $dynamicParam);
        } else {
            call_user_func($handler, $jsonData);
        }
    }
}

header('HTTP/1.1 404 Not Found');
echo json_encode(['error' => 'Endpoint not found.']);

