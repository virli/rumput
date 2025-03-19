<?php

namespace Rumput;

use Exception;
use Symfony\Component\HttpFoundation\Request;

class Router
{
    public const TYPE_SINGLE   = 'single';
    public const TYPE_MATCHALL = 'matchall';

    public static ?string $cachePath = null;

    private array $map;
    private array $route;
    private array $notfoundAction;

    public function __construct(array $configs)
    {
        if (null === self::$cachePath) {
            self::$cachePath = Rumput::$storagePath . '/cache/router.cache.php';
        }

        $this->notfoundAction = [Controller::class . ':notfoundAction'];

        if (Rumput::$debug || !file_exists(self::$cachePath)) {
            $this->parseConfigs($configs);
        }

        $this->loadRoute();
    }

    private function parseConfig0(
        array $configs,
        ?array $parent = null
    ): array {
        $result = [];
        foreach ($configs as $key => $item) {
            if ('notfound' === $key && null === $parent) {
                $this->notfoundAction = $this->normalizeRoute($item);
                continue;
            }

            $item['path'] = $item['path'] ?? $key;
            $item['path'] = '/' . ltrim($item['path'], '/');

            $outputKey  = $key;
            $path       = $item['path'];
            $middleware = [];
            if (null !== $parent) {
                $outputKey = $parent['key'] . '.' . $key;
                $path      = $parent['data']['path'] . $item['path'];

                $middleware = $parent['data']['middleware'] ?? [];
            }

            if (!empty($item['middleware'])) {
                $middleware[] = $item['middleware'];
            }

            $tempResult = [
                'path'       => $path,
                'method'     => $item['method'] ?? Request::METHOD_GET,
                'type'       => $item['type'] ?? Router::TYPE_SINGLE,
                'middleware' => $middleware,
                'controller' => $item['controller'] ?? [],
            ];

            if (!empty($tempResult['controller'])) {
                $tempResult['controller'] = $this->normalizeRoute($tempResult);
                $result[$outputKey] = $tempResult;
            }

            if (!empty($item['group'])) {
                $child = $this->parseConfig0($item['group'], [
                    'key'  => $outputKey,
                    'data' => $tempResult
                ]);

                $result = array_merge($child, $result);
            }
        }

        return $result;
    }

    public function parseConfigs(array $configs)
    {
        $routeParse0 = $this->parseConfig0($configs);

        $routeMap    = [];
        $routeParse1 = [
            'GET' => [
                'single' => [],
                'matchall' => []
            ],
        ];

        foreach ($routeParse0 as $key => $item) {
            $routeMap[$key] = $item['path'];

            $method = $item['method'];
            if (is_string($method)) {
                $method = [$method];
            }

            foreach ($method as $methodItem) {
                if (array_key_exists($methodItem, $routeParse1) === false) {
                    $routeParse1[$methodItem] = [
                        'single' => [],
                        'matchall' => []
                    ];
                }

                $routeParse1[$methodItem][$item['type']][$key] = [
                    'path' => $item['path'],
                    'controller' => $item['controller']
                ];
            }
        }

        $dumper = [
            'route'    => $routeParse1,
            'map'      => $routeMap,
            'notfound' => $this->notfoundAction
        ];

        file_put_contents(self::$cachePath, '<?php return ' . var_export($dumper, true) . ';');
    }

    protected function normalizeRoute(array $item): array
    {
        $controller = [];
        foreach ($item['middleware'] as $i) {
            $rawMiddleware = explode(':', $i);
            $controller[] = 'App\\' . $rawMiddleware[0] . 'Middleware:' . $rawMiddleware[1] . 'Action';
        }

        $rawController = explode(':', $item['controller']);
        $controller[] = 'App\\Controller\\' . $rawController[0] . 'Controller:' . $rawController[1] . 'Action';

        return $controller;
    }

    protected function loadRoute(): void
    {
        $dump = require self::$cachePath;

        $this->map = $dump['map'];
        $this->route = $dump['route'];
        $this->notfoundAction = $dump['notfound'];
    }

    public function dispatch(Request $request): array
    {
        if (false === array_key_exists($request->getMethod(), $this->route)) {
            return $this->notfoundAction;
        }

        $path  = $request->getPathInfo();
        $route = $this->route[$request->getMethod()];

        foreach ($route['single'] as $item) {
            if ($path === $item['path']) {
                return $item['controller'];
            }
        }

        foreach ($route['matchall'] as $item) {
            if ('/' === $item['path']) {
                return $item['controller'];
            }

            if (0 === strpos($path, $item['path'])) {
                $trailing = substr($path, strlen($item['path']), 1);

                if ('' === $trailing || '/' === $trailing) {
                    return $item['controller'];
                }
            }
        }

        return $this->notfoundAction;
    }

    public function getPath(string $name): string
    {
        if (array_key_exists($name, $this->map)) {
            return $this->map[$name];
        }

        throw new Exception('Route ' . $name . ' not found');
    }

    public function url(string $name, array $query = [])
    {
        $route = $this->getPath($name);
        if (empty($query) === false) {
            $route = $route . '?' . http_build_query($query);
        }

        return $route;
    }
}
