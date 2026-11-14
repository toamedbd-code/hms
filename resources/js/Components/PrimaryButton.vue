<script setup>
import { ref, watch, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps({
    type: { type: String, default: 'submit' },
});

const attrs = useAttrs();
const isProcessing = ref(false);

// Watch attrs.disabled reliably — more robust than computed for $attrs tracking
watch(
    () => attrs.disabled,
    (val) => { isProcessing.value = !!val; },
    { immediate: true }
);
</script>

<template>
    <button
        :type="type"
        v-bind="attrs"
        class="inline-flex items-center gap-2 px-6 py-3 bg-green-600 border border-transparent rounded-md font-bold text-base text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150"
    >
        <svg
            v-if="isProcessing"
            class="w-4 h-4 animate-spin flex-shrink-0"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
        </svg>
        <slot />
    </button>
</template>
