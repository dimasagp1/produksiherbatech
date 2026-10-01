<script setup lang="ts">
defineProps<{
    modelValue: boolean;
}>();

defineEmits<{ (e: 'update:modelValue', value: boolean): void }>();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition"
        :class="modelValue ? 'bg-amber-500' : 'bg-gray-300 dark:bg-gray-600'"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <span
            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition"
            :class="modelValue ? 'translate-x-4' : 'translate-x-0.5'"
        />
    </button>
</template>
