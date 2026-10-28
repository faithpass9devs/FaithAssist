<?php

namespace App\Http\Controllers\Masses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\EventRequest;
use App\Models\Masses\Event;
use App\Services\Masses\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private readonly EventService $events)
    {
        $this->authorizeResource(Event::class, 'evento');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $tab = $request->input('tab', 'current');

        if (! in_array($tab, ['current', 'history', 'upcoming'], true)) {
            $tab = 'current';
        }

        return Inertia::render('Masses/Events/Index', $this->events->indexData($request->user(), $search, $tab));
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Masses/Events/Form', $this->events->createFormData($request->user()));
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = $this->events->createEvent($request->payload());

        return redirect()->route('eventos.index')
            ->with('success', "Evento \"{$event->name}\" creado correctamente.");
    }

    public function edit(Request $request, Event $evento): Response
    {
        return Inertia::render('Masses/Events/Form', $this->events->editFormData($request->user(), $evento));
    }

    public function update(EventRequest $request, Event $evento): RedirectResponse
    {
        $event = $this->events->updateEvent($evento, $request->payload($evento));

        return redirect()->route('eventos.index')
            ->with('success', "Evento \"{$event->name}\" actualizado correctamente.");
    }

    public function destroy(Request $request, Event $evento): RedirectResponse
    {
        $this->events->deleteEvent($evento, $request->user()?->id);

        return redirect()->route('eventos.index')
            ->with('success', 'Evento eliminado correctamente.');
    }
}
