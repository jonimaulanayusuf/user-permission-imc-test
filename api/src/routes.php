<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\LevelController;
use App\Controller\PageController;
use App\Controller\PermissionController;
use App\Controller\UserController;
use App\Middleware\AuthMiddleware;
use App\Service\JwtService;
use App\Service\PermissionService;
use App\Support\Database;
use App\Support\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/** @var \Slim\App $app */

$pdo = Database::pdo();
$jwt = new JwtService();
$permissionService = new PermissionService($pdo);

$authMiddleware = static fn (?string $page = null): AuthMiddleware => new AuthMiddleware(
    $pdo,
    $jwt,
    $permissionService,
    $page,
);

$authController = new AuthController($pdo, $permissionService, $jwt);
$userController = new UserController($pdo);
$levelController = new LevelController($pdo);
$pageController = new PageController($pdo);
$permissionController = new PermissionController($pdo);

$app->get('/', function (Request $request, Response $response): Response {
    return Json::ok($response, [
        'name' => 'IMC User Access Management API',
        'version' => '1.0.0',
    ], 200, 'API berjalan.');
});

$app->group('/api', function (RouteCollectorProxy $api) use (
    $authController,
    $userController,
    $levelController,
    $pageController,
    $permissionController,
    $authMiddleware,
): void {
    $api->post('/auth/login', [$authController, 'login']);
    $api->get('/auth/me', [$authController, 'me'])->add($authMiddleware());

    $api->group('/users', function (RouteCollectorProxy $group) use ($userController): void {
        $group->get('', [$userController, 'index']);
        $group->post('', [$userController, 'store']);
        $group->get('/{id:[0-9]+}', [$userController, 'show']);
        $group->put('/{id:[0-9]+}', [$userController, 'update']);
        $group->delete('/{id:[0-9]+}', [$userController, 'destroy']);
    })->add($authMiddleware('/users'));

    $api->group('/levels', function (RouteCollectorProxy $group) use ($levelController): void {
        $group->get('', [$levelController, 'index']);
        $group->post('', [$levelController, 'store']);
        $group->get('/{id:[0-9]+}', [$levelController, 'show']);
        $group->put('/{id:[0-9]+}', [$levelController, 'update']);
        $group->delete('/{id:[0-9]+}', [$levelController, 'destroy']);
    })->add($authMiddleware('/levels'));

    $api->group('/pages', function (RouteCollectorProxy $group) use ($pageController): void {
        $group->get('', [$pageController, 'index']);
        $group->post('', [$pageController, 'store']);
        $group->get('/{id:[0-9]+}', [$pageController, 'show']);
        $group->put('/{id:[0-9]+}', [$pageController, 'update']);
        $group->delete('/{id:[0-9]+}', [$pageController, 'destroy']);
    })->add($authMiddleware('/pages'));

    $api->group('/permissions', function (RouteCollectorProxy $group) use ($permissionController): void {
        $group->get('', [$permissionController, 'index']);
        $group->put('/levels/{id:[0-9]+}', [$permissionController, 'updateLevel']);
        $group->get('/users/{id:[0-9]+}', [$permissionController, 'userDetail']);
        $group->put('/users/{id:[0-9]+}', [$permissionController, 'updateUser']);
    })->add($authMiddleware('/permissions'));
});
