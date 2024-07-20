<?php

namespace Rumput;

use Medoo\Medoo;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

abstract class Middleware
{
    private Router $router;
    private Medoo $medoo;

    public function setRouter(Router $router)
    {
        $this->router = $router;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function setDB(DB $database)
    {
        $this->medoo = $database->getMedoo();
    }

    public function db(): Medoo
    {
        return $this->medoo;
    }

    public function url(string $pathName, array $query = []): string
    {
        return $this->router->url($pathName, $query);
    }

    public function json(array $json): JsonResponse
    {
        $response = new JsonResponse($json);
        return $response;
    }

    public function redirectTo(string $pathName, array $query = []): RedirectResponse
    {
        $url = $this->url($pathName, $query);
        return new RedirectResponse($url);
    }
}
