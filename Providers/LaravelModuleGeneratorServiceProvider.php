<?php

namespace Vheins\LaravelModuleGenerator\Providers;

use Illuminate\Support\ServiceProvider;
use Lorisleiva\Actions\Facades\Actions;
use Vheins\LaravelModuleGenerator\Actions\CreatePostmanCollection;
use Vheins\LaravelModuleGenerator\Console\CreateApiCrud;
use Vheins\LaravelModuleGenerator\Console\CreateModule;
use Vheins\LaravelModuleGenerator\Console\CreateModuleAction;
use Vheins\LaravelModuleGenerator\Console\CreateModuleController;
use Vheins\LaravelModuleGenerator\Console\CreateModuleFactory;
use Vheins\LaravelModuleGenerator\Console\CreateModuleMigration;
use Vheins\LaravelModuleGenerator\Console\CreateModuleModel;
use Vheins\LaravelModuleGenerator\Console\CreateModuleRequest;
use Vheins\LaravelModuleGenerator\Console\CreateModuleSeeder;
use Vheins\LaravelModuleGenerator\Console\CreateModuleSub;
use Vheins\LaravelModuleGenerator\Console\CreateModuleVueComponentFilter;
use Vheins\LaravelModuleGenerator\Console\CreateModuleVueComponentForm;
use Vheins\LaravelModuleGenerator\Console\CreateModuleVueComponentLink;
use Vheins\LaravelModuleGenerator\Console\CreateModuleVueComponentTab;
use Vheins\LaravelModuleGenerator\Console\CreateModuleVuePageCreate;
use Vheins\LaravelModuleGenerator\Console\CreateModuleVuePageIndex;
use Vheins\LaravelModuleGenerator\Console\CreateModuleVuePageView;
use Vheins\LaravelModuleGenerator\Console\CreateModuleVueStore;

class LaravelModuleGeneratorServiceProvider extends ServiceProvider
{
    /**
     * @var string
     */
    protected $moduleName = 'LaravelModuleGenerator';

    /**
     * @var string
     */
    protected $moduleNameLower = 'laravel-module-generator';

    public $actions = [
        CreateApiCrud::class,
        CreateModule::class,
        CreateModuleAction::class,
        CreateModuleController::class,
        CreateModuleMigration::class,
        CreateModuleModel::class,
        CreateModuleFactory::class,
        CreateModuleRequest::class,
        CreateModuleSub::class,
        CreateModuleVueComponentFilter::class,
        CreateModuleVueComponentForm::class,
        CreateModuleVueComponentLink::class,
        CreateModuleVueComponentTab::class,
        CreateModuleVuePageCreate::class,
        CreateModuleVuePageIndex::class,
        CreateModuleVuePageView::class,
        CreateModuleVueStore::class,
        CreateModuleSeeder::class,
        CreatePostmanCollection::class,
    ];

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        if ($this->app->runningInConsole()) {
            foreach ($this->actions as $class) {
                Actions::registerCommandsForAction($class);
            }
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([__DIR__ . '/../laravel-module-generator.php' => config_path('laravel-module-generator.php')], 'config');
        $this->mergeConfigFrom(__DIR__ . '/../laravel-module-generator.php', 'laravel-module-generator');

        $this->publishes([__DIR__ . '/../modules.php' => config_path('modules.php')], 'config');
        $this->mergeConfigFrom(__DIR__ . '/../modules.php', 'modules');

        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs'),
        ], 'stubs');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
