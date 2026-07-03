<?php

declare(strict_types=1);

$queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
$_SERVER['REQUEST_URI'] = '/network/switch-ports' . ($queryString !== '' ? '?' . $queryString : '');
require __DIR__ . '/index.php';
