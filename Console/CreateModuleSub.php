<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreateModuleSub extends Command
{
    public $module, $fields, $db_only;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:module:sub {module} {name} {--fillable=} {--db-only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Module Scaffold';

    /**
     * Get the console command arguments.
     *
     * @return array
     */
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
            ['db-only', null, InputOption::VALUE_OPTIONAL, 'If true does not create form.']
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
        //$this->name =  $this->module . Str::studly($this->argument('name'));
        $this->name =  Str::studly($this->argument('name'));
        $this->fields = $this->option('fillable');
        $this->db_only = $this->option('db-only');
        //dd($this->module, $this->fields);

        //Check if module exists
        if (!Module::collections()->has($this->module)) {
            //Generate Module
            $this->call('module:make', [
                'name' => [$this->module],
                '--api' => true
            ]);

            if (!$this->db_only) {
                //Generate Vue
                $commands = ['create:module:vue:component:tab', 'create:module:vue:component:link',];
                foreach ($commands as $command) {
                    $this->call($command, [
                        'name' => $this->module,
                        'module' => $this->module,
                        '--fillable' => $this->fields,
                    ]);
                }
                //Fix Route File
                $routeApiFile = base_path() . "/modules/" . $this->module . "/api.php";
                $routeApi = file_get_contents($routeApiFile);
                $routeApi = str_replace('$API_ROUTE$', Str::of($this->module)->plural()->lower(), $routeApi);
                file_put_contents($routeApiFile, $routeApi);
            }
        }

        //Generate Model
        $this->call('create:module:model', [
            'model' => $this->name,
            'module' => $this->module,
            '--fillable' => $this->fields,
            '--migration' => true
        ]);

        if (!$this->db_only) {
            //Generate Controller
            $this->call('create:module:controller', [
                'controller' => $this->name,
                'module' => $this->module,
                '--api' => true
            ]);

            //Generate Create Request
            $this->call('create:module:request', [
                'name' => $this->name . 'StoreRequest',
                'module' => $this->module,
                '--fillable' => $this->fields,
            ]);
            //Generate Update Request
            $this->call('create:module:request', [
                'name' => $this->name . 'UpdateRequest',
                'module' => $this->module,
                '--fillable' => $this->fields,
            ]);

            //Generate Create Action
            $this->call('create:module:action', [
                'name' => $this->name . '/Store',
                'module' => $this->module,
            ]);
            //Create Store Action
            $storeActionFile = base_path() . "/modules/" . $this->module . "/Actions/" . $this->name . "/Store.php";
            $storeAction = file_get_contents($storeActionFile);
            $storeAction = str_replace('//use .. ;', "use " . config('modules.namespace') . "\\$this->module\\Models\\" . $this->name . ";\nuse " . config('modules.namespace') . "\\$this->module\\Requests\\" . $this->name . "StoreRequest;", $storeAction);
            $storeAction = str_replace('public function handle($handle)', 'public function handle(' . $this->name . 'StoreRequest $request)', $storeAction);
            $storeAction = str_replace('// ..', '$handle = ' . $this->name . '::create($request->validated());', $storeAction);
            file_put_contents($storeActionFile, $storeAction);

            //Generate Update Action
            $this->call('create:module:action', [
                'name' => $this->name . '/Update',
                'module' => $this->module,
            ]);
            //Create Update Action
            $updateActionFile = base_path() . "/modules/" . $this->module . "/Actions/" . $this->name . "/Update.php";
            $updateAction = file_get_contents($updateActionFile);
            $updateAction = str_replace('//use .. ;', "use " . config('modules.namespace') . "\\" . $this->module . "\\Models\\" . $this->name . ";\nuse " . config('modules.namespace') . "\\$this->module\\Requests\\" . $this->name . "UpdateRequest;",  $updateAction);
            $updateAction = str_replace('public function handle($handle)', 'public function handle(' . $this->name . 'UpdateRequest $request, ' . $this->name . ' $' . Str::camel($this->name) . ')', $updateAction);
            $updateAction = str_replace('// ..', '$' . Str::camel($this->name) . '->update($request->validated());', $updateAction);
            $updateAction = str_replace('return $handle;', 'return $' . Str::camel($this->name) . ';', $updateAction);
            file_put_contents($updateActionFile, $updateAction);

            //Generate Delete Action
            $this->call('create:module:action', [
                'name' => $this->name . '/Delete',
                'module' => $this->module,
            ]);
            $deleteActionFile = base_path() . "/modules/" . $this->module . "/Actions/" . $this->name . "/Delete.php";
            $deleteAction = file_get_contents($deleteActionFile);
            $deleteAction = str_replace('//use .. ;', "use " . config('modules.namespace') . "\\" . $this->module . "\\Models\\" . $this->name . ";", $deleteAction);
            $deleteAction = str_replace('public function handle($handle)', 'public function handle(' . $this->name . ' $' . Str::camel($this->name) . ')', $deleteAction);
            $deleteAction = str_replace('// ..', '$handle = collect($' . Str::camel($this->name) . '->delete());', $deleteAction);
            file_put_contents($deleteActionFile, $deleteAction);


            //Add New API Route
            $routeApiFile = base_path() . "/modules/" . $this->module . "/api.php";
            $routeApi = file_get_contents($routeApiFile);
            $routeApi = str_replace('//add more class here ...', "use " . config('modules.namespace') . "\\" . $this->module . "\\Controllers\\" . $this->name . "Controller;\n//add more class here ...", $routeApi);
            $routeApi = str_replace('//add more route here ...', "Route::apiResource('/" . $this->pageUrl() . "', " . $this->name . "Controller::class);\n\t//add more route here ...", $routeApi);
            file_put_contents($routeApiFile, $routeApi);

            $tableNames = explode('_', Str::of($this->module . $this->name)->snake());
            $splitNames = [];
            foreach ($tableNames as $tableName) {
                $splitNames[] = Str::of($tableName)->singular();
            }
            $unique = array_unique($splitNames);
            $unique = implode('-', $unique);
            $tableName = Str::of($unique)->plural();
            $permissions = Str::of($unique)->replace('-', '.');

            //Add Dashboard Link
            $dashboardLinkFile = base_path() . "/modules/" . $this->module . "/Vue/components/" . Str::of($this->module)->snake()->replace('_', '-') . "-dashboard-link.vue";
            $dashboardLink = file_get_contents($dashboardLinkFile);
            $dashboardLink = str_replace('//add link here ...', "
                        {
                            title: '" . Str::headline($this->name) . "',
                            link: '/dashboard/" . $this->pageUrl() . "',
                            icon: 'AppsIcon',
                            permission: 'module." . $permissions . "',
                        },
                        //add link here ...
        ", $dashboardLink);
            file_put_contents($dashboardLinkFile, $dashboardLink);

            //Add Icon Tabs
            $iconTabFile = base_path() . "/modules/" . $this->module . "/Vue/components/" . Str::of($this->module)->snake()->replace('_', '-') . "-icon-tab.vue";
            $iconTab = file_get_contents($iconTabFile);
            $iconTab = str_replace('//add tabs here ...', "
                {
                    title: '" . Str::headline($this->name) . "',
                    link: '/dashboard/" . $this->pageUrl() . "',
                    icon: 'AppsIcon',
                    permission: 'module." . $permissions . "',
                },
                //add tabs here ...
        ", $iconTab);
            file_put_contents($iconTabFile, $iconTab);

            //Fix Controller File
            $controllerFile = base_path() . "/modules/" . $this->module . "/Controllers/" . Str::studly($this->name) . "Controller.php";
            $controller = file_get_contents($controllerFile);
            $controller = str_replace('$modelVar$', Str::camel($this->name), $controller);
            file_put_contents($controllerFile, $controller);


            //Generate Vue
            $commands = [
                'create:module:vue:store',
                'create:module:vue:page:index',
                'create:module:vue:page:new',
                'create:module:vue:page:view',
                'create:module:vue:component:form',
                //'create:module:vue:component:link',
                //'create:module:vue:component:tab',
            ];
            foreach ($commands as $command) {
                $this->call($command, [
                    'name' =>  $this->module . $this->name,
                    'module' => $this->module,
                    '--fillable' => $this->fields,
                ]);
            }
        }

        //Clear Cache
        $this->call('optimize:clear');
    }

    private function pageUrl()
    {
        if ($this->argument('name') == $this->argument('module')) {
            return Str::of($this->argument('module'))->headline()->plural()->slug();
        } else {
            return Str::of($this->argument('module'))->headline()->plural()->slug() . '/' .
                Str::of($this->argument('name'))->remove($this->argument('module'), false)->headline()->plural()->slug();
        }
    }
}
