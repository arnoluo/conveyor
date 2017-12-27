# Conveyor
## Intro
Conveyor is a PHP router, supports simple route group function.

This is a project base on https://github.com/noahbuscher/macaw, so that basic functions are still working well. Some code are rewrited to support route group, on this change you can set different route prefix and namespace in each route group.

Middleware support like Laravel in route group is on the schedule.

## Install
Like [Macaw](https://github.com/noahbuscher/macaw), if you have Composer, just include  as a project dependency in your composer.json. If you don't just install it by downloading the .ZIP file and extracting it to your project directory.

```
require: {
    "arnoluo/conveyor": "dev-master"
}
```

## Usage
### First, `use` the Route namespace:

```PHP
use Conveyor\Route;
```

### Second, common setting:
You can rewrite 404 notice when request uri are not catched:
```PHP
Route::error(function() {
  echo '404 :: Not Found';
});
```

You can alse define a common namespace, instead of writing group params:
> If you call this function, all namespace parameter in `Route::group();` will not work;
```PHP
Route::nameSpace('App\\Controller\\');
```

### Third, write the route(writing style just like Laravel):

```PHP
Route::get('/', function() {
    echo 'GET request';
});

Route::post('/', function() {
    echo 'POST request';
});

Route::any('/', function() {
    echo 'both GET and POST request';
});

Route::get('/', 'DemoControllers@method');

```

You can also use regex in Route, three can be recognized now:

```PHP
Route::get('/a/(:all)b/ab', function() {
    echo 'I can receive all request uri like /a/abcb/ab, /a/123b/ab, /a/b/c/db/ab';
});

Route::get('/a/(:any)b/ab', function() {
    echo 'I can receive all request uri like /a/ab/ab, /a/4b/ab, /a/a4b/ab';
});

Route::get('/a/(:num)b/ab', function() {
    echo 'I can receive all request uri like /a/0123456789b/ab';
});

```

Route function:
> `Route::group(array $params, closure $callback);`
  `$params` only recognize two keywords now: 'prefix' and 'namespace':
  `$params['prefix']` set a prefix uri for each group, it will be inherited in the subgroup;
  `$params['namespace']` set the controller namespace for each group, so that class can be autoloaded with PSR-4. Only the first namespace is vaild in a group tree.
  `$callback` a callable function.

```PHP
Route::group(['prefix' => '/user', 'namespace' => 'App\\Controller\\'], function() {

    Route::group(['prefix' => '/sub1', 'namespace' => 'Bpp\\Controller\\'], function() {
        Route::get('/', function() {
            echo 'I can receive uri like /user/sub1';
        });

        // Request /user/sub1/abc will load class DemoController in namespace App\\Controller\\
        Route::get('/abc', 'DemoController@method');
    });

    Route::group(['prefix' => '/sub2'], function() {
        Route::get('/', function() {
            echo 'I can receive uri like /user/sub2/';
        });

        Route::get('/a(:all)', function() {
            echo 'I can receive all request uri start with /user/sub2/a';
        });
    });

});

```

### Last, route dispatch:

```PHP
Route::dispatch();
```