<script setup>
import { computed, ref, watch } from 'vue';
import {
  CalendarDays,
  CalendarRange,
  Church,
  Download,
  FileSpreadsheet,
  Info,
  Layers,
  MapPin,
  Users,
  X,
} from 'lucide-vue-next';
import { alertError } from '../../composables/useFlashAlerts';

const props = defineProps({
  open: { type: Boolean, default: false },
  /** Parroquias disponibles para el usuario: [{ id, name }]. */
  churches: { type: Array, default: () => [] },
  /** Parroquia fija cuando el usuario solo administra una. */
  lockedChurchId: { type: Number, default: null },
  /** Parroquia preseleccionada (filtro actual del índice). */
  initialChurchId: { type: Number, default: null },
});

const emit = defineEmits(['close']);

const churchId = ref(null);
const communityId = ref(null);
const levelId = ref(null);
const childStatus = ref('active');
const periodMode = ref('month');
const month = ref('');
const dateFrom = ref('');
const dateTo = ref('');

const options = ref(null);
const loading = ref(false);
const downloading = ref(false);
const error = ref('');

/** El selector de parroquia solo se muestra cuando el usuario puede elegir entre varias. */
const showChurchSelect = computed(() => props.lockedChurchId === null && props.churches.length > 1);

const childStatusOptions = [
  { value: 'active', label: 'Solo activos' },
  { value: 'all', label: 'Todos' },
];

const periodModes = [
  { value: 'month', label: 'Por mes', icon: CalendarDays },
  { value: 'range', label: 'Rango de fechas', icon: CalendarRange },
];

const messageFor = (response, json, fallback) => {
  const firstError = Object.values(json?.errors ?? {})[0]?.[0];
  if (firstError) return firstError;

  switch (response.status) {
    case 401:
      return 'Tu sesión expiró. Vuelve a iniciar sesión e inténtalo de nuevo.';
    case 403:
      return 'No tienes permiso para generar el reporte de asistencias.';
    case 404:
      return 'No se encontró la información solicitada.';
    case 419:
      return 'La sesión caducó. Recarga la página e inténtalo de nuevo.';
    case 429:
      return 'Demasiadas solicitudes seguidas. Espera un momento e inténtalo de nuevo.';
    case 500:
    case 502:
    case 503:
      return 'Ocurrió un error en el servidor al generar el reporte. Inténtalo más tarde.';
    default:
      return fallback;
  }
};

const resetFilters = () => {
  communityId.value = null;
  levelId.value = null;
  month.value = '';
  dateFrom.value = '';
  dateTo.value = '';
};

const loadOptions = async () => {
  options.value = null;
  error.value = '';
  resetFilters();

  if (!churchId.value) return;

  loading.value = true;
  const requested = churchId.value;

  try {
    const response = await fetch(
      `/fines-semana-misas/reporte-asistencias/opciones?church_id=${requested}`,
      { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
    );
    const json = await response.json().catch(() => ({}));

    if (churchId.value !== requested) return;
    if (!response.ok) {
      throw new Error(
        messageFor(response, json, 'No se pudieron cargar las opciones del reporte.'),
      );
    }

    options.value = json;
    month.value = json.months[0]?.value ?? '';
    dateFrom.value = json.range?.min ?? '';
    dateTo.value = json.range?.max ?? '';
  } catch (err) {
    if (churchId.value !== requested) return;
    error.value =
      err instanceof TypeError
        ? 'No hay conexión con el servidor. Revisa tu internet e inténtalo de nuevo.'
        : err.message;
  } finally {
    if (churchId.value === requested) loading.value = false;
  }
};

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return;
    churchId.value =
      props.lockedChurchId ??
      props.initialChurchId ??
      (props.churches.length === 1 ? props.churches[0].id : null);
    periodMode.value = 'month';
    childStatus.value = 'active';
    loadOptions();
  },
);

const hasData = computed(() => (options.value?.weekends?.length ?? 0) > 0);

const selectedWeekends = computed(() => {
  const weekends = options.value?.weekends ?? [];

  if (periodMode.value === 'month') {
    return month.value ? weekends.filter((weekend) => weekend.month === month.value) : [];
  }

  return weekends.filter(
    (weekend) =>
      (!dateFrom.value || weekend.starts_at >= dateFrom.value) &&
      (!dateTo.value || weekend.starts_at <= dateTo.value),
  );
});

const rangeInvalid = computed(
  () =>
    periodMode.value === 'range' && dateFrom.value && dateTo.value && dateFrom.value > dateTo.value,
);

const canDownload = computed(
  () =>
    !!churchId.value &&
    hasData.value &&
    !loading.value &&
    !downloading.value &&
    !rangeInvalid.value &&
    selectedWeekends.value.length > 0,
);

const periodSummary = computed(() => {
  const count = selectedWeekends.value.length;

  if (!count) return 'No hay fines de semana con asistencia en el periodo elegido.';

  const first = selectedWeekends.value[0];
  const last = selectedWeekends.value[count - 1];
  const range = count === 1 ? first.label : `${first.label} → ${last.label}`;

  return `${count} fin${count === 1 ? '' : 'es'} de semana evaluado${count === 1 ? '' : 's'} (${range}).`;
});

const close = () => {
  if (downloading.value) return;
  emit('close');
};

const buildUrl = () => {
  const url = new URL('/fines-semana-misas/reporte-asistencias', window.location.origin);
  url.searchParams.set('church_id', churchId.value);
  if (communityId.value) url.searchParams.set('community_id', communityId.value);
  if (levelId.value) url.searchParams.set('level_id', levelId.value);
  url.searchParams.set('child_status', childStatus.value);

  if (periodMode.value === 'month') {
    url.searchParams.set('month', month.value);
  } else {
    if (dateFrom.value) url.searchParams.set('date_from', dateFrom.value);
    if (dateTo.value) url.searchParams.set('date_to', dateTo.value);
  }

  return url;
};

const download = async () => {
  if (!canDownload.value) return;

  downloading.value = true;
  error.value = '';

  try {
    const response = await fetch(buildUrl(), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!response.ok) {
      const json = await response.json().catch(() => ({}));
      throw new Error(messageFor(response, json, 'No se pudo generar el reporte.'));
    }

    const disposition = response.headers.get('Content-Disposition') ?? '';
    const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
    const fileName = match ? decodeURIComponent(match[1]) : 'R-Asistencias.xlsx';

    const blob = await response.blob();
    const href = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = href;
    anchor.download = fileName;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(href);

    emit('close');
  } catch (err) {
    error.value =
      err instanceof TypeError
        ? 'No hay conexión con el servidor. Revisa tu internet e inténtalo de nuevo.'
        : err.message;
    alertError('No se pudo descargar el reporte', error.value);
  } finally {
    downloading.value = false;
  }
};
</script>

<template>
  <dialog
    v-if="open"
    class="modal modal-open px-3 sm:px-0"
    aria-labelledby="attendance-report-title"
    @click.self="close"
  >
    <div class="modal-box max-h-[92vh] w-full max-w-2xl overflow-y-auto px-4 sm:px-6">
      <div class="mb-5 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <span
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300"
          >
            <FileSpreadsheet class="h-5 w-5" />
          </span>
          <h2 id="attendance-report-title" class="text-lg font-bold text-slate-900 dark:text-white">
            Reporte de asistencias
          </h2>
        </div>
        <button
          type="button"
          class="btn btn-ghost btn-sm"
          aria-label="Cerrar"
          :disabled="downloading"
          @click="close"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <div class="space-y-5">
        <!-- Parroquia (solo cuando el usuario puede elegir entre varias) -->
        <label v-if="showChurchSelect" class="block">
          <span
            class="flex items-center gap-1.5 text-sm font-bold text-slate-600 dark:text-slate-300"
          >
            <Church class="h-4 w-4 text-slate-400" />
            Parroquia
          </span>
          <select
            v-model="churchId"
            class="select select-sm select-bordered mt-1 w-full"
            :disabled="loading || downloading"
            @change="loadOptions"
          >
            <option :value="null" disabled>Selecciona una parroquia</option>
            <option v-for="church in churches" :key="church.id" :value="church.id">
              {{ church.name }}
            </option>
          </select>
        </label>

        <!-- Comunidad y nivel -->
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block">
            <span
              class="flex items-center gap-1.5 text-sm font-bold text-slate-600 dark:text-slate-300"
            >
              <MapPin class="h-4 w-4 text-slate-400" />
              Comunidad
            </span>
            <select
              v-model="communityId"
              class="select select-sm select-bordered mt-1 w-full"
              :disabled="!options || loading || downloading"
            >
              <option :value="null">Todas las comunidades</option>
              <option
                v-for="community in options?.communities ?? []"
                :key="community.id"
                :value="community.id"
              >
                {{ community.name }}
              </option>
            </select>
          </label>

          <label class="block">
            <span
              class="flex items-center gap-1.5 text-sm font-bold text-slate-600 dark:text-slate-300"
            >
              <Layers class="h-4 w-4 text-slate-400" />
              Nivel
            </span>
            <select
              v-model="levelId"
              class="select select-sm select-bordered mt-1 w-full"
              :disabled="!options || loading || downloading"
            >
              <option :value="null">Todos los niveles</option>
              <option v-for="level in options?.levels ?? []" :key="level.id" :value="level.id">
                {{ level.name }}
              </option>
            </select>
          </label>
        </div>

        <!-- Niños y periodo: dos grupos de botones centrados -->
        <div class="grid gap-4 sm:grid-cols-2">
          <fieldset class="flex flex-col items-center gap-2">
            <legend
              class="flex items-center justify-center gap-1.5 text-sm font-bold text-slate-600 dark:text-slate-300"
            >
              <Users class="h-4 w-4 text-slate-400" />
              Niños
            </legend>
            <div
              class="inline-flex w-full rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900"
              role="radiogroup"
              aria-label="Niños incluidos"
            >
              <button
                v-for="item in childStatusOptions"
                :key="item.value"
                type="button"
                role="radio"
                :aria-checked="childStatus === item.value"
                class="flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                :class="
                  childStatus === item.value
                    ? 'bg-sky-700 text-white shadow-sm'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                "
                :disabled="downloading"
                @click="childStatus = item.value"
              >
                {{ item.label }}
              </button>
            </div>
          </fieldset>

          <fieldset class="flex flex-col items-center gap-2">
            <legend
              class="flex items-center justify-center gap-1.5 text-sm font-bold text-slate-600 dark:text-slate-300"
            >
              <CalendarDays class="h-4 w-4 text-slate-400" />
              Periodo
            </legend>
            <div
              class="inline-flex w-full rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900"
              role="tablist"
              aria-label="Tipo de periodo"
            >
              <button
                v-for="mode in periodModes"
                :key="mode.value"
                type="button"
                role="tab"
                :aria-selected="periodMode === mode.value"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                :class="
                  periodMode === mode.value
                    ? 'bg-sky-700 text-white shadow-sm'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                "
                :disabled="downloading"
                @click="periodMode = mode.value"
              >
                <component :is="mode.icon" class="h-3.5 w-3.5" />
                {{ mode.label }}
              </button>
            </div>
          </fieldset>
        </div>

        <!-- Selección del periodo -->
        <div>
          <div v-if="loading" class="py-6 text-center text-sm text-slate-500">
            <span class="loading loading-spinner loading-sm mr-2"></span>
            Buscando fines de semana con asistencia...
          </div>

          <div
            v-else-if="error"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
            role="alert"
          >
            {{ error }}
          </div>

          <p
            v-else-if="!churchId"
            class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
          >
            Selecciona una parroquia para ver los periodos disponibles.
          </p>

          <p
            v-else-if="options && !hasData"
            class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
          >
            Esta parroquia aún no tiene fines de semana concluidos con asistencia registrada.
          </p>

          <template v-else-if="options">
            <label v-if="periodMode === 'month'" class="block">
              <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mes</span>
              <select
                v-model="month"
                class="select select-sm select-bordered mt-1 w-full"
                :disabled="downloading"
              >
                <option v-for="item in options.months" :key="item.value" :value="item.value">
                  {{ item.label }} · {{ item.weekends }} fin{{ item.weekends === 1 ? '' : 'es' }}
                  de semana
                </option>
              </select>
            </label>

            <div v-else class="grid gap-3 sm:grid-cols-2">
              <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Desde
                </span>
                <input
                  v-model="dateFrom"
                  type="date"
                  class="input input-sm input-bordered mt-1 w-full"
                  :min="options.range?.min"
                  :max="options.range?.max"
                  :disabled="downloading"
                />
              </label>
              <label class="block">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Hasta
                </span>
                <input
                  v-model="dateTo"
                  type="date"
                  class="input input-sm input-bordered mt-1 w-full"
                  :min="dateFrom || options.range?.min"
                  :max="options.range?.max"
                  :disabled="downloading"
                />
              </label>
              <p
                v-if="rangeInvalid"
                class="text-xs font-medium text-red-600 sm:col-span-2"
                role="alert"
              >
                La fecha final debe ser igual o posterior a la inicial.
              </p>
            </div>

            <div
              class="mt-3 flex items-start gap-2 rounded-xl border px-3 py-2.5 text-sm"
              :class="
                selectedWeekends.length
                  ? 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-900/20 dark:text-sky-100'
                  : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100'
              "
            >
              <Info class="mt-0.5 h-4 w-4 shrink-0" />
              <div>
                <p class="font-medium">{{ periodSummary }}</p>
                <p class="mt-0.5 text-xs opacity-80">
                  Límite de faltas de la parroquia:
                  <strong>{{ options.absence_limit }}</strong>
                  Se evalúan únicamente fines de semana concluidos con asistencia capturada.
                </p>
              </div>
            </div>
          </template>
        </div>
      </div>

      <div class="modal-action justify-center gap-3">
        <button
          type="button"
          class="btn btn-sm min-w-[8rem] rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
          :disabled="downloading"
          @click="close"
        >
          Cancelar
        </button>
        <button
          type="button"
          class="btn btn-sm min-w-[10rem] gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="!canDownload"
          @click="download"
        >
          <span v-if="downloading" class="loading loading-spinner loading-xs"></span>
          <Download v-else class="h-4 w-4" />
          {{ downloading ? 'Generando...' : 'Descargar Excel' }}
        </button>
      </div>
    </div>
  </dialog>
</template>
