<?php

namespace App\Http\Controllers;

use App\Http\Requests\Whatsapp\SendWhatsappMessageRequest;
use App\Models\WhatsappMessage;
use App\Services\Standalone\WhatsappMessageService;
use Inertia\Inertia;

class WhatsappMessageController extends Controller
{
    public function __construct(private readonly WhatsappMessageService $whatsappMessages) {}

    public function index()
    {
        $this->authorize('viewAny', WhatsappMessage::class);

        return Inertia::render('Whatsapp/Index', $this->whatsappMessages->getIndexData());
    }

    public function history()
    {
        return $this->index();
    }

    public function historyJson()
    {
        $this->authorize('viewAny', WhatsappMessage::class);

        return response()->json(
            WhatsappMessage::query()
                ->latest()
                ->get(['id', 'to_phone', 'status', 'created_at'])
        );
    }

    public function send(SendWhatsappMessageRequest $request)
    {
        $this->authorize('create', WhatsappMessage::class);

        $result = $this->whatsappMessages->sendPdf([
            ...$request->validated(),
            'pdf_file' => $request->file('pdf'),
        ]);

        $statusCode = $result['ok'] ? 200 : 500;
        if (isset($result['errors'])) {
            $statusCode = 422;
        }

        return response()->json($result, $statusCode);
    }
}
