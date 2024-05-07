<?php

namespace Vheins\LaravelModuleGenerator\Actions;

use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateNavigation
{
    use AsAction;

    private $path = null;

    private $moduleId = null;

    private $data = null;

    private $module = null;

    private $name = null;

    private $childPermission = null;

    public function handle($module, $name)
    {
        $this->module = $module;
        $this->name = $name;
        $this->path = base_path() . '/modules/' . $module . '/navigation.json';
        $this->moduleId = 'module.' . Str::of($module)->slug('.')->toString();
        $this->childPermission = $this->moduleId . '.' . Str::lower($this->name);
        $this->loadData();

        $data = $this->data;
        $hasChild = collect($data)->where('children.title', $name)->first();
        if (! $hasChild) {
            $data['children'][] = $this->createChild();
        }

        //Regenerate Permission
        $modulePermissions = [];
        $permissions = collect($data['children'])->pluck('permission')->toArray();
        foreach ($permissions as $permission) {
            $modulePermissions = array_merge($modulePermissions, $permission);
        }
        $modulePermissions = array_unique($modulePermissions);
        $uniqueValues = array_unique($modulePermissions);
        $modulePermissions = array_values($uniqueValues);
        $data['permission'] = $modulePermissions;

        $text = json_encode([$data], JSON_PRETTY_PRINT);
        $text = Str::of($text)->replace('\/', '/')->toString();
        file_put_contents($this->path, $text);
    }

    private function loadData()
    {
        $data = null;
        if (file_exists($this->path)) {
            $json = json_decode(file_get_contents($this->path), true);
            $data = collect($json)->where('id', $this->moduleId)->first();
        }
        if (! $data) {
            $data = $this->baseNavigation();
        }
        $this->data = $data;
    }

    private function baseNavigation()
    {
        return [
            'id' => $this->moduleId,
            'sequence' => 1,
            'title' => Str::of($this->module)->headline()->toString(),
            'permission' => [$this->moduleId],
            'icon' => 'BrandLaravelIcon',
            'children' => [],
        ];
    }

    private function createChild()
    {
        $module = Str::of($this->module)->lower()->plural()->toString();
        $name = Str::of($this->name)->lower()->plural()->toString();

        return [
            'title' => $this->name,
            'link' => '/dashboard/' . $module . '/' . $name,
            'icon' => 'BrandLaravelIcon',
            'permission' => [$this->childPermission],
        ];
    }
}
