<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Camera, LogIn, LogOut, QrCode, Square } from 'lucide-vue-next';
import { Html5Qrcode } from 'html5-qrcode';
import Swal from 'sweetalert2';
import AppPagination from '../../../components/AppPagination.vue';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';

const props = defineProps({
  mass: { type: Object, required: true },
  attendances: { type: Object, required: true },
});

const rows = ref([...props.attendances.data]);
const childCode = ref('');
const loading = ref(false);
const errors = ref({});
const selectedAction = ref('check_in');
const scanner = ref(null);
const scannerRunning = ref(false);
const scannerError = ref('');
const lastScan = ref({ code: '', at: 0 });
const qrRegionId = 'mass-attendance-qr-reader';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const scan = async (action, code = childCode.value) => {
  errors.value = {};
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
      errors.value = json.errors ?? {};
      throw new Error(json.message ?? 'No se pudo registrar la asistencia.');
    }

    const index = rows.value.findIndex((row) => row.id === json.data.id);
    if (index === -1) {
      rows.value = [json.data, ...rows.value];
    } else {
      rows.value[index] = json.data;
    }

    childCode.value = '';
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

const startCamera = async () => {
  scannerError.value = '';

  try {
    if (!scanner.value) {
      scanner.value = new Html5Qrcode(qrRegionId);
    }

    await scanner.value.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 260, height: 260 } },
      async (decodedText) => {
        if (loading.value) return;
        const now = Date.now();
        if (lastScan.value.code === decodedText && now - lastScan.value.at < 2500) return;
        lastScan.value = { code: decodedText, at: now };
        await scan(selectedAction.value, decodedText);
      },
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
  }
};

const stopCamera = async () => {
  if (!scanner.value || !scannerRunning.value) return;

  await scanner.value.stop();
  scannerRunning.value = false;
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
      back-href="/misas"
      :icon="QrCode"
    />

    <section
      class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        <span class="badge badge-outline">{{ mass.weekend }}</span>
        <span
          class="badge"
          :class="mass.attendance_status === 'in_progress' ? 'badge-warning' : 'badge-ghost'"
        >
          Captura: {{ mass.attendance_status }}
        </span>
      </div>

      <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">
        Código único del niño
      </label>
      <div class="flex flex-col gap-3 sm:flex-row">
        <input
          v-model="childCode"
          class="input input-bordered w-full font-mono"
          :class="{ 'input-error': errors.child_code }"
          placeholder="Escanea o escribe el código del QR"
          autocomplete="off"
          autofocus
          @keyup.enter="scan('check_in')"
        />
        <button class="btn btn-primary gap-1.5" :disabled="loading" @click="scan('check_in')">
          <LogIn class="h-4 w-4" />
          Entrada
        </button>
        <button class="btn btn-outline gap-1.5" :disabled="loading" @click="scan('check_out')">
          <LogOut class="h-4 w-4" />
          Salida
        </button>
      </div>
      <p v-if="errors.child_code" class="mt-2 text-xs text-red-500">{{ errors.child_code[0] }}</p>
      <p class="mt-2 text-xs text-slate-400">
        Una asistencia cuenta como válida cuando el niño tiene entrada y salida en esta misa.
      </p>
    </section>

    <section
      class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2
            class="text-sm font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-200"
          >
            Scanner QR con cámara
          </h2>
          <p class="mt-1 text-xs text-slate-400">
            Selecciona si la lectura registrará entrada o salida antes de escanear.
          </p>
        </div>
        <select v-model="selectedAction" class="select select-bordered select-sm">
          <option value="check_in">Entrada</option>
          <option value="check_out">Salida</option>
        </select>
      </div>

      <div
        id="mass-attendance-qr-reader"
        class="mx-auto max-w-md overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800"
      ></div>

      <p v-if="scannerError" class="mt-3 text-sm text-red-500">{{ scannerError }}</p>

      <div class="mt-4 flex justify-center gap-3">
        <button
          v-if="!scannerRunning"
          type="button"
          class="btn btn-primary btn-sm gap-1.5"
          :disabled="loading"
          @click="startCamera"
        >
          <Camera class="h-4 w-4" />
          Iniciar cámara
        </button>
        <button v-else type="button" class="btn btn-outline btn-sm gap-1.5" @click="stopCamera">
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
          </tr>
        </thead>
        <tbody>
          <tr v-for="attendance in rows" :key="attendance.id">
            <td class="font-mono text-xs">{{ attendance.child_code }}</td>
            <td>{{ attendance.child_name }}</td>
            <td>{{ attendance.location }}</td>
            <td>{{ attendance.check_in_at ?? '—' }}</td>
            <td>{{ attendance.check_out_at ?? '—' }}</td>
            <td>
              <span
                class="badge badge-sm"
                :class="attendance.valid ? 'badge-success' : 'badge-warning'"
              >
                {{ attendance.valid ? 'Válida' : 'Pendiente' }}
              </span>
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td colspan="6" class="py-10 text-center text-sm text-slate-400">
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

    <div class="mt-6">
      <Link href="/misas" class="btn btn-ghost btn-sm">Volver a misas</Link>
    </div>
  </AppShell>
</template>
