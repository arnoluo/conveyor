<?php

namespace Conveyor;

use Closure;
use Conveyor\RouteException;

/**
 * ***********************************************************
 * Features:
 * Register available uri-prefix, class namespace and middleware initially;
 * Set different route prefix and namespace in each route group;
 * Set middlewares for every route.
 * 
 * References Project:
 * https://github.com/noahbuscher/macaw
 * https://github.com/nikic/FastRoute
 * ***********************************************************
 * @method static Route get(string $route, Callable $callback)
 * @method static Route post(string $route, Callable $callback)
 * @method static Route put(string $route, Callable $callback)
 * @method static Route delete(string $route, Callable $callback)
 * @method static Route options(string $route, Callable $callback)
 * @method static Route head(string $route, Callable $callback)
 * @method static Route group(array $params, Callable $callback)
 */
class Route {

    /**
     * Normal route uri array
     * @var array
     */
    public static $routes = array();

    /**
     * Regexp route uri array
     * @var array
     */
    public static $matches = array();

    /**
     * Callback function array for each route
     * @var array
     */
    public static $callbacks = array();

    /**
     * Middleware array for each route
     * @var array
     */
    public static $middlewares = array();

    /**
     * Regex matching rules
     * @var array
     */
    public static $patterns = array(
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    );

    /**
     * Common data for all routes
     * @var array
     */
    public static $common = array(
        'middleware' => array(),
        'namespace' => '',
        'prefix' => '',
        'depth' => 0
    );

    /**
     * Middleware path array
     * ['alias' => \Namespace\Class::class];
     * @var array
     */
    public static $alias = array();

    /**
     * Error callback function
     * @var callable
     */
    public static $error = null;

    /**
     * Route instance
     * @var object
     */
    public static $object = null;

    /**
     * Position of current registered route
     * @var integer
     */
    public static $pos = null;

    /**
     * Response content
     * @var string
     */
    public static $content = null;

    /**
     * Main function, handle all routes addition.
     */
    public static function __callstatic($method, $params)
    {
        list($uri, $callback) = $params;
        self::pushRoute($method, $uri, $callback);

        return self::instance();
    }

    /**
     * Set some middlewares for a single router.
     *
     * @author Arno
     *
     * @param  mixed $middleware Array or string
     *
     * @return void
     */
    public function middleware($middleware)
    {
        self::setMiddleware($middleware);
    }

    /**
     * Register some common data,
     * such as 'prefix', 'namespace', 'alias', 'middleware'.
     */
    public static function register($params = [])
    {
        self::init();
        $alias = (array)($params['alias'] ?? []);
        $middleware = trim($params['middleware'] ?? '');
        $namespace = trim($params['namespace'] ?? '');
        $prefix = self::prefix($params['prefix'] ?? '');

        self::init($prefix, $namespace, $alias, $middleware);
    }

    protected static function pushRoute($method, $uri, $callback)
    {
        $method = strtoupper($method);
        $uri = self::prefix($uri) ? : '/';
        strpos($uri, '(:') === false ? $map = & self::$routes : $map = & self::$matches;
        if ($currentPos = $map[$method][$uri] ?? false) {
            unset(self::$middlewares[$currentPos]);
        } else {
            $currentPos = self::pushPos();
            $map[$method][$uri] = $currentPos;
        }
        
        self::$callbacks[$currentPos] = self::prependNamespace($callback);
        if (self::common('depth') > 0) {
            self::$middlewares[$currentPos] = self::common('middleware');
        }
    }

    /**
     * Add group for route
     *
     * @author Arno
     *
     * @param  array    $params   Recognizable keywords: 'prefix', 'namespace', 'middleware'.
     * @param  \Closure|string $callback
     *
     * @return void
     */
    public static function group(array $params, $routes)
    {
        $allowedParams = ['prefix', 'namespace', 'middleware'];
        foreach (array_diff(array_keys($params), $allowedParams) as $key) {
            unset($params[$key]);
        }

        $context = self::constructGroup($params);
        if ($routes instanceof Closure) {
            $routes(self::instance());
        } else {
            require $routes;
        }

        self::destructGroup($context);
    }

    /**
     * Defines callback if route is not found
     */
    public static function error($callback)
    {
        self::$error = $callback;
    }

    /**
     * (For container)
     * Handle request and return a serialized array of callbacks.
     */
    public static function capture(& $matched = null)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $matchPos = self::matchUri($method, $uri, $matched);

        $captureArr = [];
        $mdlArr = self::$middlewares[$matchPos] ?? self::common('middleware');
        foreach ($mdlArr as $mdl) {
            $captureArr[] = self::$alias[$mdl];
        }

        $captureArr[] = self::$callbacks[$matchPos];

        return $captureArr;
    }

    /**
     * Runs the callback for the given request
     */
    public static function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $matchPos = self::matchUri($method, $uri, $matched);

        // Middleware check
        self::pipeMiddleware($matchPos);

        $callback = self::resolveCallback(self::$callbacks[$matchPos]);

        // Run final function
        $result = is_null($matched) ? call_user_func($callback) : call_user_func_array($callback, $matched);

        self::response($result);
    }

    protected static function response($content = null)
    {
        self::render($content);
        if (!is_null(self::$content)) {
            echo self::$content;
        }

        exit();
    }

    /**
     * Init Route from register.
     */
    protected static function init($prefix = '', $namespace = '', $alias = [], $middleware = [])
    {
        self::$alias = $alias;
        self::setMiddleware($middleware, true);
        self::common('namespace', $namespace);
        self::common('prefix', $prefix);
        self::common('depth', 0);
    }

    /**
     * Return a instance of Route.
     */
    protected static function instance()
    {
        if (is_null(self::$object)) {
            self::$object = new self();
        }

        return self::$object;
    }

    protected static function pushPos()
    {
        if (is_null(self::$pos)) {
            return self::$pos = 0;
        }

        return ++self::$pos;
    }

    protected static function setMiddleware($middleware, $isCommon = false)
    {
        $middlewareArr = self::resolveMiddleware($middleware);
        if (empty($middlewareArr)) {
            return false;
        }

        $isCommon ? $map = & self::$common['middleware'] : $map = & self::$middlewares[self::$pos];
        $map = array_unique(array_merge($map ?? [], $middlewareArr));

        return true;
    }

    protected static function resolveMiddleware($middleware)
    {
        if (empty($middleware)) {
            return [];
        }

        if (!is_array($middleware)) {
            $middleware = explode(',', str_replace(' ', '', $middleware));
        }

        $allowedMiddlewares = array_keys(self::$alias);
        $undefinedMdl = array_diff($middleware, $allowedMiddlewares);
        if (!empty($undefinedMdl)) {
            throw new RouteException(
                'Middleware [' . implode(',', $undefinedMdl) . '] is not defined in register().'
            );
        }

        return $middleware;
    }
    
    protected static function prefix($uri)
    {
        $uri = trim(trim($uri), '/');
        return self::common('prefix') . (strlen($uri) > 0 ? '/' : '') . $uri;
    }

    /**
     * Get/Set common data
     */
    protected static function common($key, $newValue = null)
    {
        if (is_null($newValue)) {
            return self::$common[$key] ?? false;
        }

        self::$common[$key] = $newValue;
    }

    protected static function prependNamespace($class)
    {
        if (!is_string($class)) {
            return $class;
        }

        $parts = explode('/', $class);

        return self::common('namespace') . end($parts);
    }

    protected static function constructGroup($params) {
        $context = [];
        foreach ($params as $param => $value) {
            if (empty($value)) {
                continue;
            }

            $context[$param] = self::common($param);

            switch ($param) {
                case 'prefix' :
                    self::common($param, self::prefix($value));
                    break;
                case 'namespace' :
                    self::common($param, trim($value));
                    break;
                case 'middleware' :
                    if (self::setMiddleware($value, true)) {
                        self::$common['depth']++;
                    }
                    break;
                default:
            }
        }

        return $context;
    }

    protected static function destructGroup($context) {
        foreach ($context as $param => $value) {
            self::common($param, $value);
        }

        if (in_array('middleware', array_keys($context))) {
            self::$common['depth']--;
        }
    }

    protected static function resolveCallback($callback, $defaultMethod = 'handle')
    {
        if (is_object($callback)) {
            return $callback;
        }

        // Grab the controller name and method call
        $segments = explode('@', $callback);
        @list($callbackClass, $callbackMethod) = $segments;

        if (!class_exists($callbackClass)) {
            throw new RouteException('Class ' . $callbackClass . ' is not exist.');
        }

        $callbackMethod = $callbackMethod ?? $defaultMethod;

        if (!method_exists($callbackClass, $callbackMethod)) {
            throw new RouteException('Method ' . $callback . ' is not callable in Class ' . $callbackClass . '.');
        }

        // Instanitate controller
        $controller = new $callbackClass();

        return array($controller, $callbackMethod);
    }

    protected static function pipeMiddleware($pos)
    {
        $mdlArr = self::$middlewares[$pos] ?? self::common('middleware');
        foreach ($mdlArr as $mdl) {
            $callback = self::resolveCallback(self::$alias[$mdl]);
            $result = call_user_func($callback);
            if ($result !== true) {
                self::response($result);
            }
        }
    }

    /**
     * Rendering response content
     */
    protected static function render($content)
    {
        if (is_null($content)) {
            return;
        }

        if (is_array($content)) {
            $content = json_encode($content);
        }

        if (!is_string($content) && !is_numeric($content) && !is_callable(array($content, '__toString'))) {
            throw new RouteException(sprintf(
                'Response content must be a string or object implementing __toString(), "%s" given.',
                gettype($content)
            ));
        }

        self::$content = strval($content);
    }

    protected static function handleNotFound()
    {
        if (is_null(self::$error)) {
            $handler = function() {
                header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
                echo 404;
            };
        } else {
            $handler = self::resolveCallback(self::$error);
        }

        self::response(call_user_func($handler));
    }

    protected static function matchUri($requestMethod, $requestUri, & $matched = null)
    {
        $matchMethods = array($requestMethod, 'ANY');
        foreach ($matchMethods as $method) {
            if (isset(self::$routes[$method][$requestUri])) {
                return self::$routes[$method][$requestUri];
            }
        }

        $searches = array_keys(self::$patterns);
        $replaces = array_values(self::$patterns);
        foreach ($matchMethods as $method) {
            foreach (self::$matches[$method] ?? [] as $regexUri => $pos) {
                $pattern = str_replace($searches, $replaces, $regexUri);
                if (preg_match('#^' . $pattern . '$#', $requestUri, $matched)) {
                    // Remove $matched[0] as [1] is the first parameter.
                    array_shift($matched);
                    return $pos;
                }
            }
        }

        // Run the error callback if the request was not matched
        self::handleNotFound();
    }
}
