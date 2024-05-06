<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Support\Str;
use Nwidart\Modules\Commands\Make\GeneratorCommand;
use Nwidart\Modules\Support\Config\GenerateConfigReader;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CreateModuleFactory extends GeneratorCommand
{
    use ModuleCommandTrait;

    /**
     * The name of argument name.
     *
     * @var string
     */
    protected $argumentName = 'name';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'create:module:factory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new model factory for the specified module.';

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the model.'],
            ['module', InputArgument::REQUIRED, 'The name of module will be used.'],
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
        ];
    }

    /**
     * @return mixed
     */
    protected function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());

        return (new Stub('/factory.stub', [
            'NAMESPACE' => $this->getClassNamespace($module),
            'NAME' => $this->getModelName(),
            'MODEL_NAMESPACE' => $this->getModelNamespace(),
            'FACTORY' => $this->getFactory(),
        ]))->render();
    }

    private function getFactory()
    {
        $tabs = "\n\t\t\t";
        $fillable = $this->option('fillable');
        if (! is_null($fillable)) {
            foreach (explode(',', $fillable) as $var) {
                $textVar = explode(':', $var)[0];
                $type = explode(':', $var)[1];

                if ($textVar == 'company_id') {
                    $array = "'company' => " . '$this->company ? [\'id\' => $this->company->id] : null';
                } else {

                    switch ($type) {
                        case 'boolean':
                            $array = "'" . $textVar . "' => " . '$this->faker->boolean()';
                            break;
                        case 'float':
                            $array = "'" . $textVar . "' => " . '$this->faker->randomFloat(2, 1, 100)';
                            break;
                        case 'text':
                            $array = "'" . $textVar . "' => " . '$this->faker->paragraph()';
                            break;
                        case 'foreignUuid':
                            $array = "'" . $textVar . "' => " . '$this->faker->uuid()';
                            break;

                        default:
                            $array = "'" . $textVar . "' => " . '$this->faker->word()';
                            break;
                    }
                }

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
        $factoryPath = GenerateConfigReader::read('factory');

        return $path . $factoryPath->getPath() . '/' . $this->getFileName();
    }

    /**
     * @return string
     */
    private function getFileName()
    {
        return Str::studly($this->argument('name')) . 'Factory.php';
    }

    /**
     * @return mixed|string
     */
    private function getModelName()
    {
        return Str::studly($this->argument('name'));
    }

    /**
     * Get default namespace.
     */
    public function getDefaultNamespace(): string
    {
        $module = $this->laravel['modules'];

        return $module->config('paths.generator.factory.namespace') ?: $module->config('paths.generator.factory.path');
    }

    /**
     * Get model namespace.
     */
    public function getModelNamespace(): string
    {
        $path = $this->laravel['modules']->config('paths.generator.model.path', 'Entities');
        $path = str_replace('/', '\\', $path);

        return $this->laravel['modules']->config('namespace') . '\\' . $this->laravel['modules']->findOrFail($this->getModuleName()) . '\\' . $path;
    }
}
