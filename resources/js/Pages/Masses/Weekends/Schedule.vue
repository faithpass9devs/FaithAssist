<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, Info, Plus, Trash2 } from 'lucide-vue-next';
import CatalogHeader from '../../../components/catalogs/CatalogHeader.vue';
import UnderlineField from '../../../components/forms/UnderlineField.vue';
import AppShell from '../../../components/layouts/AppShell.vue';
import { alertError, useFlashAlerts } from '../../../composables/useFlashAlerts';

useFlashAlerts();

const props = defineProps({
  churches: { type: Array, default: () => [] },
  lockedChurchId: { type: Number, default: null },
  selectedChurchId: { type: Number, default: null },
  chapels: { type: Array, default: () => [] },
  schedules: { type: Array, default: () => [] },
  monthLabel: { type: String, default: '' },
});

const days = [
  { value: 'saturday', label: 'Sábado' },
  { value: 'sunday', label: 'Domingo' },
];

const churchOptions = computed(() =>
  props.churches.map((church) => ({ value: church.id, label: church.name })),
);

let rowKey = 0;
const toRow = (schedule = {}) => ({
  key: ++rowKey,
  id: schedule.id ?? null,
  day_of_week: schedule.day_of_week ?? 'saturday',
  name: schedule.name ?? '',
  starts_time: schedule.starts_time ?? '',
  ends_time: schedule.ends_time ?? '',
  chapel_id: schedule.chapel_id ?? null,
});

const form = useForm({
  church_id: props.selectedChurchId,
  masses: props.schedules.map(toRow),
});

const rowsFor = (day) =>
  form.masses
    .map((mass, index) => ({ mass, index }))
    .filter(({ mass }) => mass.day_of_week === day);

const addMass = (day) => {
  form.masses.push(toRow({ day_of_week: day }));
};

const removeMass = (index) => {
  form.masses.splice(index, 1);
  form.clearErrors();
};

const selectChurch = (churchId) => {
  router.get(
    '/fines-semana-misas/programar',
    { church_id: churchId || undefined },
    { preserveScroll: true, replace: true },
  );
};

const submit = () => {
  form
    .transform((data) => ({
      church_id: data.church_id,
      masses: data.masses.map(({ key, ...mass }) => mass),
    }))
    .put('/fines-semana-misas/programar', {
      preserveScroll: true,
      onError: () =>
        alertError(
          'No se pudieron guardar las misas',
          'Revisa los campos marcados en rojo e inténtalo de nuevo.',
        ),
    });
};

const fieldError = (index, field) => form.errors[`masses.${index}.${field}`];
</script>

<template>
  <AppShell page-title="Programar misas de fin de semana">
    <form @submit.prevent="submit">
      <CatalogHeader
        title="Programar misas de fin de semana"
        subtitle="Misas fijas que se repiten cada sábado y domingo"
        back-href="/fines-semana-misas"
        :icon="CalendarClock"
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
            :disabled="form.processing || !form.church_id"
          >
            {{ form.processing ? 'Guardando...' : 'Guardar' }}
          </button>
        </template>
      </CatalogHeader>

      <div
        class="mb-4 flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200"
        role="note"
      >
        <Info class="mt-0.5 h-4 w-4 shrink-0" />
        <p>
          Las misas creadas dentro de estos días se generarán dentro de los fines de semana del mes
          actual<span v-if="monthLabel"> ({{ monthLabel }})</span> al dar clic en «Nuevo» y elegir
          generarlas.
        </p>
      </div>

      <div
        class="mb-6 rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/70 sm:p-8"
      >
        <div class="max-w-md">
          <UnderlineField
            :model-value="form.church_id"
            label="Parroquia"
            as="select"
            placeholder="Selecciona una parroquia"
            :options="churchOptions"
            :error="form.errors.church_id"
            :disabled="lockedChurchId !== null"
            number-value
            required
            @update:model-value="selectChurch"
          />
        </div>

        <p
          v-if="!form.church_id"
          class="mt-8 rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
        >
          Selecciona una parroquia para configurar sus misas de fin de semana.
        </p>

        <div v-else class="mt-8 space-y-8">
          <section v-for="day in days" :key="day.value">
            <div class="mb-3 flex items-center justify-between gap-3">
              <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">
                {{ day.label }}
                <span class="ml-1 text-sm font-medium text-slate-500">
                  ({{ rowsFor(day.value).length }})
                </span>
              </h2>
              <button
                type="button"
                class="btn btn-sm gap-1.5 rounded-xl border-0 bg-sky-700 text-white shadow-sm hover:bg-sky-800"
                @click="addMass(day.value)"
              >
                <Plus class="h-4 w-4" />
                Agregar
              </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
              <table class="table w-full">
                <thead>
                  <tr
                    class="bg-slate-50 text-xs uppercase tracking-widest text-slate-500 dark:bg-slate-950"
                  >
                    <th>Nombre</th>
                    <th>Inicia</th>
                    <th>Termina</th>
                    <th v-if="chapels.length">Capilla</th>
                    <th class="text-right">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!rowsFor(day.value).length">
                    <td
                      :colspan="chapels.length ? 5 : 4"
                      class="py-6 text-center text-sm text-slate-500 dark:text-slate-400"
                    >
                      Sin misas programadas para el {{ day.label.toLowerCase() }}.
                    </td>
                  </tr>
                  <tr
                    v-for="{ mass, index } in rowsFor(day.value)"
                    :key="mass.key"
                    class="align-top"
                  >
                    <td class="min-w-48">
                      <input
                        v-model="mass.name"
                        type="text"
                        maxlength="150"
                        placeholder="Nombre de la misa"
                        aria-label="Nombre de la misa"
                        class="input input-sm input-bordered w-full"
                        :class="{ 'input-error': fieldError(index, 'name') }"
                      />
                      <p v-if="fieldError(index, 'name')" class="mt-1 text-xs text-red-600">
                        {{ fieldError(index, 'name') }}
                      </p>
                    </td>
                    <td>
                      <input
                        v-model="mass.starts_time"
                        type="time"
                        aria-label="Hora de inicio"
                        class="input input-sm input-bordered"
                        :class="{ 'input-error': fieldError(index, 'starts_time') }"
                      />
                      <p v-if="fieldError(index, 'starts_time')" class="mt-1 text-xs text-red-600">
                        {{ fieldError(index, 'starts_time') }}
                      </p>
                    </td>
                    <td>
                      <input
                        v-model="mass.ends_time"
                        type="time"
                        aria-label="Hora de fin"
                        class="input input-sm input-bordered"
                        :class="{ 'input-error': fieldError(index, 'ends_time') }"
                      />
                      <p v-if="fieldError(index, 'ends_time')" class="mt-1 text-xs text-red-600">
                        {{ fieldError(index, 'ends_time') }}
                      </p>
                    </td>
                    <td v-if="chapels.length" class="min-w-44">
                      <select
                        v-model="mass.chapel_id"
                        aria-label="Capilla"
                        class="select select-sm select-bordered w-full"
                        :class="{ 'select-error': fieldError(index, 'chapel_id') }"
                      >
                        <option :value="null">En parroquia</option>
                        <option v-for="chapel in chapels" :key="chapel.id" :value="chapel.id">
                          {{ chapel.name }}
                        </option>
                      </select>
                      <p v-if="fieldError(index, 'chapel_id')" class="mt-1 text-xs text-red-600">
                        {{ fieldError(index, 'chapel_id') }}
                      </p>
                    </td>
                    <td class="text-right">
                      <button
                        type="button"
                        class="btn btn-ghost btn-xs text-red-600"
                        aria-label="Quitar misa"
                        @click="removeMass(index)"
                      >
                        <Trash2 class="h-3.5 w-3.5" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </div>
    </form>
  </AppShell>
</template>
