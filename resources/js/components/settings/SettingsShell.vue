<script setup>
import { BadgeCheck, Settings } from 'lucide-vue-next';

defineProps({
  categories: { type: Array, required: true },
  activeKey: { type: String, required: true },
});

const emit = defineEmits(['update:active']);

const iconFor = (name) => {
  const icons = { BadgeCheck };
  return icons[name] ?? Settings;
};

defineExpose({ iconFor });
</script>

<template>
  <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
    <!-- Sidebar de categorías -->
    <aside class="w-full shrink-0 lg:w-56">
      <nav
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
      >
        <ul class="divide-y divide-slate-100 dark:divide-slate-800">
          <li v-for="cat in categories" :key="cat.key">
            <button
              type="button"
              class="flex w-full items-center justify-between px-4 py-3 text-sm transition-colors"
              :class="
                activeKey === cat.key
                  ? 'bg-slate-100 font-semibold text-slate-900 dark:bg-slate-800 dark:text-slate-100'
                  : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50'
              "
              @click="emit('update:active', cat.key)"
            >
              <span class="flex items-center gap-2.5">
                <component :is="iconFor(cat.icon)" class="h-4 w-4 shrink-0" />
                {{ cat.name }}
              </span>
              <span class="rounded-full bg-rose-700 px-1.5 py-0.5 text-xs font-bold text-white">
                {{ cat.settings.length }}
              </span>
            </button>
          </li>
        </ul>
      </nav>
    </aside>

    <!-- Contenido del panel activo -->
    <div class="min-w-0 flex-1">
      <slot />
    </div>
  </div>
</template>
