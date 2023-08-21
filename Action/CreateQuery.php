<?php

namespace Vheins\LaravelModuleGenerator\Action;

use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Str;

class CreateQuery
{
    use AsAction;
    public $module, $name, $fileStub, $filePath;

    public function handle($args)
    {
        $this->module = $args['module'];
        $this->name = $args['name'];
        $this->fileStub = base_path() . "/stubs/controller.query.stub";
        $this->filePath = base_path() . "/modules/" . $this->module . "/Controllers/QueryController.php";

        $this->checkController();


        $text = file_get_contents($this->filePath);
        $modelName = "use " . config('modules.namespace') . "\\" . $this->module . "\Models\\" . $this->name . ";";
        $contains = Str::contains($text, $modelName);
        if (!$contains)
            $text = str_replace("//Class Refferences", "//Class Refferences\n" . $modelName, $text);

        $query = "public function " . Str::of($this->name)->camel()->plural() . '(Request $request)';
        $contains = Str::contains($text, $query);
        if (!$contains) {
            $function = "public function " . Str::of($this->name)->camel()->plural() . '(Request $request)' . "\n\t{\n\t\t" . '$model = ' . $this->name . "::active()->select('id', 'name');\n\t\t" . '$data = $this->search($model, $request);' . "\n\t\t" . 'return $this->success($data);' . "\n\t}\n";
            $text = str_replace("//Query Select2", "//Query Select2\n\t" . $function, $text);
        }

        file_put_contents($this->filePath, $text);
    }

    private function checkController()
    {

        if (!file_exists($this->filePath)) {
            $moduleName = $this->module;
            $permissions = explode('_', Str::of($moduleName)->snake());
            $splitNames = [];
            foreach ($permissions as $permission) {
                $splitNames[] = Str::of($permission)->singular();
            }
            $unique = array_unique($splitNames);
            $unique = implode('.', $unique);
            $permission = Str::of($unique);

            $namespace = "IDS\\" . $this->module . "\Controllers";

            $model = file_get_contents($this->fileStub);
            $model = str_replace('$PERMISSION$', $permission, $model);
            $model = str_replace('{{ namespace }}', $namespace, $model);
            file_put_contents($this->filePath, $model);
        }
    }
}
