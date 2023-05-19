<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Support\Str;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Commands\GeneratorCommand;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Nwidart\Modules\Support\Config\GenerateConfigReader;

final class CreateModuleVueComponentForm extends GeneratorCommand
{
    use ModuleCommandTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'create:module:vue:component:form';

    protected $argumentName = 'name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Vue Component Form for the specified module.';

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
        $classNames = explode('_', Str::of($this->getClass())->snake());
        $splitNames = [];
        foreach ($classNames as $className) {
            $splitNames[] = Str::of($className)->singular();
        }
        $unique = array_unique($splitNames);
        $unique = implode('_', $unique);
        $class = Str::of($unique)->title()->replace('_', '');

        return (new Stub('/vue/component.form.stub', [
            'STUDLY_NAME'   => $module->getStudlyName(),
            'API_ROUTE'     => $this->pageUrl($module->getStudlyName()),
            'CLASS'         => $class,
            'LOWER_NAME'    => $module->getLowerName(),
            'MODULE'        => $this->getModuleName(),
            'FILLABLE'      => $this->getFillable(),
            'THIS_FORM'     => $this->getForm(),
            'FORM'          => $this->getFormInput(),

            // 'NAME'              => $this->getModelName(),
            // 'NAMESPACE'         => $this->getClassNamespace($module),
            // 'MODULE_NAMESPACE'  => $this->laravel['modules']->config('namespace'),
        ]))->render();
    }

    private function pageUrl($text)
    {
        return Str::of($text)->headline()->plural()->slug();
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
            return "{\n\t\t\t\t" . implode(",\n\t\t\t\t", $arrays) . "\n\t\t\t}";
        }

        return '{}';
    }

    /**
     * @return string
     */
    private function getForm()
    {
        $fillable = $this->option('fillable');
        if (!is_null($fillable)) {

            foreach (explode(',', $fillable) as $var) {
                $arrays[] = "this.form." . Str::camel(explode(':', $var)[0]) . " = " . "value." . Str::camel(explode(':', $var)[0]);
            };
            return "{\n\t\t\t\t" . implode(";\n\t\t\t\t", $arrays) . "\n\t\t\t}";
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

        $fileNames = explode('-', Str::of($this->getFileName())->snake()->replace('_', '-'));
        $splitNames = [];
        foreach ($fileNames as $fileName) {
            $splitNames[] = Str::of($fileName)->singular();
        }
        $unique = array_unique($splitNames);
        $unique = implode('-', $unique);
        $fileName = Str::of($unique);


        return $path . $Path->getPath() . '/' . $fileName . '-form.vue';
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

    private function getFormInput()
    {
        $fillable = $this->option('fillable');
        foreach (explode(',', $fillable) as $var) {
            $keys = explode(':', $var);
            $form[] = $this->getInputTemplateContents($keys[0], $keys[1]);
        };
        return implode("\n", $form);
    }

    protected function getInputTemplateContents($name, $type)
    {
        switch ($type) {
            case 'boolean':
                $pathStub = '/vue/component.form.boolean.stub';
                break;

            default:
                $pathStub = '/vue/component.form.input.stub';

                //Numerical
                if (in_array($type, [
                    'bigInteger', 'mediumInteger', 'smallInteger', 'tinyInteger', 'integer', 'decimal', 'double', 'float',
                    'unsignedBigInteger', 'unsignedMediumInteger', 'unsignedSmallInteger', 'unsignedTinyInteger', 'unsignedInteger', 'unsignedDecimal', 'unsignedDouble', 'unsignedFloat'
                ])) $pathStub = '/vue/component.form.number.stub';

                //Textarea
                if (in_array($type, [
                    'text', 'mediumText', 'longText', 'tinyText'
                ])) $pathStub = '/vue/component.form.textarea.stub';

                //Foreign Keys
                if (in_array($type, [
                    'foreignId', 'foreignUuid', 'foreignUlid',
                ])) {
                    $pathStub = '/vue/component.form.select.stub';
                    $name = Str::of($name)->replace('_id', '')->toString();
                }

                break;
        }
        return (new Stub($pathStub, [
            'TITLE'     => Str::replace("_", " ", Str::title($name)),
            'VAR_NAME'  => Str::camel($name),
            'MODULE'    => Str::of($this->getModuleName())->snake()->slug()->plural(),
            'ENDPOINT'    => Str::of($name)->snake()->slug()->plural(),
        ]))->render();
    }
}
