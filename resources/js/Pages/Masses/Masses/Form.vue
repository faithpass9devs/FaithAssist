<script setup>
import { computed, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Church } from 'lucide-vue-next';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  mass: { type: Object, default: null },
  weekends: { type: Array, default: () => [] },
  churches: { type: Array, default: () => [] },
  chapels: { type: Array, default: () => [] },
});

const isEditing = computed(() => !!props.mass);
const pageTitle = computed(() => (isEditing.value ? 'Editar misa' : 'Nueva misa'));

const form = useForm({
  weekend_id: props.mass?.weekend_id ?? props.weekends[0]?.id ?? '',
  church_id: props.mass?.church_id ?? props.weekends[0]?.church_id ?? props.churches[0]?.id ?? '',
  chapel_id: props.mass?.chapel_id ?? '',
  name: props.mass?.name ?? '',
  starts_at: props.mass?.starts_at ?? '',
  ends_at: props.mass?.ends_at ?? '',
  status: props.mass?.status ?? 'upcoming',
  attendance_status: props.mass?.attendance_status ?? 'upcoming',
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
    <CatalogHeader
      :title="pageTitle"
      subtitle="Captura el inicio y fin de la misa dentro del fin de semana seleccionado"
      back-href="/misas"
      :icon="Church"
    />

    <form class="space-y-6" @submit.prevent="submit">
      <section
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
      >
        <div class="grid gap-4 md:grid-cols-2">
          <label>
            <span class="mb-1.5 block text-sm font-medium"
              >Fin de semana <span class="text-red-500">*</span></span
            >
            <select
              v-model="form.weekend_id"
              class="select select-bordered w-full"
              :class="{ 'select-error': form.errors.weekend_id }"
            >
              <option value="" disabled>Selecciona un fin de semana</option>
              <option v-for="weekend in weekends" :key="weekend.id" :value="weekend.id">
                {{ weekend.name }} · {{ weekend.church }}
              </option>
            </select>
            <p v-if="form.errors.weekend_id" class="mt-1 text-xs text-red-500">
              {{ form.errors.weekend_id }}
            </p>
          </label>

          <label>
            <span class="mb-1.5 block text-sm font-medium"
              >Parroquia <span class="text-red-500">*</span></span
            >
            <select
              v-model="form.church_id"
              class="select select-bordered w-full"
              :class="{ 'select-error': form.errors.church_id }"
            >
              <option value="" disabled>Selecciona una parroquia</option>
              <option v-for="church in churches" :key="church.id" :value="church.id">
                {{ church.name }}
              </option>
            </select>
            <p v-if="form.errors.church_id" class="mt-1 text-xs text-red-500">
              {{ form.errors.church_id }}
            </p>
          </label>

          <label>
            <span class="mb-1.5 block text-sm font-medium">Capilla</span>
            <select
              v-model="form.chapel_id"
              class="select select-bordered w-full"
              :class="{ 'select-error': form.errors.chapel_id }"
            >
              <option value="">En parroquia</option>
              <option v-for="chapel in filteredChapels" :key="chapel.id" :value="chapel.id">
                {{ chapel.name }}
              </option>
            </select>
            <p v-if="form.errors.chapel_id" class="mt-1 text-xs text-red-500">
              {{ form.errors.chapel_id }}
            </p>
          </label>

          <label>
            <span class="mb-1.5 block text-sm font-medium"
              >Nombre <span class="text-red-500">*</span></span
            >
            <input
              v-model="form.name"
              type="text"
              class="input input-bordered w-full"
              :class="{ 'input-error': form.errors.name }"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
          </label>

          <label>
            <span class="mb-1.5 block text-sm font-medium"
              >Inicia <span class="text-red-500">*</span></span
            >
            <input
              v-model="form.starts_at"
              type="datetime-local"
              class="input input-bordered w-full"
              :class="{ 'input-error': form.errors.starts_at }"
            />
            <p v-if="form.errors.starts_at" class="mt-1 text-xs text-red-500">
              {{ form.errors.starts_at }}
            </p>
          </label>

          <label>
            <span class="mb-1.5 block text-sm font-medium"
              >Termina <span class="text-red-500">*</span></span
            >
            <input
              v-model="form.ends_at"
              type="datetime-local"
              class="input input-bordered w-full"
              :class="{ 'input-error': form.errors.ends_at }"
            />
            <p v-if="form.errors.ends_at" class="mt-1 text-xs text-red-500">
              {{ form.errors.ends_at }}
            </p>
          </label>

          <label>
            <span class="mb-1.5 block text-sm font-medium">Estatus</span>
            <select
              v-model="form.status"
              class="select select-bordered w-full"
              :class="{ 'select-error': form.errors.status }"
            >
              <option value="upcoming">Próxima</option>
              <option value="in_progress">En curso</option>
              <option value="completed">Terminada</option>
            </select>
            <p v-if="form.errors.status" class="mt-1 text-xs text-red-500">
              {{ form.errors.status }}
            </p>
          </label>

          <label>
            <span class="mb-1.5 block text-sm font-medium">Captura de asistencia</span>
            <select
              v-model="form.attendance_status"
              class="select select-bordered w-full"
              :class="{ 'select-error': form.errors.attendance_status }"
            >
              <option value="upcoming">Próxima</option>
              <option value="in_progress">En curso</option>
              <option value="completed">Terminada</option>
            </select>
            <p v-if="form.errors.attendance_status" class="mt-1 text-xs text-red-500">
              {{ form.errors.attendance_status }}
            </p>
          </label>

          <label class="md:col-span-2">
            <span class="mb-1.5 block text-sm font-medium">Notas</span>
            <textarea
              v-model="form.notes"
              class="textarea textarea-bordered min-h-28 w-full"
              :class="{ 'textarea-error': form.errors.notes }"
            />
            <p v-if="form.errors.notes" class="mt-1 text-xs text-red-500">
              {{ form.errors.notes }}
            </p>
          </label>
        </div>
      </section>

      <div class="flex justify-end gap-3">
        <Link href="/misas" class="btn btn-ghost btn-sm">Cancelar</Link>
        <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
          {{ form.processing ? 'Guardando...' : isEditing ? 'Actualizar' : 'Crear' }}
        </button>
      </div>
    </form>
  </AppShell>
</template>
