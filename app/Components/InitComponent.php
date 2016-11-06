<?php

namespace App\Components;

use App\BladeDirectives;
use App\Images;
use App\Services\OptionsService;
use App\Services\TranslatorService;
use App\Strategies\OptionsStrategy;
use App\Strategies\TranslatorStrategy;
use Greg\Application;
use Greg\Cache\CacheManager;
use Greg\Orm\Driver\DriverInterface;
use Greg\Orm\Driver\Mysql;
use Greg\StaticImage\ImageCollector;
use Greg\Support\Http\Request;
use Greg\Support\Http\Response;
use Greg\View\ViewBladeCompiler;
use Greg\View\Viewer;
use Intervention\Image\ImageManager;

class InitComponent
{
    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function initViewer()
    {
        $this->app->inject(Viewer::class, function () {
            $class = new Viewer($this->app->basePath() . '/resources/views');

            $class->addExtension('.blade.php', function (Viewer $viewer) {
                $compiler = new ViewBladeCompiler($viewer, $this->app->basePath() . '/storage/views');

                $directives = new BladeDirectives($this->app, $compiler);

                $directives->load();

                return $compiler;
            });

            return $class;
        });
    }

    public function initStrategies()
    {
        $this->app->inject(OptionsStrategy::class, OptionsService::class);

        $this->app->inject(TranslatorStrategy::class, TranslatorService::class);
    }

    public function initCache()
    {
        $this->app->inject(CacheManager::class, function () {
            $manager = new CacheManager();

            foreach ($this->app->getIndexArray('cache.containers') as $name => $container) {
                $manager->register($name, function () use ($container) {
                    return $this->app->load(...(array) $container);
                });
            }

            if ($defaultContainer = $this->app->getIndex('cache.default_container')) {
                $manager->setDefaultContainerName($defaultContainer);
            }

            return $manager;
        });

        if (Request::hasGet('clear')) {
            $this->app->scope(function (CacheManager $manager) {
                $manager->delete();
            });

            if (!Request::hasGet('no')) {
                Response::sendBack();

                die;
            }
        }
    }

    public function initDb()
    {
        $this->app->inject(DriverInterface::class, function () {
            return new Mysql(
                $this->app->getIndex('db.dsn'),
                $this->app->getIndex('db.username'),
                $this->app->getIndex('db.password'),
                $this->app->getIndex('db.options')
            );
        });
    }

    public function initImageCollector()
    {
        $this->app->inject(ImageCollector::class, function () {
            $publicPath = $this->app->publicPath();

            $collector = new ImageCollector(new ImageManager(), $publicPath, $publicPath . '/static', '/static');

            $images = new Images($collector);

            $images->load();

            return $collector;
        });
    }
}
