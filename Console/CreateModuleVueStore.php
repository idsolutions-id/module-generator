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
        $classNames = explode('_', Str::of($this->getClass())->snake());
        $splitNames = [];
        foreach ($classNames as $className) {
            $splitNames[] = Str::of($className)->singular();
        }
        $unique = array_unique($splitNames);
        $unique = implode('_', $unique);
        $permission = Str::of($unique)->title()->replace('_', '.')->lower();
        $class = Str::of($unique)->title()->replace('_', '');

        return (new Stub('/vue/store.pinia.stub', [
            'STUDLY_NAME'       => $module->getStudlyName(),
            'API_ROUTE'         => $this->pageUrl(),
            'CLASS'             => $class,
            'LOWER_NAME'        => $module->getLowerName(),
            'MODULE'            => $this->getModuleName(),
            'FILLABLE'          => $this->getFillable(),
            'FILTER'            => $this->getFilter(),
            'HEADER'            => $this->getHeader(),
            'NAME'              => Str::of(Str::studly($this->argument('name')))->headline(),
            'PERMISSION'        => $permission
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

    private function getHeader()
    {
        $fillable = $this->option('fillable');
        if (!is_null($fillable)) {

            foreach (explode(',', $fillable) as $var) {
                $key = explode(':', $var)[1];
                $val = explode(':', $var)[0];
                if (in_array($key, [
                    'foreignId', 'foreignUuid', 'foreignUlid',
                ])) $val = Str::of($val)->replace('_id', '')->toString() . '.name';
                $arrays[] = "{ '" . Str::camel($val) . "': '" . Str::of($val)->replace('.', '_')->headline() . "' }";
            };
            return "[\n\t\t\t" . implode(", \n\t\t\t", $arrays) . "\n\t\t]";
        }

        return '[]';
    }

    /**
     * @return string
     */
    private function getFillable()
    {
        $fillable = $this->option('fillable');
        if (!is_null($fillable)) {

            foreach (explode(',', $fillable) as $var) {
                $key = explode(':', $var)[0];
                $arrays[] = Str::of($key)->replace('_id', '')->camel() . ": null";
            };
            return "{\n\t" . implode(",\n\t", $arrays) . "\n}";
        }

        return '{}';
    }

    /**
     * @return string
     */
    private function getFilter()
    {
        $fillable = $this->option('fillable');
        if (!is_null($fillable)) {
            $arrays = [];
            foreach (explode(',', $fillable) as $var) {
                $key = explode(':', $var)[1];
                $val = explode(':', $var)[0];
                if (in_array($key, [
                    'foreignId', 'foreignUuid', 'foreignUlid',
                ])) $arrays[] = Str::of($val)->replace('_id', '')->camel() . ": null";
            };
            return "{\n\t\t\t" . implode(",\n\t\t\t", $arrays) . "\n\t\t}";
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

        $fileNames = explode('-', Str::of($this->getFileName())->snake()->replace('_', '-'));
        $splitNames = [];
        foreach ($fileNames as $fileName) {
            $splitNames[] = Str::of($fileName)->singular();
        }
        $unique = array_unique($splitNames);
        $unique = implode('-', $unique);
        $fileName = Str::of($unique);

        return $path . $Path->getPath() . '/' . $fileName . '.js';
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
