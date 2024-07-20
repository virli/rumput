<?php

namespace Rumput;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Controller extends Middleware
{
    public function render(string $path, array $args = [])
    {
        $rumputTemplatePath = Rumput::$viewsPath . '/' . $path;

        $engine = function ($v) use ($rumputTemplatePath) {
            ob_start();
            require $rumputTemplatePath;
            return ob_get_clean();
        };

        $content = call_user_func($engine, $args);
        return new Response($content);
    }

    public function notfoundAction(Request $request)
    {
        $response = new Response("Page not found: " . $request->getPathInfo());
        $response->setStatusCode(Response::HTTP_NOT_FOUND);
        return $response;
    }
}
