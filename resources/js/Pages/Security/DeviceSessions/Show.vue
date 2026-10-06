<script setup>
import { computed, nextTick, ref } from 'vue';
import { router, usePage, usePoll } from '@inertiajs/vue3';
import { CalendarDays, CalendarRange, ChevronDown, ChevronUp, Download, MonitorSmartphone, ShieldCheck, Trash2, X } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';

const props = defineProps({
  user: { type: Object, required: true },
  sessions: { type: Array, default: () => [] },
  reportMonth: { type: String, required: true },
  reportStartMonth: { type: String, required: true },
  reportToday: { type: String, required: true },
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canDelete = computed(() => permissions.value.includes('dispositivos_sesiones.delete'));
const closingUserSessions = ref(false);
const reportDialog = ref(null);
const monthWheel = ref(null);
const reportMode = ref('month');
const month = ref(props.reportMonth);
const from = ref('');
const to = ref('');
const monthOptions = computed(() => {
  const [startYear, startMonth] = props.reportStartMonth.split('-').map(Number);
  const [endYear, endMonth] = props.reportMonth.split('-').map(Number);
  const formatter = new Intl.DateTimeFormat('es-MX', { month: 'long', year: 'numeric', timeZone: 'UTC' });
  const options = [];

  for (let year = endYear, monthNumber = endMonth; year > startYear || (year === startYear && monthNumber >= startMonth); monthNumber--) {
    if (monthNumber === 0) {
      year--;
      monthNumber = 12;
    }
    options.push({ value: `${year}-${String(monthNumber).padStart(2, '0')}`, label: formatter.format(new Date(Date.UTC(year, monthNumber - 1, 1))) });
  }

  return options;
});
const reportReady = computed(() => reportMode.value === 'month'
  ? monthOptions.value.some((option) => option.value === month.value)
  : !!from.value && !!to.value && from.value <= to.value && to.value <= props.reportToday);
const selectedMonthIndex = computed(() => monthOptions.value.findIndex((option) => option.value === month.value));
const selectMonth = (index) => {
  const option = monthOptions.value[index];
  if (!option) return;

  month.value = option.value;
  monthWheel.value?.scrollTo({ top: index * 40, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
};
const handleMonthScroll = (event) => {
  const index = Math.round(event.target.scrollTop / 40);
  if (monthOptions.value[index]) month.value = monthOptions.value[index].value;
};
const openReportDialog = () => {
  reportDialog.value?.showModal();
  nextTick(() => monthWheel.value?.scrollTo({ top: Math.max(0, selectedMonthIndex.value) * 40 }));
};
const closeReportDialog = () => reportDialog.value?.close();
const downloadHistory = () => {
  if (!reportReady.value) return;

  const dates = reportMode.value === 'month'
    ? new URLSearchParams({ month: month.value })
    : new URLSearchParams({ from: from.value, to: to.value });

  closeReportDialog();
  window.location.href = `/dispositivos-sesiones/usuario/${props.user.id}/historial?${dates}`;
};

const getAccountStatusLabel = (status) => ({ active: 'Activa', suspended: 'Suspendida', blocked: 'Bloqueada' })[status] ?? 'Activa';
const getAccountStatusClass = (status) => ({ active: 'badge-success', suspended: 'badge-warning', blocked: 'badge-error' })[status] ?? 'badge-success';

const refreshSessions = () => router.reload({ only: ['sessions', 'user'], preserveScroll: true, showProgress: false });

usePoll(5000, { only: ['sessions', 'user', 'reportMonth', 'reportToday'], preserveScroll: true, showProgress: false }, { mode: 'rest' });

const closeSession = async (sessionId) => {
  if (!canDelete.value) return;

  const response = await fetch(`/dispositivos-sesiones/${sessionId}`, {
    method: 'DELETE',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  if (response.ok) refreshSessions();
};

const closeUserSessions = async () => {
  if (!canDelete.value || closingUserSessions.value) return;

  closingUserSessions.value = true;

  try {
    if (!props.sessions.some((session) => session.status === 'active')) {
      await Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'info',
        title: `${props.user.name} no tiene sesiones activas en este momento.`,
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
      });
      return;
    }

    const result = await Swal.fire({
      toast: true,
      position: 'top-end',
      title: 'Quitar todas las sesiones',
      text: `¿Seguro que deseas cerrar las sesiones de ${props.user.name} en todos sus dispositivos?.`,
      icon: 'warning',
      width: 460,
      backdrop: false,
      showConfirmButton: true,
      showCancelButton: true,
      buttonsStyling: false,
      confirmButtonText: 'Confirmar',
      cancelButtonText: 'Cancelar',
      customClass: {
        popup: 'swal-delete-banner',
        actions: 'swal-delete-banner-actions',
        confirmButton: 'btn btn-error btn-xs',
        cancelButton: 'btn btn-ghost btn-xs',
      },
    });

    if (!result.isConfirmed) return;

    const response = await fetch(`/dispositivos-sesiones/usuario/${props.user.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    const json = await response.json();

    if (!response.ok) {
      throw new Error(json?.message ?? 'No se pudieron quitar las sesiones.');
    }

    refreshSessions();
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: json.success ? 'success' : 'info',
      title: json.message ?? 'Sesiones actualizadas.',
      showConfirmButton: false,
      timer: 2800,
      timerProgressBar: true,
    });
  } catch (error) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'error',
      title: 'No se pudieron quitar las sesiones',
      text: error?.message ?? 'Inténtalo nuevamente.',
      showConfirmButton: true,
      confirmButtonText: 'Entendido',
    });
  } finally {
    closingUserSessions.value = false;
  }
};
</script>

<template>
  <AppShell :page-title="'Detalles de sesiones'">
    <div class="mx-auto w-full max-w-7xl space-y-5">
      <CatalogHeader
        title="Detalles de sesiones"
        subtitle="Consulta la actividad reciente y las ubicaciones registradas"
        back-href="/dispositivos-sesiones"
        :count="sessions.length"
        :icon="ShieldCheck"
      >
        <template #actions>
          <div class="flex w-full flex-col items-stretch justify-end gap-2 sm:w-auto sm:flex-row sm:items-center">
            <button type="button" class="btn btn-sm w-full gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800 sm:w-auto" @click="openReportDialog">
              <Download class="h-4 w-4" />
              Descargar Excel
            </button>
            <button v-if="canDelete" type="button" :disabled="closingUserSessions" class="btn btn-sm w-full gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800 sm:w-auto" @click="closeUserSessions">
              <Trash2 class="h-4 w-4" />
              Quitar todas las sesiones
            </button>
          </div>
        </template>
      </CatalogHeader>

      <div class="ui-table-wrap overflow-x-auto overscroll-x-contain rounded-xl">
        <table class="ui-table w-full min-w-[760px] table-fixed text-sm">
          <colgroup>
            <col class="w-[26%]" />
            <col class="w-[22%]" />
            <col class="w-[20%]" />
            <col class="w-[18%]" />
            <col class="w-[14%]" />
          </colgroup>
          <thead>
            <tr class="whitespace-nowrap">
              <th class="px-3 py-3">Usuario</th>
              <th class="px-3 py-3">Correo</th>
              <th class="px-3 py-3">Parroquia</th>
              <th class="px-3 py-3">Rol</th>
              <th class="px-3 py-3">Estatus</th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-t border-slate-100 dark:border-slate-700">
              <td class="break-words px-3 py-4 font-semibold text-slate-800 dark:text-slate-100">{{ user.name }}</td>
              <td class="break-all px-3 py-4 text-slate-600 dark:text-slate-300">{{ user.email }}</td>
              <td class="break-words px-3 py-4 text-slate-600 dark:text-slate-300">{{ user.parish }}</td>
              <td class="break-words px-3 py-4 text-slate-600 dark:text-slate-300">{{ user.role }}</td>
              <td class="break-words px-3 py-4 text-slate-600 dark:text-slate-300">
                <span class="badge badge-sm font-medium" :class="getAccountStatusClass(user.account_status)">{{ getAccountStatusLabel(user.account_status) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pt-1">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-2">
            <MonitorSmartphone class="h-5 w-5 text-sky-500" />
            <div>
              <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Detalles</h2>
            </div>
          </div>
        </div>

        <div class="ui-table-wrap session-details-wrap mt-5 rounded-xl">
          <table class="ui-table session-details-table w-full table-fixed text-xs xl:text-sm">
            <colgroup>
              <col class="w-[15%]" />
              <col class="w-[13%]" />
              <col class="w-[8%]" />
              <col class="w-[17%]" />
              <col class="w-[22%]" />
              <col class="w-[16%]" />
              <col class="w-[9%]" />
            </colgroup>
            <thead>
              <tr>
                <th class="px-2 py-3">Dispositivo</th>
                <th class="px-2 py-3">Navegador</th>
                <th class="px-2 py-3">Estatus</th>
                <th class="px-2 py-3">Dirección IP</th>
                <th class="px-2 py-3">Última ubicación</th>
                <th class="px-2 py-3">Última actividad</th>
                <th class="px-2 py-3">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="session in sessions" :key="session.id" class="border-t border-slate-100 align-top dark:border-slate-700">
                <td data-label="Dispositivo" class="break-words px-2 py-3">
                  <div class="font-semibold text-slate-800 dark:text-slate-100">{{ session.device_type }}</div>
                  <div v-if="session.device_model" class="mt-1 break-words text-[10px] text-slate-500 dark:text-slate-400">{{ session.device_model }}</div>
                </td>
                <td data-label="Navegador" class="break-words px-2 py-3 text-slate-700 dark:text-slate-200">{{ session.browser }}</td>
                <td data-label="Estatus" class="px-2 py-3 xl:text-center">
                  <span class="badge badge-sm" :class="session.status === 'active' ? 'badge-success' : 'badge-ghost'">
                    {{ session.status === 'active' ? 'Activa' : 'Cerrada' }}
                  </span>
                </td>
                <td data-label="Dirección IP" class="break-all px-2 py-3 text-slate-700 dark:text-slate-200 xl:text-center">{{ session.ip_address }}</td>
                <td data-label="Última ubicación" class="break-words px-2 py-3 text-slate-500 dark:text-slate-400">
                  <div>{{ session.location }}</div>
                  <div v-if="session.location_accuracy" class="mt-1 text-[10px] text-slate-400 dark:text-slate-500">{{ session.location_accuracy }}</div>
                </td>
                <td data-label="Última actividad" class="break-words px-2 py-3 text-slate-700 dark:text-slate-200">
                  <div>{{ session.last_activity }}</div>
                  <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ session.last_activity_elapsed }}</div>
                </td>
                <td data-label="Acciones" class="px-2 py-3 text-center">
                  <button
                    v-if="canDelete"
                    type="button"
                    class="btn btn-ghost btn-xs text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40"
                    title="Quitar Registro"
                    aria-label="Quitar Registro"
                    @click="closeSession(session.id)"
                  >
                    <Trash2 class="h-3.5 w-3.5" />
                  </button>
                </td>
              </tr>
              <tr v-if="sessions.length === 0" class="session-empty">
                <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">No hay sesiones registradas para este usuario.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
    <dialog ref="reportDialog" class="modal" aria-labelledby="report-dialog-title" @click.self="closeReportDialog">
      <div class="modal-box max-h-[90dvh] w-11/12 max-w-md rounded-xl border border-slate-200 bg-white p-4 shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:p-5">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-3 dark:border-slate-700">
          <div class="flex items-center gap-3">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-sky-100 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300"><Download class="h-4 w-4" /></span>
            <h2 id="report-dialog-title" class="text-base font-bold text-slate-800 dark:text-slate-100 sm:text-lg">Historial de sesiones</h2>
          </div>
          <button type="button" class="btn btn-ghost btn-sm btn-square" title="Cerrar" aria-label="Cerrar" @click="closeReportDialog">
            <X class="h-4 w-4" />
          </button>
        </div>

        <form class="mt-3 space-y-3" @submit.prevent="downloadHistory">
          <div class="mx-auto grid w-full max-w-[280px] grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800" role="group" aria-label="Periodo del historial">
            <button type="button" class="flex h-9 items-center justify-center gap-2 rounded-lg text-sm font-semibold transition-colors" :class="reportMode === 'month' ? 'bg-sky-700 text-white shadow-md shadow-sky-900/20 dark:bg-sky-600' : 'text-sky-800 hover:bg-white dark:text-sky-200 dark:hover:bg-slate-700'" :aria-pressed="reportMode === 'month'" @click="reportMode = 'month'"><CalendarDays class="h-4 w-4" />Mes</button>
            <button type="button" class="flex h-9 items-center justify-center gap-2 rounded-lg text-sm font-semibold transition-colors" :class="reportMode === 'range' ? 'bg-sky-700 text-white shadow-md shadow-sky-900/20 dark:bg-sky-600' : 'text-sky-800 hover:bg-white dark:text-sky-200 dark:hover:bg-slate-700'" :aria-pressed="reportMode === 'range'" @click="reportMode = 'range'"><CalendarRange class="h-4 w-4" />Rango</button>
          </div>
          <div v-show="reportMode === 'month'" class="space-y-1.5">
            <div class="text-center text-xs font-semibold text-slate-600 dark:text-slate-300">Mes del reporte</div>
            <div class="relative flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50/70 p-2 dark:border-slate-700 dark:bg-slate-800/70">
              <div class="pointer-events-none absolute inset-x-2 top-[48px] h-10 rounded-md border-y border-sky-200 bg-sky-50 dark:border-sky-700 dark:bg-sky-900/50"></div>
              <div ref="monthWheel" class="month-wheel relative h-[120px] min-w-0 flex-1 overflow-y-auto text-center" role="listbox" aria-label="Mes del reporte" :aria-activedescendant="`report-month-${month}`" tabindex="0" @scroll.passive="handleMonthScroll" @keydown.up.prevent="selectMonth(selectedMonthIndex - 1)" @keydown.down.prevent="selectMonth(selectedMonthIndex + 1)">
                <button v-for="(option, index) in monthOptions" :id="`report-month-${option.value}`" :key="option.value" type="button" class="month-wheel-option flex h-10 w-full shrink-0 snap-center items-center justify-center px-2 text-sm capitalize" :class="month === option.value ? 'font-bold text-sky-900 dark:text-sky-100' : 'text-slate-400 dark:text-slate-500'" role="option" :aria-selected="month === option.value" @click="selectMonth(index)">{{ option.label }}</button>
              </div>
              <div class="relative flex flex-col gap-1">
                <button type="button" class="btn btn-ghost btn-xs btn-square text-sky-700 dark:text-sky-200" title="Mes siguiente" aria-label="Mes siguiente" :disabled="selectedMonthIndex <= 0" @click="selectMonth(selectedMonthIndex - 1)"><ChevronUp class="h-4 w-4" /></button>
                <button type="button" class="btn btn-ghost btn-xs btn-square text-sky-700 dark:text-sky-200" title="Mes anterior" aria-label="Mes anterior" :disabled="selectedMonthIndex >= monthOptions.length - 1" @click="selectMonth(selectedMonthIndex + 1)"><ChevronDown class="h-4 w-4" /></button>
              </div>
            </div>
          </div>
          <div v-show="reportMode === 'range'" class="grid gap-3 sm:grid-cols-2">
            <label class="flex min-w-0 flex-col gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
              Desde
              <input v-model="from" type="date" :max="to && to < reportToday ? to : reportToday" class="input input-bordered w-full dark:bg-slate-800" :required="reportMode === 'range'" />
            </label>
            <label class="flex min-w-0 flex-col gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
              Hasta
              <input v-model="to" type="date" :min="from || undefined" :max="reportToday" class="input input-bordered w-full dark:bg-slate-800" :required="reportMode === 'range'" />
            </label>
          </div>
          <div class="flex items-center justify-between gap-2 border-t border-slate-200 pt-3 dark:border-slate-700">
            <button type="button" class="btn btn-sm rounded-xl border-0 bg-orange-600 text-white shadow-md shadow-orange-900/20 hover:bg-orange-700" @click="closeReportDialog">Cancelar</button>
            <button type="submit" class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 hover:bg-sky-800 disabled:opacity-50" :disabled="!reportReady">
              <Download class="h-4 w-4" />
              Descargar Excel
            </button>
          </div>
        </form>
      </div>
    </dialog>
  </AppShell>
</template>

<style scoped>
.month-wheel {
  scroll-snap-type: y mandatory;
  scrollbar-width: none;
  overscroll-behavior: contain;
  mask-image: linear-gradient(to bottom, transparent, black 30%, black 70%, transparent);
}

.month-wheel::-webkit-scrollbar {
  display: none;
}

.month-wheel::before,
.month-wheel::after {
  content: '';
  display: block;
  height: 40px;
}

.month-wheel-option {
  scroll-snap-align: center;
  transition: color 180ms ease, transform 180ms ease;
}

.month-wheel-option[aria-selected='true'] {
  transform: scale(1.06);
}

.session-details-wrap {
  overflow-x: hidden;
}

.session-details-table :is(th, td) {
  overflow-wrap: anywhere;
}

.session-details-table th {
  text-align: left;
}

.session-details-table th:nth-child(3),
.session-details-table th:nth-child(4),
.session-details-table th:last-child {
  text-align: center;
}

@media (max-width: 1279px) {
  .session-details-table,
  .session-details-table tbody {
    display: block;
  }

  .session-details-table colgroup,
  .session-details-table thead {
    display: none;
  }

  .session-details-table tbody tr {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    padding: 0.5rem;
  }

  .session-details-table tbody td {
    display: block;
    min-width: 0;
    text-align: left;
  }

  .session-details-table tbody td[data-label]::before {
    display: block;
    margin-bottom: 0.25rem;
    color: #64748b;
    content: attr(data-label);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
  }

  .session-details-table .session-empty td {
    grid-column: 1 / -1;
    text-align: center;
  }
}

@media (max-width: 639px) {
  .session-details-table tbody tr {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
