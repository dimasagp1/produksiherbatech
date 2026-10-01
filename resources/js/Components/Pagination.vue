<script setup lang="ts">
defineProps<{
    currentPage: number;
    lastPage: number;
    total: number;
    perPage: number;
}>();

const emit = defineEmits<{
    (e: 'page', page: number): void;
}>();

function visiblePages(current: number, last: number): (number | string)[] {
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const pages: (number | string)[] = [1];
    if (current > 3) pages.push('...');
    for (
        let i = Math.max(2, current - 1);
        i <= Math.min(last - 1, current + 1);
        i++
    ) {
        pages.push(i);
    }
    if (current < last - 2) pages.push('...');
    pages.push(last);
    return pages;
}
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-700 dark:text-gray-300">
            Menampilkan {{ (currentPage - 1) * perPage + 1 }}&ndash;{{
                Math.min(currentPage * perPage, total)
            }}
            dari {{ total }}
        </p>
        <div class="flex items-center gap-1">
            <button
                :disabled="currentPage <= 1"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                @click="emit('page', currentPage - 1)"
            >
                &lsaquo;
            </button>
            <template
                v-for="(p, i) in visiblePages(currentPage, lastPage)"
                :key="i"
            >
                <span
                    v-if="p === '...'"
                    class="inline-flex h-8 items-center px-1 text-sm text-gray-400 dark:text-gray-500"
                    >&hellip;</span
                >
                <button
                    v-else
                    class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md border px-2 text-sm font-semibold transition"
                    :class="
                        p === currentPage
                            ? 'border-indigo-500 bg-indigo-600 text-white shadow-sm dark:border-indigo-400 dark:bg-indigo-500'
                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                    @click="emit('page', p as number)"
                >
                    {{ p }}
                </button>
            </template>
            <button
                :disabled="currentPage >= lastPage"
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
                @click="emit('page', currentPage + 1)"
            >
                &rsaquo;
            </button>
        </div>
    </div>
</template>
