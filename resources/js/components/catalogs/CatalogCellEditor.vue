<script setup>
import { computed } from 'vue';

const props = defineProps({
  column: {
    type: Object,
    required: true,
  },

  modelValue: {
    type: [String, Number, Boolean, null],
    default: '',
  },

  error: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:modelValue']);

const value = computed({
  get: () => props.modelValue,
  set: (newValue) => emit('update:modelValue', newValue),
});

const handleTextInput = (event) => {
  let val = event.target.value;

  if (props.column.uppercase !== false) {
    val = val.toUpperCase();
  }

  value.value = val;
};
</script>

<template>
  <div class="w-full">
    <!-- TEXT -->
    <input
      v-if="column.type === 'text'"
      :value="value"
      type="text"
      :placeholder="column.label"
      :class="[
        'input input-bordered input-sm w-full',
        column.uppercase !== false ? 'uppercase' : ''
      ]"
      @input="handleTextInput"
    />

    <!-- DATE -->
    <input
      v-else-if="column.type === 'date'"
      v-model="value"
      type="date"
      class="input input-bordered input-sm w-full"
    />

    <!-- SELECT -->
    <select
      v-else-if="column.type === 'select'"
      v-model="value"
      class="select select-bordered select-sm w-full"
      :disabled="column.disabled"
    >
      <option
        v-if="column.default === undefined"
        value=""
        disabled
      >
        Elige una opción
      </option>

      <option
        v-for="opt in column.options"
        :key="opt.value"
        :value="opt.value"
      >
        {{ opt.label }}
      </option>
    </select>

    <!-- FALLBACK -->
    <input
      v-else
      v-model="value"
      type="text"
      class="input input-bordered input-sm w-full"
    />

    <!-- ERROR -->
    <p
      v-if="error"
      class="mt-1 text-xs text-red-600 dark:text-red-400"
    >
      {{ error }}
    </p>
  </div>
</template>