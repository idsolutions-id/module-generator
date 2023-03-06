<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Support\Str;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Commands\GeneratorCommand;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Nwidart\Modules\Support\Config\GenerateConfigReader;

final class CreateModuleVueStore extends GeneratorCommand
{
    use ModuleCommandTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'create:module:vue:store';

    protected $argumentName = 'name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Vue Store for the specified module.';

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

        return $module->config('paths.generator.vue-stores.namespace') ?: $module->config('paths.generator.vue-stores.path', 'vue/stores');
    }

    /**
     * Get template contents.
     *
     * @return string
     */
    protected function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());

        return (new Stub('/vue/store.pinia.stub', [
            'STUDLY_NAME'       => $module->getStudlyName(),
            'API_ROUTE'         => $this->pageUrl(),
            'CLASS'             => $this->getClass(),
            'LOWER_NAME'        => $module->getLowerName(),
            'MODULE'            => $this->getModuleName(),
            'FILLABLE'          => $this->getFillable(),
            'NAME'              => Str::of(Str::studly($this->argument('name')))->headline(),
            'PERMISSION'        => $this->argument('name') == $this->argument('module') ? $module->getLowerName() : $module->getLowerName() . '.' . Str::lower(Str::remove($module->getLowerName(), $this->argument('name'), false))
        ]))->render();
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

    /**
     * @return string
     */
    private function getFillable()
    {
        $fillable = $this->option('fillable');
        if (!is_null($fillable)) {

            foreach (explode(',', $fillable) as $var) {
                $arrays[] = Str::camel(explode(':', $var)[0]) . ": null";
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
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());

        $Path = GenerateConfigReader::read('vue-stores');

        return $path . $Path->getPath() . '/' . Str::of($this->getFileName())->snake()->replace('_','-') . '.js';
    }

    /**
     * @return string
     */
    private function getFileName()
    {
        return Str::camel($this->argument('name'));
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
