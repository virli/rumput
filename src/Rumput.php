<?php

namespace Rumput;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Rumput
{
    public static bool $debug = false;

    public static string $rootPath;
    public static string $configsPath;
    public static string $storagePath;
    public static string $publicPath;
    public static string $viewsPath;

    /**
     * Request from client
     *
     * @var Request
     */
    protected Request $request;

    /**
     * Response to send
     *
     * @var Response
     */
    protected Response $response;

    /**
     * Router
     *
     * @var Router
     */
    protected Router $router;

    /**
     * Database
     *
     * @var DB
     */
    protected DB $database;

    /**
     * Store configs data
     *
     * @var array
     */
    protected array $configs;

    public static function run(Request $request)
    {
        $self = new self();
        $self->web($request);
        $self->loadConfigs();

        $self->router   = new Router($self->configs['route']);
        $self->database = new DB($self->configs['database']);

        $response = null;
        try {
            $handler = $self->router->dispatch($request);
            $listController = explode('|', $handler);

            foreach ($listController as $controller) {
                $controllerName   = explode(':', $controller);
                $controllerClass  = $controllerName[0];
                $controllerAction = $controllerName[1];

                $instance = new $controllerClass();
                $instance->setRouter($self->router);
                $instance->setDB($self->database);

                if ($instance instanceof Controller) {
                    $response = $self->callController($instance, $controllerAction);
                    continue;
                }

                $maybeResponse = $self->callMiddleware($instance, $controllerAction);
                if ($maybeResponse instanceof Response) {
                    $response = $maybeResponse;
                    break;
                }
            }
        } catch (Exception $e) {
            $response = new Response($e->getMessage());
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $self->response = $response;

        $self->response->prepare($request);
        $self->response->send();
    }

    public static function runCLI()
    {
        $self = new self();
        $self->loadConfigs();

        $self->database = new DB($self->configs['database']);
    }

    protected function __construct()
    {
        self::$configsPath = self::$rootPath . '/configs';
        self::$storagePath = self::$rootPath . '/storage';
        self::$viewsPath   = self::$rootPath . '/views';
        self::$publicPath  = self::$rootPath . '/public';
    }

    protected function web(Request $request)
    {
        $session = Session::start()->getSession();

        $request->setSession($session);
        if (
            Request::METHOD_POST === $request->getMethod()
            && 0 === strpos(
                $request->headers->get('Content-Type'),
                'application/json'
            )
        ) {
            $data = json_decode($request->getContent(), true);
            $request->request->replace(is_array($data) ? $data : array());
        }

        $this->request = $request;
    }

    protected function loadConfigs()
    {
        $files = scandir(self::$configsPath);
        $files = array_filter($files, function ($value) {
            return false !== strpos($value, '.php');
        });

        $configs = [];
        foreach ($files as $file) {
            $name = substr($file, 0, -4);
            $configs[$name] = require self::$configsPath . '/' . $file;
        }

        $this->configs = $configs;
    }

    protected function callController(Controller $controller, $method): Response
    {
        $reflectionMethod = new \ReflectionMethod($controller, $method);
        $actionParameters = $reflectionMethod->getParameters();

        $actionArguments = [];
        foreach ($actionParameters as $parameter) {
            if ($parameter->getName() == 'request') {
                $actionArguments[] = $this->request;
                break;
            }
        }

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = call_user_func_array([$controller, $method], $actionArguments);
        if (!($response instanceof Response)) {
            throw new Exception($controller::class . ':' . $method . ' - must return valid response');
        }

        return $response;
    }

    protected function callMiddleware(Middleware $middleware, $method)
    {
        return call_user_func([$middleware, $method], $this->request);
    }
}
