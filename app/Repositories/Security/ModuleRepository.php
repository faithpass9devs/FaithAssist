<?php

namespace App\Repositories\Security;

use App\Models\Module;

class ModuleRepository
{
    public function paginateWithSearch(string $search, int $perPage = 15)
    {
        return Module::query()
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('key', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'description', 'key'])
            ->withQueryString();
    }

    public function create(array $data): Module
    {
        return Module::create($data);
    }

    public function update(Module $module, array $data): Module
    {
        $module->update($data);
        return $module->fresh();
    }

    public function delete(Module $module): void
    {
        $module->delete();
    }
}
