<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import UnderlineField from '../../../components/forms/UnderlineField.vue';
import UnderlineSection from '../../../components/forms/UnderlineSection.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  weekend: { type: Object, default: null },
  churches: { type: Array, default: () => [] },
});

const isEditing = computed(() => !!props.weekend);
const pageTitle = computed(() =>
  isEditing.value ? 'Editar fin de semana de misas' : 'Nuevo fin de semana de misas',
);
const churchOptions = computed(() =>
  props.churches.map((church) => ({
    value: church.id,
    label: church.name,
  })),
);
const statusOptions = [
  { value: 'upcoming', label: 'Próximo' },
  { value: 'in_progress', label: 'En curso' },
  { value: 'completed', label: 'Terminado' },
];

const form = useForm({
  church_id: props.weekend?.church_id ?? null,
  name: props.weekend?.name ?? '',
  starts_at: props.weekend?.starts_at ?? '',
  ends_at: props.weekend?.ends_at ?? '',
  status: props.weekend?.status ?? 'upcoming',
});

const computedEndsAt = computed(() => {
  if (!form.starts_at) return '';
  const [year, month, day] = form.starts_at.split('-').map(Number);
  if (!year || !month || !day) return '';
  const date = new Date(year, month - 1, day);
  date.setDate(date.getDate() + 1);
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-');
});

const submit = () => {
  form.ends_at = computedEndsAt.value;

  if (isEditing.value) {
    form.put(`/fines-semana-misas/${props.weekend.id}`, { preserveScroll: true });
  } else {
    form.post('/fines-semana-misas');
  }
};
</script>

<template>
  <AppShell :page-title="pageTitle">
    <form @submit.prevent="submit">
    <CatalogHeader
      :title="pageTitle"
      subtitle="El rango se guarda de sábado 00:00 a domingo 23:59"
      back-href="/fines-semana-misas"
      :icon="CalendarDays"
    >
      <template #actions>
        <Link
          href="/fines-semana-misas"
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
          <UnderlineSection title="Fin de semana">
            <div class="grid gap-x-9 gap-y-7 md:grid-cols-2">
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
                v-model="form.name"
                label="Nombre"
                :error="form.errors.name"
              />

              <UnderlineField
                v-model="form.starts_at"
                label="Sábado"
                type="date"
                :error="form.errors.starts_at"
                required
              />

              <UnderlineField
                :model-value="computedEndsAt"
                label="Domingo calculado"
                type="date"
                :error="form.errors.ends_at"
                disabled
              />

              <UnderlineField
                v-model="form.status"
                label="Estatus"
                as="select"
                :options="statusOptions"
                :error="form.errors.status"
                required
              />
            </div>
          </UnderlineSection>
        </div>
      </div>
    </form>
  </AppShell>
</template>
