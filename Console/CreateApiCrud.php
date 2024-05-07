<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateApiCrud extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:api:crud {name} {--action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make API Resource';

    private $customRequest = 'Request';

    private $storeAction = 'Store';

    private $deleteAction = 'Delete';

    private $updateAction = 'Update';

    public $model;

    public $varModel;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('name');
        $action = $this->option('action');
        $this->model = str_replace('/', '', $name);
        $this->varModel = Str::camel($this->model);
        //Generate Model & Migration
        $this->call('make:model', [
            'name' => $this->model,
            '--migration' => true,
        ]);

        //Generate Form Store Request & Action
        $this->call('make:request', [
            'name' => $name . '/' . $this->customRequest,
        ]);
        if ($action) {
            $this->call('make:action', [
                'name' => $name . '/' . $this->storeAction,
            ]);
            $this->call('make:action', [
                'name' => $name . '/' . $this->deleteAction,
            ]);
        }

        //Generate Form Update Request & Action
        $this->call('make:request', [
            'name' => $name . '/' . $this->customRequest,
        ]);
        if ($action) {
            $this->call('make:action', [
                'name' => $name . '/' . $this->updateAction,
            ]);
        }

        //Generate Controller Resource
        $args = [
            'name' => $name . 'Controller',
            '--model' => $this->model,
            '--api' => true,
        ];
        if ($action) {
            $args['--type'] = 'model.action';
        }
        $this->call('make:controller', $args);
        $this->updateController($name);

        return 0;
    }

    public function updateController($name)
    {
        $customNamespaceRequest = 'Http\\Requests\\' . str_replace('/', '\\', $name) . '\\' . $this->customRequest;
        $customNamespaceRequest = 'Http\\Requests\\' . str_replace('/', '\\', $name) . '\\' . $this->customRequest;

        $namespaceStoreAction = 'Actions\\' . str_replace('/', '\\', $name) . '\\' . $this->storeAction;
        $namespaceUpdateAction = 'Actions\\' . str_replace('/', '\\', $name) . '\\' . $this->updateAction;
        $namespaceDeleteAction = 'Actions\\' . str_replace('/', '\\', $name) . '\\' . $this->deleteAction;

        $controllerFile = app_path() . '/Http/Controllers/' . $name . 'Controller.php';

        $str = file_get_contents($controllerFile);
        $str = str_replace('{{ namespaceStoreAction }}', $namespaceStoreAction, $str);
        $str = str_replace('{{ namespaceUpdateAction }}', $namespaceUpdateAction, $str);
        $str = str_replace('{{ namespaceDeleteAction }}', $namespaceDeleteAction, $str);

        $str = str_replace('{{ customNamespaceRequest }}', $customNamespaceRequest, $str);
        $str = str_replace('{{ customNamespaceRequest }}', $customNamespaceRequest, $str);

        $str = str_replace('{{ customRequest }}', $this->customRequest, $str);
        $str = str_replace('{{ customRequest }}', $this->customRequest, $str);

        $str = str_replace('{{ storeAction }}', $this->storeAction, $str);
        $str = str_replace('{{ updateAction }}', $this->updateAction, $str);
        $str = str_replace('{{ deleteAction }}', $this->deleteAction, $str);
        file_put_contents($controllerFile, $str);

        if ($this->option('action')) {
            //Create Store Action
            $storeActionFile = app_path() . '/Actions/' . $name . '/Store.php';
            $storeAction = file_get_contents($storeActionFile);
            $storeAction = str_replace('//use .. ;', 'use App\\Models\\' . $this->model . ';', $storeAction);
            $storeAction = str_replace('public function handle($handle)', 'public function handle(Array $request)', $storeAction);
            $storeAction = str_replace('// ..', '$handle = ' . $this->model . '::create($request);', $storeAction);
            file_put_contents($storeActionFile, $storeAction);

            //Create Update Action
            $updateActionFile = app_path() . '/Actions/' . $name . '/Update.php';
            $updateAction = file_get_contents($updateActionFile);
            $updateAction = str_replace('//use .. ;', 'use App\\Models\\' . $this->model . ';', $updateAction);
            $updateAction = str_replace('public function handle($handle)', 'public function handle(' . $this->model . ' $' . $this->varModel . ', Array $request)', $updateAction);
            $updateAction = str_replace('// ..', '$' . $this->varModel . '->update($request);', $updateAction);
            $updateAction = str_replace('return $handle;', 'return $' . $this->varModel . ';', $updateAction);
            file_put_contents($updateActionFile, $updateAction);

            //Create delete Action
            $deleteActionFile = app_path() . '/Actions/' . $name . '/Delete.php';
            $deleteAction = file_get_contents($deleteActionFile);
            $deleteAction = str_replace('//use .. ;', 'use App\\Models\\' . $this->model . ';', $deleteAction);
            $deleteAction = str_replace('public function handle($handle)', 'public function handle(' . $this->model . ' $' . $this->varModel . ')', $deleteAction);
            $deleteAction = str_replace('// ..', '$handle = collect($' . $this->varModel . '->delete());', $deleteAction);
            file_put_contents($deleteActionFile, $deleteAction);
        }
    }
}
