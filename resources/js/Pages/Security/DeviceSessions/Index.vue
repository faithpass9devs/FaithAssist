<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { Bell, Check, Eye, Pencil, Search, ShieldCheck, TriangleAlert, X } from 'lucide-vue-next';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';

const props = defineProps({
  sessions: { type: Array, default: () => [] },
});

const page = usePage();
const searchName = ref('');
const searchParish = ref('');
const searchRole = ref('');
const warningUser = ref(null);
const warningLevel = ref('leve');
const warningReason = ref('Uso inadecuado detectado');
const warningMessage = ref('Se detectó un uso inadecuado del sistema. Debes detener esta conducta de inmediato.');
const warningSending = ref(false);
const warningError = ref('');
const editingRowId = ref(null);
const editAccountStatus = ref('active');
const editWarningsKeep = reactive({});
const editSaving = ref(false);
const editError = ref('');

const permissions = computed(() => page.props.auth?.permissions ?? []);
const canRead = computed(() => permissions.value.includes('dispositivos_sesiones.read'));
const canModerate = computed(() => permissions.value.includes('moderacion_cuentas.create'));
const canUpdateModeration = computed(() => permissions.value.includes('moderacion_cuentas.update'));

const parishOptions = computed(() => [...new Set(props.sessions.map((session) => session.parish).filter(Boolean))].sort());
const roleOptions = computed(() => [...new Set(props.sessions.map((session) => session.role).filter(Boolean))].sort());

const refreshSessions = () => router.reload({ only: ['sessions', 'summary'], preserveScroll: true, showProgress: false });

const { start: startSessionPolling, stop: stopSessionPolling } = usePoll(
  5000,
  { only: ['sessions', 'summary'], preserveScroll: true, showProgress: false },
  { mode: 'rest' },
);

// Auto-refreshing mid-edit would discard what the moderator is typing.
watch([editingRowId, warningUser], ([editing, warning]) => {
  if (editing !== null || warning !== null) {
    stopSessionPolling();
    return;
  }

  startSessionPolling();
});

const filteredSessions = computed(() => {
  const name = searchName.value.trim().toLowerCase();

  return props.sessions.filter((session) => {
    const matchesName = !name || String(session.user ?? '').toLowerCase().includes(name) || String(session.email ?? '').toLowerCase().includes(name);
    const matchesParish = !searchParish.value || session.parish === searchParish.value;
    const matchesRole = !searchRole.value || session.role === searchRole.value;

    return matchesName && matchesParish && matchesRole;
  });
});

const getAccountStatusLabel = (status) => ({ active: 'Activa', suspended: 'Suspendida', blocked: 'Bloqueada' })[status] ?? 'Activa';
const getAccountStatusClass = (status) => ({ active: 'badge-success', suspended: 'badge-warning', blocked: 'badge-error' })[status] ?? 'badge-success';
const getModerationLabel = (count) => count >= 3 ? `Reincidente (${count})` : count > 0 ? `Advertido (${count})` : 'Sin advertencias';
const getModerationBadgeClass = (count) => count >= 3 ? 'badge-error' : count > 0 ? 'badge-warning' : 'badge-info text-black';
const getWarningLevelLabel = (level) => ({ leve: 'Primera advertencia', moderada: 'Segunda advertencia', grave: 'Advertencia final' })[level] ?? level;
const getWarningBadgeClass = (level) => ({ leve: 'border border-amber-400 bg-amber-100 text-amber-800', moderada: 'badge-warning', grave: 'badge-error' })[level] ?? 'badge-ghost';
const getWarningIconClass = (level) => ({ leve: 'text-amber-700', moderada: 'text-white', grave: 'text-white' })[level] ?? 'text-slate-500';

const toggleWarningKeep = (warningId) => {
  editWarningsKeep[warningId] = !editWarningsKeep[warningId];
};

const openWarningModal = (session) => {
  warningUser.value = session;
  warningLevel.value = 'leve';
  warningReason.value = 'Uso inadecuado detectado';
  warningMessage.value = 'Se detectó un uso inadecuado del sistema. Debes detener esta conducta de inmediato.';
  warningError.value = '';
};

const sendWarning = async () => {
  if (!warningUser.value || !canModerate.value) return;
  warningSending.value = true;
  warningError.value = '';
  const response = await fetch(`/dispositivos-sesiones/usuario/${warningUser.value.user_id}/advertencia`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ level: warningLevel.value, reason: warningReason.value, message: warningMessage.value }),
  });
  if (!response.ok) {
    warningError.value = 'No se pudo enviar la advertencia. Verifica tus permisos e inténtalo nuevamente.';
    warningSending.value = false;
    return;
  }
  warningUser.value = null;
  warningSending.value = false;
  refreshSessions();
};

const startInlineEdit = (session) => {
  editingRowId.value = session.id;
  editAccountStatus.value = session.account_status;
  editError.value = '';

  Object.keys(editWarningsKeep).forEach((key) => delete editWarningsKeep[key]);
  (session.warnings ?? []).forEach((warning) => {
    editWarningsKeep[warning.id] = true;
  });
};

const cancelInlineEdit = () => {
  editingRowId.value = null;
  editError.value = '';
};

const saveInlineEdit = async (session) => {
  if (!canUpdateModeration.value) return;

  editSaving.value = true;
  editError.value = '';

  if (editAccountStatus.value !== session.account_status) {
    const response = await fetch(`/dispositivos-sesiones/usuario/${session.user_id}/estado`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ account_status: editAccountStatus.value }),
    });

    if (!response.ok) {
      editError.value = 'No se pudo actualizar el estado de la cuenta.';
      editSaving.value = false;
      return;
    }
  }

  const warningsToRemove = (session.warnings ?? []).filter((warning) => !editWarningsKeep[warning.id]);

  for (const warning of warningsToRemove) {
    const response = await fetch(`/dispositivos-sesiones/usuario/${session.user_id}/advertencia/${warning.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (!response.ok) {
      editError.value = 'No se pudieron quitar todas las advertencias seleccionadas.';
      editSaving.value = false;
      return;
    }
  }

  editSaving.value = false;
  editingRowId.value = null;
  refreshSessions();
};
</script>

<template>
  <AppShell :page-title="'Dispositivos y sesiones'">
    <div class="space-y-5">
      <CatalogHeader
        title="Dispositivos y sesiones"
        subtitle="Consulta y administra las sesiones activas de los usuarios"
        back-href="/"
        :count="filteredSessions.length"
        :icon="ShieldCheck"
      />

      <div v-if="canRead" class="space-y-4">
        <div class="flex flex-col gap-3 border-b border-slate-200/80 pb-4 dark:border-slate-700 sm:flex-row sm:flex-wrap">
          <label class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-white/70 px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900/70 sm:min-w-[220px]">
            <Search class="h-4 w-4 text-slate-400" />
            <input v-model="searchName" type="text" placeholder="Buscar por nombre completo..." class="w-full bg-transparent text-sm outline-none placeholder:text-slate-400 dark:text-slate-200" />
            <button v-if="searchName" type="button" @click="searchName = ''" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
              <X class="h-3.5 w-3.5" />
            </button>
          </label>
          <label class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-white/70 px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900/70 sm:min-w-[220px]">
            <Search class="h-4 w-4 text-slate-400" />
            <select v-model="searchParish" class="w-full bg-transparent text-sm outline-none dark:text-slate-200">
              <option value="">Todas las parroquias</option>
              <option v-for="parish in parishOptions" :key="parish" :value="parish">{{ parish }}</option>
            </select>
          </label>
          <label class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-white/70 px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900/70 sm:min-w-[220px]">
            <Search class="h-4 w-4 text-slate-400" />
            <select v-model="searchRole" class="w-full bg-transparent text-sm outline-none dark:text-slate-200">
              <option value="">Todos los roles</option>
              <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
            </select>
          </label>
        </div>

        <div class="overflow-x-auto overscroll-x-contain rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <table class="table w-full min-w-[980px] table-fixed text-xs">
              <colgroup>
                <col class="w-[20%]" />
                <col class="w-[11%]" />
                <col class="w-[10%]" />
                <col class="w-[12%]" />
                <col class="w-[11%]" />
                <col class="w-[10%]" />
                <col class="w-[14%]" />
                <col class="w-[12%]" />
              </colgroup>
              <thead>
                <tr class="whitespace-nowrap border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
                  <th class="px-2 py-3 font-semibold sm:px-3">Usuario</th>
                  <th class="px-2 py-3 font-semibold sm:px-3">Parroquia</th>
                  <th class="px-2 py-3 font-semibold sm:px-3">Rol</th>
                  <th class="px-2 py-3 font-semibold sm:px-3">Dispositivos</th>
                  <th class="px-2 py-3 font-semibold sm:px-3">Sesiones</th>
                  <th class="px-2 py-3 font-semibold sm:px-3">Estatus</th>
                  <th class="min-w-[190px] px-2 py-3 font-semibold sm:px-3">Alertas</th>
                  <th class="min-w-[125px] px-2 py-3 text-right font-semibold sm:px-3">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="session in filteredSessions"
                  :key="session.id"
                  :class="[
                    'border-b align-top transition-colors',
                    editingRowId === session.id
                      ? 'border-amber-100 bg-amber-50/60 dark:border-amber-900/20 dark:bg-amber-950/10'
                      : 'border-slate-100 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40',
                  ]"
                >
                  <td class="break-words px-2 py-3 sm:px-3">
                    <div class="font-semibold text-slate-800 dark:text-slate-100">{{ session.user }}</div>
                    <div class="break-all text-[10px] text-slate-500 dark:text-slate-400">{{ session.email || 'Sin correo' }}</div>
                  </td>
                  <td class="break-words px-2 py-3 sm:px-3">{{ session.parish }}</td>
                  <td class="break-words px-2 py-3 sm:px-3">{{ session.role }}</td>
                  <td class="break-words px-2 py-3 sm:px-3">
                    <div class="font-semibold text-slate-800 dark:text-slate-100">{{ session.device_count }} {{ session.device_count === 1 ? 'dispositivo' : 'dispositivos' }}</div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400">Dispositivos detectados</div>
                  </td>
                  <td class="break-words px-2 py-3 sm:px-3">
                    <div class="font-semibold text-slate-800 dark:text-slate-100">{{ session.session_count }} {{ session.session_count === 1 ? 'sesión' : 'sesiones' }}</div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400">En dispositivos</div>
                  </td>
                  <td class="px-2 py-3 sm:px-3">
                    <select
                      v-if="editingRowId === session.id"
                      v-model="editAccountStatus"
                      class="select select-bordered select-sm w-40 max-w-full bg-white font-normal text-slate-800"
                    >
                      <option value="active">Activa</option>
                      <option value="suspended">Suspendida</option>
                      <option value="blocked">Bloqueada</option>
                    </select>
                    <span v-else class="badge badge-sm font-medium" :class="getAccountStatusClass(session.account_status)">{{ getAccountStatusLabel(session.account_status) }}</span>
                    <div v-if="!editingRowId && session.account_status === 'suspended' && session.suspended_until" class="text-[10px] font-normal text-slate-500 dark:text-slate-400">Hasta {{ session.suspended_until }}</div>
                  </td>
                  <td class="min-w-[190px] break-words px-2 py-3 text-xs font-semibold sm:px-3">
                    <div v-if="editingRowId === session.id" class="flex flex-wrap gap-1.5">
                      <span
                        v-for="warning in session.warnings"
                        :key="warning.id"
                        class="badge w-max max-w-none shrink-0 gap-1 whitespace-nowrap font-normal"
                        :class="[getWarningBadgeClass(warning.level), editWarningsKeep[warning.id] ? '' : 'opacity-40 line-through']"
                        :title="warning.message"
                      >
                        <TriangleAlert class="h-3 w-3" :class="getWarningIconClass(warning.level)" />
                        {{ getWarningLevelLabel(warning.level) }}
                        <button type="button" class="ml-0.5 shrink-0" :title="editWarningsKeep[warning.id] ? 'Quitar advertencia' : 'Deshacer'" @click="toggleWarningKeep(warning.id)">
                          <X class="h-3 w-3" />
                        </button>
                      </span>
                      <p v-if="!session.warnings?.length" class="font-normal text-slate-500 dark:text-slate-400">Sin advertencias</p>
                    </div>
                    <span v-else class="badge badge-sm gap-1 font-medium" :class="getModerationBadgeClass(session.warning_count)">
                      <TriangleAlert v-if="session.warning_count > 0" class="h-3 w-3 text-white" />
                      {{ getModerationLabel(session.warning_count) }}
                    </span>
                  </td>
                  <td class="min-w-[125px] whitespace-nowrap px-2 py-3 text-right sm:px-3">
                    <div v-if="editingRowId === session.id" class="inline-flex flex-col items-end gap-1">
                      <div class="inline-flex items-center gap-1">
                        <button type="button" class="btn btn-success btn-xs btn-square" title="Guardar cambios" aria-label="Guardar cambios" :disabled="editSaving" @click="saveInlineEdit(session)">
                          <Check class="h-4 w-4" />
                        </button>
                        <button type="button" class="btn btn-ghost btn-xs btn-square" title="Cancelar" aria-label="Cancelar" :disabled="editSaving" @click="cancelInlineEdit">
                          <X class="h-4 w-4" />
                        </button>
                      </div>
                      <p v-if="editError" class="max-w-[160px] text-right text-[10px] font-normal text-red-600">{{ editError }}</p>
                    </div>
                    <div v-else class="inline-flex items-center gap-1">
                      <button v-if="canModerate" type="button" class="btn btn-ghost btn-xs btn-square text-amber-600 hover:bg-amber-50 hover:text-amber-700 dark:text-amber-400 dark:hover:bg-amber-950/40" title="Enviar advertencia" aria-label="Enviar advertencia" @click="openWarningModal(session)">
                        <Bell class="h-4 w-4" />
                      </button>
                      <button v-if="canUpdateModeration" type="button" class="btn btn-ghost btn-xs btn-square text-sky-600 hover:bg-sky-50 hover:text-sky-700 dark:text-sky-400 dark:hover:bg-sky-950/40" title="Editar estado y moderación" aria-label="Editar estado y moderación" @click="startInlineEdit(session)">
                        <Pencil class="h-4 w-4" />
                      </button>
                      <Link :href="`/dispositivos-sesiones/usuario/${session.user_id}`" class="btn btn-ghost btn-xs btn-square" title="Ver detalles de dispositivos" aria-label="Ver detalles de dispositivos">
                        <Eye class="h-4 w-4" />
                      </Link>
                    </div>
                  </td>
                </tr>
                <tr v-if="filteredSessions.length === 0">
                  <td colspan="8" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">No se encontraron sesiones.</td>
                </tr>
              </tbody>
          </table>
        </div>
      </div>

      <div v-else class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
        No tienes permisos para consultar dispositivos y sesiones.
      </div>
    </div>

    <dialog v-if="warningUser" class="modal modal-open px-3 sm:px-0">
      <div class="modal-box max-h-[90vh] w-full max-w-2xl overflow-y-auto px-4 sm:px-6">
        <button type="button" class="btn btn-sm btn-circle btn-ghost absolute right-4 top-4" aria-label="Cerrar modal" @click="warningUser = null">✕</button>
        <div class="flex items-start gap-3 pr-8">
          <Bell class="mt-1 h-5 w-5 shrink-0 text-amber-500" />
          <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Enviar advertencia</h2>
        </div>
        <div class="mt-5 space-y-3">
          <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Nivel de advertencia
            <select v-model="warningLevel" class="select select-bordered mt-1 w-full">
              <option value="leve">Primera advertencia</option>
              <option value="moderada">Segunda advertencia</option>
              <option value="grave">Advertencia final</option>
            </select>
          </label>
          <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Motivo
            <input v-model="warningReason" type="text" class="input input-bordered mt-1 w-full" maxlength="255" />
          </label>
          <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Mensaje
            <textarea v-model="warningMessage" rows="5" maxlength="5000" class="textarea textarea-bordered mt-1 w-full"></textarea>
          </label>
          <p v-if="warningError" class="text-sm text-red-600">{{ warningError }}</p>
        </div>
        <div class="modal-action flex w-full flex-col-reverse items-stretch gap-2 sm:flex-row sm:items-center sm:justify-between">
          <button
            type="button"
            class="btn w-full gap-1.5 rounded-xl border-0 bg-yellow-400 text-slate-900 shadow-md shadow-yellow-900/20 transition-all hover:-translate-y-0.5 hover:bg-yellow-500 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
            @click="warningUser = null"
          >
            <X class="h-4 w-4" />
            Cancelar
          </button>
          <button
            type="button"
            class="btn w-full gap-1.5 rounded-xl border-0 bg-orange-500 text-white shadow-md shadow-orange-900/20 transition-all hover:-translate-y-0.5 hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
            :disabled="warningSending || !warningMessage.trim()"
            @click="sendWarning"
          >
            <Bell class="h-4 w-4" />
            {{ warningSending ? 'Enviando...' : 'Enviar advertencia' }}
          </button>
        </div>
      </div>
    </dialog>

  </AppShell>
</template>
