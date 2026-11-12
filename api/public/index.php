<?php

declare(strict_types=1);

use App\Middleware\CorsMiddleware;
use App\Support\DbError;
use App\Support\HttpError;
use App\Support\Json;
use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\ResponseEmitter;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(false, false, false);
$errorMiddleware->setDefaultErrorHandler(function (Request $request, Throwable $exception) use ($app) {
    $response = $app->getResponseFactory()->createResponse();

    if ($exception instanceof HttpError) {
        return Json::error($response, $exception->status, $exception->getMessage(), $exception->errors);
    }

    if ($exception instanceof HttpNotFoundException) {
        return Json::error($response, 404, 'Endpoint tidak ditemukan.');
    }

    if ($exception instanceof HttpMethodNotAllowedException) {
        return Json::error($response, 405, 'Method HTTP tidak diizinkan.');
    }

    if ($exception instanceof HttpBadRequestException) {
        return Json::error($response, 400, 'Request tidak valid.');
    }

    if ($exception instanceof PDOException && DbError::isUniqueViolation($exception)) {
        return Json::error(
            $response,
            422,
            'Data dengan nilai unik tersebut sudah ada.',
            DbError::uniqueFieldErrors($exception->getMessage()),
        );
    }

    error_log('[API] ' . $exception::class . ': ' . $exception->getMessage());

    return Json::error($response, 500, 'Terjadi kesalahan pada server.');
});

$app->add(new CorsMiddleware());

try {
    require __DIR__ . '/../src/routes.php';
} catch (PDOException $exception) {
    error_log('[API] Koneksi database gagal: ' . $exception->getMessage());

    $response = $app->getResponseFactory()
        ->createResponse(503)
        ->withHeader('Content-Type', 'application/json; charset=utf-8');
    $response->getBody()->write(json_encode([
        'success' => false,
        'message' => 'Koneksi database gagal. Periksa konfigurasi DB pada file .env.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    (new ResponseEmitter())->emit($response);

    return;
}

$app->run();
