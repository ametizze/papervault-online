<?php

declare(strict_types=1);

namespace SimpleVault\Core;

/**
 * Minimal router with support for route parameters and middleware flags.
 *
 * Middleware flags supported per route:
 *   - 'auth'   : requires an authenticated user
 *   - 'unlock' : requires the vault to be unlocked
 *   - 'guest'  : requires NO authenticated user (login/setup pages)
 *
 * All POST/PUT/PATCH/DELETE routes are CSRF-validated automatically.
 */
final class Router
{
    /** @var array<int, array{method:string,pattern:string,handler:callable|array,middleware:array}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, callable|array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            $params = $this->match($route['pattern'], $request->path);
            if ($params === null) {
                continue;
            }

            // CSRF protection for state-changing requests.
            if (in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $token = $request->string('_csrf');
                if (!Csrf::validate($token)) {
                    return $this->csrfFailure($request);
                }
            }

            $middlewareResponse = $this->runMiddleware($route['middleware'], $request);
            if ($middlewareResponse instanceof Response) {
                return $middlewareResponse;
            }

            return $this->call($route['handler'], $request, $params);
        }

        return View::render('errors/404', [], 'Not Found')->withHeader('X-Status', '404');
    }

    /**
     * @return array<string,string>|null  null if no match
     */
    private function match(string $pattern, string $path): ?array
    {
        // Convert /notes/{id} to a regex with named groups.
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function ($m) {
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $pattern);

        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function runMiddleware(array $middleware, Request $request): ?Response
    {
        if (in_array('guest', $middleware, true) && Session::isAuthenticated()) {
            return Response::redirect('/');
        }

        if (in_array('auth', $middleware, true) && !Session::isAuthenticated()) {
            Session::flash('warning', 'Please log in to continue.');
            return Response::redirect('/login');
        }

        if (in_array('unlock', $middleware, true) && !Session::isVaultUnlocked()) {
            Session::flash('warning', 'Unlock your vault to continue.');
            return Response::redirect('/vault/unlock');
        }

        return null;
    }

    private function call(callable|array $handler, Request $request, array $params): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = is_object($class) ? $class : new $class();
            $result = $instance->{$method}($request, $params);
        } else {
            $result = $handler($request, $params);
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return new Response('', 204);
    }

    private function csrfFailure(Request $request): Response
    {
        Logger::warning('CSRF validation failed', ['path' => $request->path, 'ip' => $request->ip]);
        Session::flash('danger', 'Your session expired or the request was invalid. Please try again.');

        return Response::redirect($request->path === '/login' ? '/login' : '/');
    }
}
