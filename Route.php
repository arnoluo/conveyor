<?php

namespace Conveyor;

/**
 * ***********************************************************
 * This is a project base on https://github.com/noahbuscher/macaw, so that basic functions are still working well.
 * Most codes are rewrited to support route group function.
 * On this change you can:
 * Register available uri-prefix, class namespace and middleware initially;
 * Set different route prefix and namespace in each route group;
 * Set simple middlewares for every route.
 * ***********************************************************
 * @method static Route get(string $route, Callable $callback)
 * @method static Route post(string $route, Callable $callback)
 * @method static Route put(string $route, Callable $callback)
 * @method static Route delete(string $route, Callable $callback)
 * @method static Route options(string $route, Callable $callback)
 * @method static Route head(string $route, Callable $callback)
 */
class Route {
    public static $halts = false;
    public static $routes = array();
    public static $methods = array();
    public static $callbacks = array();
    public static $error_callback;
    public static $middlewares = array();
    public static $object = null;
    public static $patterns = array(
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    );
    public static $data = array(
        'namespace' => '',
        'prefix' => '',
        'middleware' => [],
        'middlewarePath' => [],
        'pos' => -1
    );

    public static function register($params = [])
    {
        $prefix = empty($params['prefix']) ? '' : '/' . trim(trim($params['prefix']), '/');
        $namespace = isset($params['namespace']) ? trim($params['namespace'], '\\') . '\\' : '';
        $middlewarePath = isset($params['middlewarePath']) ? $params['middlewarePath'] : '';
        $middlewarePath  = is_array($middlewarePath) ? $middlewarePath : [];
        $middleware = isset($params['middleware']) ? $params['middleware'] : '';

        self::init($prefix, $namespace, $middlewarePath, $middleware);
    }

    public static function namespace($str)
    {
        self::$data['namespace'] = trim($str, '\\') . '\\';
    }

    public function middleware($middleware)
    {
        $middleware = self::resolveMiddleware($middleware);
        self::setMiddleware($middleware);
    }

    /**
     * Defines a route w/ callback and method
     */
    public static function __callstatic($method, $params)
    {
        $uri = self::prefix($params[0]);
        if (is_string($params[1])) {
            $params[1] = self::prependNamespace($params[1]);
        }
        $callback = $params[1];
        $middleware = self::$data['middleware'];

        array_push(self::$routes, $uri);
        array_push(self::$methods, strtoupper($method));
        array_push(self::$callbacks, $callback);
        array_push(self::$middlewares, $middleware);
        self::$data['pos']++;

        return self::instance();
    }

    // Add group for route
    public static function group(array $params, callable $callback)
    {
        $allowedParams = ['prefix', 'namespace', 'middleware'];
        foreach (array_diff(array_keys($params), $allowedParams) as $key) {
            unset($params[$key]);
        }
        $lastParams = self::constructGroup($params);
        call_user_func($callback);
        self::destructGroup($lastParams);
    }

    /**
     * Defines callback if route is not found
    */
    public static function error($callback)
    {
        self::$error_callback = $callback;
    }

    public static function haltOnMatch($flag = true)
    {
        self::$halts = $flag;
    }

    public function __destruct()
    {
        self::dispatch();
    }

    protected static function init($prefix = '', $namespace = '', $middlewarePath = [], $middleware = [])
    {
        self::$data['prefix'] = $prefix;
        self::$data['namespace'] = $namespace;
        self::$data['middlewarePath'] = $middlewarePath;
        self::$data['middleware'] = self::resolveMiddleware($middleware);
    }

    protected static function instance()
    {
        if (is_null(self::$object)) {
            self::$object = new Route();
        }

        return self::$object;
    }

    protected static function setMiddleware($middlewareArray)
    {
        $pos = self::$data['pos'];
        self::$middlewares[$pos] = array_unique(array_merge(self::$middlewares[$pos], $middlewareArray));
    }

    protected static function resolveMiddleware($middleware)
    {
        if (!is_array($middleware)) {
            $middleware = explode(',', $middleware);
        }

        $allowedMiddlewares = array_keys(self::$data['middlewarePath']);
        $rightMiddleware = [];
        foreach ($middleware as $mdl) {
            $mdl = trim($mdl);
            if (in_array($mdl, $allowedMiddlewares, true)) {
                $rightMiddleware[] = $mdl;
            }
        }

        return $rightMiddleware;
    }

    protected static function prefix($uri)
    {
        $uri = strpos($uri, '/') === 0 ? $uri : '/' . $uri;
        return self::$data['prefix'] . $uri;
    }

    protected static function prependNamespace($class)
    {
        $parts = explode('/', $class);
        return self::$data['namespace'] . end($parts);
    }

    protected static function constructGroup($params) {
        $lastParams = [];
        foreach ($params as $param => $value) {
            if (empty($value)) {
                continue;
            }

            $lastParams[$param] = self::$data[$param];

            if ($param == 'prefix') {
                self::$data[$param] .= '/' . trim($value, '/');
            }

            if ($param == 'namespace' && empty(self::$data[$param])) {
                self::$data[$param] = trim($value, '\\') . '\\';
            }

            if ($param == 'middleware') {
                $middleware = self::resolveMiddleware($value);
                self::$data[$param] = array_unique(array_merge(self::$data[$param], $middleware));
            }
        }

        return $lastParams;
    }

    protected static function destructGroup($params) {
        foreach ($params as $param => $value) {
            self::$data[$param] = $value;
        }
    }

    protected static function resolveCallback($callback, $defaultMethod = 'handle')
    {
        if (is_object($callback)) {
            return $callback;
        }

        // Grab all parts based on a / separator
        $parts = explode('/', $callback);

        // Collect the last index of the array
        $last = end($parts);

        // Grab the controller name and method call
        $segments = explode('@',$last);

        if (!class_exists($segments[0])) {
            return false;
        }

        if (!isset($segments[1])) {
            $segments[1] = $defaultMethod;
        }

        if (!method_exists($segments[0], $segments[1])) {
            return false;
        }

        // Instanitate controller
        $controller = new $segments[0]();

        return array($controller, $segments[1]);
    }

    protected static function pipeMiddleware($pos)
    {
        if (!isset(self::$middlewares[$pos])) {
            return true;
        }

        foreach (self::$middlewares[$pos] as $mdl) {

            $callback = self::resolveCallback(self::$data['middlewarePath'][$mdl]);

            if ($callback === false) {
                echo 'Middleware: ' . self::$data['middlewarePath'][$mdl] . '@handle() not found';
                exit();
            }

            $result = call_user_func($callback);
            if ($result !== true) {
                echo json_encode($result);
                exit();
            }
        }

        return true;
    }

    /**
     * Runs the callback for the given request
     */
    protected static function dispatch()
    {
/*        var_dump(self::$routes);
        var_dump(self::$callbacks);
        var_dump(self::$middlewares);
        var_dump(self::$data);
        exit();*/
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        $searches = array_keys(static::$patterns);
        $replaces = array_values(static::$patterns);

        $found_route = false;

        self::$routes = preg_replace('/\/+/', '/', self::$routes);

        // Check if route is defined without regex
        if (in_array($uri, self::$routes)) {
            $route_pos = array_keys(self::$routes, $uri);
            foreach ($route_pos as $route) {
                // Using an ANY option to match both GET and POST requests
                if (self::$methods[$route] == $method || self::$methods[$route] == 'ANY') {
                    $found_route = true;

                    // run middleware
                    self::pipeMiddleware($route);

                    // run final function
                    $callback = self::resolveCallback(self::$callbacks[$route]);
                    if ($callback === false) {
                        echo self::$callbacks[$route] . ' ERROR: Controller or action not found';
                    } else {
                        call_user_func($callback);
                    }

                    if (self::$halts) return;
                }
            }
        } else {
            // Check if defined with regex
            $pos = 0;
            foreach (self::$routes as $route) {
                if (strpos($route, ':') !== false) {
                    $route = str_replace($searches, $replaces, $route);
                    if (preg_match('#^' . $route . '$#', $uri, $matched)) {
                        if (self::$methods[$pos] == $method || self::$methods[$pos] == 'ANY') {
                            $found_route = true;

                            // run middleware
                            self::pipeMiddleware($route);

                            // Remove $matched[0] as [1] is the first parameter.
                            array_shift($matched);

                            // run final function
                            $callback = self::resolveCallback(self::$callbacks[$pos]);
                            if ($callback === false) {
                                echo self::$callbacks[$pos] . ' ERROR: Controller or action not found';
                            } else {
                                call_user_func_array($callback, $matched);
                            }

                            if (self::$halts) return;
                        }
                    }
                }
                $pos++;
            }
        }

        // Run the error callback if the route was not found
        if ($found_route == false) {
            if (!self::$error_callback) {
                self::$error_callback = function() {
                    header($_SERVER['SERVER_PROTOCOL']." 404 Not Found");
                    echo '404';
                };
            } else {
                if (is_string(self::$error_callback)) {
                    self::get($_SERVER['REQUEST_URI'], self::$error_callback);
                    self::$error_callback = null;
                    self::dispatch();
                    return ;
                }
            }
            call_user_func(self::$error_callback);
        }
    }
}
