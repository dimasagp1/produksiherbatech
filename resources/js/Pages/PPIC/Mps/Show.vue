<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import Grid from './Grid.vue';

interface Produk {
    id: number;
    kode_produk: string;
    nama_produk: string;
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

interface MpsPlan {
    id: number;
    produk_id: number;
    work_center_id: number;
    month_year: string;
    status: string;
    notes: string | null;
    created_by: number;
    produk: Produk;
    workCenter: WorkCenter;
    creator: { id: number; name: string };
    items: MpsItem[];
    beginning_stock_oh: number;
    beginning_stock_snapshot: string | number | null;
    beginning_stock_date: string | null;
    closed_at: string | null;
}

const props = defineProps<{
    plan: MpsPlan;
    lines: Line[];
    workCenters: WorkCenter[];
    daysInMonth: number;
    beginningStockOH: number;
    liveStockOH: number;
    isSnapshotStale: boolean;
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});

const showGenerateConfirm = ref(false);
const showApproveConfirm = ref(false);
const gridError = ref<string | null>(null);
const isDraft = computed(() => props.plan.status === 'draft');

async function handleGridUpdate(item: MpsItem, data: Record<string, any>) {
    gridError.value = null;
    if (!isDraft.value) {
        gridError.value = 'Hanya plan draft yang bisa diedit.';
        return;
    }
    try {
        if (!item.id || item.id === 0) {
            await axios.post(route('ppic.mps.item.store', props.plan.id), {
                ...data,
                tanggal: item.tanggal,
            });
        } else {
            await axios.post(route('ppic.mps.item.update', item.id), data);
        }
        router.reload({ only: ['plan'] } as any);
    } catch (e: any) {
        const msg = e?.response?.data?.message ?? e?.response?.data?.errors?.work_center_id?.[0] ?? 'Gagal menyimpan MPS item.';
        gridError.value = msg;
    }
}

const statusColor: Record<string, string> = {
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
    approved: 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300',
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300',
    closed: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
};

const statusLabel: Record<string, string> = {
    draft: 'Draft',
    approved: 'Approved',
    active: 'Active',
    closed: 'Closed',
};

function formatMonthYear(ym: string): string {
    const [y, m] = ym.split('-');
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${months[parseInt(m) - 1]} ${y}`;
}

const handleApprove = () => {
    if (props.plan.status === 'draft') {
        showApproveConfirm.value = true;
    } else if (props.plan.status === 'approved') {
        router.post(route('ppic.mps.activate', props.plan.id), {}, {
            onSuccess: () => window.location.reload(),
        });
    }
}

const confirmApprove = () => {
    router.post(route('ppic.mps.approve', props.plan.id), {}, {
        onSuccess: () => {
            showApproveConfirm.value = false;
            window.location.reload();
        },
    });
}

const handleGenerate = () => {
    showGenerateConfirm.value = true;
}

const confirmGenerate = () => {
    router.post(route('ppic.mps.generate-weekly', props.plan.id), {}, {
        onSuccess: () => {
            showGenerateConfirm.value = false;
            window.location.reload();
        },
    });
}

function getStatusLabel(status: string): string {
    return statusLabel[status] ?? status;
}

function getWcTypeIcon(type: string): string {
    const icons: Record<string, string> = { mixing: '🥣', filling: '🥤', secondary: '📦' };
    return icons[type] ?? '⚙️';
}
</script>

<template>
    <Head title="MPS Plan Detail" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                            {{ props.plan.produk?.nama_produk }} ({{ props.plan.produk?.kode_produk }})
                        </h2>
<span
                            class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-bold"
                            :class="{
                                'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300': props.plan.workCenter?.type === 'mixing',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300': props.plan.workCenter?.type === 'filling',
                                'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300': props.plan.workCenter?.type === 'secondary',
                            }"
                        >
                            {{ getWcTypeIcon(props.plan.workCenter?.type) }} {{ props.plan.workCenter?.code }} - {{ props.plan.workCenter?.name }}
                        </span>
                        <span
                            class="inline-flex w-max rounded-md px-2 py-0.5 text-[10px] font-bold uppercase"
                            :class="statusColor[props.plan.status]"
                        >
                            {{ getStatusLabel(props.plan.status) }}
                        </span>
                        </div>
                         <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            MPS Plan: {{ formatMonthYear(props.plan.month_year) }} · {{ props.plan.workCenter?.name }} · Stock OH: {{ props.beginningStockOH.toLocaleString('id-ID') }} <span v-if="props.plan.beginning_stock_snapshot!==null" class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] dark:bg-gray-700">snapshot {{ props.plan.beginning_stock_date ? String(props.plan.beginning_stock_date).slice(0,10) : '' }}: {{ Number(props.plan.beginning_stock_snapshot).toLocaleString('id-ID') }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <a :href="route('ppic.mps.export', props.plan.id)" class="inline-flex items-center rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">Export Excel (SCD-A)</a>
                    <SecondaryButton v-if="isDraft" :class="props.isSnapshotStale ? 'border-amber-400 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:bg-amber-950/40' : ''" @click="router.post(route('ppic.mps.resnapshot', props.plan.id))">Resnapshot OH<span v-if="props.isSnapshotStale" class="ml-1">⚠️ stale</span></SecondaryButton>
                    <SecondaryButton @click="router.visit(route('ppic.mps.index'))">
                        Kembali ke Daftar
                    </SecondaryButton>
                    <button
                        v-if="props.plan.status === 'draft' || props.plan.status === 'approved'"
                        type="button"
                        class="rounded-xl bg-sky-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-sky-700"
                        @click="handleApprove"
                    >
                        {{ props.plan.status === 'draft' ? 'Approve' : 'Activate' }}
                    </button>
                    <PrimaryButton
                        v-if="props.plan.status === 'approved' || props.plan.status === 'active'"
                        @click="handleGenerate"
                        class="whitespace-nowrap"
                    >
                        Generate Weekly Plans
                    </PrimaryButton>
                    <button v-if="props.plan.status === 'active' || props.plan.status === 'approved'" type="button" class="rounded-xl bg-gray-800 px-3.5 py-2 text-xs font-semibold text-white hover:bg-gray-700 dark:bg-gray-200 dark:text-gray-800" @click="router.post(route('ppic.mps.close', props.plan.id))">Close</button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
            <div v-if="flash.success" class="mb-4 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm dark:border-emerald-800/80 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="mb-4 flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm dark:border-rose-800/80 dark:bg-rose-950/40 dark:text-rose-200">
                {{ flash.error }}
            </div>

            <div v-if="props.isSnapshotStale && isDraft" class="mb-3 flex items-center justify-between rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                <span>⚠️ Stock OH berubah — snapshot {{ props.plan.beginning_stock_snapshot }} → live {{ props.liveStockOH }}. Resnapshot sebelum approve.</span>
                <button type="button" class="rounded-lg bg-amber-600 px-3 py-1 text-xs font-bold text-white hover:bg-amber-700" @click="router.post(route('ppic.mps.resnapshot', props.plan.id))">Resnapshot</button>
            </div>
            <div v-if="gridError" class="mb-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-200">{{ gridError }}</div>
            <p v-if="!isDraft" class="mb-2 text-xs text-amber-600 dark:text-amber-300">Plan berstatus {{ props.plan.status }} — grid read-only. Ubah ke draft untuk edit.</p>
            <!-- Grid View -->
            <Grid
                :plan="plan"
                :lines="lines"
                :workCenters="workCenters"
                :daysInMonth="daysInMonth"
                :readOnly="!isDraft"
                @update-item="handleGridUpdate"
            />

            <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Gap Tracking — Plan vs Adjusted</h3>
                <p class="text-[11px] text-gray-500">Target = CT×jam×MP · Adjusted = koreksi kapasitas/material/cleaning · GAP = Adjusted-Target</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-[11px] font-semibold text-gray-600 dark:text-gray-400">
                            <tr><th class="px-2 py-1.5 text-left">Tanggal</th><th class="px-2 py-1.5 text-left">Line</th><th class="px-2 py-1.5 text-center">Shift</th><th class="px-2 py-1.5 text-right">Target</th><th class="px-2 py-1.5 text-right">Adjusted</th><th class="px-2 py-1.5 text-right">GAP</th><th class="px-2 py-1.5 text-left">Reason</th><th class="px-2 py-1.5 text-left">Catatan</th><th class="px-2 py-1.5 text-center">Cleaning</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="it in [...plan.items].sort((a,b)=> String(a.tanggal).localeCompare(String(b.tanggal)) || (a.line_id??0)-(b.line_id??0) || a.shift.localeCompare(b.shift))" :key="it.id" :class="it.cleaning ? 'bg-amber-50 dark:bg-amber-950/20' : ''">
                                <td class="px-2 py-1 font-mono">{{ String(it.tanggal).slice(0,10) }}</td>
                                <td class="px-2 py-1">{{ it.line?.nama_line ?? it.line_id ?? '-' }}</td>
                                <td class="px-2 py-1 text-center">{{ it.shift==='shift1'?'S1':'S2' }}</td>
                                <td class="px-2 py-1 text-right font-mono">{{ it.target_qty.toLocaleString('id-ID') }}</td>
                                <td class="px-2 py-1 text-right font-mono">{{ it.adjusted_qty!==null ? it.adjusted_qty.toLocaleString('id-ID') : '-' }}</td>
                                <td class="px-2 py-1 text-right font-mono" :class="it.adjusted_qty===null ? 'text-gray-400' : (it.adjusted_qty - it.target_qty) > 0 ? 'text-emerald-600' : (it.adjusted_qty - it.target_qty) < 0 ? 'text-rose-600' : ''">{{ it.adjusted_qty!==null ? ((it.adjusted_qty - it.target_qty) >=0 ? '+' : '') + (it.adjusted_qty - it.target_qty).toLocaleString('id-ID') : '-' }}</td>
                                <td class="px-2 py-1"><span v-if="it.gap_reason" class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] dark:bg-gray-700">{{ it.gap_reason }}</span><span v-else class="text-gray-400">-</span></td>
                                <td class="px-2 py-1 max-w-[160px] truncate" :title="it.notes ?? ''">{{ it.notes ?? '-' }}</td>
                                <td class="px-2 py-1 text-center">{{ it.cleaning ? '🧹' : '-' }}</td>
                            </tr>
                            <tr v-if="plan.items.length===0"><td colspan="9" class="py-6 text-center text-gray-400">Belum ada item — isi grid di atas</td></tr>
                        </tbody>
                        <tfoot v-if="plan.items.length>0" class="bg-gray-100 dark:bg-gray-900 font-bold">
                            <tr><td colspan="3" class="px-2 py-1.5 text-right">TOTAL</td><td class="px-2 py-1.5 text-right font-mono">{{ plan.items.filter(i=>!i.cleaning).reduce((a,i)=>a+i.target_qty,0).toLocaleString('id-ID') }}</td><td class="px-2 py-1.5 text-right font-mono">{{ plan.items.filter(i=>!i.cleaning).reduce((a,i)=>a+(i.adjusted_qty ?? i.target_qty),0).toLocaleString('id-ID') }}</td><td class="px-2 py-1.5 text-right font-mono">{{ (plan.items.filter(i=>!i.cleaning).reduce((a,i)=>a+(i.adjusted_qty ?? i.target_qty),0) - plan.items.filter(i=>!i.cleaning).reduce((a,i)=>a+i.target_qty,0)).toLocaleString('id-ID') }}</td><td colspan="3"></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Approve Confirm Modal -->
        <Modal :show="showApproveConfirm" @close="showApproveConfirm = false">
            <template #title>{{ props.plan.status === 'draft' ? 'Approve MPS Plan' : 'Activate MPS Plan' }}</template>
            <template #content>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ props.plan.status === 'draft'
                        ? 'Approve MPS Plan ini? Setelah di-approve, plan bisa digenerate ke Weekly Plans.'
                        : 'Activate MPS Plan ini? Plan akan menjadi active untuk periode ini.' }}
                </p>
            </template>
            <template #footer>
                <SecondaryButton @click="showApproveConfirm = false">Batal</SecondaryButton>
                <PrimaryButton @click="confirmApprove">
                    {{ props.plan.status === 'draft' ? 'Approve' : 'Activate' }}
                </PrimaryButton>
            </template>
        </Modal>

        <!-- Generate Confirm Modal -->
        <Modal :show="showGenerateConfirm" @close="showGenerateConfirm = false">
            <template #title>Generate Weekly Plans</template>
            <template #content>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Generate Weekly Plan drafts dari MPS items? Item dengan flag cleaning akan di-skip.
                </p>
            </template>
            <template #footer>
                <SecondaryButton @click="showGenerateConfirm = false">Batal</SecondaryButton>
                <PrimaryButton @click="confirmGenerate">Generate</PrimaryButton>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>