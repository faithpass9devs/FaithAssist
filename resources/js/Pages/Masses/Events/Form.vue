<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import UnderlineField from '../../../components/forms/UnderlineField.vue';
import UnderlineSection from '../../../components/forms/UnderlineSection.vue';
import AppShell from '../../../components/layouts/AppShell.vue';
import { alertError, firstError } from '../../../composables/useFlashAlerts';

const props = defineProps({
  event: { type: Object, default: null },
  churches: { type: Array, default: () => [] },
  lockedChurchId: { type: Number, default: null },
});

const isEditing = computed(() => !!props.event);
const pageTitle = computed(() => (isEditing.value ? 'Editar evento' : 'Nuevo evento'));
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
  church_id: props.event?.church_id ?? props.lockedChurchId,
  name: props.event?.name ?? '',
  starts_at: props.event?.starts_at ?? '',
  ends_at: props.event?.ends_at ?? '',
  status: props.event?.status ?? 'upcoming',
  notes: props.event?.notes ?? '',
});

const submit = () => {
  const options = {
    preserveScroll: true,
    onError: (errors) =>
      alertError(
        isEditing.value ? 'No se pudo actualizar el evento' : 'No se pudo crear el evento',
        firstError(errors, 'Revisa los campos marcados e inténtalo de nuevo.'),
      ),
  };

  if (isEditing.value) {
    form.put(`/eventos/${props.event.id}`, options);
  } else {
    form.post('/eventos', options);
  }
};
</script>

<template>
  <AppShell :page-title="pageTitle">
    <form @submit.prevent="submit">
      <CatalogHeader
        :title="pageTitle"
        subtitle="Configura la parroquia y las fechas del evento"
        back-href="/eventos"
        :icon="CalendarDays"
      >
        <template #actions>
          <Link
            href="/eventos"
            class="btn btn-sm rounded-xl border border-slate-300 bg-white text-slate-700 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-400 hover:bg-slate-50"
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
        class="mb-6 rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm sm:p-8"
      >
        <div class="space-y-9">
          <UnderlineSection title="Datos del evento">
            <div class="grid gap-x-9 gap-y-7 md:grid-cols-2">
              <UnderlineField
                v-model="form.church_id"
                label="Parroquia"
                as="select"
                placeholder="Selecciona una parroquia"
                :options="churchOptions"
                :error="form.errors.church_id"
                :disabled="lockedChurchId !== null"
                number-value
                required
              />

              <UnderlineField
                v-model="form.name"
                label="Nombre"
                :error="form.errors.name"
                required
              />

              <UnderlineField
                v-model="form.starts_at"
                label="Fecha y hora de inicio"
                type="datetime-local"
                :error="form.errors.starts_at"
                required
              />

              <UnderlineField
                v-model="form.ends_at"
                label="Fecha y hora de fin"
                type="datetime-local"
                :min="form.starts_at || undefined"
                :error="form.errors.ends_at"
                required
              />

              <UnderlineField
                v-if="isEditing"
                v-model="form.status"
                label="Estatus"
                as="select"
                :options="statusOptions"
                :error="form.errors.status"
                required
              />

              <UnderlineField
                v-model="form.notes"
                label="Notas"
                as="textarea"
                :rows="4"
                :error="form.errors.notes"
              />
            </div>
          </UnderlineSection>
        </div>
      </div>
    </form>
  </AppShell>
</template>
