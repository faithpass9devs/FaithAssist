<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, ChevronDown } from 'lucide-vue-next';

const props = defineProps({
  modelValue: { type: [Number, String], default: null },
  /** Arreglo de { value, label }. */
  options: { type: Array, default: () => [] },
  /** Texto y valor de la opción "todos". */
  placeholder: { type: String, default: 'Todos' },
  /** Ícono opcional (componente lucide) mostrado a la izquierda. */
  icon: { type: [Object, Function], default: null },
  ariaLabel: { type: String, default: 'Filtrar' },
  /** `md` iguala a los botones pequeños; `lg` iguala a los campos de búsqueda. */
  size: { type: String, default: 'md' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const sizeClasses = computed(() =>
  props.size === 'lg' ? 'h-[42px] min-w-[15rem] pl-3.5 pr-3 text-sm' : 'h-9 pl-3 pr-2.5 text-sm',
);

const open = ref(false);
const root = ref(null);
const highlighted = ref(-1);
const optionRefs = ref([]);

const allOptions = computed(() => [
  { value: null, label: props.placeholder },
  ...props.options.map((option) => ({ value: option.value, label: option.label })),
]);

const selectedOption = computed(
  () => allOptions.value.find((option) => option.value === props.modelValue) ?? allOptions.value[0],
);

const isActive = computed(() => props.modelValue !== null && props.modelValue !== '');

const toggle = () => (open.value ? close() : openMenu());

const openMenu = async () => {
  open.value = true;
  highlighted.value = Math.max(
    0,
    allOptions.value.findIndex((option) => option.value === props.modelValue),
  );
  await nextTick();
  optionRefs.value[highlighted.value]?.focus();
};

const close = () => {
  open.value = false;
  highlighted.value = -1;
};

const select = (option) => {
  close();
  root.value?.querySelector('button')?.focus();
  if (option.value === props.modelValue) return;
  emit('update:modelValue', option.value);
  emit('change', option.value);
};

const move = async (delta) => {
  const count = allOptions.value.length;
  highlighted.value = (highlighted.value + delta + count) % count;
  await nextTick();
  optionRefs.value[highlighted.value]?.focus();
};

const onTriggerKeydown = (event) => {
  if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
    event.preventDefault();
    openMenu();
  }
};

const onOptionKeydown = (event, option) => {
  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault();
      move(1);
      break;
    case 'ArrowUp':
      event.preventDefault();
      move(-1);
      break;
    case 'Enter':
    case ' ':
      event.preventDefault();
      select(option);
      break;
    case 'Escape':
    case 'Tab':
      close();
      root.value?.querySelector('button')?.focus();
      break;
  }
};

const onDocumentClick = (event) => {
  if (open.value && root.value && !root.value.contains(event.target)) close();
};

onMounted(() => document.addEventListener('mousedown', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocumentClick));

watch(open, (value) => {
  if (!value) optionRefs.value = [];
});
</script>

<template>
  <div ref="root" class="relative inline-block text-left">
    <button
      type="button"
      class="inline-flex w-full items-center gap-2 rounded-xl border font-medium shadow-sm transition-all hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-200 dark:focus-visible:ring-sky-900/50"
      :class="[
        sizeClasses,
        isActive
          ? 'border-sky-300 bg-sky-50 text-sky-800 hover:border-sky-400 dark:border-sky-700 dark:bg-sky-900/30 dark:text-sky-200'
          : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300',
      ]"
      :aria-label="ariaLabel"
      aria-haspopup="listbox"
      :aria-expanded="open"
      @click="toggle"
      @keydown="onTriggerKeydown"
    >
      <component
        :is="icon"
        v-if="icon"
        class="h-4 w-4 shrink-0"
        :class="isActive ? 'text-sky-700 dark:text-sky-300' : 'text-slate-400'"
        aria-hidden="true"
      />
      <span class="max-w-[14rem] truncate sm:max-w-[16rem]">{{ selectedOption.label }}</span>
      <ChevronDown
        class="ml-auto h-4 w-4 shrink-0 transition-transform duration-200"
        :class="[
          open ? 'rotate-180' : '',
          isActive ? 'text-sky-700 dark:text-sky-300' : 'text-slate-400',
        ]"
        aria-hidden="true"
      />
    </button>

    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="-translate-y-1 scale-95 opacity-0"
      enter-to-class="translate-y-0 scale-100 opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="translate-y-0 scale-100 opacity-100"
      leave-to-class="-translate-y-1 scale-95 opacity-0"
    >
      <ul
        v-if="open"
        class="absolute left-0 z-30 mt-2 min-w-full origin-top-left overflow-hidden rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xl shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-900 dark:shadow-black/40"
        role="listbox"
        :aria-label="ariaLabel"
      >
        <li
          v-for="(option, index) in allOptions"
          :key="option.value ?? '__all__'"
          role="option"
          :aria-selected="option.value === modelValue"
        >
          <button
            :ref="(el) => (optionRefs[index] = el)"
            type="button"
            class="flex w-full items-center gap-2.5 whitespace-nowrap rounded-xl px-3 py-2 text-left text-sm transition-colors focus:outline-none"
            :class="
              option.value === modelValue
                ? 'bg-sky-50 font-semibold text-sky-800 dark:bg-sky-900/30 dark:text-sky-200'
                : 'text-slate-700 hover:bg-slate-100 focus-visible:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:bg-slate-800'
            "
            tabindex="-1"
            @click="select(option)"
            @keydown="onOptionKeydown($event, option)"
            @mouseenter="highlighted = index"
          >
            <span class="flex h-4 w-4 shrink-0 items-center justify-center">
              <Check
                v-if="option.value === modelValue"
                class="h-4 w-4 text-sky-700 dark:text-sky-300"
                aria-hidden="true"
              />
            </span>
            <span class="truncate">{{ option.label }}</span>
          </button>
        </li>
      </ul>
    </Transition>
  </div>
</template>
