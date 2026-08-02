<?php

namespace App\Http\Controllers\Catechism;

use App\Http\Controllers\Controller;
use App\Models\External\ExternalChild;
use App\Services\Catechism\ExternosService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExternosController extends Controller
{
    public function __construct(private readonly ExternosService $externos)
    {
        $this->authorizeResource(ExternalChild::class, 'externo');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Catechism/Externos/Index', $this->externos->indexData(
            $request->user(),
            $request->input('search', ''),
            $request->integer('level_id') ?: null,
            $request->integer('community_id') ?: null,
        ));
    }

    public function show(Request $request, ExternalChild $externo): Response
    {
        $this->authorize('show', $externo);

        return Inertia::render('Catechism/Externos/Index', [
            ...$this->externos->indexData(
                $request->user(),
                $request->input('search', ''),
                $request->integer('level_id') ?: null,
                $request->integer('community_id') ?: null,
            ),
            'selectedExterno' => $this->externos->showData($request->user(), $externo)['externo'],
        ]);
    }
}
