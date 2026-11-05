<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number, Boolean, Array], default: null },
  label: { type: String, required: true },
  error: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  min: { type: String, default: '' },
  as: { type: String, default: 'input' },
  options: { type: Array, default: () => [] },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  numberValue: { type: Boolean, default: false },
  multiple: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const update = (event) => {
  if (props.multiple) {
    const values = Array.from(event.target.selectedOptions).map((option) =>
      props.numberValue ? Number(option.value) : option.value,
    );
    emit('update:modelValue', values);
    return;
  }

  const value = event.target.value;

  if (props.as === 'select' && value === '') {
    emit('update:modelValue', null);
    return;
  }

  emit('update:modelValue', props.numberValue ? Number(value) : value);
};

const controlClass = computed(() => [
  'w-full border-0 border-b bg-transparent px-0 pb-1.5 pt-1 text-base font-bold text-slate-900 outline-none transition placeholder:font-semibold placeholder:text-slate-400 focus:border-rose-700 focus:ring-0 dark:text-slate-100 dark:placeholder:text-slate-500',
  props.error ? 'border-red-500' : 'border-slate-300 dark:border-slate-700',
  props.disabled ? 'cursor-not-allowed opacity-70' : '',
]);
</script>

<template>
  <div>
    <label class="block text-sm font-bold text-slate-600 dark:text-slate-300">
      {{ label }} <span v-if="required" class="text-red-500">*</span>
    </label>

    <select
      v-if="as === 'select'"
      :value="modelValue ?? ''"
      :disabled="disabled"
      :multiple="multiple"
      :class="controlClass"
      @change="update"
    >
      <option v-if="placeholder && !multiple" value="">{{ placeholder }}</option>
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>

    <textarea
      v-else-if="as === 'textarea'"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :class="[...controlClass, 'min-h-24 resize-y']"
      @input="update"
    />

    <input
      v-else
      :value="modelValue"
      :type="type"
      :min="min || undefined"
      :placeholder="placeholder"
      :disabled="disabled"
      :class="controlClass"
      @input="update"
    />

    <p v-if="error" class="mt-1 text-xs font-semibold text-red-500">{{ error }}</p>
  </div>
</template>
