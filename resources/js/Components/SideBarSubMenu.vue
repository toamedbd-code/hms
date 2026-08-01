<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";

const props = defineProps({
  align: { type: String, default: "right" },
  width: { type: String, default: "48" },
  contentClasses: { type: Array, default: () => ["py-0", "text-gray-700"] },
  activeRoute: { required: true },
  open: { type: Boolean, required: false },
});

let open = ref(false);
const emit = defineEmits(["toggle", "update:open"]);

const isActiveRoute = computed(() => {
  try {
    return route().current() === props.activeRoute;
  } catch (e) {
    return false;
  }
});

const closeOnEscape = (e) => {
  if (open.value && e.key === "Escape") {
    open.value = false;
  }
};

onMounted(() => {
  document.addEventListener("keydown", closeOnEscape);
  if (isActiveRoute.value) open.value = true;
  // If parent provided controlled `open` prop, respect it initially
  if (props.open !== undefined) {
    open.value = !!props.open;
  }
});
onUnmounted(() => document.removeEventListener("keydown", closeOnEscape));

const widthClass = computed(() => ({ 48: "w-full" })[props.width.toString()]);

const alignmentClasses = computed(() => {
  if (props.align === "left") return "ltr:origin-top-left rtl:origin-top-right start-0";
  if (props.align === "right") return "ltr:origin-top-right rtl:origin-top-left end-0";
  return "origin-top";
});

const toggle = (e) => {
  open.value = !open.value;
  try { emit('update:open', open.value); } catch (err) { /* ignore */ }
  try { emit('toggle', e); } catch (err) { /* ignore */ }

  try {
    console.debug('[SideBarSubMenu] toggle', { open: open.value, eventType: e?.type, target: e?.target?.dataset ?? null });
    window.__sidebar_debug = window.__sidebar_debug || {};
    window.__sidebar_debug.lastToggle = { time: Date.now(), open: open.value, eventType: e?.type, target: e?.target?.dataset ?? null };
  } catch (err) { /* ignore */ }
};

// Watch for controlled prop changes
import { watch } from 'vue';
watch(() => props.open, (v) => {
  if (v !== undefined) open.value = !!v;
});

const close = () => { open.value = false; };
</script>

<template>
  <div class="relative">
    <div @click.stop="toggle" :class="open ? 'bg-emerald-500 text-white border-l-2 border-emerald-600 rounded-md' : 'bg-white rounded-md'">
      <slot name="trigger" />
    </div>

      <div
        v-show="open"
        class="rounded-md"
        :class="[widthClass, alignmentClasses]"
      >
        <div :class="[...contentClasses, 'bg-white rounded-md border border-gray-100']">
          <slot name="content" />
        </div>
      </div>
  </div>
</template>