<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use Vheins\LaravelModuleGenerator\Action\CreateQuery;
use Vheins\LaravelModuleGenerator\Action\CreateRelation;
use Vheins\LaravelModuleGenerator\Action\FixQueryApi;

class CreateModule extends Command
{
    protected $signature = 'create:module {--blueprint=}';

    protected $description = 'Create Module Scaffold';

    protected function getOptions()
    {
        return [
            ['blueprint', null, InputOption::VALUE_REQUIRED, 'The specified blueprint file.'],
        ];
    }

    public function handle()
    {
        $blueprints = Yaml::parse(file_get_contents('.blueprint/'.$this->option('blueprint')));
        foreach ($blueprints as $module => $subModules) {
            $query = [];
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
                    $fillables[] = $k.':'.$v;
                }
                $this->call('create:module:sub', [
                    'module' => $module,
                    'name' => $subModule,
                    '--fillable' => implode(',', $fillables),
                    '--db-only' => $dbOnly,
                ]);

                if (isset($tables['Relation'])) {
                    $args = [
                        'module' => $module,
                        'name' => $subModule,
                        'relations' => $tables['Relation'],
                    ];
                    CreateRelation::run($args);
                }

                if (isset($tables['Query']) && $tables['Query'] == true) {
                    $args = [
                        'module' => $module,
                        'name' => $subModule,
                    ];
                    $query[] = Str::of($subModule)->snake()->plural()->slug();
                    CreateQuery::run($args);
                }

                $this->info('Module '.$module.' Submodule '.$subModule.' Created!');
                sleep(1);
            }

            //fix route query parameters
            FixQueryApi::run($module, $query);
        }
        $this->call('optimize:clear');
        $this->info('Generate Blueprint Successfull');
        $this->info('Please restart webserver / sail and vite');

        return true;
    }
}
