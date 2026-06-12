<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): self
    {
        $this->addRoute('GET', $path, $handler);
        return $this;
    }

    public function post(string $path, callable|array $handler): self
    {
        $this->addRoute('POST', $path, $handler);
        return $this;
    }

    public function put(string $path, callable|array $handler): self
    {
        $this->addRoute('PUT', $path, $handler);
        return $this;
    }

    public function delete(string $path, callable|array $handler): self
    {
        $this->addRoute('DELETE', $path, $handler);
        return $this;
    }

    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        // Convertește parametrii din {param} în regex
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                // Extrage parametrii numiți
                $params = array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);

                $handler = $route['handler'];

                if (is_array($handler)) {
                    [$class, $method] = $handler;
                    $controller = new $class();
                    $controller->$method($request, ...array_values($params));
                } else {
                    $handler($request, ...array_values($params));
                }
                return;
            }
        }

        // 404 — verifică dacă este rută API sau pagină
        if (str_starts_with($path, '/api/')) {
            Response::error('Endpoint not found', 404);
        } else {
            http_response_code(404);
            Response::view('pages.404');
        }
    }
}
