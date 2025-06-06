<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Support\Str;
use Nwidart\Modules\Commands\Make\GeneratorCommand;
use Nwidart\Modules\Support\Config\GenerateConfigReader;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CreateModuleModel extends GeneratorCommand
{
    use ModuleCommandTrait;

    /**
     * The name of argument name.
     *
     * @var string
     */
    protected $argumentName = 'model';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'create:module:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new model for the specified module.';

    public function handle(): int
    {
        if (parent::handle() === E_ERROR) {
            return E_ERROR;
        }

        $this->handleOptionalMigrationOption();
        $this->handleOptionalControllerOption();
        $this->handleOptionalSeedOption();
        $this->handleOptionalRequestOption();

        return 0;
    }

    /**
     * Create a proper migration name:
     * ProductDetail: product_details
     * Product: products
     *
     * @return string
     */
    private function createMigrationName()
    {
        $pieces = preg_split('/(?=[A-Z])/', $this->argument('model'), -1, PREG_SPLIT_NO_EMPTY);

        $string = '';
        foreach ($pieces as $i => $piece) {
            if ($i + 1 < count($pieces)) {
                $string .= strtolower($piece) . '_';
            } else {
                $string .= Str::plural(strtolower($piece));
            }
        }

        return $string;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['model', InputArgument::REQUIRED, 'The name of model will be created.'],
            ['module', InputArgument::OPTIONAL, 'The name of module will be used.'],
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
            ['fillable', null, InputOption::VALUE_OPTIONAL, 'The fillable attributes.', null],
            ['migration', 'm', InputOption::VALUE_NONE, 'Flag to create associated migrations', null],
            ['controller', 'c', InputOption::VALUE_NONE, 'Flag to create associated controllers', null],
            ['seed', 's', InputOption::VALUE_NONE, 'Create a new seeder for the model', null],
            ['request', 'r', InputOption::VALUE_NONE, 'Create a new request for the model', null],
        ];
    }

    /**
     * Create the migration file with the given model if migration flag was used
     */
    private function handleOptionalMigrationOption()
    {
        if ($this->option('migration') === true) {
            $tableNames = explode('_', Str::of($this->argument('module') . $this->argument('model'))->snake());
            $splitNames = [];
            foreach ($tableNames as $tableName) {
                $splitNames[] = $tableName != 'has' ? Str::of($tableName)->singular() : $tableName;
            }
            $unique = array_unique($splitNames);
            $unique = implode('_', $unique);
            $tableName = Str::of($unique)->plural();

            $migrationName = 'create_' . $tableName . '_' . $this->createMigrationName() . '_table';
            $this->call('create:module:migration', [
                'name' => $migrationName,
                'module' => $this->argument('module'),
                'basename' => $this->argument('module') . $this->argument('model'),
                '--fields' => $this->option('fillable'),
            ]);
        }
    }

    /**
     * Create the controller file for the given model if controller flag was used
     */
    private function handleOptionalControllerOption()
    {
        if ($this->option('controller') === true) {
            $controllerName = "{$this->getModelName()}Controller";

            $this->call('module:make-controller', array_filter([
                'controller' => $controllerName,
                'module' => $this->argument('module'),
            ]));
        }
    }

    /**
     * Create a seeder file for the model.
     *
     * @return void
     */
    protected function handleOptionalSeedOption()
    {
        if ($this->option('seed') === true) {
            $seedName = "{$this->getModelName()}Seeder";

            $this->call('module:make-seed', array_filter([
                'name' => $seedName,
                'module' => $this->argument('module'),
            ]));
        }
    }

    /**
     * Create a request file for the model.
     *
     * @return void
     */
    protected function handleOptionalRequestOption()
    {
        if ($this->option('request') === true) {
            $requestName = "{$this->getModelName()}Request";

            $this->call('module:make-request', array_filter([
                'name' => $requestName,
                'module' => $this->argument('module'),
            ]));
        }
    }

    /**
     * @return mixed
     */
    protected function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());

        $tableNames = explode('_', Str::of($this->getModuleName() . $this->getModelName())->snake()->plural());
        $splitNames = [];
        foreach ($tableNames as $tableName) {
            $splitNames[] = $tableName != 'has' ? Str::of($tableName)->singular() : $tableName;
        }
        $unique = array_unique($splitNames);
        $unique = implode('_', $unique);
        $tableName = Str::of($unique)->plural();

        return (new Stub('/model.stub', [
            'NAME' => $this->getModelName(),
            'FILLABLE' => $this->getFillable(),
            // 'RULE' => $this->getRules(),
            'NAMESPACE' => $this->getClassNamespace($module),
            'CLASS' => $this->getClass(),
            'LOWER_NAME' => $module->getLowerName(),
            'MODULE' => $this->getModuleName(),
            'STUDLY_NAME' => $module->getStudlyName(),
            'MODULE_NAMESPACE' => $this->laravel['modules']->config('namespace'),
            'TABLE_NAME' => $tableName,
        ]))->render();
    }

    /**
     * @return string
     */
    private function getRules()
    {
        $tabs = "\n\t\t\t";
        $fillable = $this->option('fillable');
        if (! is_null($fillable)) {
            foreach (explode(',', $fillable) as $var) {
                $textVar = explode(':', $var)[0];
                $array = "'" . $textVar . "' => 'required'";
                $arrays[] = $array;
            }

            return '[' . $tabs . implode(',' . $tabs, $arrays) . "\n\t\t]";
        }

        return '[]';
    }

    /**
     * @return mixed
     */
    protected function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        $modelPath = GenerateConfigReader::read('model');

        return $path . $modelPath->getPath() . '/' . $this->getModelName() . '.php';
    }

    /**
     * @return mixed|string
     */
    private function getModelName()
    {
        return Str::studly($this->argument('model'));
    }

    /**
     * @return string
     */
    private function getFillable()
    {
        $fillable = $this->option('fillable');
        if (! is_null($fillable)) {

            foreach (explode(',', $fillable) as $var) {
                $arrays[] = "'" . explode(':', $var)[0] . "'";
            }

            return '[' . implode(', ', $arrays) . ']';
        }

        return '[]';
    }

    /**
     * Get default namespace.
     */
    public function getDefaultNamespace(): string
    {
        $module = $this->laravel['modules'];

        return $module->config('paths.generator.model.namespace') ?: $module->config('paths.generator.model.path', 'Entities');
    }
}
