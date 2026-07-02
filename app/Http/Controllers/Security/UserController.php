<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\UserRequest;
use App\Models\User;
use App\Services\Security\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render(
            'Security/Users/Index',
            $this->users->paginatedIndexData($request->user(), $search)
        );
    }

    public function create(): Response
    {
        return Inertia::render('Security/Users/Form', $this->users->createFormData(auth()->user()));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->users->createUser($request->user(), $request->validated());

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $usuario): Response
    {
        return Inertia::render('Security/Users/Form', $this->users->editFormData(auth()->user(), $usuario));
    }

    public function update(UserRequest $request, User $usuario): RedirectResponse
    {
        $this->users->updateUser($request->user(), $usuario, $request->validated());

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }
}
