<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

interface MaterialNode {
    material_name: string;
    material_produk_id?: number | null;
    item_type?: string;
    quantity: number;
    quantity_per_unit?: number;
    uom: string;
    category: string;
    has_sub_bom?: boolean;
    children?: MaterialNode[];
    composition_percentage?: number | null;
}

interface BreakdownData {
    bom_id: number;
    version: string;
    product_name: string;
    product_code: string;
    product_uom: string;
    target_quantity: number;
    base_quantity: number;
    direct_items: MaterialNode[];
    raw_materials: MaterialNode[];
    total_raw_material_weight: number;
    primary_packaging: MaterialNode[];
    secondary_packaging: MaterialNode[];
    tree: MaterialNode[];
}

const props = defineProps<{
    show: boolean;
    bomId: number | null;
}>();

const emit = defineEmits(['close']);

const loading = ref(false);
const error = ref<string | null>(null);
const targetQty = ref<number>(1.0);
const activeTab = ref<'composition' | 'packaging' | 'tree' | 'all'>('composition');
const breakdownData = ref<BreakdownData | null>(null);
const copied = ref(false);

const quickQuantities = [1, 100, 500, 1000, 5000, 10000];

async function fetchBreakdown(qty: number = targetQty.value) {
    if (!props.bomId) return;
    loading.value = true;
    error.value = null;

    try {
        const res = await axios.get(route('scm.bom.breakdown', props.bomId), {
            params: { quantity: qty },
        });
        if (res.data.success) {
            breakdownData.value = res.data.data;
        } else {
            error.value = res.data.message || 'Gagal memuat breakdown BOM';
        }
    } catch (err: any) {
        error.value = err.response?.data?.message || err.message || 'Terjadi kesalahan jaringan';
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.show,
    (val) => {
        if (val && props.bomId) {
            targetQty.value = 1.0;
            activeTab.value = 'composition';
            fetchBreakdown(1.0);
        } else {
            breakdownData.value = null;
            error.value = null;
        }
    }
);

function setQuickQty(qty: number) {
    targetQty.value = qty;
    fetchBreakdown(qty);
}

function onQtyChange() {
    if (targetQty.value <= 0) targetQty.value = 1;
    fetchBreakdown(targetQty.value);
}

function formatNumber(num: number | undefined | null, decimals = 4): string {
    if (num === undefined || num === null || isNaN(num)) return '0';
    // If it's an integer
    if (Number.isInteger(num)) return num.toLocaleString('id-ID');
    // Format up to `decimals` without trailing useless zeros
    const str = num.toFixed(decimals);
    const trimmed = parseFloat(str).toString();
    const parts = trimmed.split('.');
    const intPart = parseInt(parts[0], 10).toLocaleString('id-ID');
    return parts.length > 1 ? `${intPart},${parts[1]}` : intPart;
}

function copyCompositionToClipboard() {
    if (!breakdownData.value) return;

    const b = breakdownData.value;
    let text = `=== BREAKDOWN BOM: ${b.product_name} (v${b.version}) ===\n`;
    text += `Target Quantity: ${formatNumber(b.target_quantity)} ${b.product_uom}\n\n`;

    text += `--- 1. KOMPOSISI BAHAN BAKU / FORMULA ---\n`;
    b.raw_materials.forEach((r, idx) => {
        const pct = r.composition_percentage !== null ? `(${r.composition_percentage}%)` : '';
        text += `${idx + 1}. ${r.material_name}: ${formatNumber(r.quantity, 4)} ${r.uom} ${pct}\n`;
    });
    text += `Total Bobot Formula: ${formatNumber(b.total_raw_material_weight, 4)} g/ml\n\n`;

    if (b.primary_packaging.length > 0) {
        text += `--- 2. KEMASAN PRIMER ---\n`;
        b.primary_packaging.forEach((p, idx) => {
            text += `${idx + 1}. ${p.material_name}: ${formatNumber(p.quantity, 4)} ${p.uom}\n`;
        });
        text += `\n`;
    }

    if (b.secondary_packaging.length > 0) {
        text += `--- 3. KEMASAN SEKUNDER & TERSIER ---\n`;
        b.secondary_packaging.forEach((s, idx) => {
            text += `${idx + 1}. ${s.material_name}: ${formatNumber(s.quantity, 4)} ${s.uom}\n`;
        });
    }

    navigator.clipboard.writeText(text).then(() => {
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2500);
    });
}
</script>

<template>
    <Modal :show="show" max-width="5xl" @close="emit('close')">
        <div class="flex max-h-[90vh] flex-col overflow-hidden bg-white text-gray-800 dark:bg-gray-900 dark:text-gray-100">
            <!-- Modal Header -->
            <div class="flex items-start justify-between border-b border-gray-200 bg-gradient-to-r from-emerald-50/70 via-teal-50/50 to-white px-6 py-4 dark:border-gray-800 dark:from-gray-800 dark:via-gray-850 dark:to-gray-900">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-600/10 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            Breakdown Komposisi 1 Pcs
                        </span>
                        <span v-if="breakdownData" class="rounded border border-gray-200 bg-gray-100 px-2 py-0.5 font-mono text-[11px] font-bold text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            v{{ breakdownData.version }}
                        </span>
                        <span v-if="breakdownData?.product_code" class="text-xs text-gray-500 dark:text-gray-400">
                            ({{ breakdownData.product_code }})
                        </span>
                    </div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {{ breakdownData?.product_name || 'Detail Komposisi & Kemasan BOM' }}
                    </h2>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    @click="emit('close')"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Simulator & Multiplier Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-6 py-3 dark:border-gray-800 dark:bg-gray-800/60">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="font-bold text-gray-600 dark:text-gray-300">Simulasi Output:</span>
                    <div class="flex items-center gap-1">
                        <input
                            v-model.number="targetQty"
                            type="number"
                            min="0.001"
                            step="any"
                            class="w-24 rounded-md border-gray-300 px-2 py-1 text-right font-mono text-xs font-bold text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                            @change="onQtyChange"
                            @keyup.enter="onQtyChange"
                        />
                        <span class="font-semibold text-gray-700 dark:text-gray-300">
                            {{ breakdownData?.product_uom || 'Pcs' }}
                        </span>
                    </div>

                    <!-- Quick Preset Buttons -->
                    <div class="ml-2 flex items-center gap-1">
                        <button
                            v-for="q in quickQuantities"
                            :key="q"
                            type="button"
                            :class="targetQty === q ? 'bg-emerald-600 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700'"
                            class="rounded px-2 py-0.5 text-[11px] transition active:scale-95"
                            @click="setQuickQty(q)"
                        >
                            {{ q === 1 ? '1 Pcs' : q.toLocaleString('id-ID') }}
                        </button>
                    </div>
                </div>

                <!-- Action Button: Copy Breakdown -->
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="!breakdownData || loading"
                        @click="copyCompositionToClipboard"
                        class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <svg v-if="!copied" class="h-3.5 w-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                        </svg>
                        <svg v-else class="h-3.5 w-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ copied ? 'Tersalin!' : 'Salin Data' }}</span>
                    </button>
                </div>
            </div>

            <!-- Loading & Error States -->
            <div v-if="loading" class="flex flex-col items-center justify-center p-12 text-gray-500">
                <svg class="h-8 w-8 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-3 text-xs font-semibold">Menguraikan komposisi multi-level BOM...</p>
            </div>

            <div v-else-if="error" class="p-6">
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                    <div class="flex items-center gap-2 font-bold">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Gagal memuat detail breakdown BOM</span>
                    </div>
                    <p class="mt-1">{{ error }}</p>
                </div>
            </div>

            <!-- Main Content Area -->
            <div v-else-if="breakdownData" class="flex-1 overflow-y-auto p-6 space-y-6">
                <!-- KPI Highlight Cards -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <!-- Total Formula Weight -->
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-3.5 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                        <div class="text-[11px] font-semibold text-emerald-800 dark:text-emerald-300">Bobot Formula Ruahan</div>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span class="text-xl font-extrabold text-emerald-900 dark:text-emerald-200">
                                {{ formatNumber(breakdownData.total_raw_material_weight, 3) }}
                            </span>
                            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">g / {{ targetQty === 1 ? 'pcs' : `${formatNumber(targetQty)} pcs` }}</span>
                        </div>
                        <div class="mt-1 text-[10px] text-emerald-600 dark:text-emerald-400">
                            {{ breakdownData.raw_materials.length }} Komponen Formula
                        </div>
                    </div>

                    <!-- Primary Packaging -->
                    <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-3.5 dark:border-blue-900/40 dark:bg-blue-950/20">
                        <div class="text-[11px] font-semibold text-blue-800 dark:text-blue-300">Kemasan Primer (WIP)</div>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span class="text-xl font-extrabold text-blue-900 dark:text-blue-200">
                                {{ breakdownData.primary_packaging.length }}
                            </span>
                            <span class="text-xs font-semibold text-blue-700 dark:text-blue-400">Komponen</span>
                        </div>
                        <div class="mt-1 text-[10px] text-blue-600 dark:text-blue-400">
                            Botol, Tutup, Plug, Dropper
                        </div>
                    </div>

                    <!-- Secondary Packaging -->
                    <div class="rounded-xl border border-amber-100 bg-amber-50/50 p-3.5 dark:border-amber-900/40 dark:bg-amber-950/20">
                        <div class="text-[11px] font-semibold text-amber-800 dark:text-amber-300">Kemasan Sekunder & Tersier</div>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span class="text-xl font-extrabold text-amber-900 dark:text-amber-200">
                                {{ breakdownData.secondary_packaging.length }}
                            </span>
                            <span class="text-xs font-semibold text-amber-700 dark:text-amber-400">Komponen</span>
                        </div>
                        <div class="mt-1 text-[10px] text-amber-600 dark:text-amber-400">
                            Dusbox, Label, Shrink, Karton
                        </div>
                    </div>

                    <!-- Multi-level Depth -->
                    <div class="rounded-xl border border-purple-100 bg-purple-50/50 p-3.5 dark:border-purple-900/40 dark:bg-purple-950/20">
                        <div class="text-[11px] font-semibold text-purple-800 dark:text-purple-300">Tahap Produksi</div>
                        <div class="mt-1 flex items-baseline gap-1">
                            <span class="text-xl font-extrabold text-purple-900 dark:text-purple-200">
                                3 Tahap
                            </span>
                        </div>
                        <div class="mt-1 text-[10px] text-purple-600 dark:text-purple-400">
                            Mixing → Filling → Packing
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-6 text-xs font-bold">
                        <button
                            type="button"
                            :class="activeTab === 'composition' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                            class="flex items-center gap-1.5 border-b-2 py-2.5 transition"
                            @click="activeTab = 'composition'"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <span>Komposisi Bahan Baku ({{ breakdownData.raw_materials.length }})</span>
                        </button>

                        <button
                            type="button"
                            :class="activeTab === 'packaging' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                            class="flex items-center gap-1.5 border-b-2 py-2.5 transition"
                            @click="activeTab = 'packaging'"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <span>Kemasan & Packaging ({{ breakdownData.primary_packaging.length + breakdownData.secondary_packaging.length }})</span>
                        </button>

                        <button
                            type="button"
                            :class="activeTab === 'tree' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                            class="flex items-center gap-1.5 border-b-2 py-2.5 transition"
                            @click="activeTab = 'tree'"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            <span>Pohon Multi-Level (BOM Tree)</span>
                        </button>

                        <button
                            type="button"
                            :class="activeTab === 'all' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                            class="flex items-center gap-1.5 border-b-2 py-2.5 transition"
                            @click="activeTab = 'all'"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Ringkasan Keseluruhan</span>
                        </button>
                    </nav>
                </div>

                <!-- TAB 1: RAW MATERIALS COMPOSITION -->
                <div v-if="activeTab === 'composition'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                Formula & Komposisi Bahan Baku (Ruahan)
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Kebutuhan netto bahan baku aktif & eksipien per 1 pcs produk jadi
                            </p>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                            Total: {{ formatNumber(breakdownData.total_raw_material_weight, 3) }} g / pcs
                        </span>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 font-semibold text-gray-600 dark:bg-gray-700/50 dark:text-gray-300">
                                <tr>
                                    <th class="w-10 px-3 py-2.5 text-center">#</th>
                                    <th class="px-3 py-2.5">Nama Bahan Baku</th>
                                    <th class="px-3 py-2.5 text-right">Kebutuhan</th>
                                    <th class="px-3 py-2.5 text-center">Satuan (UoM)</th>
                                    <th class="px-3 py-2.5 text-right">Komposisi Formula (%)</th>
                                    <th class="w-40 px-3 py-2.5">Rasio Visual</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr
                                    v-for="(item, idx) in breakdownData.raw_materials"
                                    :key="idx"
                                    class="hover:bg-gray-50/80 dark:hover:bg-gray-750/50"
                                >
                                    <td class="px-3 py-2.5 text-center font-mono text-gray-400">
                                        {{ idx + 1 }}
                                    </td>
                                    <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                        {{ item.material_name }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono font-bold text-gray-900 dark:text-emerald-400">
                                        {{ formatNumber(item.quantity, 4) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-center font-semibold text-gray-600 dark:text-gray-300">
                                        <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-[11px] dark:bg-gray-700">
                                            {{ item.uom }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono font-bold text-gray-800 dark:text-gray-200">
                                        {{ item.composition_percentage != null ? `${item.composition_percentage}%` : '-' }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div v-if="item.composition_percentage != null" class="flex items-center gap-2">
                                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                                <div
                                                    class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400"
                                                    :style="{ width: `${Math.min(100, item.composition_percentage || 0)}%` }"
                                                ></div>
                                            </div>
                                        </div>
                                        <span v-else class="text-[10px] text-gray-400">-</span>
                                    </td>
                                </tr>
                                <tr v-if="breakdownData.raw_materials.length === 0">
                                    <td colspan="6" class="px-3 py-6 text-center text-gray-400">
                                        Tidak ada bahan baku langsung pada formula ini
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t-2 border-gray-200 bg-gray-50/80 font-bold text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                <tr>
                                    <td colspan="2" class="px-3 py-2.5 text-right">
                                        Total Bobot Formula:
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono text-emerald-600 dark:text-emerald-400">
                                        {{ formatNumber(breakdownData.total_raw_material_weight, 4) }}
                                    </td>
                                    <td class="px-3 py-2.5 text-center font-mono">
                                        g / ml
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono">
                                        100.00%
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: PACKAGING (PRIMER & SEKUNDER) -->
                <div v-if="activeTab === 'packaging'" class="space-y-6">
                    <!-- Kemasan Primer -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-blue-900 dark:text-blue-300">
                                    1. Kemasan Primer (Tahap Filling / WIP)
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Material yang bersentuhan langsung dengan produk (botol, tutup, dropper, inner pouch, dll)
                                </p>
                            </div>
                            <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-bold text-blue-800 dark:bg-blue-950/60 dark:text-blue-300">
                                {{ breakdownData.primary_packaging.length }} Item
                            </span>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-gray-50 font-semibold text-gray-600 dark:bg-gray-700/50 dark:text-gray-300">
                                    <tr>
                                        <th class="w-10 px-3 py-2.5 text-center">#</th>
                                        <th class="px-3 py-2.5">Nama Kemasan</th>
                                        <th class="px-3 py-2.5 text-right">Kebutuhan (Qty)</th>
                                        <th class="px-3 py-2.5 text-center">Satuan (UoM)</th>
                                        <th class="px-3 py-2.5 text-center">Tipe Material</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="(p, idx) in breakdownData.primary_packaging"
                                        :key="idx"
                                        class="hover:bg-gray-50/80 dark:hover:bg-gray-750/50"
                                    >
                                        <td class="px-3 py-2.5 text-center font-mono text-gray-400">
                                            {{ idx + 1 }}
                                        </td>
                                        <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                            {{ p.material_name }}
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-mono font-bold text-blue-600 dark:text-blue-400">
                                            {{ formatNumber(p.quantity, 4) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-center font-semibold text-gray-600 dark:text-gray-300">
                                            <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-[11px] dark:bg-gray-700">
                                                {{ p.uom }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                                Kemasan Primer
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="breakdownData.primary_packaging.length === 0">
                                        <td colspan="5" class="px-3 py-6 text-center text-gray-400">
                                            Tidak ada kemasan primer tercatat pada BOM ini
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Kemasan Sekunder & Tersier -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-amber-900 dark:text-amber-300">
                                    2. Kemasan Sekunder & Tersier (Tahap Packing / FG)
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Material pembungkus luar, identitas & logistik (dusbox, label, plastik shrink, master box, lakban)
                                </p>
                            </div>
                            <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                {{ breakdownData.secondary_packaging.length }} Item
                            </span>
                        </div>

                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-gray-50 font-semibold text-gray-600 dark:bg-gray-700/50 dark:text-gray-300">
                                    <tr>
                                        <th class="w-10 px-3 py-2.5 text-center">#</th>
                                        <th class="px-3 py-2.5">Nama Kemasan</th>
                                        <th class="px-3 py-2.5 text-right">Kebutuhan (Qty)</th>
                                        <th class="px-3 py-2.5 text-center">Satuan (UoM)</th>
                                        <th class="px-3 py-2.5 text-center">Kategori</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="(s, idx) in breakdownData.secondary_packaging"
                                        :key="idx"
                                        class="hover:bg-gray-50/80 dark:hover:bg-gray-750/50"
                                    >
                                        <td class="px-3 py-2.5 text-center font-mono text-gray-400">
                                            {{ idx + 1 }}
                                        </td>
                                        <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                            {{ s.material_name }}
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-mono font-bold text-amber-600 dark:text-amber-400">
                                            {{ formatNumber(s.quantity, 4) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-center font-semibold text-gray-600 dark:text-gray-300">
                                            <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-[11px] dark:bg-gray-700">
                                                {{ s.uom }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                                Sekunder / Logistik
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="breakdownData.secondary_packaging.length === 0">
                                        <td colspan="5" class="px-3 py-6 text-center text-gray-400">
                                            Tidak ada kemasan sekunder tercatat pada BOM ini
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: TREE VIEW -->
                <div v-if="activeTab === 'tree'" class="space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                            Pohon Struktur Hirarki BOM (Multi-Level Explosion)
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Struktur bertingkat dari Finished Good → Primer / WIP → Ruahan Formula (Mixing)
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-4 font-mono text-xs dark:border-gray-700 dark:bg-gray-800/60">
                        <!-- Root FG -->
                        <div class="flex items-center gap-2 font-bold text-emerald-800 dark:text-emerald-400">
                            <span class="rounded bg-emerald-600 px-2 py-0.5 text-[10px] text-white">PRODUK JADI (FG)</span>
                            <span>{{ breakdownData.product_name }}</span>
                            <span class="text-gray-500">({{ formatNumber(breakdownData.target_quantity) }} {{ breakdownData.product_uom }})</span>
                        </div>

                        <!-- Tree Lines -->
                        <div class="mt-3 ml-4 space-y-2 border-l-2 border-gray-300 pl-4 dark:border-gray-600">
                            <div
                                v-for="(node, nIdx) in breakdownData.tree"
                                :key="nIdx"
                                class="space-y-1.5"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        :class="node.has_sub_bom ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300' : (node.category === 'primary_packaging' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300' : (node.category === 'secondary_packaging' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300'))"
                                        class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                                    >
                                        {{ node.has_sub_bom ? 'SUB-ASSEMBLY / WIP' : (node.category === 'primary_packaging' ? 'PRIMER' : (node.category === 'secondary_packaging' ? 'SEKUNDER' : 'BAHAN BAKU')) }}
                                    </span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ node.material_name }}</span>
                                    <span class="text-gray-500">—</span>
                                    <span class="font-bold text-gray-700 dark:text-gray-300">{{ formatNumber(node.quantity, 4) }} {{ node.uom }}</span>
                                </div>

                                <!-- Children Nodes if Sub-BOM exists -->
                                <div
                                    v-if="node.children && node.children.length > 0"
                                    class="ml-4 space-y-1 border-l-2 border-dashed border-indigo-300 pl-4 dark:border-indigo-600"
                                >
                                    <div
                                        v-for="(child, cIdx) in node.children"
                                        :key="cIdx"
                                        class="space-y-1"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                :class="child.has_sub_bom ? 'bg-purple-100 text-purple-800' : (child.category === 'primary_packaging' ? 'bg-blue-100 text-blue-800' : (child.category === 'secondary_packaging' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'))"
                                                class="rounded px-1.5 py-0.5 text-[9px] font-bold"
                                            >
                                                {{ child.has_sub_bom ? 'RUAHAN / BULK' : (child.category === 'primary_packaging' ? 'PRIMER' : 'BAHAN BAKU') }}
                                            </span>
                                            <span class="text-gray-800 dark:text-gray-200">{{ child.material_name }}</span>
                                            <span class="text-gray-400">—</span>
                                            <span class="font-bold text-gray-600 dark:text-gray-400">{{ formatNumber(child.quantity, 4) }} {{ child.uom }}</span>
                                        </div>

                                        <!-- Grandchildren (Raw formula in Ruahan) -->
                                        <div
                                            v-if="child.children && child.children.length > 0"
                                            class="ml-4 space-y-1 border-l-2 border-dotted border-emerald-300 pl-4 dark:border-emerald-600"
                                        >
                                            <div
                                                v-for="(gc, gIdx) in child.children"
                                                :key="gIdx"
                                                class="flex items-center gap-2 text-[11px]"
                                            >
                                                <span class="text-emerald-500">↳</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ gc.material_name }}</span>
                                                <span class="text-gray-400">:</span>
                                                <span class="font-bold text-emerald-700 dark:text-emerald-400">{{ formatNumber(gc.quantity, 4) }} {{ gc.uom }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: COMPLETE SUMMARY (ALL) -->
                <div v-if="activeTab === 'all'" class="space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                            Ringkasan Lengkap Material & Kemasan (1 Pcs)
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Semua komponen yang dibutuhkan untuk memproduksi {{ formatNumber(breakdownData.target_quantity) }} {{ breakdownData.product_uom }}
                        </p>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 font-semibold text-gray-600 dark:bg-gray-700/50 dark:text-gray-300">
                                <tr>
                                    <th class="w-10 px-3 py-2.5 text-center">#</th>
                                    <th class="px-3 py-2.5">Kategori</th>
                                    <th class="px-3 py-2.5">Nama Komponen / Material</th>
                                    <th class="px-3 py-2.5 text-right">Kebutuhan</th>
                                    <th class="px-3 py-2.5 text-center">Satuan (UoM)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <!-- Raw Materials -->
                                <tr
                                    v-for="(r, idx) in breakdownData.raw_materials"
                                    :key="'rm-' + idx"
                                    class="hover:bg-gray-50/80 dark:hover:bg-gray-750/50"
                                >
                                    <td class="px-3 py-2 text-center font-mono text-gray-400">R{{ idx + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            Bahan Baku
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ r.material_name }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ formatNumber(r.quantity, 4) }}</td>
                                    <td class="px-3 py-2 text-center font-semibold text-gray-600 dark:text-gray-300">{{ r.uom }}</td>
                                </tr>

                                <!-- Primary Packaging -->
                                <tr
                                    v-for="(p, idx) in breakdownData.primary_packaging"
                                    :key="'p-' + idx"
                                    class="hover:bg-gray-50/80 dark:hover:bg-gray-750/50"
                                >
                                    <td class="px-3 py-2 text-center font-mono text-gray-400">P{{ idx + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-800 dark:bg-blue-950/60 dark:text-blue-300">
                                            Kemasan Primer
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ p.material_name }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-blue-600 dark:text-blue-400">{{ formatNumber(p.quantity, 4) }}</td>
                                    <td class="px-3 py-2 text-center font-semibold text-gray-600 dark:text-gray-300">{{ p.uom }}</td>
                                </tr>

                                <!-- Secondary Packaging -->
                                <tr
                                    v-for="(s, idx) in breakdownData.secondary_packaging"
                                    :key="'s-' + idx"
                                    class="hover:bg-gray-50/80 dark:hover:bg-gray-750/50"
                                >
                                    <td class="px-3 py-2 text-center font-mono text-gray-400">S{{ idx + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            Kemasan Sekunder
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">{{ s.material_name }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-amber-600 dark:text-amber-400">{{ formatNumber(s.quantity, 4) }}</td>
                                    <td class="px-3 py-2 text-center font-semibold text-gray-600 dark:text-gray-300">{{ s.uom }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-6 py-3 dark:border-gray-800 dark:bg-gray-850">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">✓ Presisi Standar Produksi</span> — Dihitung secara otomatis dari multi-level Odoo BOM
                </div>
                <SecondaryButton @click="emit('close')">
                    Tutup
                </SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
