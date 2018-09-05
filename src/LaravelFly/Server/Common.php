<?php

namespace LaravelFly\Server;

use swoole_atomic;
use Symfony\Component\EventDispatcher\EventDispatcher;

// this is necessary for const mapFlyFiles where LARAVELFLY_SERVICES is used
if (!defined('LARAVELFLY_SERVICES')) include __DIR__ . '/../../../config/laravelfly-server-config.example.php';

class Common
{
    use Traits\DispatchRequestByQuery;
    use Traits\Preloader;
    use Traits\Tinker;
    use Traits\Worker;
    use Traits\Laravel;
    use Traits\ShareInteger;

    /**
     * where laravel app located
     * @var string
     */
    protected $root;

    /**
     * @var array
     */
    protected $options;

    static $laravelMainVersion;

    const mapFlyFiles = [
        'Container.php' =>
            '/vendor/laravel/framework/src/Illuminate/Container/Container.php',
        'Application.php' =>
            '/vendor/laravel/framework/src/Illuminate/Foundation/Application.php',
        'ServiceProvider.php' =>
            '/vendor/laravel/framework/src/Illuminate/Support/ServiceProvider.php',
        'Router.php' =>
            '/vendor/laravel/framework/src/Illuminate/Routing/Router.php',
        'ViewConcerns/ManagesComponents.php' =>
            '/vendor/laravel/framework/src/Illuminate/View/Concerns/ManagesComponents.php',
        'ViewConcerns/ManagesLayouts.php' =>
            '/vendor/laravel/framework/src/Illuminate/View/Concerns/ManagesLayouts.php',
        'ViewConcerns/ManagesLoops.php' =>
            '/vendor/laravel/framework/src/Illuminate/View/Concerns/ManagesLoops.php',
        'ViewConcerns/ManagesStacks.php' =>
            '/vendor/laravel/framework/src/Illuminate/View/Concerns/ManagesStacks.php',
        'ViewConcerns/ManagesTranslations.php' =>
            '/vendor/laravel/framework/src/Illuminate/View/Concerns/ManagesTranslations.php',
        'Facade.php' =>
            '/vendor/laravel/framework/src/Illuminate/Support/Facades/Facade.php',

        // otherwise on each boot of PaginationServiceProvider and NotificationServiceProvider,view paths would be appended to app('view')->finder->hints
        // by  $this->loadViewsFrom forever
        'FileViewFinder' . (LARAVELFLY_SERVICES['view.finder'] ? 'SameView' : '') . '.php' =>
            '/vendor/laravel/framework/src/Illuminate/View/FileViewFinder.php',

    ];
    protected static $conditionFlyFiles = [
        'log_cache' => [
            'StreamHandler.php' =>
                '/vendor/monolog/monolog/src/Monolog/Handler/StreamHandler.php',
        ],
        'config' => [
            'Config/Repository.php' =>
                '/vendor/october/rain/src/Config/Repository.php'

        ]
    ];


    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var \swoole_http_server
     */
    var $swoole;

    /**
     * @var string
     */
    protected $appClass = '\LaravelFly\Map\Application';

    /**
     * @var string
     */
    protected $kernelClass = \LaravelFly\Kernel::class;


    protected $colorize = true;

    /**
     * log message level
     *
     * 0: ERR
     * 1: ERR, WARN
     * 2: ERR, WARN, NOTE
     * 3: ERR, WARN, NOTE, INFO
     */
    protected $echoLevel = 3;

    public function __construct(EventDispatcher $dispatcher = null)
    {
        $this->dispatcher = $dispatcher ?: new EventDispatcher();

        $this->root = realpath(__DIR__ . '/../../../../../..');

        if (!(is_dir($this->root) && is_file($this->root . '/bootstrap/app.php'))) {
            die("This doc root is not for a Laravel app: {$this->root} \n");
        }

    }

    public function config(array $options)
    {
        if (empty($options['mode'])) $options['mode'] = LARAVELFLY_MODE;

        $this->options = array_merge($this->getDefaultConfig(), $options);

        $this->parseOptions($this->options);

    }

    public function getDefaultConfig()
    {
        // why @ ? For the errors like : Constant LARAVELFLY_MODE already defined
        $d = @ include __DIR__ . '/../../../config/laravelfly-server-config.example.php';

        return array_merge([
            'mode' => 'Map',
            'conf' => null, // server config file
        ], $d);
    }

    public function getConfig($name = null)
    {
        if (is_string($name)) {
            return $this->options[$name] ?? null;
        }
        return $this->options;

    }

    protected function parseOptions(array &$options)
    {
        static::includeFlyFiles($options);

        // as earlier as possible
        if ($options['pre_include'])
            $this->preInclude();

        if (isset($options['pid_file'])) {
            $options['pid_file'] .= '-' . $options['listen_port'];
        } else {
            $options['pid_file'] = $this->root . '/bootstrap/laravel-fly-' . $options['listen_port'] . '.pid';
        }

        $appClass = '\LaravelFly\\' . $options['mode'] . '\Application';

        if (class_exists($appClass)) {
            $this->appClass = $appClass;
        } else {
            $this->echo("Mode in config file not valid, no appClass $appClass\n", 'WARN', true);
        }

        if (LARAVELFLY_MODE === 'Backup') {
            $this->kernelClass = \LaravelFly\Backup\Kernel::class;
        } elseif (LARAVELFLY_MODE === 'Map') {
            $this->kernelClass = \LaravelFly\Map\Kernel::class;
        } else {
            $this->kernelClass = \LaravelFly\Kernel::class;
            $this->echo("OctoberFly default kernel used: $kernelClass,\n".
                        "please edit bootstrap/app.php as in the documentation.",
                        'WARN', true);
        }

        $this->prepareTinker($options);

        $this->dispatchRequestByQuery($options);
    }

    static function getApplicationVersion($full = false, $laravelProjectRoot = null): ?string
    {
        if (static::$laravelMainVersion) return static::$laravelMainVersion;

        $laravelProjectRoot = $laravelProjectRoot ?: realpath(__DIR__ .
            '/../../../../../..');
        $file = $laravelProjectRoot. '/vendor/laravel/framework/src/Illuminate/Foundation/Application.php';

        if (!is_file($file)) return null;

        if (preg_match("/const VERSION = '(\d+)\.(\d+)\.(\d+)';/", file_get_contents($file), $r)) {
            static::$laravelMainVersion = "$r[1].$r[2]";
            return $full ? "$r[1].$r[2].$r[3]" : "$r[1].$r[2]";
        }
        return null;
    }

    static function includeFlyFiles(&$options)
    {
	    if (LARAVELFLY_MODE === 'FpmLike') return;

        $v = static::getApplicationVersion();

        $flyBaseDir = __DIR__ . '/../../fly/' . $v . '/';

        if (!is_dir($flyBaseDir))
            die("[ERROR] refactor not made for current Laravel version $v.\n");

        // all fly files are for Mode Map, except Config/SimpleRepository.php for Mode Backup
        if (empty(LARAVELFLY_SERVICES['config']))
            include_once $flyBaseDir . 'Config/' . (LARAVELFLY_MODE === 'Map' ? '' : 'Backup') . 'Repository.php';

        static $mapLoaded = false;
        static $logLoaded = false;

        if ($options['mode'] === 'Map' && !$mapLoaded) {

            $mapLoaded = true;

            if (empty(LARAVELFLY_SERVICES['kernel']))
                include_once $flyBaseDir . '../Kernel.php';

            foreach (static::mapFlyFiles as $f => $offical) {
                require $flyBaseDir . $f;
            }

        }

        if ($logLoaded) return;

        $logLoaded = true;

        if (is_int($options['log_cache']) && $options['log_cache'] > 1) {

            foreach (static::$conditionFlyFiles['log_cache'] as $f => $offical) {
                require $flyBaseDir . $f;
            }

        } else {

            $options['log_cache'] = false;
        }

    }

    public function createSwooleServer(): \swoole_http_server
    {
        $options = $this->options;

        if ($options['early_laravel']) $this->startLaravel();

        if ($this->options['daemonize'])
            $this->colorize = false;

        if ($this->options['echo_level'])
            $this->echoLevel = (int)$this->options['echo_level'];

        $this->swoole = $swoole = new \swoole_http_server($options['listen_ip'], $options['listen_port']);

        $options['enable_coroutine'] = false;

        $swoole->set($options);

        $this->setListeners();

        $swoole->fly = $this;

        return $swoole;
    }

    public function setListeners()
    {
        $this->swoole->on('WorkerStart', array($this, 'onWorkerStart'));

        $this->swoole->on('WorkerStop', array($this, 'onWorkerStop'));

        $this->swoole->on('Request', array($this, 'onRequest'));

    }

    function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
    }

    public function start()
    {
        if (is_callable($this->options['before_start_func'])) {
            $this->options['before_start_func']->call($this);
        }

        if (!method_exists('\Co', 'getUid'))
            die("[ERROR] pecl install swoole or enable swoole.use_shortname.\n");


        if ($this->getConfig('watch_down')) {

            $this->newIntegerMemory('isDown', new swoole_atomic(0));
        }

        try {

            $this->swoole->start();

        } catch (\Throwable $e) {

            die("[ERROR] swoole server started failed: {$e->getMessage()} \n");

        }
    }

    /**
     * @return EventDispatcher
     */
    public function getDispatcher(): EventDispatcher
    {
        return $this->dispatcher;
    }

    static function getAllFlyMap()
    {
        $r = static::mapFlyFiles;

        foreach (static::$conditionFlyFiles as $map) {
            $r = array_merge($r, $map);
        }
        return $r;
    }

    public function getSwooleServer(): \swoole_server
    {
        return $this->swoole;
    }

    public function path($path = null): string
    {
        return $path ? "{$this->root}/$path" : $this->root;
    }


    function echo($text, $status = 'INFO', $color = false)
    {
        switch ($status) {
            case 'INFO':
                $level = 3;
                break;
            case 'NOTE':
                $level = 2;
                break;
            case 'WARN':
                $level = 1;
                break;
            case 'ERR':
                $level = 0;
                break;
            default:
                $level = 0;
        }
        if ($level <= $this->echoLevel) {
            $text = "[$status] $text\n";
            echo $color ? $this->colorize($text, $status) : $text;
        }

    }

    function colorize($text, $status)
    {
        if (!$this->colorize) return $text;

        $out = "";
        switch ($status) {
            case "WARN":
                $out = "[41m"; //Red background
                break;
            case "NOTE":
                $out = "[43m"; //Yellow background
                // $out = "[44m"; //Blue background
                break;
            case "SUCCESS":
                $out = "[42m"; //Green background
                break;
            case "ERR":
                $out = "[41m"; //Red background
                break;
            default:
                throw new Exception("Invalid status: " . $status);
        }
        return chr(27) . "$out" . "$text" . chr(27) . "[0m";
    }

}
