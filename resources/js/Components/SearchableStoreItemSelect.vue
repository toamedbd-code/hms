<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  items: { type: Array, default: () => [] },
  formatOption: {
    type: Function,
    default: (item) => item.item_name ?? '',
  },
  placeholder: { type: String, default: 'Search or select item' },
  dropUp: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'select']);
const root = ref(null);
const search = ref('');
const isOpen = ref(false);
const activeIndex = ref(0);

const selectedItem = computed(() => props.items.find(
  (item) => String(item.id) === String(props.modelValue),
) ?? null);

const filteredItems = computed(() => {
  const term = search.value.trim().toLocaleLowerCase();
  if (!term) return props.items;

  return props.items.filter((item) => [
    item.item_name,
    item.name,
    item.item_code,
    item.category,
    item.unit,
    props.formatOption(item),
  ].some((value) => String(value ?? '').toLocaleLowerCase().includes(term)));
});

watch(selectedItem, (item) => {
  search.value = item ? props.formatOption(item) : '';
});

watch(filteredItems, () => {
  activeIndex.value = 0;
});

const onInput = (event) => {
  search.value = event.target.value;
  isOpen.value = true;
  activeIndex.value = 0;
  emit('update:modelValue', '');
};

const chooseItem = (item) => {
  emit('update:modelValue', item.id);
  emit('select', item);
  search.value = props.formatOption(item);
  isOpen.value = false;
};

const onKeydown = (event) => {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    isOpen.value = true;
    activeIndex.value = Math.min(activeIndex.value + 1, filteredItems.value.length - 1);
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    activeIndex.value = Math.max(activeIndex.value - 1, 0);
  } else if (event.key === 'Enter' && isOpen.value && filteredItems.value[activeIndex.value]) {
    event.preventDefault();
    chooseItem(filteredItems.value[activeIndex.value]);
  } else if (event.key === 'Escape') {
    isOpen.value = false;
  }
};

const onDocumentPointerDown = (event) => {
  if (!root.value?.contains(event.target)) isOpen.value = false;
};

onMounted(() => document.addEventListener('pointerdown', onDocumentPointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onDocumentPointerDown));
</script>

<template>
  <div ref="root" class="relative">
    <input
      :value="search"
      type="text"
      role="combobox"
      aria-autocomplete="list"
      :aria-expanded="isOpen"
      :placeholder="placeholder"
      class="w-full p-2 text-sm border rounded"
      @input="onInput"
      @focus="isOpen = true"
      @keydown="onKeydown"
    />

    <div
      v-if="isOpen"
      class="absolute z-50 w-full overflow-hidden bg-white border border-gray-300 rounded shadow-lg"
      :class="dropUp ? 'bottom-full mb-1' : 'top-full mt-1'"
    >
      <ul v-if="filteredItems.length" role="listbox" class="max-h-60 overflow-y-auto py-1">
        <li v-for="(item, index) in filteredItems" :key="item.id" role="option" :aria-selected="String(item.id) === String(modelValue)">
          <button
            type="button"
            class="w-full px-3 py-2 text-sm text-left"
            :class="index === activeIndex ? 'bg-emerald-100 text-emerald-900' : 'hover:bg-gray-100'"
            @mouseenter="activeIndex = index"
            @click="chooseItem(item)"
          >
            {{ formatOption(item) }}
          </button>
        </li>
      </ul>
      <p v-else class="px-3 py-2 text-sm text-gray-500">No matching items found.</p>
    </div>
  </div>
</template>
