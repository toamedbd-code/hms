<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: '' },
  allowCustom: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);
const isOpen = ref(false);
const activeIndex = ref(0);
const query = ref(props.modelValue);

const normalizedOptions = computed(() => {
  const options = props.options.map((option) => (
    typeof option === 'string'
      ? { label: option, value: option }
      : option
  ));
  return [...new Map(options.map((option) => [option.value, option])).values()];
});

const filteredOptions = computed(() => {
  const search = query.value.trim().toLocaleLowerCase();
  return normalizedOptions.value
    .filter((option) => !search || option.label.toLocaleLowerCase().includes(search))
    .slice(0, 50);
});

const displayedValue = computed(() => {
  if (props.allowCustom) return props.modelValue;
  if (isOpen.value) return query.value;
  return normalizedOptions.value.find((option) => option.value === props.modelValue)?.label ?? props.modelValue;
});

watch(() => props.modelValue, (value) => {
  if (!isOpen.value) query.value = value;
});

const updateValue = (event) => {
  query.value = event.target.value;
  if (props.allowCustom) emit('update:modelValue', event.target.value);
  activeIndex.value = 0;
  isOpen.value = true;
};

const selectOption = (option) => {
  emit('update:modelValue', option.value);
  query.value = option.label;
  isOpen.value = false;
};

const closeOptions = () => {
  isOpen.value = false;
  if (!props.allowCustom) {
    query.value = normalizedOptions.value.find((option) => option.value === props.modelValue)?.label ?? props.modelValue;
  }
};

const handleKeydown = (event) => {
  if (event.key === 'ArrowDown' && filteredOptions.value.length) {
    event.preventDefault();
    isOpen.value = true;
    activeIndex.value = (activeIndex.value + 1) % filteredOptions.value.length;
  } else if (event.key === 'ArrowUp' && filteredOptions.value.length) {
    event.preventDefault();
    isOpen.value = true;
    activeIndex.value = (activeIndex.value - 1 + filteredOptions.value.length) % filteredOptions.value.length;
  } else if (event.key === 'Enter' && isOpen.value) {
    event.preventDefault();
    if (filteredOptions.value[activeIndex.value]) {
      selectOption(filteredOptions.value[activeIndex.value]);
    } else {
      closeOptions();
    }
  } else if (event.key === 'Escape') {
    closeOptions();
  }
};
</script>

<template>
  <div class="relative w-full min-w-0">
    <input
      :value="displayedValue"
      type="search"
      :placeholder="placeholder"
      autocomplete="off"
      class="w-full p-2 text-sm border rounded"
      role="combobox"
      aria-autocomplete="list"
      :aria-expanded="isOpen && filteredOptions.length > 0"
      @input="updateValue"
      @focus="isOpen = true; activeIndex = 0"
      @keydown="handleKeydown"
      @blur="closeOptions"
    />
    <ul
      v-if="isOpen && filteredOptions.length"
      class="absolute z-50 mt-1 max-h-52 w-full overflow-y-auto rounded border border-slate-200 bg-white py-1 shadow-lg"
      role="listbox"
    >
      <li
        v-for="(option, index) in filteredOptions"
        :key="option.value"
        :class="[
          'cursor-pointer px-3 py-2 text-sm',
          index === activeIndex ? 'bg-blue-600 text-white' : 'text-slate-800 hover:bg-slate-100',
        ]"
        role="option"
        :aria-selected="index === activeIndex"
        @mouseenter="activeIndex = index"
        @mousedown.prevent="selectOption(option)"
      >{{ option.label }}</li>
    </ul>
  </div>
</template>
