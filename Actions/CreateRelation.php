<?php

namespace Vheins\LaravelModuleGenerator\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateRelation
{
    use AsAction;

    public $module;

    public $name;

    public $relations;

    public $modelFile;

    public function handle($args)
    {
        $this->module = $args['module'];
        $this->name = $args['name'];
        $this->relations = $args['relations'];
        $this->modelFile = base_path() . '/modules/' . $this->module . '/Models/' . Str::studly($this->name) . '.php';

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
                    $this->belongsTo($k, $v);
                    break;
                case 'HasOne':
                    $this->hasOne($k, $v);
                    break;
                case 'HasOneThrough':
                    $this->hasManyThrough($k, $v);
                    break;
                case 'HasMany':
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
