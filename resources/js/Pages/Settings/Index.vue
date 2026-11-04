<script setup>
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { Church, Save, Settings } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import AppShell from '../../components/layouts/AppShell.vue';
import CatalogHeader from '../../components/catalogs/CatalogHeader.vue';
import FilterDropdown from '../../components/FilterDropdown.vue';
import SettingsShell from '../../components/settings/SettingsShell.vue';
import SettingField from '../../components/settings/SettingField.vue';

const props = defineProps({
  categories: { type: Array, required: true },
  church: { type: Object, required: true },
  churchOptions: { type: Array, default: null },
  canUpdate: { type: Boolean, default: false },
});

const groups = reactive(JSON.parse(JSON.stringify(props.categories)));

const activeKey = ref(props.categories[0]?.key ?? '');
const savingKey = ref('');
const uploadingKey = ref('');
const busy = ref(false);

const activeCategory = computed(() => groups.find((g) => g.key === activeKey.value));

const getCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const apiFetch = async (url, method, data = null) => {
  const response = await fetch(url, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': getCsrf(),
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: data ? JSON.stringify(data) : undefined,
  });

  const json = await response.json();

  if (!response.ok) {
    throw { status: response.status, errors: json.errors, message: json.message };
  }

  return json;
};

const toast = (icon, title) => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon,
    title,
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
  });
};

const setValue = (setting, value) => {
  setting.value = value === '' ? null : value;
  setting.dirty = true;
};

const saveCategory = async () => {
  const category = activeCategory.value;
  if (!category || !props.canUpdate || busy.value) return;

  const values = {};
  for (const setting of category.settings) {
    values[setting.key] = setting.type === 'number' ? Number(setting.value) : setting.value;
  }

  busy.value = true;
  savingKey.value = category.key;

  try {
    await apiFetch('/ajustes', 'PATCH', { church_id: props.church.id, values });
    for (const setting of category.settings) {
      setting.origin = 'church';
      setting.dirty = false;
    }
    toast('success', 'Ajustes guardados correctamente.');
  } catch (err) {
    toast('error', err?.message ?? 'Ocurrió un error inesperado.');
  } finally {
    busy.value = false;
    savingKey.value = '';
  }
};

const resetSetting = async (setting) => {
  if (!props.canUpdate || busy.value) return;

  busy.value = true;

  try {
    await apiFetch('/ajustes/reset', 'POST', {
      church_id: props.church.id,
      keys: [setting.key],
    });
    setting.value = setting.default_value;
    setting.origin = 'global';
    setting.dirty = false;
    toast('success', 'Ajuste restablecido.');
  } catch (err) {
    toast('error', err?.message ?? 'Ocurrió un error inesperado.');
  } finally {
    busy.value = false;
  }
};

const uploadFile = async (setting, file) => {
  if (!props.canUpdate || busy.value) return;

  busy.value = true;
  uploadingKey.value = setting.key;

  const form = new FormData();
  form.append('church_id', props.church.id);
  form.append('key', setting.key);
  form.append('file', file);

  try {
    const response = await fetch('/ajustes/files', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': getCsrf(),
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: form,
    });

    const json = await response.json();

    if (!response.ok) {
      throw { status: response.status, errors: json.errors, message: json.message };
    }

    setting.value = json.path;
    setting.origin = 'church';
    setting.dirty = true;
    toast('success', 'Imagen subida.');
  } catch (err) {
    toast('error', err?.message ?? 'Ocurrió un error inesperado.');
  } finally {
    busy.value = false;
    uploadingKey.value = '';
  }
};

const churchFilterOptions = computed(
  () => props.churchOptions?.map((opt) => ({ value: opt.id, label: opt.name })) ?? [],
);

const changeChurch = (churchId) => {
  if (!churchId || churchId === props.church.id || busy.value) return;

  router.get('/ajustes', { church_id: churchId }, { preserveState: false });
};

const dirtyCount = computed(
  () => (activeCategory.value?.settings ?? []).filter((s) => s.dirty === true).length,
);
</script>

<template>
  <AppShell :page-title="`Ajustes — ${church.name}`">
    <div class="mx-auto w-full max-w-6xl">
      <CatalogHeader
        title="Configuración de la parroquia"
        subtitle="Personalización de los ajustes por parroquia"
        back-href="/"
        :icon="Settings"
      >
        <template #actions>
          <FilterDropdown
            v-if="churchOptions"
            :model-value="church.id"
            :options="churchFilterOptions"
            :clearable="false"
            :icon="Church"
            aria-label="Cambiar de parroquia"
            class="w-full sm:ml-auto sm:w-auto"
            @change="changeChurch"
          />

          <span
            v-else
            class="inline-flex h-9 max-w-full items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3 text-sm font-medium text-sky-800 shadow-sm dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-200 sm:ml-auto"
          >
            <Church class="h-4 w-4 shrink-0 text-sky-700 dark:text-sky-300" aria-hidden="true" />
            <span class="truncate">{{ church.name }}</span>
          </span>
        </template>
      </CatalogHeader>

      <SettingsShell v-model:active-key="activeKey" :categories="groups">
        <div v-if="activeCategory" class="space-y-6">
          <div class="space-y-1">
            <h2
              class="text-sm font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-400"
            >
              {{ activeCategory.name }}
            </h2>
            <p v-if="activeCategory.description" class="text-sm text-slate-500 dark:text-slate-400">
              {{ activeCategory.description }}
            </p>
          </div>

          <div v-for="setting in activeCategory.settings" :key="setting.key" class="space-y-3">
            <SettingField
              :setting="setting"
              :disabled="!canUpdate || busy"
              @update:model-value="setValue(setting, $event)"
              @reset="resetSetting(setting)"
              @file="uploadFile(setting, $event)"
            />
          </div>

          <div
            v-if="canUpdate"
            class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
          >
            <p class="text-sm text-slate-500 dark:text-slate-400">
              <template v-if="dirtyCount">{{ dirtyCount }} ajuste(s) sin guardar.</template>
              <template v-else>Sin cambios pendientes.</template>
            </p>
            <button
              type="button"
              class="btn btn-primary btn-sm gap-2"
              :disabled="busy || dirtyCount === 0"
              @click="saveCategory"
            >
              <Save class="h-4 w-4" />
              {{ savingKey === activeCategory.key ? 'Guardando…' : 'Guardar ajustes' }}
            </button>
          </div>
        </div>
      </SettingsShell>
    </div>
  </AppShell>
</template>
