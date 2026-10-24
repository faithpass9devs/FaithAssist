<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
  CalendarClock,
  CalendarDays,
  Church,
  Clock3,
  History,
  Pencil,
  Plus,
  Sparkles,
  Trash2,
  X,
} from 'lucide-vue-next';
import AppPagination from '../../../components/AppPagination.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';
import { alertError, firstError, useFlashAlerts } from '../../../composables/useFlashAlerts';

useFlashAlerts();

const props = defineProps({
  weekends: { type: Object, required: true },
  tab: { type: String, default: null },
  allowedTabs: { type: Array, default: () => [] },
  churches: { type: Array, default: () => [] },
  lockedChurchId: { type: Number, default: null },
  scheduledChurchIds: { type: Array, default: () => [] },
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('weekends.create'));
const canUpdate = computed(() => permissions.value.includes('weekends.update'));
const canDelete = computed(() => permissions.value.includes('weekends.delete'));
const canGenerate = computed(() => permissions.value.includes('masses.create'));
const canViewMasses = computed(() => permissions.value.includes('masses.read'));
const allTabs = [
  { value: 'current', label: 'Actual', icon: CalendarDays },
  { value: 'upcoming', label: 'Próximos', icon: Clock3 },
  { value: 'history', label: 'Histórico', icon: History },
];
const tabs = computed(() => allTabs.filter((item) => props.allowedTabs.includes(item.value)));

const visitIndex = (tab = props.tab) => {
  router.get(
    '/fines-semana-misas',
    { tab: tab === 'current' ? undefined : tab },
    { preserveState: true, preserveScroll: true, replace: true },
  );
};

const selectTab = (tab) => visitIndex(tab);

const destroyWeekend = (weekend) => {
  if (!confirm(`Eliminar el fin de semana ${weekend.name || weekend.starts_at}?`)) return;
  router.delete(`/fines-semana-misas/${weekend.id}`, { preserveScroll: true });
};

const emptyMessages = {
  current: 'No hay un fin de semana en curso en las parroquias disponibles.',
  history: 'Aún no hay fines de semana concluidos.',
  upcoming: 'No hay fines de semana próximos.',
};
const emptyMessage = computed(
  () =>
    emptyMessages[props.tab] ??
    'No tienes permisos para consultar las pestañas de fines de semana de misas.',
);

const generatorOpen = ref(false);
const generatorChurchId = ref(null);
const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref('');
const generating = ref(false);

const dayFormatter = new Intl.DateTimeFormat('es-MX', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
});
const parseDate = (value) => {
  const [date, time = '00:00'] = value.split(' ');
  const [year, month, day] = date.split('-').map(Number);
  const [hours, minutes] = time.split(':').map(Number);
  return new Date(year, month - 1, day, hours, minutes);
};
const formatDay = (value) => dayFormatter.format(parseDate(value));
const formatTime = (value) => value.split(' ')[1]?.slice(0, 5) ?? '';
const massesFor = (weekend, day) => weekend.masses.filter((mass) => mass.day_of_week === day);

const loadPreview = async () => {
  preview.value = null;
  previewError.value = '';

  if (!generatorChurchId.value) return;

  previewLoading.value = true;

  const churchId = generatorChurchId.value;
  try {
    const response = await fetch(`/fines-semana-misas/generar?church_id=${churchId}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const json = await response.json();

    if (generatorChurchId.value !== churchId) return;

    if (!response.ok) {
      throw new Error(
        json.errors?.church_id?.[0] ?? json.message ?? 'No se pudo cargar el desglose.',
      );
    }

    preview.value = json;
  } catch (error) {
    if (generatorChurchId.value !== churchId) return;
    previewError.value = error.message;
    alertError('No se pudo cargar el desglose', error.message);
  } finally {
    if (generatorChurchId.value === churchId) previewLoading.value = false;
  }
};

const openNew = () => {
  if (!canGenerate.value || !props.scheduledChurchIds.length) {
    router.visit('/fines-semana-misas/create');
    return;
  }

  generatorChurchId.value = props.lockedChurchId ?? props.scheduledChurchIds[0];
  generatorOpen.value = true;
  loadPreview();
};

const closeGenerator = () => {
  if (generating.value) return;
  generatorOpen.value = false;
};

const generate = () => {
  if (!generatorChurchId.value || !preview.value?.weekends.length) return;

  router.post(
    '/fines-semana-misas/generar',
    { church_id: generatorChurchId.value },
    {
      preserveScroll: true,
      onStart: () => (generating.value = true),
      onFinish: () => (generating.value = false),
      onSuccess: () => (generatorOpen.value = false),
      onError: (errors) => {
        previewError.value = firstError(errors, 'No se pudo generar.');
        alertError('No se pudieron generar los fines de semana', previewError.value);
      },
    },
  );
};
</script>

<template>
  <AppShell :page-title="'Fines de semana de misas'">
    <CatalogHeader
      title="Fines de semana de misas"
      subtitle="Gestión de fines de semana por parroquia"
      back-href="/"
      :count="weekends.total"
      :icon="CalendarDays"
    />

    <div
      class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center"
      :class="tabs.length ? 'sm:justify-between' : 'sm:justify-end'"
    >
      <div
        v-if="tabs.length"
        class="inline-flex w-fit rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-700 dark:bg-slate-900"
        role="tablist"
        aria-label="Fines de semana"
      >
        <button
          v-for="item in tabs"
          :key="item.value"
          type="button"
          role="tab"
          :aria-selected="tab === item.value"
          class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
          :class="
            tab === item.value
              ? 'bg-sky-700 text-white shadow-sm'
              : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
          "
          @click="selectTab(item.value)"
        >
          <component :is="item.icon" class="h-4 w-4" />
          {{ item.label }}
        </button>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <Link
          v-if="canCreate"
          href="/fines-semana-misas/programar"
          class="btn btn-sm gap-1.5 rounded-xl border border-sky-200 bg-white text-sky-700 shadow-sm transition-all hover:-translate-y-0.5 hover:border-sky-300 hover:bg-sky-50 dark:border-sky-800 dark:bg-slate-900 dark:text-sky-300 dark:hover:bg-slate-800"
        >
          <CalendarClock class="h-4 w-4" />
          Programar
        </Link>

        <button
          v-if="canCreate"
          type="button"
          class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800"
          @click="openNew"
        >
          <Plus class="h-4 w-4" />
          Nuevo
        </button>
      </div>
    </div>

    <div
      v-if="tab === 'history' && weekends.data.length"
      class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <table class="table w-full">
        <thead>
          <tr
            class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950"
          >
            <th>Nombre</th>
            <th>Parroquia</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Estatus</th>
            <th class="text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="weekend in weekends.data" :key="weekend.id">
            <td class="font-semibold">{{ weekend.name || 'Sin nombre' }}</td>
            <td>{{ weekend.church }}</td>
            <td>{{ weekend.starts_at }}</td>
            <td>{{ weekend.ends_at }}</td>
            <td>
              <span class="badge badge-sm badge-success">Concluido</span>
            </td>
            <td class="whitespace-nowrap text-right">
              <Link
                v-if="canViewMasses"
                :href="`/misas?weekend_id=${weekend.id}`"
                class="btn btn-ghost btn-xs text-purple-600"
                title="Misas"
                aria-label="Ver misas del fin de semana"
              >
                <Church class="h-3.5 w-3.5" />
              </Link>
              <Link
                v-if="canUpdate"
                :href="`/fines-semana-misas/${weekend.id}/edit`"
                class="btn btn-ghost btn-xs text-sky-600"
                title="Editar"
                aria-label="Editar fin de semana"
              >
                <Pencil class="h-3.5 w-3.5" />
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="tab !== 'history' && weekends.data.length" class="grid gap-4 xl:grid-cols-2">
      <article
        v-for="weekend in weekends.data"
        :key="weekend.id"
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
      >
        <div
          class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-slate-800"
        >
          <div class="min-w-0">
            <p
              class="text-xs font-semibold uppercase tracking-wider text-sky-700 dark:text-sky-400"
            >
              {{ weekend.church }}
            </p>
            <h2 class="mt-1 truncate text-lg font-bold text-slate-900 dark:text-white">
              {{ weekend.name || 'Fin de semana de misas' }}
            </h2>
          </div>
          <span
            class="badge badge-sm shrink-0"
            :class="
              weekend.status === 'in_progress'
                ? 'badge-warning'
                : weekend.status === 'completed'
                  ? 'badge-success'
                  : 'badge-info'
            "
          >
            {{
              weekend.status === 'in_progress'
                ? 'En curso'
                : weekend.status === 'completed'
                  ? 'Concluido'
                  : 'Próximo'
            }}
          </span>
        </div>

        <div class="grid gap-4 px-5 py-4 sm:grid-cols-2">
          <div>
            <p
              class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400"
            >
              Inicio
            </p>
            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-200">
              {{ weekend.starts_at }}
            </p>
          </div>
          <div>
            <p
              class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400"
            >
              Fin
            </p>
            <p class="mt-1 font-semibold text-slate-800 dark:text-slate-200">
              {{ weekend.ends_at }}
            </p>
          </div>
        </div>

        <div
          v-if="canViewMasses || canUpdate || canDelete"
          class="flex items-center justify-end gap-1 border-t border-slate-100 px-4 py-3 dark:border-slate-800"
        >
          <Link
            v-if="canViewMasses"
            :href="`/misas?weekend_id=${weekend.id}`"
            class="btn btn-ghost btn-sm gap-1.5 text-purple-600"
            aria-label="Ver misas del fin de semana"
          >
            <Church class="h-4 w-4" />
            Misas
          </Link>
          <Link
            v-if="canUpdate"
            :href="`/fines-semana-misas/${weekend.id}/edit`"
            class="btn btn-ghost btn-sm text-sky-600"
            aria-label="Editar fin de semana"
          >
            <Pencil class="h-4 w-4" />
          </Link>
          <button
            v-if="canDelete"
            type="button"
            class="btn btn-ghost btn-sm text-red-600"
            aria-label="Eliminar fin de semana"
            @click="destroyWeekend(weekend)"
          >
            <Trash2 class="h-4 w-4" />
          </button>
        </div>
      </article>
    </div>

    <div
      v-else
      class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-900"
    >
      <CalendarDays class="mx-auto h-9 w-9 text-slate-400" />
      <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
        {{ emptyMessage }}
      </p>
    </div>

    <AppPagination
      :links="weekends.links"
      :from="weekends.from"
      :to="weekends.to"
      :total="weekends.total"
    />

    <dialog
      v-if="generatorOpen"
      class="modal modal-open px-3 sm:px-0"
      aria-labelledby="generator-title"
      @click.self="closeGenerator"
    >
      <div class="modal-box max-h-[90vh] w-full max-w-3xl overflow-y-auto px-4 sm:px-6">
        <div class="mb-4 flex items-start justify-between gap-3">
          <div>
            <h2 id="generator-title" class="text-lg font-bold text-slate-900 dark:text-white">
              Nuevos fines de semana
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
              Fines de semana pendientes del mes actual<span v-if="preview?.month_label">
                ({{ preview.month_label }})</span
              >
              con sus misas programadas.
            </p>
          </div>
          <button
            type="button"
            class="btn btn-ghost btn-sm"
            aria-label="Cerrar"
            @click="closeGenerator"
          >
            <X class="h-4 w-4" />
          </button>
        </div>

        <label class="mb-4 block">
          <span class="block text-sm font-bold text-slate-600 dark:text-slate-300">Parroquia</span>
          <select
            v-model="generatorChurchId"
            class="select select-sm select-bordered mt-1 w-full"
            :disabled="lockedChurchId !== null || generating"
            @change="loadPreview"
          >
            <option v-for="church in churches" :key="church.id" :value="church.id">
              {{ church.name }}
            </option>
          </select>
        </label>

        <div v-if="previewLoading" class="py-10 text-center text-sm text-slate-500">
          <span class="loading loading-spinner loading-sm mr-2"></span>
          Cargando desglose...
        </div>

        <div
          v-else-if="previewError"
          class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
          role="alert"
        >
          {{ previewError }}
        </div>

        <template v-else-if="preview">
          <p
            v-if="!preview.schedules_count"
            class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
          >
            Esta parroquia no tiene misas programadas. Configúralas en «Programar» o crea el fin de
            semana de forma manual.
          </p>

          <p
            v-else-if="!preview.weekends.length"
            class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
          >
            Todos los fines de semana del mes actual ya fueron creados.
          </p>

          <ul v-else class="space-y-3">
            <li
              v-for="weekend in preview.weekends"
              :key="weekend.starts_at"
              class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"
            >
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-semibold text-slate-900 dark:text-white">{{ weekend.name }}</p>
                <span class="badge badge-sm badge-info">Próximo</span>
              </div>
              <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div
                  v-for="day in [
                    { value: 'saturday', date: weekend.starts_at },
                    { value: 'sunday', date: weekend.ends_at },
                  ]"
                  :key="day.value"
                >
                  <p
                    class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400"
                  >
                    {{ formatDay(day.date) }}
                  </p>
                  <ul class="mt-1 space-y-1 text-sm">
                    <li
                      v-for="mass in massesFor(weekend, day.value)"
                      :key="`${mass.name}-${mass.starts_at}`"
                      class="text-slate-700 dark:text-slate-200"
                    >
                      <span class="font-semibold">
                        {{ formatTime(mass.starts_at) }} - {{ formatTime(mass.ends_at) }}
                      </span>
                      · {{ mass.name }}
                      <span v-if="mass.chapel" class="text-slate-500">({{ mass.chapel }})</span>
                    </li>
                    <li v-if="!massesFor(weekend, day.value).length" class="text-slate-400">
                      Sin misas
                    </li>
                  </ul>
                </div>
              </div>
            </li>
          </ul>
        </template>

        <div class="modal-action flex-wrap">
          <Link
            href="/fines-semana-misas/create"
            class="btn btn-sm rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
          >
            <Pencil class="h-4 w-4" />
            Creación manual
          </Link>
          <button
            type="button"
            class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="generating || previewLoading || !preview?.weekends.length"
            @click="generate"
          >
            <Sparkles class="h-4 w-4" />
            {{
              generating
                ? 'Generando...'
                : `Generar ${preview?.weekends.length ?? 0} fin(es) de semana`
            }}
          </button>
        </div>
      </div>
    </dialog>
  </AppShell>
</template>
