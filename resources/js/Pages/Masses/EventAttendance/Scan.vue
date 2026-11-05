<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Home, Play, QrCode, Square } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import AppPagination from '../../../components/AppPagination.vue';
import AppShell from '../../../components/layouts/AppShell.vue';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';

const props = defineProps({
  event: { type: Object, required: true },
  attendances: { type: Object, required: true },
  canScan: { type: Boolean, default: false },
  canManage: { type: Boolean, default: false },
});

const rows = ref([]);
watch(
  () => props.attendances.data,
  (data) => {
    rows.value = [...data];
  },
  { immediate: true },
);

const attendeeCode = ref('');
const action = ref('check_in');
const loading = ref(false);

const checkInInProgress = computed(() => props.event.attendance_check_in_status === 'in_progress');
const checkOutInProgress = computed(() => props.event.attendance_check_out_status === 'in_progress');

const scan = async () => {
  if (!attendeeCode.value.trim()) return;

  loading.value = true;

  try {
    const response = await fetch(`/eventos/${props.event.id}/asistencias/scan`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
      },
      body: JSON.stringify({ attendee_code: attendeeCode.value.trim(), action: action.value }),
    });

    const payload = await response.json();

    if (!response.ok) {
      const first = Object.values(payload.errors ?? {})[0]?.[0] ?? 'No se pudo registrar la asistencia.';
      throw new Error(first);
    }

    attendeeCode.value = '';
    rows.value = [payload.data, ...rows.value.filter((item) => item.id !== payload.data.id)];

    Swal.fire({
      toast: true,
      position: 'top-end',
      timer: 1800,
      showConfirmButton: false,
      icon: 'success',
      title: payload.message,
    });
  } catch (error) {
    Swal.fire({ icon: 'error', title: 'Error', text: error.message ?? 'Inténtalo nuevamente.' });
  } finally {
    loading.value = false;
  }
};

const setCaptureStatus = async (capture, status) => {
  try {
    const response = await fetch(`/eventos/${props.event.id}/asistencias/status`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
      },
      body: JSON.stringify({ capture, status }),
    });

    const payload = await response.json();

    if (!response.ok) {
      const first = Object.values(payload.errors ?? {})[0]?.[0] ?? 'No se pudo actualizar el estado.';
      throw new Error(first);
    }

    router.reload({ only: ['event'] });
  } catch (error) {
    Swal.fire({ icon: 'error', title: 'Error', text: error.message ?? 'Inténtalo nuevamente.' });
  }
};
</script>

<template>
  <AppShell :page-title="'Asistencia de evento'">
    <CatalogHeader
      title="Asistencia de evento"
      :subtitle="`${event.name} · ${event.location ?? event.church ?? 'Sin ubicación'}`"
      back-href="/eventos"
      :icon="QrCode"
    >
      <template #actions>
        <Link
          href="/eventos"
          class="btn btn-sm rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-400 hover:bg-slate-50"
        >
          <Home class="mr-1 h-4 w-4" /> Eventos
        </Link>
      </template>
    </CatalogHeader>

    <div class="mb-4 grid gap-3 md:grid-cols-2">
      <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Entrada</p>
        <p class="mt-1 text-sm font-semibold" :class="checkInInProgress ? 'text-emerald-700' : 'text-slate-600'">
          {{ event.attendance_check_in_status }}
        </p>
        <div v-if="canManage" class="mt-3 flex gap-2">
          <button
            type="button"
            class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700"
            @click="setCaptureStatus('check_in', 'in_progress')"
          >
            <Play class="mr-1 inline h-3.5 w-3.5" /> Reabrir
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700"
            @click="setCaptureStatus('check_in', 'completed')"
          >
            <Square class="mr-1 inline h-3.5 w-3.5" /> Cerrar
          </button>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Salida</p>
        <p class="mt-1 text-sm font-semibold" :class="checkOutInProgress ? 'text-emerald-700' : 'text-slate-600'">
          {{ event.attendance_check_out_status }}
        </p>
        <div v-if="canManage" class="mt-3 flex gap-2">
          <button
            type="button"
            class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700"
            @click="setCaptureStatus('check_out', 'in_progress')"
          >
            <Play class="mr-1 inline h-3.5 w-3.5" /> Reabrir
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700"
            @click="setCaptureStatus('check_out', 'completed')"
          >
            <Square class="mr-1 inline h-3.5 w-3.5" /> Cerrar
          </button>
        </div>
      </div>
    </div>

    <div class="mb-5 rounded-2xl border border-slate-200 bg-white p-4">
      <div class="grid gap-3 md:grid-cols-3">
        <input
          v-model="attendeeCode"
          type="text"
          class="rounded-xl border border-slate-300 px-3 py-2 text-sm"
          placeholder="Escanea o escribe código"
          @keyup.enter="scan"
          :disabled="!canScan || loading"
        />
        <select v-model="action" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" :disabled="!canScan || loading">
          <option value="check_in">Entrada</option>
          <option value="check_out">Salida</option>
        </select>
        <button
          type="button"
          class="rounded-xl bg-sky-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60"
          :disabled="!canScan || loading"
          @click="scan"
        >
          {{ loading ? 'Procesando...' : 'Registrar' }}
        </button>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Código</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Asistente</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Tipo</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Entrada</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Salida</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Completa</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in rows" :key="row.id">
            <td class="px-4 py-3 text-sm text-slate-700">{{ row.attendee_code }}</td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ row.attendee_name }}</td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ row.attendee_kind }}</td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ row.check_in_at || '—' }}</td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ row.check_out_at || '—' }}</td>
            <td class="px-4 py-3 text-sm font-semibold" :class="row.valid ? 'text-emerald-700' : 'text-amber-700'">
              {{ row.valid ? 'Sí' : 'No' }}
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">Sin asistencias registradas.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination class="mt-4" :pagination="attendances" />
  </AppShell>
</template>
