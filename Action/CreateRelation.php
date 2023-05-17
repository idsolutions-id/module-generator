<?php

namespace Vheins\LaravelModuleGenerator\Action;

use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Str;

class CreateRelation
{
    use AsAction;
    public $module, $name, $relations, $modelFile;

    public function handle($args)
    {
        $this->module = $args['module'];
        $this->name = $args['name'];
        $this->relations = $args['relations'];
        $this->modelFile = base_path() . "/modules/" . $this->module . "/Models/" . Str::studly($this->name) . ".php";

        foreach ($this->relations as $k => $v) {
            //Check if relation references exist
            $reff = "use Illuminate\Database\Eloquent\Relations\\" . $k . ";";
            $model = file_get_contents($this->modelFile);
            $contains = Str::contains($model, $reff);
            if (!$contains) {
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $reff, $model);
                file_put_contents($this->modelFile, $model);
            }

            switch ($k) {
                case "HasOne":
                    $this->hasone($k, $v);
                    break;
                case "HasMany":
                    $this->hasMany($k, $v);
                    break;
                case "BelongsTo":
                    $this->belongsTo($k, $v);
                    break;
            }
        }
    }

    private function hasOne($k, $v)
    {
        $v = Arr::sortDesc($v);

        foreach ($v as $m) {
            $model = file_get_contents($this->modelFile);

            //Add class refferences || Check if model references exist
            $class = "use IDS\\" . $this->module . "\\Models\\" . $m . ";";
            $contains = Str::contains($model, $class);
            if (!$contains)
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);

            $mm = Str::of($m);
            $model = str_replace('//Model Relationship', "//Model Relationship\n\tpublic function " . $mm->camel()->singular() . "(): " . $k . "\n\t{\n\t\treturn " . '$this->' . Str::camel($k) . "(" . $mm->studly() . "::class);\n\t}\n", $model);
            file_put_contents($this->modelFile, $model);
        }
    }

    private function hasMany($k, $v)
    {
        $v = Arr::sortDesc($v);

        foreach ($v as $m) {
            $model = file_get_contents($this->modelFile);

            //Add class refferences || Check if model references exist
            $class = "use IDS\\" . $this->module . "\\Models\\" . $m . ";";
            $contains = Str::contains($model, $class);
            if (!$contains)
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);

            $mm = Str::of($m);
            $model = str_replace('//Model Relationship', "//Model Relationship\n\tpublic function " . $mm->camel()->plural() . "(): " . $k . "\n\t{\n\t\treturn " . '$this->' . Str::camel($k) . "(" . $mm->studly() . "::class);\n\t}\n", $model);
            file_put_contents($this->modelFile, $model);
        }
    }

    private function belongsTo($k, $v)
    {
        $v = Arr::sortDesc($v);
        $reff = "use Illuminate\Database\Eloquent\Relations\BelongsTo;";
        foreach ($v as $m) {
            $model = file_get_contents($this->modelFile);

            //Add class refferences || Check if model references exist
            $class = "use IDS\\" . $this->module . "\\Models\\" . $m . ";";
            $contains = Str::contains($model, $class);
            if (!$contains)
                $model = str_replace('//Class Refferences', "//Class Refferences\n" . $class, $model);

            $mm = Str::of($m);
            $model = str_replace('//Model Relationship', "//Model Relationship\n\tpublic function " . $mm->camel()->singular() . "(): " . $k . "\n\t{\n\t\treturn " . '$this->' . Str::camel($k) . "(" . $mm->studly() . "::class);\n\t}\n", $model);
            file_put_contents($this->modelFile, $model);
        }
    }
}
