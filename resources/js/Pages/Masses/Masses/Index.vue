<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Church, Clock, MapPin, Pencil, Plus, QrCode, Trash2, Users } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import AppPagination from '../../../components/AppPagination.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';
import { useFlashAlerts } from '../../../composables/useFlashAlerts';

useFlashAlerts();

const props = defineProps({
  weekend: { type: Object, required: true },
  masses: { type: Object, required: true },
});

const page = usePage();
const auth = computed(() => page.props.auth ?? {});
const user = computed(() => auth.value.user ?? null);
const permissions = computed(() => auth.value.permissions ?? []);
const isChapelUser = computed(() => user.value?.chapel?.id || page.props.auth?.scope?.chapel_id);
const canCreate = computed(() => permissions.value.includes('masses.create') && !isChapelUser.value);
const canUpdate = computed(() => permissions.value.includes('masses.update'));
const canDelete = computed(() => permissions.value.includes('masses.delete') && !isChapelUser.value);
const canOpenAttendance = computed(
  () =>
    permissions.value.includes('mass_attendance.read') ||
    permissions.value.includes('mass_attendance.scan'),
);

const statusLabels = {
  upcoming: 'Próximo',
  in_progress: 'En curso',
  completed: 'Concluido',
};

const statusClass = (status) =>
  status === 'in_progress'
    ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300'
    : status === 'completed'
      ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300'
      : 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-300';

const attendanceStatusClass = (status) => {
  if (status === 'completed' || status === 'closed') {
    return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300';
  }
  if (status === 'in_progress' || status === 'open') {
    return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300';
  }
  return 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-300';
};

const attendanceLabel = (status) => {
  if (status === 'completed' || status === 'closed') return 'Cerrado';
  if (status === 'in_progress' || status === 'open') return 'Abierto';
  return status || 'Sin iniciar';
};

const weekendTabs = { in_progress: 'current', upcoming: 'upcoming', completed: 'history' };
const weekendsHref = computed(() => {
  const tab = weekendTabs[props.weekend.status] ?? 'current';

  return tab === 'current' ? '/fines-semana-misas' : `/fines-semana-misas?tab=${tab}`;
});

const destroyMass = async (mass) => {
  const result = await Swal.fire({
    icon: 'warning',
    title: '¿Eliminar misa?',
    text: `Se eliminará la misa ${mass.name}.`,
    showCancelButton: true,
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#dc2626',
  });

  if (!result.isConfirmed) return;

  router.delete(`/misas/${mass.id}`, { preserveScroll: true });
};
</script>

<template>
  <AppShell :page-title="'Misas'">
    <CatalogHeader
      :title="`Misas · ${weekend.name}`"
      :subtitle="`${weekend.church} · ${weekend.starts_at} al ${weekend.ends_at}`"
      :back-href="weekendsHref"
      :count="masses.total"
      :icon="Church"
    >
      <template #actions>
        <span class="badge badge-sm shrink-0" :class="statusClass(weekend.status)">
          Fin de semana {{ statusLabels[weekend.status] ?? weekend.status }}
        </span>
        <Link
          v-if="canCreate"
          :href="`/misas/create?weekend_id=${weekend.id}`"
          class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800"
        >
          <Plus class="h-4 w-4" />
          Nueva misa
        </Link>
      </template>
    </CatalogHeader>

    <div
      class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <div class="overflow-x-auto">
        <table class="table w-full">
          <thead>
            <tr
              class="border-b border-slate-200 bg-slate-50/80 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400"
            >
              <th class="px-6 py-3">Misa</th>
              <th class="px-6 py-3">Ubicación</th>
              <th class="px-6 py-3">Horario</th>
              <th class="px-6 py-3">Captura de asistencia</th>
              <th class="px-6 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="mass in masses.data"
              :key="mass.id"
              class="border-b border-slate-100 transition-colors hover:bg-slate-50/80 dark:border-slate-800 dark:hover:bg-slate-800/40"
            >
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300"
                  >
                    <Church class="h-4 w-4" />
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                      {{ mass.name }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                      {{ mass.weekend || weekend.name }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                  <MapPin class="h-4 w-4 text-slate-400" />
                  <span>{{ mass.location || weekend.church }}</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                  <Clock class="h-4 w-4 text-slate-400" />
                  <span>{{ mass.starts_at }}<span v-if="mass.ends_at"> — {{ mass.ends_at }}</span></span>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-wrap items-center gap-2">
                  <span
                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
                    :class="attendanceStatusClass(mass.attendance_check_in_status)"
                  >
                    <Users class="h-3.5 w-3.5" />
                    Entrada: {{ attendanceLabel(mass.attendance_check_in_status) }}
                  </span>
                  <span
                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
                    :class="attendanceStatusClass(mass.attendance_check_out_status)"
                  >
                    <Users class="h-3.5 w-3.5" />
                    Salida: {{ attendanceLabel(mass.attendance_check_out_status) }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 text-right">
                <div class="inline-flex items-center justify-end gap-1">
                  <Link
                    v-if="canOpenAttendance"
                    :href="`/misas/${mass.id}/asistencias`"
                    class="btn btn-ghost btn-xs gap-1 text-purple-600 hover:bg-purple-50 hover:text-purple-700 dark:text-purple-300 dark:hover:bg-purple-500/10"
                    title="Asistencias"
                  >
                    <QrCode class="h-3.5 w-3.5" />
                    Asistencias
                  </Link>
                  <Link
                    v-if="canUpdate"
                    :href="`/misas/${mass.id}/edit`"
                    class="btn btn-ghost btn-xs gap-1 text-sky-600 hover:bg-sky-50 hover:text-sky-700 dark:text-sky-300 dark:hover:bg-sky-500/10"
                    title="Editar"
                  >
                    <Pencil class="h-3.5 w-3.5" />
                    Editar
                  </Link>
                  <button
                    v-if="canDelete"
                    type="button"
                    class="btn btn-ghost btn-xs gap-1 text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10"
                    title="Eliminar"
                    @click="destroyMass(mass)"
                  >
                    <Trash2 class="h-3.5 w-3.5" />
                    Eliminar
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="masses.data.length === 0">
              <td colspan="5" class="py-12 text-center">
                <div class="flex flex-col items-center gap-2 text-slate-400 dark:text-slate-500">
                  <Church class="h-8 w-8" />
                  <p class="text-sm font-medium">No hay misas registradas en este fin de semana.</p>
                  <p class="text-xs">Prueba con otro término de búsqueda o agrega una nueva misa.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <AppPagination
      :links="masses.links"
      :from="masses.from"
      :to="masses.to"
      :total="masses.total"
    />
  </AppShell>
</template>
