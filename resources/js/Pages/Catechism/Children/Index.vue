<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import {
  Download,
  Filter,
  Pencil,
  Plus,
  RotateCcw,
  QrCode,
  Search,
  Trash2,
  Users,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import QRCode from 'qrcode';
import AppPagination from '../../../components/AppPagination.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  children: { type: Object, required: true },
  search: { type: String, default: '' },
  filters: {
    type: Object,
    default: () => ({
      church_id: null,
      municipality_id: null,
      community_id: null,
      level_id: null,
      status: null,
      origin: null,
    }),
  },
  churches: { type: Array, default: () => [] },
  municipalities: { type: Array, default: () => [] },
  communities: { type: Array, default: () => [] },
  levels: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] },
  statusLabels: { type: Object, default: () => ({}) },
  sexLabels: { type: Object, default: () => ({}) },
  bloodTypeLabels: { type: Object, default: () => ({}) },
  latestPdfExportBatch: { type: Object, default: null },
});

const searchTerm = ref(props.search);
const selectedChurch = ref(props.filters.church_id);
const selectedMunicipality = ref(props.filters.municipality_id);
const selectedCommunity = ref(props.filters.community_id);
const selectedLevel = ref(props.filters.level_id);
const qrChild = ref(null);
const qrDataUrl = ref('');
const selectedStatus = ref(props.filters.status);
const selectedOrigin = ref(props.filters.origin);
let debounce = null;

const availableCommunities = computed(() => {
  if (!selectedMunicipality.value) return props.communities;
  return props.communities.filter(
    (community) => String(community.municipality_id) === String(selectedMunicipality.value),
  );
});

const activeFilters = computed(
  () =>
    !!searchTerm.value ||
    !!selectedChurch.value ||
    !!selectedMunicipality.value ||
    !!selectedCommunity.value ||
    !!selectedLevel.value ||
    !!selectedStatus.value ||
    !!selectedOrigin.value,
);

const reload = () => {
  const params = {
    search: searchTerm.value || undefined,
    church_id: selectedChurch.value || undefined,
    municipality_id: selectedMunicipality.value || undefined,
    community_id: selectedCommunity.value || undefined,
    level_id: selectedLevel.value || undefined,
    status: selectedStatus.value || undefined,
    origin: selectedOrigin.value || undefined,
  };

  router.get('/children', params, { preserveState: true, replace: true });
};

watch(
  [
    searchTerm,
    selectedChurch,
    selectedMunicipality,
    selectedCommunity,
    selectedLevel,
    selectedStatus,
    selectedOrigin,
  ],
  () => {
    clearTimeout(debounce);
    debounce = setTimeout(reload, 400);
  },
);

watch(selectedMunicipality, () => {
  if (!selectedCommunity.value) return;

  const exists = availableCommunities.value.some(
    (community) => String(community.id) === String(selectedCommunity.value),
  );

  if (!exists) {
    selectedCommunity.value = null;
  }
});

const clearFilters = () => {
  searchTerm.value = '';
  selectedChurch.value = null;
  selectedMunicipality.value = null;
  selectedCommunity.value = null;
  selectedLevel.value = null;
  selectedStatus.value = null;
  selectedOrigin.value = null;
};

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const hasPermission = (action) => permissions.value.includes(`children.${action}`);
const canCreate = computed(() => hasPermission('create'));
const canUpdate = computed(() => hasPermission('update'));
const canDelete = computed(() => hasPermission('delete'));
const canExport = computed(() => hasPermission('export'));
const isSuperadmin = computed(() => page.props.auth?.roles?.includes('Superadmin') ?? false);

const exportChildren = () => {
  if (!canExport.value) return;

  const url = new URL('/children/export', window.location.origin);

  if (searchTerm.value) url.searchParams.set('search', searchTerm.value);
  if (selectedChurch.value) url.searchParams.set('church_id', selectedChurch.value);
  if (selectedMunicipality.value)
    url.searchParams.set('municipality_id', selectedMunicipality.value);
  if (selectedCommunity.value) url.searchParams.set('community_id', selectedCommunity.value);
  if (selectedLevel.value) url.searchParams.set('level_id', selectedLevel.value);
  if (selectedStatus.value) url.searchParams.set('status', selectedStatus.value);

  window.location.assign(url.toString());
};

const pdfBatch = ref(props.latestPdfExportBatch);
const launchingPdf = ref(false);
const downloadingPdf = ref(false);
let pdfPollTimer = null;
let pdfPollStaleCount = 0;

const isPdfBatchActive = computed(
  () =>
    !!pdfBatch.value &&
    !pdfBatch.value.cancelled &&
    (!pdfBatch.value.finished || !pdfBatch.value.has_result),
);

const stopPdfPolling = () => {
  if (pdfPollTimer) {
    clearInterval(pdfPollTimer);
    pdfPollTimer = null;
  }
};

const refreshPdfBatch = async () => {
  if (!pdfBatch.value) return;

  try {
    const res = await fetch(`/children/export-pdf/${pdfBatch.value.batch_id}`, {
      headers: { Accept: 'application/json' },
    });

    if (!res.ok) {
      stopPdfPolling();
      return;
    }

    const data = await res.json();
    pdfBatch.value = data;

    if (data.cancelled) {
      stopPdfPolling();
      return;
    }

    if (data.finished && data.has_result) {
      stopPdfPolling();
      return;
    }

    if (data.finished && !data.has_result) {
      pdfPollStaleCount += 1;
      if (pdfPollStaleCount >= 40) {
        stopPdfPolling();
      }
      return;
    }
  } catch {
    stopPdfPolling();
  }
};

const startPdfPolling = () => {
  stopPdfPolling();
  pdfPollStaleCount = 0;
  pdfPollTimer = setInterval(refreshPdfBatch, 3000);
};

onMounted(() => {
  if (isPdfBatchActive.value) {
    startPdfPolling();
  }
});

onBeforeUnmount(stopPdfPolling);

const csrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const pdfFilterParams = () => ({
  search: searchTerm.value || undefined,
  church_id: selectedChurch.value || undefined,
  municipality_id: selectedMunicipality.value || undefined,
  community_id: selectedCommunity.value || undefined,
  level_id: selectedLevel.value || undefined,
  status: selectedStatus.value || undefined,
  origin: selectedOrigin.value || undefined,
});

const launchPdfExport = async () => {
  if (!canExport.value) return;

  const confirmed = await Swal.fire({
    title: '¿Exportar gafetes PDF?',
    text: 'Se generarán los gafetes de todos los niños que coincidan con los filtros actuales y se combinarán en un solo PDF. Esto puede tardar unos momentos.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, exportar',
    cancelButtonText: 'Cancelar',
  });

  if (!confirmed.isConfirmed) return;

  launchingPdf.value = true;

  try {
    const res = await fetch('/children/export-pdf', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(pdfFilterParams()),
    });

    const data = await res.json();

    if (data.batch_id) {
      pdfBatch.value = {
        batch_id: data.batch_id,
        total: data.total,
        processed: 0,
        pending: data.total,
        failed: 0,
        progress: 0,
        finished: false,
        cancelled: false,
        has_result: false,
      };
      startPdfPolling();
    } else {
      Swal.fire({
        title: data.message || 'No hay registros para exportar',
        text: 'No hay niños que coincidan con los filtros actuales.',
        icon: 'info',
      });
    }
  } catch {
    Swal.fire({
      title: 'No se pudo iniciar la exportación',
      text: 'La exportación no pudo comenzar en este momento.',
      icon: 'error',
    });
  } finally {
    launchingPdf.value = false;
  }
};

const downloadPdfExport = () => {
  if (!pdfBatch.value?.has_result) return;
  downloadingPdf.value = true;
  window.location.assign(`/children/export-pdf/${pdfBatch.value.batch_id}/download`);
  setTimeout(() => {
    downloadingPdf.value = false;
  }, 2000);
};

const destroyChild = (child) => {
  if (!confirm(`Eliminar el registro de ${child.full_name}?`)) return;
  router.delete(`/children/${child.id}`, { preserveScroll: true });
};

const openQr = async (child) => {
  qrChild.value = child;
  qrDataUrl.value = await QRCode.toDataURL(child.code, {
    width: 256,
    margin: 2,
  });
};

const closeQr = () => {
  qrChild.value = null;
  qrDataUrl.value = '';
};

const badgePdfHref = computed(() =>
  qrChild.value ? `/children/${qrChild.value.id}/badge-pdf` : '#',
);

</script>

<template>
  <AppShell :page-title="'Niños'">
    <CatalogHeader
      title="Niños"
      subtitle="Gestión de niños registrados en catecismo"
      back-href="/"
      :count="children.total"
      :icon="Users"
    >
      <template #actions>
        <button
          v-if="canExport"
          type="button"
          class="btn btn-outline btn-sm gap-1.5"
          :disabled="isPdfBatchActive || launchingPdf"
          @click="launchPdfExport"
        >
          <span v-if="launchingPdf" class="loading loading-spinner loading-sm"></span>
          <Download class="h-4 w-4" v-else />
          Exportar PDF
        </button>

        <button
          v-if="canExport"
          type="button"
          class="btn btn-outline btn-sm gap-1.5"
          @click="exportChildren"
        >
          <Download class="h-4 w-4" />
          Exportar Excel
        </button>

        <Link
          v-if="canCreate"
          href="/children/create"
          class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800"
        >
          <Plus class="h-4 w-4" />
          Nuevo niño
        </Link>
      </template>
    </CatalogHeader>

    <section
      class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900"
    >
      <div
        class="mb-4 flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between"
      >
        <h2
          class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300"
        >
          <Filter class="h-4 w-4 text-rose-700 dark:text-rose-400" />
          Filtros
        </h2>
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

      <div class="grid gap-4">
        <label
          class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-3 shadow-sm focus-within:border-sky-400 focus-within:ring-2 focus-within:ring-sky-100 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-sky-600 dark:focus-within:ring-sky-900/40"
        >
          <Search class="h-4 w-4 shrink-0 text-slate-400" />
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Buscar por código, nombre, correo o teléfono..."
            class="w-full bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 dark:text-slate-200"
          />
        </label>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
          <select
            v-model="selectedChurch"
            class="select select-bordered h-11 w-full rounded-2xl bg-white pr-10 sm:col-span-2 lg:col-span-1 xl:col-span-2 dark:bg-slate-950"
          >
            <option :value="null">Todas las iglesias</option>
            <option v-for="church in churches" :key="church.id" :value="church.id">
              {{ church.name }}
            </option>
          </select>

          <select
            v-model="selectedMunicipality"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Todos los municipios</option>
            <option
              v-for="municipality in municipalities"
              :key="municipality.id"
              :value="municipality.id"
            >
              {{ municipality.name }}
            </option>
          </select>

          <select
            v-model="selectedCommunity"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Todas las comunidades</option>
            <option
              v-for="community in availableCommunities"
              :key="community.id"
              :value="community.id"
            >
              {{ community.name }}
            </option>
          </select>

          <select
            v-model="selectedLevel"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Todos los niveles</option>
            <option v-for="level in levels" :key="level.id" :value="level.id">
              {{ level.name }}
            </option>
          </select>

          <select
            v-model="selectedStatus"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Todos los estados</option>
            <option v-for="status in statuses" :key="status.value" :value="status.value">
              {{ status.label }}
            </option>
          </select>

          <select
            v-if="isSuperadmin"
            v-model="selectedOrigin"
            class="select select-bordered h-11 w-full rounded-2xl bg-white dark:bg-slate-950"
          >
            <option :value="null">Todos los origenes</option>
            <option value="registered">Registrado</option>
            <option value="imported">Importado</option>
          </select>
        </div>
      </div>
    </section>

    <div
      v-if="pdfBatch"
      class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h3
          class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200"
        >
          <span class="text-sky-600 dark:text-sky-400">
            <Download class="h-4 w-4" v-if="!isPdfBatchActive" />
            <span v-else class="loading loading-spinner loading-sm"></span>
          </span>
          Exportación masiva de gafetes
        </h3>
        <button
          v-if="pdfBatch.has_result && !isPdfBatchActive"
          type="button"
          class="btn btn-primary btn-sm gap-1.5 rounded-xl"
          :disabled="downloadingPdf"
          @click="downloadPdfExport"
        >
          <span v-if="downloadingPdf" class="loading loading-spinner loading-sm"></span>
          <Download class="h-4 w-4" v-else />
          Descargar PDF
        </button>
      </div>

      <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
        <template v-if="isPdfBatchActive"
          >Generando los gafetes de los niños que coinciden con los filtros aplicados.</template
        >
        <template v-else-if="!pdfBatch.has_result">Finalizando la generación del PDF…</template>
        <template v-else>El PDF con todos los gafetes está listo para descargar.</template>
      </p>

      <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
        <div
          class="h-full rounded-full bg-sky-600 transition-all"
          :style="{ width: pdfBatch.progress + '%' }"
        ></div>
      </div>

      <div
        class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-1 text-xs text-slate-500 dark:text-slate-400"
      >
        <span>
          Procesados:
          <strong class="text-slate-700 dark:text-slate-200">{{ pdfBatch.processed }}</strong>
          de {{ pdfBatch.total }}
        </span>
        <span v-if="pdfBatch.failed > 0" class="font-semibold text-amber-600 dark:text-amber-400">
          Fallidos: {{ pdfBatch.failed }}
        </span>
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
            <th class="px-4 py-3 font-semibold">Código</th>
            <th class="px-4 py-3 font-semibold">Nombre</th>
            <th class="px-4 py-3 font-semibold">Iglesia</th>
            <th class="px-4 py-3 font-semibold">Niveles</th>
            <th class="px-4 py-3 font-semibold">Comunidad</th>
            <th class="px-4 py-3 font-semibold">Teléfono</th>
            <th class="px-4 py-3 font-semibold">Estado</th>
            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="child in children.data"
            :key="child.id"
            class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"
          >
            <td class="px-4 py-3">
              <span
                class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
              >
                {{ child.code }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                  {{ child.full_name }}
                </p>
                <p class="text-xs text-slate-400">
                  {{ sexLabels[child.sex] ?? child.sex }} ·
                  {{ bloodTypeLabels[child.blood_type] ?? child.blood_type }}
                </p>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ child.church }}</td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              <span v-if="child.levels.length">{{
                child.levels.map((level) => level.name).join(', ')
              }}</span>
              <span v-else class="text-slate-400">Sin nivel</span>
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              {{ child.community }}
            </td>
            <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
              <p v-if="child.phone" class="font-medium text-slate-600 dark:text-slate-300">
                {{ child.phone_lada }}{{ child.phone }}
              </p>
              <p v-else class="text-slate-400">Sin teléfono</p>
            </td>
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-700 dark:border-sky-800 dark:bg-sky-900/40 dark:text-sky-300"
              >
                {{ statusLabels[child.status] ?? child.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="inline-flex items-center gap-1">
                <span
                  :class="
                    child.badge_pdf_downloaded
                      ? 'text-emerald-500 dark:text-emerald-400'
                      : 'text-slate-300 dark:text-slate-600'
                  "
                  :title="
                    child.badge_pdf_downloaded
                      ? `Gafete PDF descargado (${child.badge_pdf_downloaded_at})`
                      : 'Gafete PDF no descargado'
                  "
                >
                  <Download class="h-4 w-4" />
                </span>
                <a
                  :href="`/children/${child.id}/badge-pdf?download=1`"
                  class="btn btn-ghost btn-xs text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 dark:text-indigo-400 dark:hover:bg-indigo-950/40"
                  title="Descargar gafete PDF"
                >
                  <Download class="h-3.5 w-3.5" />
                </a>
                <button
                  type="button"
                  class="btn btn-ghost btn-xs text-slate-600 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-300 dark:hover:bg-slate-800"
                  title="Mostrar QR"
                  @click="openQr(child)"
                >
                  <QrCode class="h-3.5 w-3.5" />
                </button>
                <Link
                  v-if="canUpdate"
                  :href="`/children/${child.id}/edit`"
                  class="btn btn-ghost btn-xs text-sky-600 hover:bg-sky-50 hover:text-sky-700 dark:text-sky-400 dark:hover:bg-sky-950/40"
                  title="Editar niño"
                >
                  <Pencil class="h-3.5 w-3.5" />
                </Link>
                <button
                  v-if="canDelete"
                  type="button"
                  class="btn btn-ghost btn-xs text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40"
                  title="Eliminar niño"
                  @click="destroyChild(child)"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </td>
          </tr>

          <tr v-if="children.data.length === 0">
            <td
              colspan="8"
              class="px-4 py-12 text-center text-sm text-slate-400 dark:text-slate-500"
            >
              <span v-if="activeFilters"
                >No se encontraron niños con los filtros seleccionados.</span
              >
              <span v-else>No hay niños registrados.</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      :links="children.links"
      :from="children.from"
      :to="children.to"
      :total="children.total"
    />

    <div
      v-if="qrChild"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4"
      @click.self="closeQr"
    >
      <div
        class="w-full max-w-sm rounded-3xl bg-white p-6 text-center shadow-2xl dark:bg-slate-900"
      >
        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100">
          {{ qrChild.full_name }}
        </h2>
        <p class="mt-1 font-mono text-xs text-slate-500">{{ qrChild.code }}</p>
        <img
          v-if="qrDataUrl"
          :src="qrDataUrl"
          :alt="`QR ${qrChild.code}`"
          class="mx-auto my-5 h-64 w-64"
        />
        <p class="text-xs text-slate-400">Este QR contiene únicamente el código único del niño.</p>
        <div class="mt-5 flex gap-3">
          <button
            type="button"
            class="btn btn-ghost btn-sm flex-1 rounded-2xl border border-slate-300 dark:border-slate-600"
            @click="closeQr"
          >
            Cerrar
          </button>
          <a
            :href="badgePdfHref"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-outline btn-sm flex-1 rounded-2xl border-sky-300 text-sky-700 hover:border-sky-400 hover:bg-sky-50 dark:border-sky-800 dark:text-sky-300 dark:hover:bg-sky-950/40"
          >
            Ver gafete
          </a>
        </div>
      </div>
    </div>
  </AppShell>
</template>
