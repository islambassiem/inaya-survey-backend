<?php

declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

$config = require __DIR__ . "/../config/env.php";

$allowedOrigins = $config["allowed_origins"];

$origin = $_SERVER["HTTP_ORIGIN"] ?? null;

// CORS validation
if ($origin !== null) {

    if (!in_array($origin, $allowedOrigins, true)) {
        respond(403, [
            "success" => false,
            "code" => "origin_not_allowed"
        ]);
    }

    header("Access-Control-Allow-Origin: " . $origin);
    header("Vary: Origin");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Max-Age: 600");

// Handle preflight requests
$method = $_SERVER["REQUEST_METHOD"] ?? "";

if ($method === "OPTIONS") {
    http_response_code(204);
    exit;
}

// JSON response helper
function respond(int $status, array $data): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}
