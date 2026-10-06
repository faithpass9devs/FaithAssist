<script setup>
import { computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Church } from 'lucide-vue-next';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import UnderlineField from '../../../components/forms/UnderlineField.vue';
import UnderlineSection from '../../../components/forms/UnderlineSection.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  mass: { type: Object, default: null },
  weekends: { type: Array, default: () => [] },
  churches: { type: Array, default: () => [] },
  chapels: { type: Array, default: () => [] },
});

const isEditing = computed(() => !!props.mass);
const pageTitle = computed(() => (isEditing.value ? 'Editar misa' : 'Nueva misa'));
const weekendOptions = computed(() =>
  props.weekends.map((weekend) => ({
    value: weekend.id,
    label: `${weekend.name} · ${weekend.church}`,
  })),
);
const churchOptions = computed(() =>
  props.churches.map((church) => ({
    value: church.id,
    label: church.name,
  })),
);
const chapelOptions = computed(() =>
  filteredChapels.value.map((chapel) => ({
    value: chapel.id,
    label: chapel.name,
  })),
);
const statusOptions = [
  { value: 'upcoming', label: 'Próxima' },
  { value: 'in_progress', label: 'En curso' },
  { value: 'completed', label: 'Terminada' },
];

const form = useForm({
  weekend_id: props.mass?.weekend_id ?? null,
  church_id: props.mass?.church_id ?? null,
  chapel_id: props.mass?.chapel_id ?? '',
  name: props.mass?.name ?? '',
  starts_at: props.mass?.starts_at ?? '',
  ends_at: props.mass?.ends_at ?? '',
  status: props.mass?.status ?? 'upcoming',
  attendance_check_in_status: props.mass?.attendance_check_in_status ?? 'upcoming',
  attendance_check_out_status: props.mass?.attendance_check_out_status ?? 'upcoming',
  notes: props.mass?.notes ?? '',
});

const selectedWeekend = computed(() =>
  props.weekends.find((weekend) => weekend.id === Number(form.weekend_id)),
);
const filteredChapels = computed(() =>
  props.chapels.filter((chapel) => chapel.church_id === Number(form.church_id)),
);

watch(
  () => form.weekend_id,
  () => {
    if (!selectedWeekend.value) return;
    form.church_id = selectedWeekend.value.church_id;
    if (!filteredChapels.value.some((chapel) => chapel.id === Number(form.chapel_id))) {
      form.chapel_id = '';
    }
  },
);

watch(
  () => form.church_id,
  () => {
    if (!filteredChapels.value.some((chapel) => chapel.id === Number(form.chapel_id))) {
      form.chapel_id = '';
    }
  },
);

const submit = () => {
  if (isEditing.value) {
    form.put(`/misas/${props.mass.id}`, { preserveScroll: true });
  } else {
    form.post('/misas');
  }
};
</script>

<template>
  <AppShell :page-title="pageTitle">
    <form @submit.prevent="submit">
      <CatalogHeader
        :title="pageTitle"
        subtitle="Captura el inicio y fin de la misa dentro del fin de semana seleccionado"
        back-href="/misas"
        :icon="Church"
      >
        <template #actions>
          <Link
            href="/misas"
            class="btn btn-sm rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:bg-slate-800"
          >
            Cancelar
          </Link>
          <button
            type="submit"
            class="btn btn-sm rounded-xl border-0 bg-sky-700 text-white shadow-md shadow-sky-900/20 transition-all hover:-translate-y-0.5 hover:bg-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="form.processing"
          >
            {{ form.processing ? 'Guardando...' : isEditing ? 'Actualizar' : 'Crear' }}
          </button>
        </template>
      </CatalogHeader>

      <div
        class="mb-6 rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/70 sm:p-8"
      >
        <div class="space-y-9">
          <UnderlineSection title="Programación de misa">
            <div class="grid gap-x-9 gap-y-7 md:grid-cols-2">
              <UnderlineField
                v-model="form.weekend_id"
                label="Fin de semana"
                as="select"
                placeholder="Selecciona un fin de semana"
                :options="weekendOptions"
                :error="form.errors.weekend_id"
                number-value
                required
              />

              <UnderlineField
                v-model="form.church_id"
                label="Parroquia"
                as="select"
                placeholder="Selecciona una parroquia"
                :options="churchOptions"
                :error="form.errors.church_id"
                number-value
                required
              />

              <UnderlineField
                v-model="form.chapel_id"
                label="Capilla"
                as="select"
                placeholder="En parroquia"
                :options="chapelOptions"
                :error="form.errors.chapel_id"
                number-value
              />

              <UnderlineField
                v-model="form.name"
                label="Nombre"
                :error="form.errors.name"
                required
              />

              <UnderlineField
                v-model="form.starts_at"
                label="Inicia"
                type="datetime-local"
                :error="form.errors.starts_at"
                required
              />

              <UnderlineField
                v-model="form.ends_at"
                label="Termina"
                type="datetime-local"
                :error="form.errors.ends_at"
                required
              />

              <UnderlineField
                v-model="form.status"
                label="Estatus"
                as="select"
                :options="statusOptions"
                :error="form.errors.status"
              />

              <UnderlineField
                v-model="form.attendance_check_in_status"
                label="Captura de entradas"
                as="select"
                :options="statusOptions"
                :error="form.errors.attendance_check_in_status"
              />

              <UnderlineField
                v-model="form.attendance_check_out_status"
                label="Captura de salidas"
                as="select"
                :options="statusOptions"
                :error="form.errors.attendance_check_out_status"
              />
            </div>
          </UnderlineSection>

          <UnderlineSection title="Notas">
            <UnderlineField
              v-model="form.notes"
              label="Observaciones"
              as="textarea"
              placeholder="Notas logísticas o pastorales"
              :error="form.errors.notes"
            />
          </UnderlineSection>
        </div>
      </div>
    </form>
  </AppShell>
</template>
