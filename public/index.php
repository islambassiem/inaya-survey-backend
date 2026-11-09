<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$routes = [
    '/api/questions' => __DIR__ . '/../api/questions.php',
    '/api/submit' => __DIR__ . '/../api/submit.php',
];

if (!isset($routes[$path])) {
    header('Content-Type: application/json; charset=UTF-8');

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'code' => 'route_not_found'
    ]);

    exit;
}

require $routes[$path];
