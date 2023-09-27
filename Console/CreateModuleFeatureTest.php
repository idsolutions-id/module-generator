<?php

namespace Vheins\LaravelModuleGenerator\Console;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nwidart\Modules\Commands\GeneratorCommand;
use Nwidart\Modules\Support\Config\GenerateConfigReader;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use ReflectionMethod;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CreateModuleFeatureTest extends GeneratorCommand
{
    use ModuleCommandTrait;

    protected $argumentName = 'name';

    protected $name = 'create:module:test';

    protected $description = 'Create a new test class for the specified module.';

    protected $originalUri;

    protected $uri;

    public function getDefaultNamespace(): string
    {
        $module = $this->laravel['modules'];

        if ($this->option('feature')) {
            return $module->config('paths.generator.test-feature.namespace') ?: $module->config('paths.generator.test-feature.path', 'Tests/Feature');
        }

        return $module->config('paths.generator.test.namespace') ?: $module->config('paths.generator.test.path', 'Tests/Unit');
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the form request class.'],
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
            ['feature', false, InputOption::VALUE_NONE, 'Create a feature test.'],
        ];
    }

    /**
     * @return mixed
     */
    protected function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());
        $stub = '/unit-test.stub';

        if ($this->option('feature')) {
            $stub = '/feature-test.stub';
        }

        $return = [
            'NAMESPACE' => $this->getClassNamespace($module),
            'CLASS' => $this->getClass(),
            'LOWER_NAME' => $module->getLowerName(),
            'STUDLY_NAME' => $module->getStudlyName(),
            'PLURAL_NAME' => Str::of($module->getLowerName())->plural()->toString(),
        ];

        return (new Stub($stub, $return))->render();
    }

    /**
     * @return mixed
     */
    protected function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());

        if ($this->option('feature')) {
            $testPath = GenerateConfigReader::read('test-feature');
        } else {
            $testPath = GenerateConfigReader::read('test');
        }

        return $path.$testPath->getPath().'/'.$this->getFileName().'.php';
    }

    /**
     * @return string
     */
    private function getFileName()
    {
        return Str::studly($this->argument('name'));
    }

    /**
     * Run the command.
     */
    public function handle(): int
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());
        $baseUri = Str::of($module->getLowerName())->plural()->toString();
        //$routes = collect(app('router')->getRoutes())->toArray();
        $routes = app('router')->getRoutes();

        foreach ($routes as $route) {
            if (! Str::of($route->uri)->contains($baseUri, true)) {
                continue;
            }
            $originalUri = $this->getRouteUri($route);
            $uri = $this->strip_optional_char($originalUri);

            $action = $route->getAction('uses');
            $actionName = $this->getActionName($route->getActionName());
            $controllerName = $this->getControllerName($route->getActionName());
            $methods = $route->methods();

            foreach ($methods as $method) {
                $method = strtoupper($method);

                if (in_array($method, ['HEAD', 'OPTIONS'])) {
                    continue;
                }
                echo $method.'-'.$uri."\n";

                $rules = $this->getFormRules($action);
                if (empty($rules)) {
                    $rules = [];
                } else {
                    $rules = Arr::undot($rules);
                }
                // dd($rules);
            }

            // dd([
            //     'originalUri' => $originalUri,
            //     'uri' => $uri,
            //     'action' => $action,
            //     'actionName' => $actionName,
            //     'controllerName' => $controllerName,
            //     'methods' => $methods,
            // ]);
        }

        return 0;
    }

    protected function getRouteUri(Route $route)
    {
        $uri = $route->uri();

        if (! str_starts_with($uri, '/')) {
            $uri = '/'.$uri;
        }

        return $uri;
    }

    protected function strip_optional_char($uri)
    {
        return str_replace('?', '', $uri);
    }

    protected function getActionName($actionName)
    {
        $actionNameSubString = substr($actionName, strpos($actionName, '@') + 1);
        $actionNameArray = preg_split('/(?=[A-Z])/', ucfirst($actionNameSubString));
        $actionName = trim(implode('', $actionNameArray));

        return $actionName;
    }

    protected function getControllerName($controller)
    {
        $namespaceReplaced = substr($controller, strrpos($controller, '\\') + 1);
        $actionNameReplaced = substr($namespaceReplaced, 0, strpos($namespaceReplaced, '@'));
        $controllerReplaced = str_replace('Controller', '', $actionNameReplaced);
        $controllerNameArray = preg_split('/(?=[A-Z])/', $controllerReplaced);
        $controllerName = trim(implode('', $controllerNameArray));

        return $controllerName;
    }

    protected function getFormRules($action)
    {
        if (! is_string($action)) {
            return false;
        }

        $parsedAction = Str::parseCallback($action);

        $reflector = (new ReflectionMethod($parsedAction[0], $parsedAction[1]));
        $parameters = $reflector->getParameters();

        foreach ($parameters as $parameter) {
            $class = optional($parameter->getType())->getName();
            if (is_subclass_of($class, FormRequest::class)) {
                return (new $class)->rules();
            }
        }
    }

    protected function isAuthorizationExist($middlewares)
    {
        $hasAuth = array_filter($middlewares, function ($var) {
            return strpos($var, 'auth') > -1;
        });

        return $hasAuth;
    }
}
