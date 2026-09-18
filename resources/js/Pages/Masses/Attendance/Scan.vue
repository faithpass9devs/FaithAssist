<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Camera, Home, Play, QrCode, Square, SwitchCamera } from 'lucide-vue-next';
import { Html5Qrcode } from 'html5-qrcode';
import Swal from 'sweetalert2';
import AppPagination from '../../../components/AppPagination.vue';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';

const props = defineProps({
  mass: { type: Object, required: true },
  attendances: { type: Object, required: true },
  canScan: { type: Boolean, default: false },
  canManage: { type: Boolean, default: false },
});

const rows = ref([]);
watch(
  () => props.attendances.data,
  (data) => {
    rows.value = [...data];
  },
  { immediate: true },
);

const loading = ref(false);
const scanner = ref(null);
const scannerRunning = ref(false);
const scannerError = ref('');
const scannerLoading = ref(false);
const cameras = ref([]);
const selectedCameraId = ref('');
const cameraError = ref('');
const cameraLoading = ref(false);
const captureLoading = ref('');
const lastScan = ref({ code: '', at: 0 });
const qrRegionId = 'mass-attendance-qr-reader';
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const checkInStatus = computed(() => props.mass.attendance_check_in_status);
const checkOutStatus = computed(() => props.mass.attendance_check_out_status);

const captureStatus = (capture) => (capture === 'check_in' ? checkInStatus.value : checkOutStatus.value);

const statusPillClass = (status) =>
  status === 'in_progress'
    ? 'bg-amber-100 text-amber-800'
    : status === 'completed'
      ? 'bg-emerald-100 text-emerald-800'
      : 'bg-sky-100 text-sky-800';

const statusLabel = (status) =>
  status === 'in_progress' ? 'En curso' : status === 'completed' ? 'Completada' : 'No iniciada';

const noActiveCapture = computed(
  () => checkInStatus.value !== 'in_progress' && checkOutStatus.value !== 'in_progress',
);

const inferAction = (code) => {
  if (checkInStatus.value === 'in_progress' && checkOutStatus.value === 'in_progress') {
    const openCheckIn = rows.value.find(
      (row) => row.child_code === code && row.check_in_at && !row.check_out_at,
    );

    return openCheckIn ? 'check_out' : 'check_in';
  }

  if (checkInStatus.value === 'in_progress') return 'check_in';
  if (checkOutStatus.value === 'in_progress') return 'check_out';

  return null;
};

const scan = async (action, code) => {
  if (!props.canScan) return;

  loading.value = true;

  try {
    const response = await fetch(`/misas/${props.mass.id}/asistencias/scan`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        child_code: code,
        action,
      }),
    });
    const json = await response.json();

    if (!response.ok) {
      throw new Error(json.message ?? 'No se pudo registrar la asistencia.');
    }

    const index = rows.value.findIndex((row) => row.id === json.data.id);
    if (index === -1) {
      rows.value = [json.data, ...rows.value];
    } else {
      rows.value[index] = json.data;
    }

    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: json.message,
      timer: 2500,
      showConfirmButton: false,
    });
  } catch (error) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'error',
      title: error.message,
      timer: 3000,
      showConfirmButton: false,
    });
  } finally {
    loading.value = false;
  }
};

const onDecoded = async (decodedText) => {
  if (loading.value) return;
  const now = Date.now();
  if (lastScan.value.code === decodedText && now - lastScan.value.at < 2500) return;
  lastScan.value = { code: decodedText, at: now };

  const action = inferAction(decodedText);
  if (!action) return;

  await scan(action, decodedText);
};

const loadCameras = async () => {
  cameraError.value = '';
  cameraLoading.value = true;

  try {
    const list = await Html5Qrcode.getCameras();

    if (list.length === 0) {
      cameraError.value = 'No se detectaron cámaras en este dispositivo.';
      return;
    }

    cameras.value = list.map((device) => ({ id: device.id, label: device.label }));
    const preferred =
      list.find((device) => /back|rear|trasera|environment/i.test(device.label)) ??
      list.find((device) => /front|delantera|user/i.test(device.label)) ??
      list[0];
    selectedCameraId.value = preferred?.id ?? '';
  } catch (error) {
    cameraError.value = error?.message ?? 'No se pudieron detectar las cámaras.';
  } finally {
    cameraLoading.value = false;
  }
};

const startCamera = async () => {
  if (!props.canScan || noActiveCapture.value) return;

  scannerError.value = '';
  scannerLoading.value = true;

  try {
    if (!scanner.value) {
      scanner.value = new Html5Qrcode(qrRegionId);
    }

    if (cameras.value.length === 0 && !cameraError.value) {
      await loadCameras();
    }

    await scanner.value.start(
      selectedCameraId.value || { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 300, height: 300 } },
      onDecoded,
    );

    scannerRunning.value = true;
  } catch (error) {
    scannerError.value = error?.message ?? 'No se pudo iniciar la cámara.';
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'error',
      title: scannerError.value,
      timer: 3000,
      showConfirmButton: false,
    });
  } finally {
    scannerLoading.value = false;
  }
};

const switchCamera = async () => {
  if (cameraLoading.value) return;

  if (!scannerRunning.value) {
    await startCamera();
    return;
  }

  cameraLoading.value = true;

  try {
    await scanner.value?.stop();
    await scanner.value?.start(
      selectedCameraId.value || { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 300, height: 300 } },
      onDecoded,
    );
  } catch (error) {
    scannerError.value = error?.message ?? 'No se pudo cambiar de cámara.';
  } finally {
    cameraLoading.value = false;
  }
};

const stopCamera = async () => {
  if (!scanner.value || !scannerRunning.value) return;

  await scanner.value.stop();
  scannerRunning.value = false;
};

const toggleCapture = async (capture) => {
  const target = captureStatus(capture) === 'in_progress' ? 'completed' : 'in_progress';
  captureLoading.value = capture;

  try {
    const response = await fetch(`/misas/${props.mass.id}/asistencias/status`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ capture, status: target }),
    });
    const json = await response.json();

    if (!response.ok) {
      throw new Error(json.message ?? 'No se pudo actualizar la captura.');
    }

    await router.reload({ only: ['mass'] });

    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: json.message,
      timer: 2500,
      showConfirmButton: false,
    });
  } catch (error) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'error',
      title: error.message,
      timer: 3000,
      showConfirmButton: false,
    });
  } finally {
    captureLoading.value = '';
  }
};

const formatAttendanceTime = (value) => {
  if (!value) return '—';

  if (typeof value === 'string' && /\b(AM|PM)\b/i.test(value)) {
    return value;
  }

  const normalized = String(value).trim();
  const match = normalized.match(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}):(\d{2})(?::\d{2})?$/);

  if (match) {
    const [, date, hourStr, minute] = match;
    const hour = Number(hourStr);
    const period = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 === 0 ? 12 : hour % 12;

    return `${date} ${String(hour12).padStart(2, '0')}:${minute} ${period}`;
  }

  const date = new Date(normalized.replace(' ', 'T'));
  if (!Number.isNaN(date.getTime())) {
    return new Intl.DateTimeFormat('es-MX', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
    }).format(date);
  }

  return normalized;
};

onBeforeUnmount(() => {
  if (scanner.value && scannerRunning.value) {
    scanner.value.stop();
  }
});
</script>

<template>
  <AppShell :page-title="'Asistencia a misa'">
    <CatalogHeader
      title="Asistencia a misa"
      :subtitle="`${mass.name} · ${mass.location} · ${mass.starts_at} - ${mass.ends_at ?? 'sin fin'}`"
      back-href="/"
      :icon="QrCode"
    >
      <template #actions>
        <Link
          href="/"
          class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800"
        >
          <Home class="h-4 w-4" />
          Regresar al inicio
        </Link>
      </template>
    </CatalogHeader>

    <section
      v-if="canManage"
      class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="mb-4">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-200">
          Control de captura
        </h2>
        <p class="mt-1 text-xs text-slate-400">
          Inicia o termina la captura de entradas y salidas de esta misa.
        </p>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
        <div
          class="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700"
        >
          <div>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Captura de entradas</p>
            <span
              class="mt-1 inline-flex rounded-full px-3 py-1 text-xs font-semibold"
              :class="statusPillClass(checkInStatus)"
            >
              {{ statusLabel(checkInStatus) }}
            </span>
          </div>
          <button
            type="button"
            class="btn btn-sm gap-1.5 rounded-xl px-4"
            :class="checkInStatus === 'in_progress' ? 'btn-error' : 'btn-primary'"
            :disabled="captureLoading !== ''"
            @click="toggleCapture('check_in')"
          >
            <Square v-if="checkInStatus === 'in_progress'" class="h-4 w-4" />
            <Play v-else class="h-4 w-4" />
            {{ checkInStatus === 'in_progress' ? 'Terminar captura' : checkInStatus === 'completed' ? 'Reabrir captura' : 'Iniciar captura' }}
          </button>
        </div>

        <div
          class="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700"
        >
          <div>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Captura de salidas</p>
            <span
              class="mt-1 inline-flex rounded-full px-3 py-1 text-xs font-semibold"
              :class="statusPillClass(checkOutStatus)"
            >
              {{ statusLabel(checkOutStatus) }}
            </span>
          </div>
          <button
            type="button"
            class="btn btn-sm gap-1.5 rounded-xl px-4"
            :class="checkOutStatus === 'in_progress' ? 'btn-error' : 'btn-primary'"
            :disabled="captureLoading !== ''"
            @click="toggleCapture('check_out')"
          >
            <Square v-if="checkOutStatus === 'in_progress'" class="h-4 w-4" />
            <Play v-else class="h-4 w-4" />
            {{ checkOutStatus === 'in_progress' ? 'Terminar captura' : checkOutStatus === 'completed' ? 'Reabrir captura' : 'Iniciar captura' }}
          </button>
        </div>
      </div>
    </section>

    <section
      class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="mb-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px] lg:items-start">
        <div class="min-w-0 text-center lg:text-left">
          <h2
            class="text-sm font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-200"
          >
            Scanner QR con cámara
          </h2>
          <p class="mx-auto mt-1 max-w-xl text-xs leading-5 text-slate-500 dark:text-slate-400 lg:mx-0">
            La lectura registrará entrada o salida automáticamente según la captura que esté en curso.
          </p>
          <div class="mt-3 flex flex-wrap items-center justify-center gap-2 lg:justify-start">
            <span
              class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
              :class="statusPillClass(checkInStatus)"
            >
              Entradas: {{ statusLabel(checkInStatus) }}
            </span>
            <span
              class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
              :class="statusPillClass(checkOutStatus)"
            >
              Salidas: {{ statusLabel(checkOutStatus) }}
            </span>
          </div>
        </div>
        <div class="w-full space-y-3 lg:max-w-55">
          <div v-if="cameras.length > 0 || cameraError">
            <label
              class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400"
            >
              Cámara
            </label>
            <div class="flex items-center gap-2">
              <select
                v-model="selectedCameraId"
                class="select h-11 w-full rounded-xl border text-sm shadow-sm transition-colors"
                :disabled="cameraLoading || !scannerRunning"
                @change="switchCamera"
              >
                <option v-for="cam in cameras" :key="cam.id" :value="cam.id">
                  {{ cam.label || 'Cámara disponible' }}
                </option>
              </select>
              <button
                v-if="cameras.length > 1"
                type="button"
                class="btn btn-outline btn-sm gap-1.5 rounded-xl"
                :disabled="cameraLoading"
                title="Cambiar de cámara"
                @click="switchCamera"
              >
                <SwitchCamera class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!canScan" class="alert alert-warning mb-4 text-sm">
        No tienes permiso para capturar códigos QR en esta misa.
      </div>

      <div v-else-if="noActiveCapture" class="alert alert-info mb-4 text-sm">
        No hay una captura activa. El coordinador debe iniciar la captura de entradas o de salidas.
      </div>

      <div class="border-t border-slate-200 pt-5 dark:border-slate-800">
        <div
          id="mass-attendance-qr-reader"
          class="mx-auto max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950"
        ></div>

        <p class="mt-3 text-center text-xs leading-5 text-slate-500 dark:text-slate-400">
          Coloca el código QR dentro del recuadro y mantén la cámara estable para una lectura más rápida.
        </p>
      </div>

      <div
        v-if="scannerError"
        class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
      >
        {{ scannerError }}
      </div>

      <div class="mt-5 flex flex-col items-center justify-center gap-3 sm:flex-row">
        <button
          v-if="!scannerRunning"
          type="button"
          class="btn btn-primary gap-1.5 rounded-xl px-5 sm:btn-sm"
          :disabled="loading || scannerLoading || !canScan || noActiveCapture"
          @click="startCamera"
        >
          <Camera class="h-4 w-4" />
          {{ scannerLoading ? 'Iniciando...' : 'Iniciar cámara' }}
        </button>
        <button
          v-else
          type="button"
          class="btn btn-outline gap-1.5 rounded-xl px-5 sm:btn-sm"
          @click="stopCamera"
        >
          <Square class="h-4 w-4" />
          Detener cámara
        </button>
      </div>
    </section>

    <div
      class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <table class="table w-full">
        <thead>
          <tr
            class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950"
          >
            <th>Código</th>
            <th>Niño</th>
            <th>Ubicación</th>
            <th>Entrada</th>
            <th>Salida</th>
            <th>Estado</th>
            <th>Justificación</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="attendance in rows" :key="attendance.id">
            <td class="font-mono text-xs">{{ attendance.child_code }}</td>
            <td>{{ attendance.child_name }}</td>
            <td>{{ attendance.location }}</td>
            <td>{{ formatAttendanceTime(attendance.check_in_at) }}</td>
            <td>{{ formatAttendanceTime(attendance.check_out_at) }}</td>
            <td>
              <span
                class="badge badge-sm"
                :class="attendance.valid ? 'badge-success' : 'badge-warning'"
              >
                {{ attendance.valid ? 'Válida' : 'Pendiente' }}
              </span>
            </td>
            <td>
              <span
                v-if="attendance.justified"
                class="badge badge-sm badge-info"
                :title="attendance.incidence_description"
              >
                {{ attendance.incidence_type || 'Justificada' }}
              </span>
              <span v-else class="text-xs text-slate-400">—</span>
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td colspan="7" class="py-10 text-center text-sm text-slate-400">
              No hay asistencias registradas.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      :links="attendances.links"
      :from="attendances.from"
      :to="attendances.to"
      :total="attendances.total"
    />

  </AppShell>
</template>