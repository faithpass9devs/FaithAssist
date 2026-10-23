<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Church, Pencil, Plus, QrCode, Trash2 } from 'lucide-vue-next';
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
const permissions = computed(() => page.props.auth?.permissions ?? []);
const canCreate = computed(() => permissions.value.includes('masses.create'));
const canUpdate = computed(() => permissions.value.includes('masses.update'));
const canDelete = computed(() => permissions.value.includes('masses.delete'));
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
    ? 'badge-warning'
    : status === 'completed'
      ? 'badge-success'
      : 'badge-info';

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
      class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <table class="table w-full">
        <thead>
          <tr
            class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:border-slate-800 dark:bg-slate-950"
          >
            <th>Misa</th>
            <th>Ubicación</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Captura</th>
            <th class="text-right">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="mass in masses.data" :key="mass.id">
            <td class="font-semibold">{{ mass.name }}</td>
            <td>{{ mass.location }}</td>
            <td>{{ mass.starts_at }}</td>
            <td>{{ mass.ends_at || 'Pendiente' }}</td>
            <td>
              <div class="flex flex-col items-start gap-1">
                <span class="badge badge-sm" :class="statusClass(mass.attendance_check_in_status)">
                  E: {{ mass.attendance_check_in_status }}
                </span>
                <span class="badge badge-sm" :class="statusClass(mass.attendance_check_out_status)">
                  S: {{ mass.attendance_check_out_status }}
                </span>
              </div>
            </td>
            <td class="whitespace-nowrap text-right">
              <div class="inline-flex items-center justify-end gap-1">
                <Link
                  v-if="canOpenAttendance"
                  :href="`/misas/${mass.id}/asistencias`"
                  class="btn btn-ghost btn-xs text-purple-600"
                  title="Asistencias"
                >
                  <QrCode class="h-3.5 w-3.5" />
                </Link>
                <Link
                  v-if="canUpdate"
                  :href="`/misas/${mass.id}/edit`"
                  class="btn btn-ghost btn-xs text-sky-600"
                  title="Editar"
                >
                  <Pencil class="h-3.5 w-3.5" />
                </Link>
                <button
                  v-if="canDelete"
                  type="button"
                  class="btn btn-ghost btn-xs text-red-600"
                  title="Eliminar"
                  @click="destroyMass(mass)"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="masses.data.length === 0">
            <td colspan="6" class="py-10 text-center text-sm text-slate-400">
              No hay misas registradas en este fin de semana.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      :links="masses.links"
      :from="masses.from"
      :to="masses.to"
      :total="masses.total"
    />
  </AppShell>
</template>
