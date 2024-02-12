<?php

namespace Includes\Http;

class getRequestInfo
{
    public static function getClientIP(): string
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $clientIP = $_SERVER['REMOTE_ADDR'];
        }
        return $clientIP;
    }

    public static function getServerData(): string
    {
        $clientInfo = [];
        $clientInfo['ipAddress'] = $_SERVER['REMOTE_ADDR'] ?? 'Not available';
        $clientInfo['clientPort'] = $_SERVER['REMOTE_PORT'] ?? 'Not available';
        $clientInfo['serverPort'] = $_SERVER['SERVER_PORT'] ?? 'Not available';
        $clientInfo['httpMethod'] = $_SERVER['REQUEST_METHOD'] ?? 'Not available';
        $clientInfo['userAgent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Not available';
        $clientInfo['requestUri'] = $_SERVER['REQUEST_URI'] ?? 'Not available';
        $clientInfo['queryString'] = $_SERVER['QUERY_STRING'] ?? 'Not available';

        return json_encode($clientInfo, true);
    }

    public static function getBasicPage(): array
    {
        $page = 1;
        $maxPerPage = 20;
        $mode = 'desc';
        if (!empty($_GET['mode']) && $_GET['mode'] === 'asc') {
            $mode = 'asc';
        }
        if (!empty($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT) !== false) {
            $page = (int)$_GET['page'];
        }
        if (!empty($_GET['perPage']) && filter_var($_GET['perPage'], FILTER_VALIDATE_INT) !== false) {
            $maxPerPage = (int)$_GET['perPage'];
        }
        return [$maxPerPage, $page, $mode];
    }
}