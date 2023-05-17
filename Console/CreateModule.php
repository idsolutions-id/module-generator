<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Vheins\LaravelModuleGenerator\Action\CreateRelation;

class CreateModule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:module {--blueprint=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Module Scaffold';

    // /**
    //  * Get the console command arguments.
    //  *
    //  * @return array
    //  */
    // protected function getArguments()
    // {
    //     return [
    //         ['module', InputArgument::REQUIRED, 'The name of module will be created.'],
    //     ];
    // }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['blueprint', null, InputOption::VALUE_REQUIRED, 'The specified blueprint file.'],
        ];
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $blueprints = Yaml::parse(file_get_contents($this->option('blueprint')));
        foreach ($blueprints as $module => $subModules) {
            foreach ($subModules as $subModule => $tables) {
                $dbOnly = false;
                if (isset($tables['CRUD'])) $dbOnly = false;
                if (isset($tables['CRUD']) && $tables['CRUD'] == false) $dbOnly = true;
                //Fillable
                $fillables = [];
                foreach ($tables['Fillable'] as $k => $v) {
                    $fillables[] = $k . ":" . $v;
                }
                $this->call('create:module:sub', [
                    'module' => $module,
                    'name' => $subModule,
                    '--fillable' => implode(",", $fillables),
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

                $this->info('Module ' . $module . ' Submodule ' . $subModule . ' Created!');
                sleep(1);
            }
        }
        $this->call('optimize:clear');
        $this->info('Generate Blueprint Successfull');
        $this->info('Please restart webserver / sail and vite');
    }

    private function pageUrl($text)
    {
        return Str::of($text)->headline()->plural()->slug();
    }
}
