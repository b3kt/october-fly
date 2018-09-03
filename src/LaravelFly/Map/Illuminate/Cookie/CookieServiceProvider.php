<?php

namespace LaravelFly\Map\Illuminate\Cookie;

use October\Rain\Support\ServiceProvider;

class CookieServiceProvider extends ServiceProvider
{
    static public function coroutineFriendlyServices():array
    {
        /**
         * CookieJar's path, domain, secure and sameSite  are not rewriten to be a full COROUTINE-FRIENDLY SERVICE.
         * so this provider requires there values always be same in all requests.
         */
        return ['cookie'];
    }
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('cookie', function ($app) {
            $config = $app->make('config')->get('session');

            $class = LARAVELFLY_SERVICES['cookie']? CookieJarSame::class: CookieJar::class;
            return (new $class)->setDefaultPathAndDomain(
                $config['path'], $config['domain'], $config['secure'], $config['same_site'] ?? null
            );
        });
    }
}
