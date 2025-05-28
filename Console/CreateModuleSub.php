<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Vheins\LaravelModuleGenerator\Actions\CreateNavigation;

class CreateModuleSub extends Command
{
    public $module;

    public $fields;

    public $relation;

    public $db_only;

    protected $signature = 'create:module:sub {module} {name} {--fillable=} {--db-only} {--relation=}';

    protected $description = 'Create Module Scaffold';

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of sub-module will be attached.'],
            ['module', InputArgument::REQUIRED, 'The name of module will be attached.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['fillable', null, InputOption::VALUE_REQUIRED, 'The specified fields table.'],
            ['db-only', null, InputOption::VALUE_OPTIONAL, 'If true does not create form.'],
            ['relation', null, InputOption::VALUE_OPTIONAL, 'If true does not create form.'],
        ];
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->module = Str::studly($this->argument('module'));
        $this->name = Str::studly($this->argument('name'));
        $this->fields = $this->option('fillable');
        $this->db_only = $this->option('db-only');
        $this->relation = $this->option('relation');

        $this->createModule();
        $this->createMenu();
        $this->createModelFactorySeeder();

        if (! $this->db_only) {
            //Generate Controller
            $this->call('create:module:controller', [
                'controller' => $this->name,
                'module' => $this->module,
                '--api' => true,
            ]);

            //Generate Validation Request
            $this->call('create:module:request', [
                'name' => $this->name . 'Request',
                'module' => $this->module,
                '--fillable' => $this->fields,
            ]);

            //Generate Create Action
            $this->createActionStore();

            //Generate Update Action
            $this->createActionUpdate();

            //Generate Delete Action
            $this->createActionDelete();

            // create unique table
            $tableNames = explode('_', Str::of($this->module . $this->name)->snake());
            $splitNames = [];
            foreach ($tableNames as $tableName) {
                $splitNames[] = Str::of($tableName)->singular();
            }
            $unique = array_unique($splitNames);
            $unique = implode('-', $unique);
            $tableName = Str::of($unique)->plural();
            $permissions = Str::of($unique)->replace('-', '.');

            //Add New API Route
            $routeApiFile = config('modules.paths.modules') . '/'. $this->module . '/api.php';
            $routeApi = file_get_contents($routeApiFile);
            $routeClass = 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Controllers\\' . $this->name . 'Controller;';
            $contains = Str::contains($routeApi, $routeClass);
            if (! $contains) {
                $routeApi = str_replace('//add more class here ...', $routeClass . "\n//add more class here ...", $routeApi);
            }
            $routeText = "Route::apiResource('" . $this->apiUrl() . "', " . $this->name . "Controller::class, ['as' => '" . Str::of($this->module)->plural()->snake()->slug() . "']);";
            $contains = Str::contains($routeApi, $routeText);
            if (! $contains && $this->apiUrl() != '' && $this->apiUrl() != Str::of($this->module)->plural()->lower()->toString()) {
                $routeApi = str_replace('//add more route here ...', "//add more route here ...\n\t\t" . $routeText, $routeApi);
            }
            file_put_contents($routeApiFile, $routeApi);

            //Add Dashboard Link
        //     $dashboardLinkFile = config('modules.paths.modules') . '/'. $this->module . '/Vue/components/' . Str::of($this->module)->snake()->replace('_', '-') . '-dashboard-link.vue';
        //     if (File::exists($dashboardLinkFile)) {
        //         $dashboardLink = file_get_contents($dashboardLinkFile);
        //         $dashboardLink = str_replace('//add link here ...', "
        //                 {
        //                     title: '" . Str::headline($this->name) . "',
        //                     link: '/dashboard/" . $this->pageUrl() . "',
        //                     icon: 'BrandLaravelIcon',
        //                     permission: 'module." . $permissions . "',
        //                 },
        //                 //add link here ...
        // ", $dashboardLink);
        //         file_put_contents($dashboardLinkFile, $dashboardLink);
        //     }
        //     //Add Icon Tabs
        //     $iconTabFile = config('modules.paths.modules') . '/'. $this->module . '/Vue/components/' . Str::of($this->module)->snake()->replace('_', '-') . '-icon-tab.vue';
        //     if (File::exists($iconTabFile)) {
        //         $iconTab = file_get_contents($iconTabFile);
        //         $iconTab = str_replace('//add tabs here ...', "
        //         {
        //             title: '" . Str::headline($this->name) . "',
        //             link: '/dashboard/" . $this->pageUrl() . "',
        //             icon: 'BrandLaravelIcon',
        //             permission: 'module." . $permissions . "',
        //         },
        //         //add tabs here ...
        // ", $iconTab);
        //         file_put_contents($iconTabFile, $iconTab);
        //     }

            //Fix Controller File
            $controllerFile = config('modules.paths.modules') . '/'. $this->module . '/Controllers/' . Str::studly($this->name) . 'Controller.php';
            if (File::exists($controllerFile)) {
                $controller = file_get_contents($controllerFile);
                $controller = str_replace('$modelVar$', Str::camel($this->name), $controller);
                file_put_contents($controllerFile, $controller);
            }

            //Generate Vue
            $this->createFrontEnd();
        }

        //Clear Cache
        $this->call('optimize:clear');
    }

    private function pageUrl()
    {
        $module = $this->argument('module') . '_' . $this->argument('name');
        $tableNames = explode('_', $module);
        $splitNames = [];
        foreach ($tableNames as $tableName) {
            $splitNames[] = Str::of($tableName)->snake()->slug()->plural()->toString();
        }
        $unique = array_unique($splitNames);

        return implode('/', $unique);
    }

    private function apiUrl()
    {
        $module = $this->argument('name');
        $tableNames = explode('_', $module);
        $splitNames = [];
        foreach ($tableNames as $tableName) {
            $splitNames[] = Str::of($tableName)->snake()->slug()->plural()->toString();
        }
        $unique = array_unique($splitNames);

        return implode('/', $unique);
    }

    private function getForeign()
    {
        $foreign = [];
        if (! is_null($this->fields)) {
            foreach (explode(',', $this->fields) as $var) {
                $dataType = Str::lower(explode(':', $var)[1]);
                $textVar = Str::of(explode(':', $var)[0])->lower()->replace('_id', '')->toString();
                if (in_array($dataType, ['foreignuuid', 'foreignid'])) {
                    $foreign[] = $textVar;
                }
            }
        }

        return $foreign;
    }

    private function createActionStore()
    {
        $this->call('create:module:action', [
            'name' => $this->name . '/Store',
            'module' => $this->module,
        ]);

        // $foreign = $this->getForeign();
        //Create Store Action
        $storeActionFile = config('modules.paths.modules') . '/'. $this->module . '/Actions/' . $this->name . '/Store.php';
        $storeAction = file_get_contents($storeActionFile);
        $storeAction = str_replace('//use .. ;', 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $this->name . ";\nuse " . config('modules.namespace') . "\\$this->module\\Requests\\" . $this->name . 'Request;', $storeAction);
        $storeAction = str_replace('public function handle($handle)', 'public function handle(' . $this->name . 'Request $request): ' . $this->name, $storeAction);
        // if (count($foreign) > 0) {
        //     $storeAction = str_replace('// ..', '$request = Helper::mergeRequest(' . json_encode($foreign) . ', $request);' . "\n\t\t// ..", $storeAction);
        // }
        $storeAction = str_replace('// ..', '$fillable = app(' . $this->name . '::class)->getFillable();' . "\n\t\t" . '$handle = ' . $this->name . '::create($request->only($fillable));', $storeAction);
        $storeAction = str_replace('function () use ($request)', 'function () use ($request): ' . $this->name, $storeAction);
        file_put_contents($storeActionFile, $storeAction);
    }

    private function createActionUpdate()
    {
        $this->call('create:module:action', [
            'name' => $this->name . '/Update',
            'module' => $this->module,
        ]);
        //Create Update Action
        $updateActionFile = config('modules.paths.modules') . '/'. $this->module . '/Actions/' . $this->name . '/Update.php';
        $updateAction = file_get_contents($updateActionFile);
        $updateAction = str_replace('//use .. ;', 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $this->name . ";\nuse " . config('modules.namespace') . "\\$this->module\\Requests\\" . $this->name . 'Request;', $updateAction);
        $updateAction = str_replace('public function handle($handle)', 'public function handle(' . $this->name . 'Request $request, ' . $this->name . ' $' . Str::camel($this->name) . '): ' . $this->name, $updateAction);
        $updateAction = str_replace('// ..', '$fillable = app(' . $this->name . '::class)->getFillable();' . "\n\t\t" . '$' . Str::camel($this->name) . '->update($request->only($fillable));', $updateAction);
        $updateAction = str_replace('return $handle;', 'return $' . Str::camel($this->name) . ';', $updateAction);
        $updateAction = str_replace('function () use ($request)', 'function () use ($request, $' . Str::camel($this->name) . '): ' . $this->name, $updateAction);
        file_put_contents($updateActionFile, $updateAction);
    }

    private function createActionDelete()
    {
        $this->call('create:module:action', [
            'name' => $this->name . '/Delete',
            'module' => $this->module,
        ]);
        $deleteActionFile = config('modules.paths.modules') . '/'. $this->module . '/Actions/' . $this->name . '/Delete.php';
        $deleteAction = file_get_contents($deleteActionFile);
        $deleteAction = str_replace('//use .. ;', 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $this->name . ';', $deleteAction);
        $deleteAction = str_replace('public function handle($handle)', 'public function handle(' . $this->name . ' $' . Str::camel($this->name) . '): ?bool', $deleteAction);
        $deleteAction = str_replace('// ..', '$handle = $' . Str::camel($this->name) . '->delete();', $deleteAction);
        $deleteAction = str_replace('function () use ($request)', 'function () use ($' . Str::camel($this->name) . '): ?bool', $deleteAction);
        file_put_contents($deleteActionFile, $deleteAction);
    }

    private function createModule()
    {
        //Check if module exists
        if (! Module::collections()->has($this->module)) {
            //Generate Module
            $this->call('module:make', [
                'name' => [$this->module],
                '--api' => true,
            ]);
        }
    }

    private function createMenu()
    {
        if (! $this->db_only) {
            //Generate Vue
            $commands = [
                'create:module:vue:component:tab',
                // 'create:module:vue:component:link',
            ];
            foreach ($commands as $command) {
                $this->call($command, [
                    'name' => $this->name,
                    'module' => $this->module,
                    '--fillable' => $this->fields,
                ]);
            }

            CreateNavigation::run($this->module, $this->name);

            //Fix Route File
            $routeApiFile = config('modules.paths.modules') . '/'. $this->module . '/api.php';
            $routeApi = file_get_contents($routeApiFile);
            $routeApi = str_replace('$API_ROUTE$', Str::of($this->module)->snake()->slug()->plural()->lower(), $routeApi);
            file_put_contents($routeApiFile, $routeApi);
        }
    }

    private function createFrontEnd()
    {
        $commands = [
            'create:module:vue:store',
            'create:module:vue:page:index',
            'create:module:vue:page:new',
            'create:module:vue:page:view',
            'create:module:vue:component:form',
            // 'create:module:vue:component:filter',
        ];

        foreach ($commands as $command) {
            $this->call($command, [
                'name' => $this->module . $this->name,
                'module' => $this->module,
                '--fillable' => $this->fields,
            ]);
        }

        $this->createFilter();
    }

    private function createFilter(): void
    {
        $filters = [];
        $basePath = config('modules.paths.modules') . '/'. $this->module . '/Vue/filters/';
        $jsonPath = $basePath . Str::of($this->name)->snake()->slug()->lower() . '.json';
        File::ensureDirectoryExists($basePath);
        if (File::exists($jsonPath)) {
            $filters = json_decode(file_get_contents($jsonPath), true);
        }

        $relation = $this->relation;
        if(!empty($relation)){
            foreach ($relation as $key => $value) {
                if(in_array($key,['BelongsTo','HasOne'])){
                    foreach ($value as $v) {
                        $modules = Str::of($this->module)->plural()->headline()->slug()->lower();
                        $name = Str::of($v)->singular()->headline()->slug()->lower()->toString();
                        $names = Str::of($v)->plural()->headline()->slug()->lower()->toString();
                        $filters[] = [
                            'name' => $v,
                            'source' => "/{$modules}/queries/{$names}",
                            'field' => $name,
                            'type' => 'select',
                            'multiple' => true,
                            'selectLabel' => 'name',
                            'withParams' => false,
                            'expand' => false,
                        ];
                    }
                }
            }
        }

        file_put_contents($jsonPath, json_encode($filters,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function createModelFactorySeeder(): void
    {
        //Generate Model
        $this->call('create:module:model', [
            'model' => $this->name,
            'module' => $this->module,
            '--fillable' => $this->fields,
            '--migration' => true,
        ]);

        //Generate Factory
        $this->call('create:module:factory', [
            'name' => $this->name,
            'module' => $this->module,
            '--fillable' => $this->fields,
        ]);

        //Generate seeder
        $this->call('create:module:seeder', [
            'name' => $this->name,
            'module' => $this->module,
        ]);
    }
}
