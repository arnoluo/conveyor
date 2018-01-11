# Conveyor
## Intro
Conveyor is a PHP router, supports simple route group function.

This is a project base on https://github.com/noahbuscher/macaw, so that basic functions are still working well.

Most codes are rewrited to support route group. On this change you can:

Register available uri-prefix, class namespace and middleware initially;

Set different route prefix and namespace in each route group;

Set simple middlewares for every route.

## Install
If you have Composer, just include  as a project dependency in your composer.json. If you don't just install it by downloading the .ZIP file and extracting it to your project directory.

```
require: {
    "ween/conveyor": "dev-master"
}
```

## Usage
### First, `use` the Route namespace:

```PHP
use Conveyor\Route;
```

### Second and last, write the route(writing style just like Laravel):

#### Basic:

```PHP
Route::get('/', function() {
    return 'GET request';
});

Route::post('/', function() {
    return 'POST request';
});

Route::any('/', function() {
    return 'both GET and POST request';
});

Route::get('/', 'DemoControllers@method');

```

#### You can also use regex in Route, three can be recognized now:

```PHP
Route::get('/a/(:all)b/ab', function() {
    return 'I can receive all request uri like /a/abcb/ab, /a/123b/ab, /a/b/c/db/ab';
});

Route::get('/a/(:any)b/ab', function() {
    return 'I can receive all request uri like /a/ab/ab, /a/4b/ab, /a/a4b/ab';
});

Route::get('/a/(:num)b/ab', function() {
    return 'I can receive all request uri like /a/0123456789b/ab';
});

```

#### Route group function:

```PHP
/**
 * $params recognize three keywords now:
 * $params['prefix'] set a prefix uri for current group;
 * $params['namespace'] set the namespace for current group, so that class can be autoloaded with PSR-4;
 * $params['middleware'] set some registered middlewares before call final action.
 */
Route::group(array $params, closure $callback);
```

> In a group tree, only the first namespace will work.


```PHP
Route::group(['prefix' => '/user', 'namespace' => 'App\\Controller\\'], function() {

    Route::group(['prefix' => '/sub1', 'namespace' => 'Bpp\\Controller\\', 'middleware' => 'foo, bar'], function() {
        
        Route::get('/', function() {
            return 'I can receive uri like /user/sub1';
        });

        // Request /user/sub1/abc will load class DemoController in namespace App\\Controller\\
        // Middleware will not work because no registered middleware exist.
        Route::get('/abc', 'DemoController@method');
    });

    Route::group(['prefix' => '/sub2'], function() {
        
        Route::get('/', function() {
            return 'I can receive uri like /user/sub2/';
        });

        Route::get('/a(:all)', function() {
            return 'I can receive all request uri start with /user/sub2/a';
        });
    });

});

```
If you do not need middleware function, the above codes are enough. 
If not, please read the Extension below.

### Extension
There are some useful functions provided by Conveyor:

Registe some common properties:
> register need be called before writing router.
```PHP
Route::register([
    'prefix' => '',
    'namespace' => 'App\\Controllers\\',
    'middlewarePath' => [
        //'alias' => \Namespace\Class::class;
        'abc' => \Foo\Bar\A::class,
        'home' => \App\Middlewares\HomeMiddleware::class,
    ],
    // just registered alias are valid; 
    'middleware' => 'abc, home'
]);
```

Rewrite 404 notice when route dispatch failed:
```PHP
Route::error(function() {
  return '404 :: Not Found';
});
```

Define a common namespace initially, instead of writing group params:
> If you call this function first, all namespace parameter in `Route::group()` will not work;
```PHP
Route::namespace('App\\Controller\\');
```

Set some middlewares for a single route:
```PHP
Route::get('abc', 'HomeController@demo')->middleware('foo, bar');
```

### Example for middleware:

yourRoute.php:
```PHP
<?php

use Conveyor\Route;

Route::register([
    'namespace' => 'App\\Controllers\\',
    'middlewarePath' => [
        'home' => \App\Middlewares\HomeMiddleware::class,
    ]
]);

// some routes...

Route::get('abc', 'HomeController@demo')->middleware('home');
```
HomeMiddleware.php
> Conveyor will run middleware as HomeMiddleware->handle(); So every middleware need a `handle()` method.
```PHP
<?php

namespace App\Controllers;

class HomeMiddleware
{
    public function handle()
    {
        // $result = 'result of your milldeware validation';
        if ($result) {
            // if validation success, just return true
            return true;
        } else {
            // if failed ,please return a error-info array
            return ['result' => 'error', 'msg' => 'not good'];
        }
    }
}
```
