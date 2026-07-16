<?php

declare(strict_types=1);

use HKL\Forensics\Http\ApiRouter;

require dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=UTF-8');

try {

    $endpoint = $_GET['endpoint'] ?? '';

    if ($endpoint === '') {

        throw new RuntimeException(
            'Geen API-endpoint opgegeven.'
        );

    }

    $rawInput = file_get_contents('php://input');

    $payload = [];

    if ($rawInput !== false && trim($rawInput) !== '') {

        $payload = json_decode(
            $rawInput,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

    }

    $router = new ApiRouter();

    $response = $router->dispatch(
        $endpoint,
        $payload
    );

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

} catch (Throwable $exception) {

    http_response_code(500);

    echo json_encode(

        [

            'success' => false,

            'message' => $exception->getMessage(),

        ],

        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

}