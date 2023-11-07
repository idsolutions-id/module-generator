<?php

namespace Vheins\LaravelModuleGenerator\Action;

use App\Http\Requests\FormRequest;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

class CreatePostmanCollection
{
    use AsAction;

    public string $commandSignature = 'create:postman-collection {module?}';

    private $module;

    private $collection;

    public function asCommand(Command $command): void
    {
        $this->module = $command->argument('module');
        $command->info(self::run($this->module));
    }

    public function handle($module = null)
    {
        $this->module = $module;
        $this->setInfo();
        foreach (collect(app('router')->getRoutes()) as $route) {
            $uri = $route->uri();
            if (! Str::contains($uri, 'api/v1/')) {
                continue;
            }

            $name = Str::of($route->getName())->replace('.', ' ')->headline()->toString();
            $routeNames = [];

            foreach (explode('.', Str::of($route->uri())->replaceMatches('/{([[:alnum:]]+)}/', '')->replace('/', '.')->toString()) as $name) {
                $routeNames[] = Str::of($name)->headline()->toString();
            }

            $routeNames = array_filter($routeNames, function ($value) {
                return ! is_null($value) && $value !== '' && $value !== 'Api';
            });

            $request = $this->makeRequest($route, $route->methods()[0]);
            $this->buildTree($this->collection, $routeNames, $request);
        }

        Storage::put($exportName = 'postman/'.config('app.name').'.json', json_encode($this->collection, JSON_PRETTY_PRINT));

        return 'Postman Collection Exported: '.storage_path('app/'.$exportName);
    }

    protected function buildTree(array &$routes, array $segments, array $request): void
    {
        $parent = &$routes;
        $destination = end($segments);
        foreach ($segments as $segment) {
            $matched = false;
            foreach ($parent['item'] as &$item) {
                if ($item['name'] == $segment) {
                    $parent = &$item;
                    if ($segment == $destination) {
                        $parent['item'][] = $request;
                    }
                    $matched = true;
                    break;
                }
            }

            unset($item);

            if (! $matched) {
                $item = [
                    'name' => $segment,
                    'item' => $segment === $destination ? [$request] : [],
                ];

                $parent['item'][] = &$item;
                $parent = &$item;
            }

            unset($item);
        }
    }

    private function getTextMethod($payload)
    {
        if (! $payload) {
            return '';
        }
        $payload = $payload->getName();
        switch ($payload) {
            case 'show':
                return 'Detail ';
                break;
            case 'store':
                return 'Create ';
                break;
            case 'update':
                return 'Update ';
                break;
            case 'destroy':
                return 'Delete ';
                break;

            default:
                return '';
                break;
        }
    }

    public function makeRequest($route, $method)
    {
        $uri = Str::of($route->uri())->replaceMatches('/{([[:alnum:]]+)}/', ':$1');
        $variables = $uri->matchAll('/(?<={)[[:alnum:]]+(?=})/m');
        $path = explode('/', $route->uri());
        $name = Str::of(end($path))->headline();

        $routeAction = $route->getAction();
        $reflection = $this->getReflectionMethod($routeAction);

        if (Str::of($route->uri())->contains('{')) {
            $name = $name->replaceMatches('/{([[:alnum:]]+)}/', '$1')->singular();
            $name = $this->getTextMethod($reflection).$name->toString();
        } else {
            if (! empty($this->getTextMethod($reflection))) {
                $name = $this->getTextMethod($reflection).$name->singular()->toString();
            } else {
                $name = $name->toString();
            }
        }

        $data = [
            'name' => $name,
            'description' => [
                'content' => $name,
            ],
            'request' => [
                'method' => strtoupper($method),
                'header' => $this->getHeader(),
                'url' => [
                    'raw' => '{{url}}/'.$uri,
                    'host' => ['{{url}}'],
                    'path' => $uri->explode('/')->filter(),
                    'variable' => $variables->transform(function ($variable) {
                        return ['key' => $variable, 'value' => ''];
                    })->all(),
                ],
            ],
        ];
        $rules = [];

        $action = $route->getAction('uses');
        $parsedAction = Str::parseCallback($action);
        $reflector = (new ReflectionMethod($parsedAction[0], $parsedAction[1]));
        $parameters = $reflector->getParameters();

        foreach ($parameters as $parameter) {
            $classes = explode('|', $parameter->getType());
            foreach ($classes as $class) {
                // code...
                // $class = $parameter->getType()?->getName();
                if ($reflection && (($reflection->getName() == 'index' && $class == Request::class) || $this->isQueryController($parsedAction[0]))) {
                    $data['request']['url']['query'] = $this->getQueryDefault();
                    $request = new Request(['per_page' => 1]);
                    try {
                        $getData = (new $parsedAction[0])->{$reflection->getName()}($request);
                        foreach ($getData->getData()->data as $result) {
                            if ($result) {
                                foreach ($result as $k => $v) {
                                    $collect = collect($data['request']['url']['query']);
                                    $key = Str::camel($k);
                                    $check = $collect->where('key', $key)->first();
                                    if (! $check && ! is_array($v) && ! is_object($v)) {
                                        $data['request']['url']['query'][] = [
                                            'key' => $key,
                                            'value' => $v,
                                            'description' => 'Nullable|Filter data by '.$key,
                                            'disabled' => true,
                                        ];
                                    }
                                }

                                $data['request']['description'] = "<h1>Response Example</h1>\n\n```json\n".json_encode($getData->getData(), JSON_PRETTY_PRINT)."\n```";
                            }
                        }
                    } catch (\Throwable $th) {
                        // return $th;
                    }
                }
                if (is_subclass_of($class, FormRequest::class)) {
                    $json_data = '';
                    if (in_array($reflection->getName(), ['store', 'update'])) {
                        $controller = new $parsedAction[0];
                        $model = property_exists($controller, 'model') ? $controller->model : null;
                        if ($model) {
                            $json_data = json_encode($model::factory()->make(), JSON_PRETTY_PRINT);
                        }
                    }
                    $rules = (new $class)->rules();
                    if (count($rules) > 0) {
                        $rules = $this->convert('camel', Arr::undot($rules));
                        $data['request']['body'] = [
                            'mode' => 'raw',
                            'options' => [
                                'raw' => [
                                    'language' => 'json',
                                ],
                            ],
                            'raw' => $json_data,
                        ];
                        $data['request']['description'] = "<h1>Request & Validation Rules</h1>\n\n```json\n".json_encode($rules, JSON_PRETTY_PRINT)."\n```";
                    }
                }
            }
        }

        return $data;
    }

    private function isQueryController($payload)
    {
        return Str::of($payload)->contains('QueryController');
    }

    private function getQueryDefault()
    {
        return [
            [
                'key' => 'skipPagination',
                'value' => 'false',
                'description' => 'Nullable|Boolean|Default:false|Skip Pagination to Fetch All Data',
                'disabled' => true,
            ],
            [
                'key' => 'skipOrder',
                'value' => 'false',
                'description' => 'Nullable|Boolean|Default:false|Skip Ordering',
                'disabled' => true,
            ],
            [
                'key' => 'status',
                'value' => 'all',
                'description' => 'Nullable|Boolean|String:all|Default:all|Fetch data by Status',
                'disabled' => true,
            ],
            [
                'key' => 'search',
                'value' => null,
                'description' => 'Nullable|String|Default:null|Keyword for Search',
                'disabled' => true,
            ],
            [
                'key' => 'searchType',
                'value' => 'name',
                'description' => 'Nullable|String|Default:name|Search Key for custom search',
                'disabled' => true,
            ],
            [
                'key' => 'sortBy',
                'value' => 'createdAt',
                'description' => 'Nullable|String|Default:createdAt|Sort By',
                'disabled' => true,
            ],
            [
                'key' => 'sortKey',
                'value' => 'desc',
                'description' => 'Nullable|String:asc,desc|Default:desc|Sort Key',
                'disabled' => true,
            ],
            [
                'key' => 'perPage',
                'value' => '10',
                'description' => 'Nullable|Integer|Default:10|Items Per Page',
                'disabled' => true,
            ],
            [
                'key' => 'page',
                'value' => '1',
                'description' => 'Nullable|Integer|Page Number|Default:1',
                'disabled' => true,
            ],
        ];
    }

    private function getHeader()
    {
        return [
            [
                'key' => 'Content-Type',
                'value' => 'application/json',
            ],
        ];
    }

    private function setInfo()
    {
        $this->collection['item'] = [];
        $this->collection['info'] = [
            'name' => config('app.name'),
            'description' => 'Generated by IDS Stater Kit on '.date('Y-m-d H:i:s'),
            'schema' => 'https://schema.getpostman.com/json/collection/v2.0.0/collection.json',
        ];
        $this->collection['auth'] = [
            'type' => 'bearer',
            'bearer' => [
                [
                    'key' => 'token',
                    'value' => '{{token}}',
                    'type' => 'string',
                ],
            ],
        ];
        $this->collection['event'][] = [
            'listen' => 'prerequest',
            'script' => [
                'type' => 'text/javascript',
                'exec' => [
                    'pm.request.headers.add({',
                    "    key: pm.globals.get('enc_key'),",
                    "    value: pm.globals.get('enc_val')",
                    '});',
                    'pm.request.headers.add({',
                    "    key: 'disable-cache',",
                    '    value: true',
                    '});',
                    'pm.request.headers.add({',
                    "    key: 'return-payload',",
                    '    value: true',
                    '});',
                ],
            ],
        ];
    }

    private function item()
    {
        return [
            'name' => null,
            'request' => [
                'method' => null,
                'header' => [],
                'url' => [
                    'raw' => '{{url}}',
                    'host' => ['{{url}}'],
                    'path' => [],
                ],
            ],
            'response' => [],
        ];
    }

    protected function getReflectionMethod(array $routeAction): ?object
    {
        // Hydrates the closure if it is an instance of Opis\Closure\SerializableClosure
        if ($this->containsSerializedClosure($routeAction)) {
            $routeAction['uses'] = unserialize($routeAction['uses'])->getClosure();
        }

        if ($routeAction['uses'] instanceof Closure) {
            return new ReflectionFunction($routeAction['uses']);
        }

        $routeData = explode('@', $routeAction['uses']);
        $reflection = new ReflectionClass($routeData[0]);

        if (! $reflection->hasMethod($routeData[1])) {
            return null;
        }

        return $reflection->getMethod($routeData[1]);
    }

    public static function containsSerializedClosure(array $action): bool
    {
        return is_string($action['uses']) && Str::startsWith($action['uses'], [
            'C:32:"Opis\\Closure\\SerializableClosure',
            'O:47:"Laravel\SerializableClosure\\SerializableClosure',
            'O:55:"Laravel\\SerializableClosure\\UnsignedSerializableClosure',
        ]);
    }

    private function convert(string $case, $data)
    {
        if (! in_array($case, ['camel', 'snake'])) {
            throw new InvalidArgumentException('Case must be either snake or camel');
        }

        if (! is_array($data)) {
            return $data;
        }

        $array = [];

        foreach ($data as $key => $value) {
            $array[Str::{$case}($key)] = is_array($value)
                ? $this->convert($case, $value)
                : $value;
        }

        return $array;
    }
}
