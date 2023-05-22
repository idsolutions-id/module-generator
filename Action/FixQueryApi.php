<?php

namespace Vheins\LaravelModuleGenerator\Action;

use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Str;

class FixQueryApi
{
    use AsAction;
    public $module, $name, $query, $filePath;

    public function handle($module, $query)
    {
        $this->module = $module;
        $this->query = $query;
        $this->filePath = base_path() . "/modules/" . $this->module . "/api.php";

        $textApi = file_get_contents($this->filePath);
        $routeClass = "use " . config('modules.namespace') . "\\" . $this->module . "\\Controllers\\QueryController;";
        $contains = Str::contains($textApi, $routeClass);
        if (!$contains) $textApi = str_replace('//add more class here ...', $routeClass . "\n//add more class here ...", $textApi);

        $contains = Str::contains($textApi, '//Route Queries');
        if (!$contains) {
            $routeFunction = "\n\t\t//Route Queries\n\t\tRoute::prefix('queries')->controller(QueryController::class)->group(function(){\n\t\t\t//add more queries here ...\n\t\t});";
            $textApi = str_replace('//add more queries here ...', $routeFunction, $textApi);
        }

        foreach ($this->query as $val) {
            $v = Str::slug($val->toString());
            $cv = Str::camel($v);
            $routeText = "Route::get('" . $v . "', '" . $cv . "')->name('" . Str::of($this->module)->snake()->slug() . "." . $cv . ".query');";
            $contains = Str::contains($textApi, $routeText);
            if (!$contains)
                $textApi = str_replace('//add more queries here ...', "//add more queries here ...\n\t\t\t" . $routeText, $textApi);
        }

        file_put_contents($this->filePath, $textApi);
    }
}
