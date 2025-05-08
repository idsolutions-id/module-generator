<?php

namespace Vheins\LaravelModuleGenerator\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\DB;

class CreateRelation
{
    use AsAction;

    public $module;

    public $name;

    public $relations;

    public $modelFile;

    public function getDataType($v){
        return match ($v) {
            'bigIncrements' => 'integer',
            'bigInteger' => 'integer',
            'boolean' => 'boolean',
            'char' => 'string',
            'date' => 'date',
            'dateTime' => 'date',
            'dateTimeTz' => 'date',
            'decimal' => 'numeric',
            'double' => 'numeric',
            'enum' => 'string',
            'float' => 'numeric',
            'id' => 'integer',
            'increments' => 'integer',
            'integer' => 'integer',
            'ipAddress' => 'string',
            'json' => 'array',
            'jsonb' => 'array',
            'longText' => 'string',
            'macAddress' => 'string',
            'mediumIncrements' => 'integer',
            'mediumInteger' => 'integer',
            'mediumText' => 'string',
            'nullableUlidMorphs' => 'string',
            'nullableUuidMorphs' => 'string',
            'rememberToken' => 'string',
            'set' => 'string',
            'smallIncrements' => 'integer',
            'smallInteger' => 'integer',
            'softDeletes' => 'date',
            'softDeletesTz' => 'date',
            'string' => 'string',
            'text' => 'string',
            'time' => 'date',
            'timeTz' => 'date',
            'timestamp' => 'date',
            'timestamps' => 'date',
            'timestampsTz' => 'date',
            'tinyIncrements' => 'integer',
            'tinyInteger' => 'integer',
            'tinyText' => 'string',
            'ulid' => 'string',
            'ulidMorphs' => 'string',
            'unsignedBigInteger' => 'integer',
            'unsignedInteger' => 'integer',
            'unsignedMediumInteger' => 'integer',
            'unsignedSmallInteger' => 'integer',
            'unsignedTinyInteger' => 'integer',
            'uuid' => 'string',
            'foreignId' => 'integer',
            'foreignUuid' => 'uuid',
            'foreignUlid' => 'ulid',
            'uuidMorphs' => 'string',
            'vector' => 'string',
            'year' => 'integer',
            default => $v
        };
    }

    public function handle($args): void
    {
        $rules = [];
        $controllerRelations = [];
        $controllerMapRequests = [];
        $cascadeDeletes = [];
        foreach ($args['fillable'] as $k => $v) {
            $dataType = $this->getDataType($v);
            $rules[$k] = ['required', $dataType];
        }

        $this->module = $args['module'];
        $this->name = $args['name'];
        $this->relations = $args['relations'];
        $this->modelFile = base_path() . '/modules/' . $this->module . '/Models/' . Str::studly($this->name) . '.php';
        $this->controllerFile = base_path() . '/modules/' . $this->module . '/Controllers/' . Str::studly($this->name) . 'Controller.php';

        foreach ($this->relations as $k => $v) {

            //Check if relation references exist
            $reff = "use Illuminate\Database\Eloquent\Relations\\" . $k . ';';
            $model = file_get_contents($this->modelFile);
            $contains = Str::contains($model, $reff);
            if (! $contains) {
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $reff, $model);
                file_put_contents($this->modelFile, $model);
            }

            switch ($k) {
                case 'BelongsTo':
                    foreach($v as $relation){
                        $rule = 'exists:' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . Str::studly($relation) . ',id';
                        $column = Str::snake($relation).'_id';
                        $relationName = Str::of($relation)->camel()->toString();
                        $rules[$column][] = $rule;
                        $controllerMapRequests[$relationName] = $column;
                        $controllerRelations[] = "'".Str::of($relation)->camel()->toString().":id,name'";
                    }
                    $this->belongsTo($k, $v);
                    break;
                case 'HasOne':
                    foreach($v as $relation){
                        $relationName = Str::of($relation)->camel()->toString();
                        $column = Str::snake($relation).'_id';
                        $controllerMapRequests[$relation] = $column;
                        $controllerRelations[] = "'".Str::of($relation)->camel()->toString().":id,name'";
                    }
                    $this->hasOne($k, $v);
                    break;
                case 'HasOneThrough':
                    $this->hasManyThrough($k, $v);
                    break;
                case 'HasMany':
                    foreach($v as $relation){
                        $cascadeDeletes[] = "'".Str::of($relation)->camel()->plural()->toString()."'";
                    }
                    $this->hasMany($k, $v);
                    break;
                case 'HasManyThrough':
                    $this->hasManyThrough($k, $v);
                    break;
                case 'MorphOne':
                    $this->morphOne($k, $v);
                    break;
                case 'MorphMany':
                    $this->morphMany($k, $v);
                    break;
                case 'MorphTo':
                    $this->morphTo($k, $v);
                    break;
            }
        }

        if($controllerRelations != []){
            $stringRelations = "[\n" . implode(",\n", $controllerRelations) . "\n];";
            $controller = file_get_contents($this->controllerFile);
            $controller = str_replace('private $relation = [];', 'private $relation = ' . $stringRelations, $controller);
            file_put_contents($this->controllerFile, $controller);
        }

        if($controllerMapRequests != []){
            $stringMaps = '';
            foreach($controllerMapRequests as $k => $v){
                $stringMaps .= "'" . $k . "' => '" .  $v . "',\n";
            }
            $stringMaps = "[\n" . $stringMaps . "];";
            $model = file_get_contents($this->controllerFile);
            $model = str_replace('$mapRequest = [];', '$mapRequest = ' . $stringMaps, $model);
            file_put_contents($this->controllerFile, $model);
        }

        if($rules != []){
            $stringRules = '';
            foreach($rules as $k => $v){
                $stringRules .= "'" . $k . "' => '" . implode('|', $v) . "',\n";
            }
            $stringRules = "[\n" . $stringRules . "];";
            $model = file_get_contents($this->modelFile);
            $model = str_replace('$rules = [];', '$rules = ' . $stringRules, $model);
            file_put_contents($this->modelFile, $model);
        }

        if($cascadeDeletes != []){
            $model = file_get_contents($this->modelFile);
            $model = str_replace('$cascadeDeletes = [];', '$cascadeDeletes = [' . implode(',',$cascadeDeletes) . '];', $model);
            file_put_contents($this->modelFile, $model);
        }
    }

    private function morphTo($k, $v)
    {
        $v = Arr::sortDesc($v);
        foreach ($v as $m) {
            $model = file_get_contents($this->modelFile);
            $mm = Str::of($m);
            $model = str_replace('//Model Relationship', "//Model Relationship\n\tpublic function " . $mm->camel()->singular() . "()\n\t{\n\t\treturn " . '$this->' . Str::camel($k) . "();\n\t}\n", $model);
            file_put_contents($this->modelFile, $model);
        }
    }

    private function hasOne($k, $v)
    {
        $v = Arr::sortDesc($v);

        foreach ($v as $m) {
            $model = file_get_contents($this->modelFile);

            //Add class refferences || Check if model references exist
            $class = 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $m . ';';
            $contains = Str::contains($model, $class);
            if (! $contains) {
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);
            }

            $mm = Str::of($m);
            $model = str_replace('//Model Relationship', "//Model Relationship\n\tpublic function " . $mm->camel()->singular() . '(): ' . $k . "\n\t{\n\t\treturn " . '$this->' . Str::camel($k) . '(' . $mm->studly() . "::class);\n\t}\n", $model);
            file_put_contents($this->modelFile, $model);
        }
    }

    private function hasOneThrough($k, $v)
    {
        $v = Arr::sortDesc($v);
        foreach ($v as $key => $val) {
            $model = file_get_contents($this->modelFile);

            //Add class refferences || Check if model references exist
            $class = 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $val . ';';
            $contains = Str::contains($model, $class);
            if (! $contains) {
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);
            }

            //Add class refferences || Check if model references exist
            $class = 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $key . ';';
            $contains = Str::contains($model, $class);
            if (! $contains) {
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);
            }

            $strKey = Str::of($key);
            $strVal = Str::of($val);
            $model = str_replace('//Model Relationship', "//Model Relationship\n\tpublic function " . $strKey->camel()->plural() . '(): ' . $k . "\n\t{\n\t\treturn " . '$this->' . Str::camel($k) . "(\n\t\t\t" . $strKey->studly() . "::class,\n\t\t\t" . $strVal->studly() . "::class,\n\t\t\t'" . Str::snake($this->name) . "_id',\n\t\t\t'id',\n\t\t\t'id',\n\t\t\t'" . $strKey->snake() . "_id'\n\t\t)->latest();\n\t}\n", $model);

            file_put_contents($this->modelFile, $model);
        }
    }

    private function hasMany($k, $v)
    {
        $v = Arr::sortDesc($v);

        foreach ($v as $m) {
            $model = file_get_contents($this->modelFile);

            //Add class refferences || Check if model references exist
            $class = 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $m . ';';
            $contains = Str::contains($model, $class);
            if (! $contains) {
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);
            }

            $mm = Str::of($m);
            $model = str_replace('//Model Relationship', "//Model Relationship\n\tpublic function " . $mm->camel()->plural() . '(): ' . $k . "\n\t{\n\t\treturn " . '$this->' . Str::camel($k) . '(' . $mm->studly() . "::class);\n\t}\n", $model);
            file_put_contents($this->modelFile, $model);
        }
    }

    private function hasManyThrough($k, $v)
    {
        $v = Arr::sortDesc($v);
        foreach ($v as $key => $val) {
            $model = file_get_contents($this->modelFile);

            //Add class refferences || Check if model references exist
            $class = 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $val . ';';
            $contains = Str::contains($model, $class);
            if (! $contains) {
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);
            }

            //Add class refferences || Check if model references exist
            $class = 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $key . ';';
            $contains = Str::contains($model, $class);
            if (! $contains) {
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);
            }

            $strKey = Str::of($key);
            $strVal = Str::of($val);
            $model = str_replace('//Model Relationship', "//Model Relationship\n\tpublic function " . $strKey->camel()->plural() . '(): ' . $k . "\n\t{\n\t\treturn " . '$this->' . Str::camel($k) . "(\n\t\t\t" . $strKey->studly() . "::class,\n\t\t\t" . $strVal->studly() . "::class,\n\t\t\t'" . Str::snake($this->name) . "_id',\n\t\t\t'id',\n\t\t\t'id',\n\t\t\t'" . $strKey->snake() . "_id'\n\t\t)->latest();\n\t}\n", $model);

            file_put_contents($this->modelFile, $model);
        }
    }

    private function belongsTo($k, $v)
    {
        $v = Arr::sortDesc($v);
        foreach ($v as $m) {
            $model = file_get_contents($this->modelFile);

            //Add class refferences || Check if model references exist
            $class = 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $m . ';';
            $contains = Str::contains($model, $class);
            if (! $contains) {
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);
            }

            $mm = Str::of($m);
            $model = str_replace('//Model Relationship', "//Model Relationship\n\tpublic function " . $mm->camel()->singular() . '(): ' . $k . "\n\t{\n\t\treturn " . '$this->' . Str::camel($k) . '(' . $mm->studly() . "::class);\n\t}\n", $model);
            file_put_contents($this->modelFile, $model);
        }
    }

    private function morphOne($k, $v)
    {
        $v = Arr::sortDesc($v);
        foreach ($v as $key => $val) {
            $model = file_get_contents($this->modelFile);

            //Add class refferences || Check if model references exist
            $class = 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $key . ';';
            $contains = Str::contains($model, $class);
            if (! $contains) {
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);
            }

            $strKey = Str::of($key);
            $strVal = Str::of($val);
            $model = str_replace('//Model Relationship', "//Model Relationship\n\tpublic function " . $strKey->camel()->singular() . '(): ' . $k . "\n\t{\n\t\treturn " . '$this->' . Str::camel($k) . '(' . $strKey->studly() . "::class,'" . $strVal->snake() . "');\n\t}\n", $model);
            file_put_contents($this->modelFile, $model);
        }
    }

    private function morphMany($k, $v)
    {
        $v = Arr::sortDesc($v);
        foreach ($v as $key => $val) {
            $model = file_get_contents($this->modelFile);

            //Add class refferences || Check if model references exist
            $class = 'use ' . config('modules.namespace') . '\\' . $this->module . '\\Models\\' . $key . ';';
            $contains = Str::contains($model, $class);
            if (! $contains) {
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);
            }

            $strKey = Str::of($key);
            $strVal = Str::of($val);
            $model = str_replace('//Model Relationship', "//Model Relationship\n\tpublic function " . $strKey->camel()->singular() . '(): ' . $k . "\n\t{\n\t\treturn " . '$this->' . Str::camel($k) . '(' . $strKey->studly() . "::class,'" . $strVal->snake() . "');\n\t}\n", $model);
            file_put_contents($this->modelFile, $model);
        }
    }
}
