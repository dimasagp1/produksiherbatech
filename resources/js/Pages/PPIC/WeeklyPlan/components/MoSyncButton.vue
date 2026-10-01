<script setup lang="ts">
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref } from 'vue';

const props = defineProps<{
    syncing?: boolean;
}>();

const emit = defineEmits<{ (e: 'sync'): void }>();

const busy = ref(false);

function handleSync() {
    if (busy.value || props.syncing) return;
    busy.value = true;
    emit('sync');
    setTimeout(() => {
        busy.value = false;
    }, 1500);
}
</script>

<template>
    <PrimaryButton
        type="button"
        class="justify-center py-1.5 text-xs"
        :disabled="busy || syncing"
        @click="handleSync"
    >
        {{ busy || syncing ? 'Sync berjalan...' : 'Sync Odoo MO' }}
    </PrimaryButton>
</template>
