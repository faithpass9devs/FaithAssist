<script setup>
import { router, usePage } from '@inertiajs/vue3';
import {
  CheckCircle2,
  CloudUpload,
  Eye,
  Filter,
  LoaderCircle,
  RotateCcw,
  Search,
  Users,
  UserPlus,
  X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import AppPagination from '../../../components/AppPagination.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  externos: { type: Object, required: true },
  search: { type: String, default: '' },
  filters: {
    type: Object,
    default: () => ({ level_id: null, community_id: null }),
  },
  communities: { type: Array, default: () => [] },
  levels: { type: Array, default: () => [] },
  sexLabels: { type: Object, default: () => ({}) },
  levelStatusLabels: { type: Object, default: () => ({}) },
  selectedExterno: { type: Object, default: null },
  latestImportBatch: { type: Object, default: null },
});

const searchTerm = ref(props.search);
const selectedLevel = ref(props.filters.level_id);
const selectedCommunity = ref(props.filters.community_id);
let debounce = null;

const activeFilters = computed(
  () => !!searchTerm.value || !!selectedLevel.value || !!selectedCommunity.value,
);

const params = () => ({
  search: searchTerm.value || undefined,
  level_id: selectedLevel.value || undefined,
  community_id: selectedCommunity.value || undefined,
});

const reload = () => {
  router.get('/externos', params(), { preserveState: true, replace: true });
};

watch([searchTerm, selectedLevel, selectedCommunity], () => {
  clearTimeout(debounce);
  debounce = setTimeout(reload, 400);
});

const clearFilters = () => {
  searchTerm.value = '';
  selectedLevel.value = null;
  selectedCommunity.value = null;
};

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canShow = computed(() => permissions.value.includes('externos.show'));
const canImport = computed(() => permissions.value.includes('externos.import'));
const canImportAll = computed(() => permissions.value.includes('externos.import_all'));

const batch = ref(props.latestImportBatch);
const launching = ref(false);
let pollTimer = null;

const isBatchActive = computed(
  () => !!batch.value && !batch.value.finished && !batch.value.cancelled,
);

const stopPolling = () => {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
};

const refreshBatch = async () => {
  if (!batch.value) return;

  try {
    const res = await fetch(`/externos/import-batch/${batch.value.batch_id}`, {
      headers: { Accept: 'application/json' },
    });

    if (!res.ok) {
      stopPolling();
      return;
    }

    const data = await res.json();
    batch.value = data;

    if (!data.finished && !data.cancelled) {
      return;
    }

    stopPolling();
  } catch {
    stopPolling();
  }
};

const startPolling = () => {
  stopPolling();
  pollTimer = setInterval(refreshBatch, 3000);
};

onMounted(() => {
  if (isBatchActive.value) {
    startPolling();
  }
});

onBeforeUnmount(stopPolling);

const csrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const launchImport = async () => {
  const confirmed = await Swal.fire({
    title: '¿Importar externos?',
    text: 'Se importarán al módulo de niños todos los externos que coincidan con los filtros actuales, enviando el gafete por WhatsApp a cada uno.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, importar',
    cancelButtonText: 'Cancelar',
  });

  if (!confirmed.isConfirmed) return;

  launching.value = true;

  try {
    const res = await fetch('/externos/import-batch', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(params()),
    });

    const data = await res.json();

    if (data.batch_id) {
      batch.value = {
        batch_id: data.batch_id,
        total: data.total,
        processed: 0,
        pending: data.total,
        failed: 0,
        progress: 0,
        finished: false,
        cancelled: false,
      };
      startPolling();
    } else {
      Swal.fire({
        title: 'Sin externos',
        text: 'No hay externos que coincidan con los filtros actuales.',
        icon: 'info',
      });
    }
  } catch {
    Swal.fire({
      title: 'Error',
      text: 'No se pudo iniciar la importación masiva.',
      icon: 'error',
    });
  } finally {
    launching.value = false;
  }
};

const finishImport = () => {
  stopPolling();
  batch.value = null;
  reload();
};

const detail = ref(props.selectedExterno);

watch(
  () => props.selectedExterno,
  (value) => {
    detail.value = value;
  },
);

const openDetail = (externo) => {
  router.get(`/externos/${externo.id}`, params(), { preserveState: true });
};

const openImport = (externo) => {
  router.get(`/externos/${externo.id}/register`);
};

const closeDetail = () => {
  detail.value = null;
  reload();
};
</script>

<template>
  <AppShell :page-title="'Externos'">
    <CatalogHeader
      title="Externos"
      subtitle="Niños registrados en la base de datos externa (Hostinger)"
      back-href="/"
      :count="externos.total"
      :icon="Users"
    />

    <section
      class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="mb-4 flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h2
          class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300"
        >
          <Filter class="h-4 w-4 text-rose-700 dark:text-rose-400" />
          Filtros
        </h2>
        <div class="flex flex-wrap items-center gap-2">
          <button
            v-if="canImportAll"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl border-0 bg-rose-700 px-3 py-1.5 text-xs font-bold text-white shadow-md shadow-rose-900/20 transition hover:bg-rose-800 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="launching || isBatchActive"
            @click="launchImport"
          >
            <CloudUpload class="h-3.5 w-3.5" />
            {{ launching ? 'Iniciando...' : 'Importación masiva' }}
          </button>
          <button
            v-if="activeFilters"
            type="button"
            class="inline-flex items-center gap-1.5 self-start rounded-xl border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 transition hover:border-sky-300 hover:bg-sky-100 sm:self-auto dark:border-sky-900/60 dark:bg-sky-900/30 dark:text-sky-200 dark:hover:bg-sky-900/50"
            @click="clearFilters"
          >
            <RotateCcw class="h-3.5 w-3.5" />
            Limpiar filtros
          </button>
        </div>
      </div>

      <div class="grid gap-4">
        <label
          class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40"
        >
          <Search class="h-4 w-4 shrink-0 text-slate-400" />
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Buscar por nombre, apellidos, correo o teléfono..."
            class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
          />
        </label>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <select
            v-model="selectedCommunity"
            class="select select-bordered h-11 w-full rounded-2xl bg-white pr-10 dark:bg-slate-950"
          >
            <option :value="null">Todas las comunidades</option>
            <option v-for="community in communities" :key="community.id" :value="community.id">
              {{ community.name }}
            </option>
          </select>

          <select
            v-model="selectedLevel"
            class="select select-bordered h-11 w-full rounded-2xl bg-white pr-10 dark:bg-slate-950"
          >
            <option :value="null">Todos los niveles</option>
            <option v-for="level in levels" :key="level.id" :value="level.id">
              {{ level.name }}
            </option>
          </select>
        </div>
      </div>
    </section>

    <div
      v-if="batch"
      class="mb-4 rounded-2xl border border-sky-200 bg-sky-50/70 p-4 shadow-sm sm:p-5 dark:border-sky-900/60 dark:bg-sky-950/30"
    >
      <div class="flex items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
          <LoaderCircle
            v-if="isBatchActive"
            class="h-4 w-4 animate-spin text-sky-600 dark:text-sky-300"
          />
          <CheckCircle2
            v-else
            class="h-4 w-4 text-emerald-600 dark:text-emerald-300"
          />
          <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200">
            Importación masiva
          </h3>
          <span
            class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700 dark:bg-sky-900/40 dark:text-sky-300"
          >
            {{
              batch.cancelled
                ? 'Cancelada'
                : batch.finished
                  ? batch.failed > 0
                    ? 'Terminada con errores'
                    : 'Completada'
                  : 'En progreso'
            }}
          </span>
        </div>
        <button
          v-if="batch.finished"
          type="button"
          class="btn btn-xs rounded-xl border-0 bg-sky-600 text-white shadow-md transition hover:bg-sky-700"
          @click="finishImport"
        >
          Actualizar listado
        </button>
      </div>

      <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
        {{
          batch.filters?.community_id || batch.filters?.level_id || batch.filters?.search
            ? 'Importando los externos que coinciden con los filtros aplicados.'
            : 'Importando todos los externos del listado.'
        }}
      </p>

      <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
        <div
          class="h-full rounded-full bg-sky-600 transition-all"
          :style="{ width: batch.progress + '%' }"
        ></div>
      </div>

      <div
        class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-1 text-xs text-slate-500 dark:text-slate-400"
      >
        <span>
          Procesados:
          <strong class="text-slate-700 dark:text-slate-200">{{ batch.processed }}</strong>
          de {{ batch.total }}
        </span>
        <span v-if="batch.failed > 0" class="font-semibold text-amber-600 dark:text-amber-400">
          Fallidos: {{ batch.failed }}
        </span>
        <span class="text-slate-400">Iniciado: {{ batch.created_at }}</span>
      </div>
    </div>

    <div
      class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <table class="table w-full">
        <thead>
          <tr
            class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400"
          >
            <th class="px-4 py-3 font-semibold">Nombre</th>
            <th class="px-4 py-3 font-semibold">Teléfono</th>
            <th class="px-4 py-3 font-semibold">Correo</th>
            <th class="px-4 py-3 font-semibold">Comunidad</th>
            <th class="px-4 py-3 font-semibold">Nacimiento</th>
            <th class="px-4 py-3 font-semibold">Niveles</th>
            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="externo in externos.data"
            :key="externo.id"
            class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"
          >
            <td class="px-4 py-3">
              <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                  {{ externo.full_name }}
                </p>
                <p class="text-xs text-slate-400">
                  {{ sexLabels[externo.sex] ?? externo.sex }}
                </p>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ externo.phone || '—' }}
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ externo.email || '—' }}
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ externo.community || '—' }}
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ externo.birthdate || '—' }}
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              <span v-if="externo.levels.length">
                {{ externo.levels.map((level) => level.name).join(', ') }}
              </span>
              <span v-else class="text-slate-400">Sin nivel</span>
            </td>
            <td class="px-4 py-3 text-right">
              <span
                v-if="externo.imported"
                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
              >
                <CheckCircle2 class="h-3 w-3" />
                Importado
              </span>
              <button
                v-if="!externo.imported && canImport"
                type="button"
                class="btn btn-ghost btn-xs text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:text-rose-400 dark:hover:bg-rose-950/40"
                title="Importar al módulo de niños"
                @click="openImport(externo)"
              >
                <UserPlus class="h-3.5 w-3.5" />
              </button>
              <button
                v-if="canShow"
                type="button"
                class="btn btn-ghost btn-xs text-sky-600 hover:bg-sky-50 hover:text-sky-700 dark:text-sky-400 dark:hover:bg-sky-950/40"
                title="Ver información"
                @click="openDetail(externo)"
              >
                <Eye class="h-3.5 w-3.5" />
              </button>
            </td>
          </tr>

          <tr v-if="externos.data.length === 0">
            <td
              colspan="7"
              class="px-4 py-12 text-center text-sm text-slate-400 dark:text-slate-500"
            >
              <span v-if="activeFilters"
                >No se encontraron externos con los filtros seleccionados.</span
              >
              <span v-else>No hay externos registrados.</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      :links="externos.links"
      :from="externos.from"
      :to="externos.to"
      :total="externos.total"
    />

    <div
      v-if="detail"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"
      @click.self="closeDetail"
    >
      <div
        class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-3xl bg-white shadow-2xl dark:bg-slate-900"
      >
        <header
          class="flex items-start justify-between gap-4 border-b border-slate-200 p-6 dark:border-slate-800"
        >
          <div>
            <h2 class="text-xl font-black text-slate-800 dark:text-slate-100">
              {{ detail.full_name }}
            </h2>
            <p class="mt-1 text-xs text-slate-400">
              {{ sexLabels[detail.sex] ?? detail.sex }}
              <template v-if="detail.birthdate"> · {{ detail.birthdate }}</template>
            </p>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            @click="closeDetail"
          >
            <X class="h-4 w-4" />
          </button>
        </header>

        <div class="grid gap-6 p-6">
          <div>
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
              Datos personales
            </h3>
            <dl class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <dt class="text-xs text-slate-400">Sexo</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">
                  {{ sexLabels[detail.sex] ?? detail.sex }}
                </dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">Nacimiento</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">
                  {{ detail.birthdate || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">Tipo de sangre</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">
                  {{ detail.blood_type || '—' }}
                </dd>
              </div>
              <div class="col-span-2">
                <dt class="text-xs text-slate-400">Notas</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">
                  {{ detail.notes || '—' }}
                </dd>
              </div>
            </dl>
          </div>

          <div>
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
              Contacto
            </h3>
            <dl class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <dt class="text-xs text-slate-400">Teléfono</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">
                  {{ detail.phone || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">Teléfono de emergencia</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">
                  {{ detail.emergency_phone || '—' }}
                </dd>
              </div>
              <div class="col-span-2">
                <dt class="text-xs text-slate-400">Correo</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">
                  {{ detail.email || '—' }}
                </dd>
              </div>
            </dl>
          </div>

          <div>
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
              Comunidad e iglesia
            </h3>
            <dl class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <dt class="text-xs text-slate-400">Comunidad</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">
                  {{ detail.community || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-xs text-slate-400">Iglesia</dt>
                <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">
                  {{ detail.church || '—' }}
                </dd>
              </div>
            </dl>
          </div>

          <div>
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
              Niveles
            </h3>
            <ul v-if="detail.levels.length" class="space-y-2">
              <li
                v-for="level in detail.levels"
                :key="level.level_id"
                class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700"
              >
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    {{ level.name }}
                  </span>
                  <span
                    v-if="level.is_primary"
                    class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700 dark:bg-sky-900/40 dark:text-sky-300"
                  >
                    Primario
                  </span>
                </div>
                <span
                  class="rounded-full border border-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-500 dark:border-slate-600 dark:text-slate-300"
                >
                  {{ levelStatusLabels[level.status] ?? level.status }}
                </span>
              </li>
            </ul>
            <p v-else class="text-sm text-slate-400">Sin niveles asignados.</p>
          </div>
        </div>

        <footer class="border-t border-slate-200 p-4 dark:border-slate-800">
          <div class="flex flex-col gap-2">
            <button
              v-if="!detail.imported && canImport"
              type="button"
              class="btn btn-sm w-full rounded-2xl border-0 bg-rose-700 text-white shadow-md shadow-rose-900/20 transition hover:bg-rose-800"
              @click="openImport(detail)"
            >
              <UserPlus class="h-4 w-4" />
              Importar al módulo de niños
            </button>
            <div
              v-if="detail.imported"
              class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-100 px-4 py-2.5 text-sm font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
            >
              <CheckCircle2 class="h-4 w-4" />
              Ya importado al módulo de niños
            </div>
            <button
              type="button"
              class="btn btn-ghost btn-sm w-full rounded-2xl border border-slate-300 dark:border-slate-600"
              @click="closeDetail"
            >
              Cerrar
            </button>
          </div>
        </footer>
      </div>
    </div>
  </AppShell>
</template>
