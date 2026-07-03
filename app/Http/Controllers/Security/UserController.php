<?php

namespace App\Http\Controllers\Security;

use App\Exports\Security\UsersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Security\UserRequest;
use App\Models\User;
use App\Services\Security\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

class UserController extends Controller
{
    public function __construct(private readonly UserService \) {}

    public function index(Request \): Response
    {
        \ = \->input('search', '');

        return Inertia::render(
            'Security/Users/Index',
            \->users->paginatedIndexData(\->user(), \)
        );
    }

    public function create(): Response
    {
        return Inertia::render('Security/Users/Form', \->users->createFormData(auth()->user()));
    }

    public function store(UserRequest \): RedirectResponse
    {
        \->users->createUser(\->user(), \->validated());

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User \): Response
    {
        return Inertia::render('Security/Users/Form', \->users->editFormData(auth()->user(), \));
    }

    public function update(UserRequest \, User \): RedirectResponse
    {
        \->users->updateUser(\->user(), \, \->validated());

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request \, User \): JsonResponse
    {
        abort_unless(\->user()->can('usuarios.delete'), 403);

        if (\->user()->id === \->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar tu propio usuario.',
            ], 422);
        }

        \->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }

    public function export(Request \)
    {
        abort_unless(\->user()->can('usuarios.export'), 403);

        \ = \->input('search', '');
        \ = 'usuarios_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new UsersExport(\->user(), \),
            \,
            ExcelWriter::XLSX
        );
    }
}
