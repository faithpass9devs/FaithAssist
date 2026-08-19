# Plan: Envio masivo de WhatsApp (Baileys) a ninos importados

## Contexto

En `Externos/Index.vue` existe el boton "Importacion masiva" que usa `Bus::batch()` para importar ninos externos de Hostinger y crear registros en la tabla local `children` con `origin = 'imported'`. El envio de WhatsApp (gafete PDF) estaba incluido pero **deshabilitado** en `ExternalChildImportService:131-138`.

La nueva funcionalidad es **independiente**: enviar el gafete QR por WhatsApp a ninos que **ya fueron importados** (`origin = 'imported'` en la tabla local `children`), usando Baileys.

## Decisiones

- **Contenido**: Gafete QR existente via `ChildQrWhatsappService`
- **Alcance**: Respetar filtros activos (busqueda, comunidad, nivel)
- **Permiso**: Reusar `externos.import_all`

## Archivos a crear (4)

| # | Archivo | Descripcion |
|---|---------|-------------|
| 1 | `database/migrations/2026_08_18_XXXXXX_create_whatsapp_mass_batches_table.php` | Tabla de tracking: `batch_id`, `user_id`, `total_jobs`, `filters` |
| 2 | `app/Models/WhatsappMassBatch.php` | Modelo Eloquent para la tabla anterior |
| 3 | `app/Jobs/SendMassWhatsAppToImportedChildJob.php` | Job que recibe `child_id`, llama a `ChildQrWhatsappService::sendChildQrBadge()` |
| 4 | `app/Services/Catechism/MassWhatsAppService.php` | Servicio: crea batch, consulta ninos importados con telefono, tracking |

## Archivos a modificar (5)

| # | Archivo | Cambio |
|---|---------|--------|
| 5 | `app/Http/Controllers/Catechism/ExternosController.php` | +2 metodos: `massWhatsApp()`, `massWhatsAppStatus()` |
| 6 | `routes/web.php` | +2 rutas: `POST /externos/mass-whatsapp`, `GET /externos/mass-whatsapp/{batch}` |
| 7 | `app/Services/Catechism/ExternosService.php` | Agregar `latestWhatsappBatch()` al `indexData()` |
| 8 | `resources/js/Pages/Catechism/Externos/Index.vue` | Nuevo boton, panel de progreso, polling, props |
| 9 | `app/Policies/ExternosPolicy.php` | Metodo `massWhatsApp()` que reutiliza `import_all` |

## Flujo

```
Usuario clickea "Envio masivo WhatsApp"
  -> POST /externos/mass-whatsapp
  -> MassWhatsAppService::createBatch()
    -> Child::where('origin','imported')->whereNotNull('phone')
    -> Aplica filtros de busqueda/comunidad/nivel
    -> Bus::batch([SendMassWhatsAppToImportedChildJob, ...])
    -> Registra en whatsapp_mass_batches
  -> Frontend inicia polling cada 3s
  -> GET /externos/mass-whatsapp/{batch}
  -> Cada job genera PDF + crea WhatsappMessage + dispatch ProcessWhatsappQueueBatchJob
  -> Cuando batch termina, usuario ve resultado
```

## Consideraciones tecnicas

- El `SendMassWhatsAppToImportedChildJob` maneja errores individualmente (nino sin telefono, PDF falla, etc.) sin detener el batch
- Se reutiliza la cola `whatsapp` existente para el envio real via Baileys
- Limpieza de PDFs temporales despues del envio (ya manejado por `ChildQrWhatsappService`)
