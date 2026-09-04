<script setup>
import { CloudUpload, Image as ImageIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import InheritanceTag from './InheritanceTag.vue';

const props = defineProps({
  setting: { type: Object, required: true },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'reset', 'file']);

const meta = computed(() => props.setting.meta ?? {});

const selectOptions = computed(() => {
  if (!Array.isArray(meta.value.options)) return [];
  return meta.value.options;
});

const fallbackValue = (type, fallback) => {
  if (props.setting.value === null || props.setting.value === undefined) return fallback;
  return props.setting.value;
};

const textVal = computed({
  get: () => fallbackValue(props.setting.type, props.setting.default_value ?? ''),
  set: (v) => emit('update:modelValue', v),
});

const isCheckboxChecked = computed(() => fallbackValue('boolean', false));

const emitModel = (value) => emit('update:modelValue', value);

const onFilePicked = (event) => {
  const file = event.target.files?.[0];
  if (file) emit('file', file);
  event.target.value = '';
};

const acceptAttr = computed(() => meta.value.accept ?? 'image/*');

const fileName = computed(() => {
  const value = props.setting.value;
  if (typeof value !== 'string' || !value) return '';
  const parts = value.split('/');
  return parts[parts.length - 1];
});
</script>

<template>
  <div
    class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900"
  >
    <div class="p-5 sm:p-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0 flex-1">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
            {{ setting.name }}
          </h3>
          <p v-if="setting.description" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
            {{ setting.description }}
          </p>
          <p v-if="meta.help" class="mt-1 text-xs italic text-slate-400 dark:text-slate-500">
            {{ meta.help }}
          </p>

          <div class="mt-2">
            <InheritanceTag :origin="setting.origin" :disabled="disabled" @reset="emit('reset')" />
          </div>
        </div>

        <div class="w-full shrink-0 sm:w-64">
          <!-- Boolean -->
          <label v-if="setting.type === 'boolean'" class="flex items-center gap-2">
            <input
              type="checkbox"
              class="toggle toggle-primary"
              :disabled="disabled"
              :checked="isCheckboxChecked"
              @change="emitModel($event.target.checked)"
            />
            <span class="text-sm text-slate-600 dark:text-slate-300">Activado</span>
          </label>

          <!-- Color -->
          <div v-else-if="setting.type === 'color'" class="flex items-center gap-2">
            <input
              type="color"
              class="h-9 w-14 cursor-pointer rounded-md border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-800"
              :disabled="disabled"
              :value="textVal"
              @input="emitModel($event.target.value)"
            />
            <input
              type="text"
              class="input input-bordered input-sm w-32 font-mono"
              :disabled="disabled"
              :value="textVal"
              maxlength="7"
              @input="emitModel($event.target.value)"
            />
          </div>

          <!-- Select -->
          <select
            v-else-if="setting.type === 'select'"
            class="select select-bordered w-full"
            :disabled="disabled"
            :value="textVal"
            @change="emitModel($event.target.value)"
          >
            <option v-for="opt in selectOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>

          <!-- Text -->
          <textarea
            v-else-if="setting.type === 'text'"
            class="textarea textarea-bordered w-full"
            rows="3"
            :disabled="disabled"
            v-model="textVal"
          ></textarea>

          <!-- Number -->
          <input
            v-else-if="setting.type === 'number'"
            type="number"
            class="input input-bordered w-full"
            :disabled="disabled"
            :min="meta.min"
            :max="meta.max"
            :step="meta.step ?? 1"
            :value="textVal"
            @input="emitModel($event.target.value)"
          />

          <!-- File -->
          <div v-else-if="setting.type === 'file'" class="flex flex-col gap-2">
            <label class="cursor-pointer">
              <span
                class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
              >
                <CloudUpload class="h-4 w-4" />
                {{ fileName ? 'Reemplazar imagen' : 'Subir imagen' }}
              </span>
              <input
                type="file"
                class="hidden"
                :accept="acceptAttr"
                :disabled="disabled"
                @change="onFilePicked"
              />
            </label>
            <span
              v-if="fileName"
              class="inline-flex max-w-full items-center gap-1.5 truncate text-xs text-slate-500 dark:text-slate-400"
            >
              <ImageIcon class="h-3.5 w-3.5 shrink-0" />
              <span class="truncate">{{ fileName }}</span>
            </span>
          </div>

          <!-- String (default) -->
          <input
            v-else
            type="text"
            class="input input-bordered w-full"
            :disabled="disabled"
            :placeholder="meta.placeholder ?? ''"
            :maxlength="meta.max_length ?? undefined"
            v-model="textVal"
          />
        </div>
      </div>
    </div>
  </div>
</template>
