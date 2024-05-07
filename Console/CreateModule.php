<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use Vheins\LaravelModuleGenerator\Actions\CreateQuery;
use Vheins\LaravelModuleGenerator\Actions\CreateRelation;
use Vheins\LaravelModuleGenerator\Actions\FixQueryApi;

class CreateModule extends Command
{
    protected $signature = 'create:module {blueprint}';

    protected $description = 'Create Module Scaffold';

    private $query = [];

    private $blueprint = null;

    protected function getOptions()
    {
        return [
            ['blueprint', null, InputOption::VALUE_REQUIRED, 'The specified blueprint file.'],
        ];
    }

    /**
     * Handles the execution of the function.
     *
     * @return bool Returns true if the function executes successfully.
     */
    public function handle()
    {
        if (! $this->validateFile()) {
            return true;
        }

        $blueprints = Yaml::parse(file_get_contents('.blueprint/' . $this->blueprint));
        foreach ($blueprints as $module => $subModules) {
            foreach ($subModules as $subModule => $tables) {
                $dbOnly = false;
                if (isset($tables['CRUD'])) {
                    $dbOnly = false;
                }
                if (isset($tables['CRUD']) && $tables['CRUD'] == false) {
                    $dbOnly = true;
                }
                //Fillable
                $fillables = [];
                foreach ($tables['Fillable'] as $k => $v) {
                    $fillables[] = $k . ':' . $v;
                }
                $this->createSub($module, $subModule, $fillables, $dbOnly);
                $this->createRelation($module, $subModule, $tables);
                $this->createQuery($module, $subModule, $tables);

                $this->info('Module ' . $module . ' Submodule ' . $subModule . ' Created!');
                sleep(1);
            }

            //fix route query parameters
            FixQueryApi::run($module, $this->query);
        }
        $this->call('optimize:clear');
        $this->info('Generate Blueprint Successfull');
        $this->info('Running Laravel Pint, Please Wait...');
        Process::run('./vendor/bin/pint');
        $this->info('Please restart webserver / sail and vite');

        return true;
    }

    /**
     * Validates the file specified as a command argument.
     *
     * This function checks if the file specified as a command argument has a '.yml' extension.
     * If it doesn't, the '.yml' extension is appended to the file name.
     * Then, it checks if the file exists in the '.blueprint/' directory.
     * If the file doesn't exist, an error message is displayed and the function returns false.
     *
     * @return bool Returns false if the file is not found, otherwise returns nothing.
     */
    private function validateFile()
    {
        $this->blueprint = $this->argument('blueprint');
        if (! Str::of($this->blueprint)->endsWith('.yml')) {
            $this->blueprint .= '.yml';
        }
        if (! file_exists('.blueprint/' . $this->blueprint)) {
            $this->error('Blueprint not found');

            return false;
        }

        return true;
    }

    /**
     * Calls the 'create:module:sub' command with the provided module, submodule, fillables, and dbOnly options.
     *
     * @param  mixed  $module  The name of the module.
     * @param  mixed  $subModule  The name of the submodule.
     * @param  array  $fillables  An array of fillable columns.
     * @param  bool  $dbOnly  Flag to indicate if only database operations should be performed.
     */
    private function createSub($module, $subModule, $fillables, $dbOnly)
    {
        $this->call('create:module:sub', [
            'module' => $module,
            'name' => $subModule,
            '--fillable' => implode(',', $fillables),
            '--db-only' => $dbOnly,
        ]);
    }

    /**
     * Create relation for the given module and submodule if the 'Relation' key is set in the $tables array.
     *
     * @param  mixed  $module  The name of the module.
     * @param  mixed  $subModule  The name of the submodule.
     * @param  array  $tables  An associative array containing the table configuration.
     * @return void
     */
    private function createRelation($module, $subModule, $tables)
    {
        if (isset($tables['Relation'])) {
            $args = [
                'module' => $module,
                'name' => $subModule,
                'relations' => $tables['Relation'],
            ];
            CreateRelation::run($args);
        }
    }

    /**
     * Creates a query for the given module and submodule if the 'Query' key is set to true in the $tables array.
     *
     * @param  string  $module  The name of the module.
     * @param  string  $subModule  The name of the submodule.
     * @param  array  $tables  An associative array containing the table configuration.
     * @return void
     */
    private function createQuery($module, $subModule, $tables)
    {
        if (isset($tables['Query']) && $tables['Query'] == true) {
            $args = [
                'module' => $module,
                'name' => $subModule,
            ];
            $this->query[] = Str::of($subModule)->snake()->plural()->slug();
            CreateQuery::run($args);
        }
    }
}
