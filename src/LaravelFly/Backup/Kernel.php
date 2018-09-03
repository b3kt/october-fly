<?php

namespace LaravelFly\Backup;


use Exception;
use Throwable;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Foundation\Http\Events;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{

    protected $bootstrappers = [
        '\October\Rain\Foundation\Bootstrap\RegisterClassLoader',
        '\October\Rain\Foundation\Bootstrap\LoadEnvironmentVariables',

//        \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
        // must placed before RegisterProviders, because it change config('app.providers')
        \LaravelFly\Backup\Bootstrap\LoadConfiguration::class,

        '\October\Rain\Foundation\Bootstrap\LoadTranslation',

        \Illuminate\Foundation\Bootstrap\HandleExceptions::class,

        \Illuminate\Foundation\Bootstrap\RegisterFacades::class,

        '\October\Rain\Foundation\Bootstrap\RegisterOctober',

        \Illuminate\Foundation\Bootstrap\RegisterProviders::class,

        // replaced by `$this->app->bootProvidersInRequest();`
        // \Illuminate\Foundation\Bootstrap\BootProviders::class,

        \LaravelFly\Backup\Bootstrap\SetBackupForBaseServices::class,
        \LaravelFly\Backup\Bootstrap\BackupConfigs::class,
        \LaravelFly\Backup\Bootstrap\BackupAttributes::class,
    ];
    /**
     * The application implementation.
     *
     * @var \LaravelFly\Backup\Application
     */
    protected $app;

    /**
     * Override
     */
    protected function sendRequestThroughRouter($request)
    {
        $this->app->instance('request', $request);

        // moved to Application::restoreAfterRequest
        // Facade::clearResolvedInstance('request');

        // replace $this->bootstrap();
        $this->app->boot();

        return (new Pipeline($this->app))
            ->send($request)
            ->through($this->app->shouldSkipMiddleware() ? [] : $this->middleware)
            ->then($this->dispatchToRouter());

    }
    /**
     * Override
     */
    public function handle($request)
    {
        try {
            // moved to LaravelFlyServer::initAfterStart
            // $request::enableHttpMethodParameterOverride();

            $response = $this->sendRequestThroughRouter($request);

        } catch (Exception $e) {
            $this->reportException($e);

            $response = $this->renderException($request, $e);
        } catch (Throwable $e) {

            $this->reportException($e = new FatalThrowableError($e));

            $response = $this->renderException($request, $e);
        }

        $this->app['events']->dispatch(
            new Events\RequestHandled($request, $response)
        );

        return $response;
    }


}
