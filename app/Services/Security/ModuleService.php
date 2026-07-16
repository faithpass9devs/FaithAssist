<?php

namespace App\Services\Security;

use App\Models\Module;
use App\Repositories\Security\ModuleRepository;

class ModuleService
{
    public function __construct(private readonly ModuleRepository $modules) {}

    public function indexData(string $search): array
    {
        return [
            'modules' => $this->modules->paginateWithSearch($search),
            'search' => $search,
        ];
    }

    public function createModule(array $data): array
    {
        $module = $this->modules->create($data);
        return $this->moduleData($module);
    }

    public function updateModule(Module $module, array $data): array
    {
        $module = $this->modules->update($module, $data);
        return $this->moduleData($module);
    }

    public function deleteModule(Module $module): void
    {
        $this->modules->delete($module);
    }

    private function moduleData(Module $module): array
    {
        return $module->only(['id', 'name', 'description', 'key']);
    }
}
