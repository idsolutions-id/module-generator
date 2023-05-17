<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Support\Str;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Commands\GeneratorCommand;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Nwidart\Modules\Support\Config\GenerateConfigReader;

final class CreateModuleVuePageView extends GeneratorCommand
{
    use ModuleCommandTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'create:module:vue:page:view';

    protected $argumentName = 'name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Vue Page View for the specified module.';

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

        return $module->config('paths.generator.vue-pages.namespace') ?: $module->config('paths.generator.vue-pages.path', 'vue/stores');
    }

    /**
     * Get template contents.
     *
     * @return string
     */
    protected function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());
        $classNames = explode('_', Str::of($module->getStudlyName())->snake());
        $splitNames = [];
        foreach ($classNames as $className) {
            $splitNames[] = Str::of($className)->singular();
        }
        $unique = array_unique($splitNames);
        $unique = implode('_', $unique);
        $classNames = Str::of($unique)->plural()->studly();
        return (new Stub('/vue/page.[id].stub', [
            'STUDLY_NAME'   => $module->getStudlyName(),
            'API_ROUTE'     => $this->pageUrl($module->getStudlyName()),
            'CLASS'         => $classNames,
            'LOWER_NAME'    => $module->getLowerName(),
            'MODULE'        => $this->getModuleName(),
            'SEARCHABLE'    => $this->getSearchable(),
            'HEADER'        => $this->getHeader(),

            // 'NAME'              => $this->getModelName(),
            // 'NAMESPACE'         => $this->getClassNamespace($module),
            // 'MODULE_NAMESPACE'  => $this->laravel['modules']->config('namespace'),
        ]))->render();
    }

    /**
     * @return string
     */
    private function getHeader()
    {
        $fillable = $this->option('fillable');
        if (!is_null($fillable)) {

            foreach (explode(',', $fillable) as $var) {
                $val = explode(':', $var)[0];
                $arrays[] = "{'" . $val . "': '" . Str::title($val) . "'}";
            };
            return "[\n\t\t\t\t" . implode(",\n\t\t\t\t", $arrays) . "\n\t\t\t]";
        }

        return '[]';
    }

    /**
     * @return string
     */
    private function getSearchable()
    {
        $fillable = $this->option('fillable');
        if (!is_null($fillable)) {

            foreach (explode(',', $fillable) as $var) {
                $arrays[] = "'" . explode(':', $var)[0] . "'";
            };
            return '[' . implode(', ', $arrays) . ']';
        }

        return '[]';
    }

    /**
     * Get the destination file path.
     *
     * @return string
     */
    protected function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        $Path = GenerateConfigReader::read('vue-pages');

        return $path . $Path->getPath() . '/dashboard/' . $this->pageUrl() . '/[id].vue';
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
