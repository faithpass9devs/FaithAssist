<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Camera, Home, LogIn, LogOut, QrCode, Square } from 'lucide-vue-next';
import { Html5Qrcode } from 'html5-qrcode';
import Swal from 'sweetalert2';
import AppPagination from '../../../components/AppPagination.vue';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';

const props = defineProps({
  mass: { type: Object, required: true },
  attendances: { type: Object, required: true },
  weekendOptions: { type: Array, default: () => [] },
  canScan: { type: Boolean, default: false },
});

const selectedWeekendId = ref(props.mass.weekend_id);
const selectedMassId = ref(props.mass.id);

const availableMasses = computed(
  () =>
    props.weekendOptions.find((weekend) => String(weekend.id) === String(selectedWeekendId.value))
      ?.masses ?? [],
);

const selectedMassLabel = computed(
  () => availableMasses.value.find((massOption) => String(massOption.id) === String(selectedMassId.value))?.label ?? '',
);

const selectedMassShortLabel = computed(
  () =>
    availableMasses.value.find((massOption) => String(massOption.id) === String(selectedMassId.value))
      ?.short_label ?? '',
);

const rows = ref([]);
watch(
  () => props.attendances.data,
  (data) => {
    rows.value = [...data];
  },
  { immediate: true },
);
watch(
  () => props.mass,
  (mass) => {
    selectedWeekendId.value = mass.weekend_id;
    selectedMassId.value = mass.id;
  },
  { immediate: true },
);
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

const openAttendance = (massId) => {
  if (!massId || String(massId) === String(props.mass.id)) return;

  router.get(`/misas/${massId}/asistencias`, {}, { preserveScroll: true });
};

const onWeekendChange = (event) => {
  const weekendId = event.target.value;
  selectedWeekendId.value = weekendId;

  const weekend = props.weekendOptions.find((item) => String(item.id) === String(weekendId));
  const firstMassId = weekend?.masses?.[0]?.id;

  if (!firstMassId) return;

  selectedMassId.value = firstMassId;
  openAttendance(firstMassId);
};

const onMassChange = (event) => {
  const massId = event.target.value;
  selectedMassId.value = massId;
  openAttendance(massId);
};

const scan = async (action, code = childCode.value) => {
  if (!props.canScan) return;

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
  if (!props.canScan) return;

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
      class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="mb-4">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-200">
          Seleccion de misa
        </h2>
        <p class="mt-1 text-xs text-slate-400">
          Elige el fin de semana y la misa que usaras para registrar asistencias.
        </p>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
        <label class="block">
          <span class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">
            Fin de semana
          </span>
          <select
            :value="selectedWeekendId"
            class="select select-bordered w-full"
            @change="onWeekendChange"
          >
            <option v-for="weekend in weekendOptions" :key="weekend.id" :value="weekend.id">
              {{ weekend.label }}
            </option>
          </select>
        </label>

        <label class="block">
          <span class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">
            Misa
          </span>
          <select
            :value="selectedMassId"
            class="select select-bordered w-full text-xs sm:text-sm"
            @change="onMassChange"
          >
            <option v-for="massOption in availableMasses" :key="massOption.id" :value="massOption.id">
              {{ massOption.short_label || massOption.label }}
            </option>
          </select>
          <p v-if="selectedMassShortLabel" class="mt-2 text-xs font-medium text-slate-700 md:hidden">
            {{ selectedMassShortLabel }}
          </p>
          <p v-if="selectedMassLabel" class="mt-2 text-xs leading-5 text-slate-500 md:hidden wrap-break-word">
            {{ selectedMassLabel }}
          </p>
        </label>
      </div>

      <p class="mt-4 text-xs text-slate-400">
        Al entrar desde el módulo de asistencias se carga automáticamente la misa del fin de semana más próximo disponible.
      </p>
    </section>

    <section
      class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <h2 class="mb-4 text-sm font-semibold text-red-600 dark:text-red-400">
        ¿No puedes escanear el QR? Registra manualmente.
      </h2>

      <div v-if="!canScan" class="alert alert-warning mb-4 text-sm">
        No tienes permiso para capturar códigos QR en esta misa.
      </div>

      <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200">
        Código único del niño
      </label>
      <div class="flex flex-col gap-3 sm:flex-row">
        <input
          v-model="childCode"
          class="input input-bordered w-full font-mono"
          :class="{ 'input-error': errors.child_code }"
          placeholder="Escribe el código del QR"
          autocomplete="off"
          autofocus
          :disabled="!canScan"
          @keyup.enter="scan('check_in')"
        />
        <button
          class="btn btn-primary gap-1.5"
          :disabled="loading || !canScan"
          @click="scan('check_in')"
        >
          <LogIn class="h-4 w-4" />
          Entrada
        </button>
        <button
          class="btn btn-outline gap-1.5"
          :disabled="loading || !canScan"
          @click="scan('check_out')"
        >
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
      <div class="mb-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px] lg:items-start">
        <div class="min-w-0 text-center lg:text-left">
          <h2
            class="text-sm font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-200"
          >
            Scanner QR con cámara
          </h2>
          <p class="mx-auto mt-1 max-w-xl text-xs leading-5 text-slate-500 dark:text-slate-400 lg:mx-0">
            Selecciona si la lectura registrará entrada o salida antes de escanear.
          </p>
          <span
            class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold"
            :class="
              mass.attendance_status === 'in_progress'
                ? 'bg-amber-100 text-amber-800'
                : mass.attendance_status === 'completed'
                  ? 'bg-emerald-100 text-emerald-800'
                  : 'bg-sky-100 text-sky-800'
            "
          >
            Captura: {{ mass.attendance_status }}
          </span>
        </div>
        <div class="w-full lg:max-w-55">
          <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
            Tipo de lectura
          </label>
          <select
            v-model="selectedAction"
            class="select h-11 w-full rounded-xl border text-sm font-medium shadow-sm transition-colors"
            :class="
              selectedAction === 'check_in'
                ? 'border-orange-300 bg-orange-100 text-orange-900 dark:border-orange-700 dark:bg-orange-900/40 dark:text-orange-100'
                : 'border-emerald-300 bg-emerald-100 text-emerald-900 dark:border-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-100'
            "
          >
            <option class="bg-white text-slate-900" value="check_in">Entrada</option>
            <option class="bg-white text-slate-900" value="check_out">Salida</option>
          </select>
        </div>
      </div>

      <div class="border-t border-slate-200 pt-5 dark:border-slate-800">
        <div
          id="mass-attendance-qr-reader"
          class="mx-auto max-w-md overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950"
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
          :disabled="loading || !canScan"
          @click="startCamera"
        >
          <Camera class="h-4 w-4" />
          Iniciar cámara
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
