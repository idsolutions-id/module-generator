<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Console\Command;
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
        $blueprints = Yaml::parse(file_get_contents('.blueprint/' . $this->argument('blueprint')));
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
        $this->info('Please restart webserver / sail and vite');

        return true;
    }

    /**
     * Calls the 'create:module:sub' command with the provided module, submodule, fillables, and dbOnly options.
     *
     * @param mixed $module The name of the module.
     * @param mixed $subModule The name of the submodule.
     * @param array $fillables An array of fillable columns.
     * @param bool $dbOnly Flag to indicate if only database operations should be performed.
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
     * @param mixed $module The name of the module.
     * @param mixed $subModule The name of the submodule.
     * @param array $tables An associative array containing the table configuration.
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
     * @param string $module The name of the module.
     * @param string $subModule The name of the submodule.
     * @param array $tables An associative array containing the table configuration.
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
