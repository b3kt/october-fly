OctoberFly aims to make OctoberCMS faster by using Swoole extension.

## Version Compatibility

- OctoberCMS (Laravel 5.5.* under the hood)
- Swoole >4.0

Fork of [LaravelFly](https://github.com/scil/LaravelFly)

## PHP Setup Requirements

1. Install swoole extension
```pecl install swoole```

Make sure swoole is included in php.ini file.
```extension=swoole.so```

Also Suggested:
```pecl install inotify```

2. `composer require "tamerhassan/october-fly":"dev-master"`

## Quick Start

1. Add the following line to your 'providers' array in `config/app.php`
```
'LaravelFly\Providers\ServiceProvider',
```

2. Publish server config
```
php artisan vendor:publish --tag=fly-server
```

3. Publish app config 
```
php artisan vendor:publish --tag=fly-app
```

4. Edit `vendor/october/rain/src/Foundation/Http/Kernel.php` so that it begins like this:
```
<?php namespace October\Rain\Foundation\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

if (defined('LARAVELFLY_MODE')) {
    if (LARAVELFLY_MODE == 'Map') {
        class WhichKernel extends \LaravelFly\Map\Kernel { }
    }elseif (LARAVELFLY_MODE == 'Backup') {
        class WhichKernel extends \LaravelFly\Backup\Kernel { }
    } elseif (LARAVELFLY_MODE == 'FpmLike') {
        class WhichKernel extends HttpKernel{}
    }
} else {
    class WhichKernel extends HttpKernel
    {
        /**
         * The bootstrap classes for the application.
         *
         * @var array
         */
       protected $bootstrappers = [
           '\October\Rain\Foundation\Bootstrap\RegisterClassLoader',
           '\October\Rain\Foundation\Bootstrap\LoadEnvironmentVariables',
           '\October\Rain\Foundation\Bootstrap\LoadConfiguration',
           '\October\Rain\Foundation\Bootstrap\LoadTranslation',
           \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
           \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
           '\October\Rain\Foundation\Bootstrap\RegisterOctober',
           \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
           \Illuminate\Foundation\Bootstrap\BootProviders::class,
       ];
    }
}

class Kernel extends WhichKernel
//class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected $middleware = [
        'Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode',
    ];
...
```

5. Finally you can start the server:
```
php vendor/scil/laravel-fly/bin/fly start
```

---

# LaravelFly Original Readme:
---

LaravelFly speeds up our existing Laravel projects without data pollution and memory leak, and make Tinker to be used online (use tinker while Laravel is responding requests from browsers).

Thanks to [Laravel](http://laravel.com/), [Swoole](https://github.com/swoole/swoole-src) and [PsySh](https://github.com/bobthecow/psysh)

## Version Compatibility

- Laravel 5.6.* (just some changes in src/fly to port to 5.5)
- Swoole >4.0

## Features

- Same codes can run on PHP-FPM or LaravelFly

- To be absolutely safe, put your code under control. Coroutine is fully supported (code execution can jump from one request to another).

- Laravel services or any other objexts can be made before any requests. There are two types:
  - be configurable to serve in multiple requests (only one instance of the service). LaravelFly named it  **WORKER SERVICE/OBJECT** or **COROUTINE-FRIENDLY SERVICE/OBJECT**.
  - to be cloned in each request (one instance in one request).LaravelFly named it **CLONE SERVICE/OBJECT**. This way is simple, but often has the problem [Stale Reference](https://github.com/scil/LaravelFly/wiki/clone-and-Stale-Reference). This type is used rarely by LaravelFly, while used widely by [laravel-swoole](https://github.com/swooletw/laravel-swoole) and [laravel-s](https://github.com/hhxsv5/laravel-s).

- Extra speed improvements such as middlewares cache, view path cache.

- Check server info at /laravel-fly/info. (This feture is under dev and more infomations will be available.)

## Quick Start

1.`pecl install swoole`   
Make sure `extension=swoole.so` in config file for php cli.   
Suggest: `pecl install inotify`   

2.`composer require "scil/laravel-fly":"dev-master"`

3.`php vendor/scil/laravel-fly/bin/fly start`   
If you enable `eval(tinker())` and see an error about mkdir, you can start LaravelFly with sudo.

Now, your project is flying and listening to port 9501. Enjoy yourself.

## Doc

[Configuration](https://github.com/scil/LaravelFly/wiki/Configuration)

[Commands: Start, Reload & Debug](https://github.com/scil/LaravelFly/wiki/Commands)

[Coding Guideline](https://github.com/scil/LaravelFly/wiki/Coding-Requirement)

[Events about LaravelFly](doc/events.md)

[Using tinker when Laravel Working](doc/tinker.md)

[For Dev](doc/dev.md)

## A simple ab test

 `ab -k -n 1000 -c 10 http://zc.test

.   | fpm  | Fly (Mode Map)
------------ | ------------ | -------------
Requests per second   | 3    | 34
Time taken ≈ | 325  | 30
  50%  | 2538  | 126
  80%  | 3213  | 187
  99%  | 38584 | 3903

<details>
<summary>Test Env</summary>
<div>


* A visit to http://zc.test relates to 5 Models and 5 db query.
* env:   
  - ubuntu 16.04 on virtualbox ( 2 CPU: i5-2450M 2.50GHz ; Memory: 1G  )  
  - php7.1 + opcache + 5 workers for both fpm and laravelfly ( phpfpm : pm=static  pm.max_children=5)
  - 'max_conn' => 1024
* Test date : 2018/02

</div>
</details>

## LaravelFly Usability

It can be installed on your existing projects without affecting nginx/apache server, that's to say, you can run LaravelFly server and nginx/apache server simultaneously to run the same laravel project.

The nginx conf [swoole_fallback_to_phpfpm.conf](config/swoole_fallback_to_phpfpm.conf) allow you use LaravelFlyServer as the primary server, and the phpfpm as a backup server which will be passed requests when the LaravelFlyServer is unavailable. .

Another nginx conf [use_swoole_or_fpm_depending_on_clients](config/use_swoole_or_fpm_depending_on_clients.conf) allows us use query string `?useserver=<swoole|fpm|...` to select the server between swoole or fpm. That's wonderful for test, such as to use eval(tinker()) as a online debugger for your fpm-supported projects.

## Similar projects that mix swoole and laravel

### 1. [laravel-swoole](https://github.com/swooletw/laravel-swoole)

It is alse a safe sollution. It is light.It has supported Lumen and websocket. Its doc is great and also useful for LaravelFly.   

The first difference is that laravel-swoole's configuration is based on service,like log, view while LaravelFly is based on service providers like LogServiceProvider, ViewServiceProvider.(In Mode Map some providers are registered and booted before any requests, in Mode Backup, providers are registered before any requests, )

The main difference is that in laravel-swoole user's code will be processed by a new `app` cloned from SwooleTW\Http\Server\Application::$application and laravel-swoole updates related container bindings to the new app. However in LaravelFly, the sandbox is not a new app, but an item in the $corDict of the unique application container. In LaravelFly, most other objects such as `app`, `event`.... always keep one object in a worker process, `clone` is used only to create `url` (and `routes` when LARAVELFLY_SERVICES['routes'] ===false ) in Mode Map. LaravelFly makes most of laravel objects keep safe on its own. It's about high cohesion & low coupling and the granularity is at the level of app container or services/objects. For users of laravel-swoole, it's a big challenge to handle the relations of multiple packages and objects which to be booted before any requests. Read [Stale Reference](https://github.com/scil/LaravelFly/wiki/clone-and-Stale-Reference).

 .  | speed |technique | every service is in control |  every service provider is in control | work to maintaining relations of cloned objects to avoid Stale Reference
------------ |------------ | ------------ | ------------- | ------------- | -------------
laravel-swoole  | slow | clone app contaniner and objects to make them safe |  yes | no | more work (app,event...are cloned)
LaravelFly Mode Map | fast | refactor most official objects to make them safe on their own |  yes  | yes  | few work (only url is cloned by default)

### 2. [laravel-s](https://github.com/hhxsv5/laravel-s)

Many great features!

About data pollution? Same technique and problems as laravel-swoole. And neither support coroutine jumping (from one request to another request).


## Todo About Improvement

- [x] Pre-include. Server configs 'pre_include' and 'pre_files'.
- [x] Server config 'early_laravel'
- [x] Cache for LaravelFly app config. laravelfly_ps_map.php or laravelfly_ps_simple.php located bootstrap/cache
- [x] Cache for Log. Server options 'log_cache'.
- [x] Watching maintenance mode using swoole_event_add. No need to check file storage/framework/down in every request.
- [x] Cache for kernel middlewares objects. Kernel::getParsedKernelMiddlewares, only when LARAVELFLY_SERVICES['kernel'] is true.
- [x] Cache for route middlewares. $cacheByRoute in Router::gatherRouteMiddleware, only useful when all route middleaes are reg on worker.
- [x] Cache for route middlewares objects. config('laravelfly.singleton_route_middlewares') and $cacheForObj in Router::gatherRouteMiddleware, avoid creating instances repeatly.
- [x] Cache for terminateMiddleware objects.
- [x] Cache for event listeners. $listenersStalbe in LaravelFly\Map\IlluminateBase\Dispatcher
- [x] Cache for view compiled path. LARAVELFLY_SERVICES['view.finder'] or  App config 'view_compile_1'
- [x] Mysql coroutine. Old code dropped, laravel-s used.
- [ ] Mysql connection pool
- [ ] event: wildcardsCache? keep in memory，no clean?
- [ ] Converting between swoole request/response and Laravel Request/Response
- [ ] safe: auth, remove some props?

## Other Todo

- [x] add events
- [x] watch code changes and hot reload
- [x] supply server info. default url is: /laravel-fly/info
- [ ] add tests about auth SessionGuard: Illuminate/Auth/SessionGuard.php with uses Request::createFromGlobals
- [ ] add tests about uploaded file, related symfony/http-foundation files: File/UploadedFile.php  and FileBag.php(fixPhpFilesArray)
- [ ] websocket
- [ ] send file
- [ ] travis, static analyze like phan, phpstan or https://github.com/exakat/php-static-analysis-tools
- [ ] decrease worker ready time
- [ ] cache fly
