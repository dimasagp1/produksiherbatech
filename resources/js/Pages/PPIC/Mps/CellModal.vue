<script setup lang="ts">
import { computed, ref, watch, ComputedRef } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import { defineProps, defineEmits } from 'vue';
import { useForm } from '@inertiajs/vue3';

interface WorkCenter {
    id: number;
    code: string;
    name: string;
    type: string;
    standard_ct_seconds: number;
    fit_mp: number;
    shift_hours: number;
    saturday_shift_hours: number;
    is_active: boolean;
}

interface Line {
    id: number;
    kode_line: string;
    nama_line: string;
    default_work_center_id: number | null;
    effective_ct_seconds: number | null;
    can_run_work_centers: string[] | null;
}

interface MpsItem {
    id: number;
    tanggal: string;
    line_id: number | null;
    work_center_id: number | null;
    shift: string;
    mp_count: number;
    target_qty: number;
    cleaning: boolean;
    adjusted_qty: number | null;
    gap_reason: string | null;
    notes: string | null;
    line: Line | null;
    workCenter: WorkCenter | null;
}

interface Line {
    id: number;
    kode_line: string;
    nama_line: string;
    default_work_center_id: number | null;
    effective_ct_seconds: number | null;
    can_run_work_centers: string[] | null;
}

interface WorkCenter {
    id: number;
    code: string;
    name: string;
    type: string;
    standard_ct_seconds: number;
    fit_mp: number;
    shift_hours: number;
    saturday_shift_hours: number;
    is_active: boolean;
}

const props = defineProps<{
    item: MpsItem | null;
    line: Line;
    shift: string;
    day: number;
    lines: Line[];
    workCenters: WorkCenter[];
    monthYear: string;
}>();

const emit = defineEmits(['update-item', 'close']);

const form = useForm({
    line_id: props.item?.line_id ? String(props.item.line_id) : String(props.line.id),
    work_center_id: props.item?.work_center_id ? String(props.item.work_center_id) : (props.line.default_work_center_id ? String(props.line.default_work_center_id) : ''),
    shift: props.item?.shift ?? props.shift,
    mp_count: props.item?.mp_count ?? 0,
    target_qty: props.item?.target_qty ?? 0,
    cleaning: props.item?.cleaning ?? false,
    adjusted_qty: props.item?.adjusted_qty ?? 0,
    gap_reason: props.item?.gap_reason ?? null,
    notes: props.item?.notes ?? '',
});

const isNew = computed(() => !props.item || props.item.id === 0);

const filteredWorkCenters = computed(() => {
    const line = props.lines.find(l => l.id === Number(form.line_id));
    if (!line || !line.can_run_work_centers || line.can_run_work_centers.length === 0) {
        return props.workCenters.filter(wc => wc.is_active);
    }
    return props.workCenters.filter(wc => wc.is_active && line.can_run_work_centers!.includes(wc.type));
});

const selectedWorkCenter = computed(() => {
    return props.workCenters.find(wc => wc.id === Number(form.work_center_id)) ?? null;
}) as ComputedRef<WorkCenter | null>;

const selectedWorkCenterNonNull = computed(() => {
    const wc = selectedWorkCenter.value;
    if (!wc) throw new Error('WorkCenter not found');
    return wc;
});

const targetPreview = computed(() => {
    if (!selectedWorkCenter.value || form.mp_count <= 0) return 0;
    
    const wc = selectedWorkCenter.value;
    if (!wc) return 0;
    
    const isSaturday = new Date(`${props.monthYear}-${String(props.day).padStart(2, '0')}`).getDay() === 6;
    const hours = isSaturday ? wc.saturday_shift_hours : wc.shift_hours;
    
    if (wc.standard_ct_seconds <= 0) return 0;
    const outputPerMinute = 60 / wc.standard_ct_seconds;
    
    return Math.round(outputPerMinute * hours * 60 * form.mp_count);
});

const shiftLabel: Record<string, string> = {
    shift1: 'Shift 1 (Pagi)',
    shift2: 'Shift 2 (Siang)',
};

const gapReasonOptions = [
    { value: 'cleaning', label: 'Cleaning' },
    { value: 'capacity_limit', label: 'Kapasitas Terbatas' },
    { value: 'material_shortage', label: 'Kekurangan Material' },
    { value: 'manual', label: 'Manual' },
];

const typeIcon: Record<string, string> = {
    mixing: '🥣',
    filling: '🥤',
    secondary: '📦',
};

const typeLabel: Record<string, string> = {
    mixing: 'Mixing',
    filling: 'Filling',
    secondary: 'Secondary',
};

function submit() {
    const data = {
        line_id: Number(form.line_id),
        work_center_id: form.work_center_id ? Number(form.work_center_id) : null,
        shift: form.shift,
        mp_count: form.mp_count,
        target_qty: form.target_qty,
        cleaning: form.cleaning,
        adjusted_qty: form.adjusted_qty,
        gap_reason: form.gap_reason,
        notes: form.notes,
    };
    
    // Always emit update-item with a valid item (either existing or new with id: 0)
    const item = props.item ?? {
        id: 0,
        tanggal: `${props.monthYear}-${String(props.day).padStart(2, '0')}`,
        line_id: Number(form.line_id),
        work_center_id: form.work_center_id ? Number(form.work_center_id) : null,
        shift: form.shift,
        mp_count: form.mp_count,
        target_qty: form.target_qty,
        cleaning: form.cleaning,
        adjusted_qty: form.adjusted_qty ?? null,
        gap_reason: form.gap_reason ?? null,
        notes: form.notes ?? '',
    } as MpsItem;
    
    emit('update-item', item, data);
    emit('close');
}

function closeModal() {
    emit('close');
}

function getHours(): number {
    if (!selectedWorkCenter.value) return 0;
    const isSaturday = new Date(`${props.monthYear}-${String(props.day).padStart(2, '0')}`).getDay() === 6;
    return isSaturday ? selectedWorkCenter.value.saturday_shift_hours : selectedWorkCenter.value.shift_hours;
}
</script>

<template>
    <Modal :show="true" @close="closeModal" max-width="lg">
        <template #title>
            {{ isNew ? 'Tambah' : 'Edit' }} MPS Item
            <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                ({{ props.monthYear }}-{{ String(props.day).padStart(2, '0') }} - {{ props.line.nama_line }} - {{ shiftLabel[props.shift] }})
            </span>
        </template>

        <template #content>
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Line" />
                        <select
                            v-model="form.line_id"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            required
                        >
                            <option value="" disabled>Pilih Line</option>
                            <option
                                v-for="l in lines"
                                :key="l.id"
                                :value="l.id"
                            >
                                {{ l.nama_line }} ({{ l.kode_line }})
                            </option>
                        </select>
                        <InputError :message="form.errors.line_id" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Work Center" />
                        <select
                            v-model="form.work_center_id"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            required
                        >
                            <option value="" disabled>Pilih Work Center</option>
                            <optgroup
                                v-for="type in ['mixing', 'filling', 'secondary']"
                                :key="type"
                                :label="type === 'mixing' ? 'Mixing' : type === 'filling' ? 'Filling' : 'Secondary'"
                            >
                                <option
                                    v-for="wc in filteredWorkCenters.filter(w => w.type === type)"
                                    :key="wc.id"
                                    :value="wc.id"
                                >
                                    {{ wc.code }} - {{ wc.name }} (CT: {{ wc.standard_ct_seconds }}s, MP: {{ wc.fit_mp }})
                                </option>
                            </optgroup>
                        </select>
                        <InputError :message="form.errors.work_center_id" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Shift" />
                        <select
                            v-model="form.shift"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            required
                        >
                            <option value="shift1">Shift 1 (Pagi)</option>
                            <option value="shift2">Shift 2 (Siang)</option>
                        </select>
                    </div>

                    <div>
                        <InputLabel value="Manpower (MP)" />
                        <TextInput
                            v-model="form.mp_count"
                            type="number"
                            min="0"
                            class="mt-1.5 block w-full text-xs"
                            @input="form.target_qty = targetPreview"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Target Qty (Auto)" />
                        <TextInput
                            v-model="form.target_qty"
                            type="number"
                            min="0"
                            class="mt-1.5 block w-full text-xs font-mono bg-slate-50 dark:bg-slate-800"
                            readonly
                        />
                    </div>

                    <div class="flex items-end">
                        <InputLabel value="Jam Kerja" />
                        <div class="mt-1.5 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-900 dark:bg-slate-700 dark:text-slate-100 w-full">
                            {{ getHours() }} jam {{ new Date(`${props.monthYear}-${String(props.day).padStart(2, '0')}`).getDay() === 6 ? '(Sabtu)' : '(Sen-Jum)' }}
                        </div>
                    </div>
                </div>

                <div>
                    <InputLabel value="Cleaning (Full Day Block)" />
                    <label class="mt-1.5 flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.cleaning"
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="text-sm text-slate-700 dark:text-slate-300">
                            Blokir hari ini untuk cleaning - tidak generate Weekly Plan
                        </span>
                    </label>
                </div>

                <div v-if="!isNew">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Adjusted Qty" />
                            <TextInput
                                v-model="form.adjusted_qty"
                                type="number"
                                min="0"
                                class="mt-1.5 block w-full text-xs"
                            />
                        </div>

                        <div>
                            <InputLabel value="Gap Reason" />
                            <select
                                v-model="form.gap_reason"
                                class="mt-1.5 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            >
                                <option value="">— Pilih Alasan Gap —</option>
                                <option v-for="opt in gapReasonOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                    </div>

<div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400" v-if="selectedWorkCenterNonNull">
                        <span :class="typeIcon[selectedWorkCenterNonNull.type] + ' text-lg'"></span>
                        <span>{{ typeLabel[selectedWorkCenterNonNull.type] ?? '—' }}</span>
                        <span class="ml-2 px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800">
                            CT: {{ selectedWorkCenterNonNull.standard_ct_seconds }}s
                        </span>
                        <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800">
                            MP Standar: {{ selectedWorkCenterNonNull.fit_mp }}
                        </span>
                    </div>
                </div>

                <div>
                    <InputLabel value="Catatan" />
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        class="mt-1 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                    ></textarea>
                </div>
            </form>
        </template>

        <template #footer>
            <SecondaryButton @click="closeModal">Batal</SecondaryButton>
            <PrimaryButton :disabled="form.processing" @click="submit">
                {{ isNew ? 'Tambah' : 'Simpan' }}
            </PrimaryButton>
        </template>
    </Modal>
</template>