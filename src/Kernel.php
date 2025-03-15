<?php

namespace Noma\Core;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class Kernel
{
    /**
     * An array of already instantiated controllers,
     * with their constructor methods already injected.
     *
     * @var array<Controller> $controllers
     */
    private array $controllers = [];

    public function __construct()
    {
        $this->initControllers();
    }

    /**
     * @param array $nodes
     * @return string|null
     */
    private function findControllerInNodes(array $nodes): ?string
    {
        foreach ($nodes as $node) {
            if ($node instanceof Namespace_) {
                return self::findControllerInNodes($node->stmts);
            }

            if (!$node instanceof Class_) {
                continue;
            }

            if ($node?->extends?->name === 'Noma\Noma\Controller') {
                return $node?->namespacedName?->name ?? null;
            }
        }

        return null;
    }

    /**
     * Attempts to find the root directory of the project.
     *
     * @return string
     */
    private function findRootDir(): string
    {
        $startingDir = __DIR__;
        $rootDir = null;
        $iterations = 5;

        while ($iterations > 0) {
            // TODO: need better check than just composer.json
            if (file_exists($startingDir . "/composer.json")) {
                $rootDir = $startingDir;
            } else {
                $startingDir = dirname($startingDir);
            }

            $iterations--;
        }

        if (!$rootDir) {
            return $startingDir;
        }

        return $rootDir;
    }

    /**
     * @return void
     */
    private function initControllers(): void
    {
        $dir = new RecursiveDirectoryIterator($this->findRootDir());
        $ite = new RecursiveIteratorIterator($dir);
        $files = new RegexIterator($ite, '/^.+\Controller\.php$/i', RegexIterator::MATCH);

        foreach ($files as $file) {
            $parser = new ParserFactory()->createForHostVersion();

            try {
                $nameResolver = new NameResolver();
                $nodeTraverser = new NodeTraverser();
                $nodeTraverser->addVisitor($nameResolver);
                $nodes = $nodeTraverser->traverse($parser->parse(file_get_contents($file)));
                $controller = $this->findControllerInNodes($nodes);

                if ($controller) {
                    $this->controllers[] = $this->hydrateClass($controller);
                }
            } catch (\Exception $e) {
                // TODO: hook into logger
            }
        }
    }

    /**
     * @param string $class
     * @return array
     * @throws \ReflectionException
     */
    private function getConstructorParams(string $class): array
    {
        $reflection = new \ReflectionClass($class);

        if ($reflection->getConstructor()) {
            return $reflection->getConstructor()->getParameters();
        }

        return [];
    }

    /**
     * @throws \ReflectionException
     */
    private function getMethodParams(string $class, string $method): array
    {
        $reflection = new \ReflectionClass($class);

        if ($reflection->hasMethod($method)) {
            return $reflection->getMethod($method)->getParameters();
        }

        return [];
    }

    /**
     * @param string $path
     * @param string $name
     * @return string|null
     */
    private function getPathParam(string $path, string $name): ?string
    {
        $splitInputPath = explode('/', $_SERVER['REQUEST_URI']);
        $splitRoutePath = explode('/', $path);

        for ($i = 0; $i < \count($splitRoutePath); $i++) {
            $routePathPart = $splitRoutePath[$i];

            if (
                str_starts_with($routePathPart, "{") &&
                str_ends_with($routePathPart, "}") &&
                $name === trim($routePathPart, "{}")
            ) {
                return $splitInputPath[$i];
            }
        }

        return null;
    }

    /**
     * @param array $params
     * @return array
     */
    private function composeInjectables(array $params): array
    {
        $injectables = [];

        foreach ($params as $param) {
            if ($param->getType() && !$param->getType()->isBuiltin()) {
                try {
                    $injectables[] = $this->hydrateClass($param->getType()->getName());
                } catch (\ReflectionException $e) {
                    // TODO: hook to logger
                }
            }
        }

        return $injectables;
    }

    /**
     * @param string $path
     * @param array $params
     * @return array
     */
    private function composeParams(string $path, array $params): array
    {
        $parameters = [];

        foreach ($params as $param) {
            if ($param->getType() && $param->getType()->isBuiltin()) {
                // TODO check type and coerce
                $parameters[] = $this->getPathParam($path, $param->getName());
            }
        }

        return $parameters;
    }

    /**
     * @param string $class
     * @returns object
     * @throws \ReflectionException
     */
    private function hydrateClass(string $class): object
    {
        $constructorParams = $this->getConstructorParams($class);
        $injectables = $this->composeInjectables($constructorParams);
        $reflectedClass = new \ReflectionClass($class);

        return $reflectedClass->newInstanceArgs($injectables);
    }

    /**
     * @throws \ReflectionException
     */
    private function attemptHttpResponse(HttpMethod $httpMethod, string $path): ?Response
    {
        foreach ($this->controllers as $controller) {
            $class = new \ReflectionClass($controller);
            $classMethods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($classMethods as $classMethod) {
                $attributes = $classMethod->getAttributes(Route::class);

                if (empty($attributes)) {
                    continue;
                }

                /** @var Route $attribute */
                $route = $attributes[0]->newInstance();

                if (!$route->matches($httpMethod, $path)) {
                    continue;
                }

                $methodParams = $this->getMethodParams($class->getName(), $classMethod->getName());

                return $controller->{$classMethod->getName()}(
                    ...$this->composeInjectables($methodParams),
                    ...$this->composeParams($route->getPath(), $methodParams)
                );
            }
        }

        return null;
    }

    /**
     * @return Response
     * @throws \ReflectionException
     */
    public function handleHttpRequest(): Response
    {
        if ($response = $this->attemptHttpResponse(
            httpMethod: HttpMethod::from($_SERVER['REQUEST_METHOD']),
            path: Util::normalizePath($_SERVER['REQUEST_URI'])
        )) {
            return $response;
        }

        return Response::text('404');
    }

    public function serveHttp(): void
    {
        try {
            $this->handleHttpRequest()->deliver();
        } catch (\ReflectionException $e) {
            var_dump($e);
            // TODO: hook to logger
        }
    }

    /**
     * @param Runner $driver
     * @return void
     */
    public function serveHttpWith(Runner $driver): void
    {
        $driver->handleRequest(function () {
            $this->serveHttp();
        });
    }
}
