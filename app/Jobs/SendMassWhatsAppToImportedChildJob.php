<?php

namespace App\Jobs;

use App\Models\Catechism\Child;
use App\Models\FailedWhatsappChild;
use App\Models\WhatsappMessage;
use App\Services\Catechism\ChildQrWhatsappService;
use App\Services\WhatsApp\BaileysClient;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMassWhatsAppToImportedChildJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly int $childId,
        public readonly ?int $recordId = null,
    ) {}

    public function handle(BaileysClient $client): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $child = Child::find($this->childId);

        if (! $child) {
            $this->recordFailure('Nino no encontrado en la base de datos');

            return;
        }

        if (! $child->phone || ! $child->phone_lada) {
            $this->recordFailure('El nino no tiene telefono registrado');

            return;
        }

        try {
            $fullName = trim(collect([$child->name, $child->paterno, $child->materno])->filter()->implode(' '));
            $caption = "🎓 GAFETE DE ASISTENCIA\n\n"
                ."Con gusto le compartimos el gafete de asistencia correspondiente a su hijo(a). 📄\n\n"
                ."👤 Nombre: {$fullName}\n\n"
                ."🔎 Le solicitamos verificar que los datos sean correctos.\n\n"
                ."⚠️ En caso de detectar alguna información incorrecta, favor de acudir a las *oficinas de la Parroquia del Centro* para solicitar la aclaración correspondiente.\n\n"
                .'📌 *Mensaje informativo. No es necesario responder a este WhatsApp.*';

            $qrService = app(ChildQrWhatsappService::class);
            $badgePdfPath = $qrService->generateBadgePdfFile($child);

            if (! $badgePdfPath) {
                $this->recordFailure('No se pudo generar el PDF del gafete');

                return;
            }

            $phoneNumber = "{$child->phone_lada}{$child->phone}";
            $firstName = trim($child->name ?? '') !== '' ? explode(' ', trim($child->name))[0] : 'NINO';
            $firstLastName = trim($child->paterno ?? '') !== '' ? explode(' ', trim($child->paterno))[0] : 'SIN_APELLIDO';
            $displayName = preg_replace('/[^\pL\pN\s\-]/u', '', trim($firstName.' '.$firstLastName)) ?: 'NINO SIN_APELLIDO';

            $message = WhatsappMessage::create([
                'to_phone' => $phoneNumber,
                'country_code' => $child->phone_lada,
                'message_type' => 'document',
                'message_body' => $caption,
                'pdf_path' => $badgePdfPath,
                'filename' => 'Gafete de Asistencia '.$displayName.'.pdf',
                'status' => 'pending',
                'max_retries' => 1,
                'legend_text' => '',
            ]);

            $result = $client->send($message);

            $message->update([
                'status' => 'sent',
                'baileys_message_id' => $result['message_id'] ?? null,
                'sent_at' => now(),
            ]);

            Log::info('MassWhatsApp: gafete enviado', [
                'child_id' => $child->id,
                'child_code' => $child->code,
            ]);
        } catch (\Throwable $e) {
            Log::error('MassWhatsApp: error enviando gafete', [
                'child_id' => $child->id,
                'child_code' => $child->code,
                'error' => $e->getMessage(),
            ]);

            $this->recordFailure($e->getMessage());

            throw $e;
        }
    }

    private function recordFailure(string $message): void
    {
        $child = Child::withTrashed()->find($this->childId);

        FailedWhatsappChild::create([
            'child_id' => $this->childId,
            'batch_id' => $this->recordId,
            'error_message' => $message.' | '.$child?->full_name.' ('.$child?->code.')',
        ]);
    }
}
