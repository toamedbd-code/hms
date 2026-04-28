<script setup>
import { computed, useAttrs } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'xs' },
  href: { type: [String, Object], default: null },
  external: { type: Boolean, default: false },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
  title: { type: String, default: '' },
  rounded: { type: Boolean, default: true },
});

const attrs = useAttrs();

const variantMap = {
  primary: 'bg-blue-600 text-white hover:bg-blue-700',
  indigo: 'bg-indigo-600 text-white hover:bg-indigo-700',
  success: 'bg-emerald-600 text-white hover:bg-emerald-700',
  danger: 'bg-red-600 text-white hover:bg-red-700',
  warning: 'bg-yellow-500 text-white hover:bg-yellow-600',
  // Use a darker muted background so white text remains readable
  muted: 'bg-gray-700 text-white hover:bg-gray-800',
  rose: 'bg-rose-600 text-white hover:bg-rose-700',
  fuchsia: 'bg-fuchsia-700 text-white hover:bg-fuchsia-800',
  orange: 'bg-orange-600 text-white hover:bg-orange-700',
};

const sizeMap = {
  xs: 'px-3 py-1.5 text-xs',
  sm: 'px-3 py-2 text-sm',
};

const rootClass = computed(() => {
  const sizeCls = sizeMap[props.size] || sizeMap.xs;
  const varCls = variantMap[props.variant] || variantMap.primary;
  const round = props.rounded ? 'rounded' : '';
  return `${sizeCls} ${varCls} ${round} inline-flex items-center gap-1.5`;
});
</script>

<template>
  <Link v-if="href && !external" :href="href" :class="rootClass" v-bind="attrs" :title="title">
    <slot />
  </Link>
  <a v-else-if="href && external" :href="href" :class="rootClass" target="_blank" rel="noopener" v-bind="attrs" :title="title">
    <slot />
  </a>
  <button v-else :type="type" :class="rootClass" :disabled="disabled" v-bind="attrs" :title="title">
    <slot />
  </button>
</template>
