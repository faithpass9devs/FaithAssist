<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import AppShell from '../../../components/layouts/AppShell.vue';

const props = defineProps({
  weekend: { type: Object, default: null },
  churches: { type: Array, default: () => [] },
});

const isEditing = computed(() => !!props.weekend);
const pageTitle = computed(() =>
  isEditing.value ? 'Editar fin de semana de misas' : 'Nuevo fin de semana de misas',
);

const form = useForm({
  church_id: props.weekend?.church_id ?? props.churches[0]?.id ?? '',
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
    <CatalogHeader
      :title="pageTitle"
      subtitle="El rango se guarda de sábado 00:00 a domingo 23:59"
      back-href="/fines-semana-misas"
      :icon="CalendarDays"
    />

    <form class="space-y-6" @submit.prevent="submit">
      <section
        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
      >
        <div class="grid gap-4 md:grid-cols-2">
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
            <span class="mb-1.5 block text-sm font-medium">Nombre</span>
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
              >Sábado <span class="text-red-500">*</span></span
            >
            <input
              v-model="form.starts_at"
              type="date"
              class="input input-bordered w-full"
              :class="{ 'input-error': form.errors.starts_at }"
            />
            <p v-if="form.errors.starts_at" class="mt-1 text-xs text-red-500">
              {{ form.errors.starts_at }}
            </p>
          </label>

          <label>
            <span class="mb-1.5 block text-sm font-medium">Domingo calculado</span>
            <input
              :value="computedEndsAt"
              type="date"
              class="input input-bordered w-full"
              disabled
            />
            <p v-if="form.errors.ends_at" class="mt-1 text-xs text-red-500">
              {{ form.errors.ends_at }}
            </p>
          </label>

          <label>
            <span class="mb-1.5 block text-sm font-medium"
              >Estatus <span class="text-red-500">*</span></span
            >
            <select
              v-model="form.status"
              class="select select-bordered w-full"
              :class="{ 'select-error': form.errors.status }"
            >
              <option value="upcoming">Próximo</option>
              <option value="in_progress">En curso</option>
              <option value="completed">Terminado</option>
            </select>
            <p v-if="form.errors.status" class="mt-1 text-xs text-red-500">
              {{ form.errors.status }}
            </p>
          </label>
        </div>
      </section>

      <div class="flex justify-end gap-3">
        <Link href="/fines-semana-misas" class="btn btn-ghost btn-sm">Cancelar</Link>
        <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">
          {{ form.processing ? 'Guardando...' : isEditing ? 'Actualizar' : 'Crear' }}
        </button>
      </div>
    </form>
  </AppShell>
</template>
