# Plan: Arreglar envío masivo de WhatsApp + trazabilidad por niño

## Diagnóstico

| Problema | Evidencia |
|---|---|
| El job masivo envía por HTTP directo en vez de la cola | `app/Jobs/SendMassWhatsAppToImportedChildJob.php:86` llama `$client->send()` síncrono, sin rate-limiting |
| Timeout de ack de 15s causa fallos falsos | `servers/baileys-server.js:327` rechaza si no llega confirmación → 26/26 jobs fallidos en los últimos 2 batches |
| No se puede saber a qué niño se le envió | `whatsapp_messages` no tiene `child_id`; `failed_whatsapp_children` solo registra fallos |
| Estado BD corrupto | 2199 mensajes `pending` huérfanos, batch de 2971 jobs con 2718 pendientes |
| `dd()` olvidado | `app/Http/Controllers/Catechism/ChildController.php:138` rompe el envío individual ante errores |
| Cola sin auto-reanudación | `ProcessWhatsappQueueBatchJob` no está en el scheduler; al agotar el límite diario (500) se estanca |

## Cambios

### 1. Migración + modelo `ChildWhatsappDelivery`
- Tabla `child_whatsapp_deliveries`: `child_id` FK, `whatsapp_mass_batch_id` FK nullable,
  `whatsapp_message_id` FK nullable, `status` (`queued`/`sent`/`failed`), `error_message`
  nullable, `sent_at` nullable, timestamps, índice `(batch_id, status)`,
  unique `(child_id, batch_id)`.
- Relaciones: `Child::lastDelivery()` (`latestOfMany`), `WhatsappMessage::delivery()`.

### 2. Refactor `SendMassWhatsAppToImportedChildJob` (solo encolar)
- Genera PDF → crea `WhatsappMessage` (`pending`, `max_retries` de config) → crea/actualiza
  `ChildWhatsappDelivery` (`queued`) → despacha `ProcessWhatsappQueueBatchJob`.
- Elimina el `$client->send()` directo y los writes a `failed_whatsapp_children`.

### 3. `SendWhatsappMessageJob` actualiza el delivery
- Al `sent`: delivery → `sent` + `sent_at`. Al fallo definitivo
  (`retry_count >= max_retries`): delivery → `failed` + `error_message`.

### 4. `MassWhatsAppService`
- `createBatch`: crea los delivery rows por adelantado (universo completo del batch).
- `serializeBatch`: progreso basado en deliveries (enviados/pendientes/fallidos + lista de
  fallidos con nombre/código/error), con fallback a los 120 min.
- `dismissBatch`: marca `queued` → `failed ("Envío cancelado")`.

### 5. Scheduler (`routes/console.php`)
- `Schedule::job(ProcessWhatsappQueueBatchJob)->everyFifteenMinutes()->onOneServer()`.

### 6. `servers/baileys-server.js`
- `waitForAck`: en timeout de 15s resolver `null` (mensaje aceptado, ack opcional) en vez
  de rechazar → elimina fallos falsos y duplicados.

### 7. Frontend `resources/js/Pages/Catechism/Children/Index.vue`
- Tarjeta del envío: contadores Enviados/Fallidos/Pendientes + lista colapsable de niños
  fallidos.
- Nueva columna "Gafete": badge verde (enviado + fecha) / rojo (fallido) / ámbar (en cola) /
  gris (nunca). Requiere serializar `last_delivery` en `ChildService`/`ChildRepository`.
- `launchMassWhatsApp`: manejar respuestas `!res.ok` (403/422/500) mostrando el mensaje real.
- Aviso en la confirmación: el envío es gradual (~500/día por límite diario).

### 8. Limpieza del `dd()` en `ChildController::sendQrWhatsapp`.

### 9. Limpieza de datos (pre-arranque)
- Marcar `failed` los `whatsapp_messages` huérfanos `pending`.
- Cancelar/eliminar batches atorados.

### 10. Verificación
1. `composer run dev` (levanta Horizon + Baileys) y `curl /status` → `{"connected":true}`.
2. Envío de prueba con filtro pequeño → confirmar `whatsapp_messages` +
   `child_whatsapp_deliveries` → UI muestra enviados/fallidos.
3. `php artisan test` + lint/format.
