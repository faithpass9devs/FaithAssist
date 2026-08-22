<?php

namespace App\Jobs;

use App\Models\Catechism\Child;
use App\Models\ChildWhatsappDelivery;
use App\Models\WhatsappMessage;
use App\Services\Catechism\ChildQrWhatsappService;
use App\Services\WhatsApp\PhoneNormalizer;
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

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $child = Child::find($this->childId);

        if (! $child) {
            $this->markDelivery(ChildWhatsappDelivery::STATUS_FAILED, 'Nino no encontrado en la base de datos');

            return;
        }

        if (! $child->phone || ! $child->phone_lada) {
            $this->markDelivery(ChildWhatsappDelivery::STATUS_FAILED, 'El nino no tiene telefono registrado');

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
                $this->markDelivery(ChildWhatsappDelivery::STATUS_FAILED, 'No se pudo generar el PDF del gafete');

                return;
            }

            $phone = PhoneNormalizer::normalize($child->phone_lada, $child->phone);

            if ($phone['error'] || ! $phone['number']) {
                $this->markDelivery(ChildWhatsappDelivery::STATUS_FAILED, $phone['error'] ?? 'Teléfono inválido.');

                return;
            }

            $firstName = trim($child->name ?? '') !== '' ? explode(' ', trim($child->name))[0] : 'NINO';
            $firstLastName = trim($child->paterno ?? '') !== '' ? explode(' ', trim($child->paterno))[0] : 'SIN_APELLIDO';
            $displayName = preg_replace('/[^\pL\pN\s\-]/u', '', trim($firstName.' '.$firstLastName)) ?: 'NINO SIN_APELLIDO';

            $message = WhatsappMessage::create([
                'to_phone' => $phone['number'],
                'country_code' => $phone['country'],
                'message_type' => 'document',
                'message_body' => $caption,
                'pdf_path' => $badgePdfPath,
                'filename' => 'Gafete de Asistencia '.$displayName.'.pdf',
                'status' => WhatsappMessage::STATUS_PENDING,
                'max_retries' => (int) config('baileys.retry.max_retries', 3),
                'legend_text' => '',
            ]);

            ChildWhatsappDelivery::updateOrCreate(
                [
                    'child_id' => $this->childId,
                    'whatsapp_mass_batch_id' => $this->recordId,
                ],
                [
                    'whatsapp_message_id' => $message->id,
                    'status' => ChildWhatsappDelivery::STATUS_QUEUED,
                    'error_message' => null,
                ],
            );

            ProcessWhatsappQueueBatchJob::dispatch()->onQueue('whatsapp');

            Log::info('MassWhatsApp: gafete encolado', [
                'child_id' => $child->id,
                'child_code' => $child->code,
                'message_id' => $message->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('MassWhatsApp: error encolando gafete', [
                'child_id' => $this->childId,
                'error' => $e->getMessage(),
            ]);

            $this->markDelivery(ChildWhatsappDelivery::STATUS_FAILED, $e->getMessage());

            throw $e;
        }
    }

    private function markDelivery(string $status, ?string $error = null): void
    {
        try {
            ChildWhatsappDelivery::updateOrCreate(
                [
                    'child_id' => $this->childId,
                    'whatsapp_mass_batch_id' => $this->recordId,
                ],
                [
                    'status' => $status,
                    'error_message' => $error,
                ],
            );
        } catch (\Throwable $e) {
            Log::error('MassWhatsApp: error guardando estado del envio', [
                'child_id' => $this->childId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
