<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Support\Str;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Commands\GeneratorCommand;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Nwidart\Modules\Support\Config\GenerateConfigReader;

final class CreateModuleVueComponentLink extends GeneratorCommand
{
    use ModuleCommandTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'create:module:vue:component:link';

    protected $argumentName = 'name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Vue Component Link for the specified module.';

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['fillable', null, InputOption::VALUE_OPTIONAL, 'The fillable attributes.', null],
        ];
    }


    public function getDefaultNamespace(): string
    {
        $module = $this->laravel['modules'];

        return $module->config('paths.generator.vue-components.namespace') ?: $module->config('paths.generator.vue-components.path', 'vue/components');
    }

    /**
     * Get template contents.
     *
     * @return string
     */
    protected function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());
        $tableNames = explode('_', Str::of($module->getStudlyName())->snake());
        $splitNames = [];
        foreach ($tableNames as $tableName) {
            $splitNames[] = Str::of($tableName)->singular();
        }
        $unique = array_unique($splitNames);
        $unique = implode('-', $unique);
        $tableName = Str::of($unique)->plural();
        $permissions = Str::of($unique)->replace('-', '.');

        return (new Stub('/vue/component.link.stub', [
            'STUDLY_NAME'       => $module->getStudlyName(),
            'API_ROUTE'         => $this->pageUrl(),
            'CLASS'             => $this->getClass(),
            'LOWER_NAME'        => $tableName,
            'PERMISSIONS'       => $permissions,
            'MODULE'            => $this->getModuleName(),
            'FILLABLE'          => $this->getFillable(),
            'NAME'              => Str::of($module->getStudlyName())->headline()

            // 'NAME'              => $this->getModelName(),
            // 'NAMESPACE'         => $this->getClassNamespace($module),
            // 'MODULE_NAMESPACE'  => $this->laravel['modules']->config('namespace'),
        ]))->render();
    }

    private function pageUrl()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());
        $tableNames = explode('_', Str::of($module->getStudlyName())->snake());
        $splitNames = [];
        foreach ($tableNames as $tableName) {
            $splitNames[] = Str::of($tableName)->plural();
        }
        $unique = array_unique($splitNames);
        $url = implode('/', $unique);
        return $url;
    }

    /**
     * @return string
     */
    private function getFillable()
    {
        $fillable = $this->option('fillable');
        if (!is_null($fillable)) {

            foreach (explode(',', $fillable) as $var) {
                $arrays[] = explode(':', $var)[0] . ": null";
            };
            return "{\n\t" . implode(",\n\t", $arrays) . "\n}";
        }

        return '{}';
    }

    /**
     * Get the destination file path.
     *
     * @return string
     */
    protected function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        $Path = GenerateConfigReader::read('vue-components');

        return $path . $Path->getPath() . '/' . Str::of($this->getModuleName())->snake()->replace('_', '-') . '-dashboard-link.vue';
    }

    /**
     * @return string
     */
    private function getFileName()
    {
        return Str::studly($this->argument('name'));
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the notification class.'],
            ['module', InputArgument::OPTIONAL, 'The name of module will be used.'],
        ];
    }
}
