<script setup>
import { computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { SlidersHorizontal } from 'lucide-vue-next';
import AppShell from '../../components/layouts/AppShell.vue';
import CatalogHeader from '../../components/catalogs/CatalogHeader.vue';
import UnderlineField from '../../components/forms/UnderlineField.vue';
import UnderlineSection from '../../components/forms/UnderlineSection.vue';

const props = defineProps({
  churches: { type: Array, default: () => [] },
  selectedChurchId: { type: Number, default: null },
  settings: { type: Array, default: () => [] },
});

const churchOptions = computed(() =>
  props.churches.map((church) => ({
    value: church.id,
    label: church.name,
  })),
);

const normalizeValue = (setting) => {
  if (setting.type_data === 'boolean') {
    return Boolean(setting.value);
  }

  if (['array', 'json'].includes(setting.type_data)) {
    return setting.value === null || setting.value === undefined
      ? ''
      : JSON.stringify(setting.value, null, 2);
  }

  if (setting.type_data === 'file') {
    return null;
  }

  return setting.value ?? '';
};

const buildSettingsPayload = () =>
  Object.fromEntries(props.settings.map((setting) => [setting.key, normalizeValue(setting)]));

const form = useForm({
  _method: 'put',
  settings: buildSettingsPayload(),
});

watch(
  () => props.settings,
  () => {
    form.defaults({
      _method: 'put',
      settings: buildSettingsPayload(),
    });
    form.reset();
  },
);

const selectedChurch = computed({
  get: () => props.selectedChurchId,
  set: (churchId) => {
    router.get(
      '/configuraciones',
      { church_id: churchId || undefined },
      {
        preserveScroll: true,
        replace: true,
      },
    );
  },
});

const hasMultipleChurches = computed(() => props.churches.length > 1);

const fileAccept = (setting) => setting.meta?.accept ?? '';

const handleFile = (setting, event) => {
  form.settings[setting.key] = event.target.files?.[0] ?? null;
};

const submit = () => {
  if (!props.selectedChurchId) return;

  form.post(`/configuraciones/${props.selectedChurchId}`, {
    forceFormData: true,
    preserveScroll: true,
  });
};
</script>

<template>
  <AppShell :page-title="'Configuraciones'">
    <CatalogHeader
      title="Configuraciones"
      subtitle="Administra los valores configurables de la parroquia"
      back-href="/"
      :count="settings.length"
      :icon="SlidersHorizontal"
    />

    <form
      class="mb-6 rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-sm backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/70 sm:p-8"
      @submit.prevent="submit"
    >
      <div class="space-y-9">
        <UnderlineSection title="Parroquia">
          <UnderlineField
            v-model="selectedChurch"
            label="Parroquia"
            as="select"
            placeholder="Selecciona una parroquia..."
            :options="churchOptions"
            :disabled="!hasMultipleChurches"
            number-value
            required
          />
        </UnderlineSection>

        <UnderlineSection title="Valores">
          <div v-if="!selectedChurchId" class="text-sm font-semibold text-slate-500">
            No hay parroquias disponibles para configurar.
          </div>

          <div v-else class="grid gap-x-9 gap-y-8 md:grid-cols-2">
            <div
              v-for="setting in settings"
              :key="setting.key"
              :class="{ 'md:col-span-2': ['array', 'json', 'file'].includes(setting.type_data) }"
            >
              <label
                v-if="setting.type_data === 'boolean'"
                class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950"
              >
                <input
                  v-model="form.settings[setting.key]"
                  type="checkbox"
                  class="mt-1 rounded border-slate-300 text-sky-700 focus:ring-sky-700"
                />
                <span>
                  <span class="block text-sm font-black text-slate-700 dark:text-slate-100">
                    {{ setting.name }}
                  </span>
                  <span class="mt-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">
                    {{ setting.description }}
                  </span>
                </span>
              </label>

              <p
                v-if="setting.type_data === 'boolean' && form.errors[`settings.${setting.key}`]"
                class="mt-1 text-xs font-semibold text-red-500"
              >
                {{ form.errors[`settings.${setting.key}`] }}
              </p>

              <UnderlineField
                v-else-if="setting.type_data === 'string'"
                v-model="form.settings[setting.key]"
                :label="setting.name"
                :placeholder="setting.description"
                :error="form.errors[`settings.${setting.key}`]"
              />

              <UnderlineField
                v-else-if="setting.type_data === 'number'"
                v-model="form.settings[setting.key]"
                :label="setting.name"
                type="number"
                :placeholder="setting.description"
                :error="form.errors[`settings.${setting.key}`]"
              />

              <UnderlineField
                v-else-if="['array', 'json'].includes(setting.type_data)"
                v-model="form.settings[setting.key]"
                :label="setting.name"
                as="textarea"
                :placeholder="setting.description"
                :error="form.errors[`settings.${setting.key}`]"
              />

              <div v-else-if="setting.type_data === 'file'">
                <label class="block text-sm font-bold text-slate-600 dark:text-slate-300">
                  {{ setting.name }}
                </label>
                <p class="mb-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                  {{ setting.description }}
                </p>
                <input
                  type="file"
                  :accept="fileAccept(setting)"
                  class="block w-full text-sm font-semibold text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-sky-700 file:px-4 file:py-2 file:text-sm file:font-black file:text-white hover:file:bg-sky-800 dark:text-slate-300"
                  @change="handleFile(setting, $event)"
                />
                <p
                  v-if="setting.file?.name"
                  class="mt-2 text-xs font-semibold text-slate-500 dark:text-slate-400"
                >
                  Archivo actual: {{ setting.file.name }}
                </p>
                <p
                  v-if="form.errors[`settings.${setting.key}`]"
                  class="mt-1 text-xs font-semibold text-red-500"
                >
                  {{ form.errors[`settings.${setting.key}`] }}
                </p>
              </div>
            </div>
          </div>
        </UnderlineSection>
      </div>

      <div class="mt-8 flex justify-end">
        <button
          type="submit"
          class="btn btn-primary btn-sm"
          :disabled="form.processing || !selectedChurchId"
        >
          {{ form.processing ? 'Guardando...' : 'Guardar configuraciones' }}
        </button>
      </div>
    </form>
  </AppShell>
</template>
