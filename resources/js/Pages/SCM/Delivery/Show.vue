<script setup lang="ts">
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

interface Item {
    id: number;
    product_name: string;
    quantity: number;
    uom_name?: string | null;
    produk?: { nama_produk?: string } | null;
}
interface Plan {
    id: number;
    delivery_number: string;
    odoo_so_id?: number | null;
    customer_name: string;
    planned_date: string;
    status: string;
    driver_name?: string | null;
    revenue_coa?: string | null;
    on_time?: boolean | null;
    in_full?: boolean | null;
    damage_free?: boolean | null;
    doc_accuracy?: boolean | null;
    complaint?: boolean;
    actual_delivery_date?: string | null;
    notes?: string | null;
    fleet?: {
        nama_armada?: string;
        plat_number?: string | null;
        driver_name?: string | null;
    } | null;
    items: Item[];
}

const props = defineProps<{
    plan: Plan;
    fgChecks: Array<{
        product_name: string;
        requested: number;
        available_fg: number;
        sufficient: boolean;
    }>;
    allSufficient: boolean;
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const role = computed(() => (usePage().props as any).auth?.user?.role ?? '');
const canManage = computed(() =>
    ['warehouse_admin', 'superadmin', 'admin'].includes(role.value),
);

const statusForm = reactive({
    status: '',
    on_time: null as boolean | null,
    in_full: null as boolean | null,
    damage_free: null as boolean | null,
    doc_accuracy: null as boolean | null,
    complaint: false,
    actual_delivery_date: '',
    notes: '',
});

watch(
    () => props.plan.status,
    () => {
        statusForm.status = '';
    },
    { immediate: true },
);

function nextStatus(): string[] {
    const map: Record<string, string[]> = {
        planned: ['in_transit'],
        in_transit: ['delivered', 'delayed'],
        delivered: [],
        delayed: [],
    };
    return map[props.plan.status] ?? [];
}

function updateStatus() {
    if (!statusForm.status) return;
    router.put(
        route('scm.delivery.status', props.plan.id),
        {
            status: statusForm.status,
            on_time: statusForm.on_time,
            in_full: statusForm.in_full,
            damage_free: statusForm.damage_free,
            doc_accuracy: statusForm.doc_accuracy,
            complaint: statusForm.complaint,
            actual_delivery_date: statusForm.actual_delivery_date || null,
            notes: statusForm.notes || null,
        },
        { preserveScroll: true },
    );
}

function print(name: 'surat-jalan' | 'faktur' | 'coa') {
    if (
        !props.allSufficient &&
        props.plan.status !== 'delivered' &&
        props.plan.status !== 'delayed'
    ) {
        alert('Stok FG tidak mencukupi — cetak diblokir.');
        return;
    }
    window.open(route(`scm.delivery.print.${name}`, props.plan.id), '_blank');
}

const statusColor: Record<string, string> = {
    planned: 'bg-gray-100 text-gray-700',
    in_transit: 'bg-sky-100 text-sky-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    delayed: 'bg-red-100 text-red-800',
};
</script>

<template>
    <Head :title="plan.delivery_number" />
    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-bold text-gray-800 dark:text-gray-200"
                    >
                        {{ plan.delivery_number }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ plan.customer_name }} · {{ plan.planned_date }} ·
                        <span
                            :class="statusColor[plan.status]"
                            class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                            >{{ plan.status }}</span
                        >
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('scm.delivery.index')"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-300"
                        >Kembali</Link
                    >
                    <SecondaryButton
                        class="py-1.5 text-xs"
                        @click="print('surat-jalan')"
                        >Surat Jalan</SecondaryButton
                    >
                    <SecondaryButton
                        class="py-1.5 text-xs"
                        @click="print('faktur')"
                        >Faktur</SecondaryButton
                    >
                    <SecondaryButton
                        class="py-1.5 text-xs"
                        @click="print('coa')"
                        >CoA</SecondaryButton
                    >
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div
                v-if="flash.success"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ flash.success }}
            </div>
            <div
                v-if="flash.error"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"
            >
                {{ flash.error }}
            </div>

            <!-- FG Stock Check -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
            >
                <h3 class="mb-3 text-sm font-semibold uppercase text-gray-500">
                    Validasi Stok FG
                </h3>
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700/60">
                        <tr>
                            <th class="px-2 py-2 text-left">Produk</th>
                            <th class="px-2 py-2 text-right">Diminta</th>
                            <th class="px-2 py-2 text-right">Tersedia</th>
                            <th class="px-2 py-2 text-center">Cukup?</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-700"
                    >
                        <tr v-for="c in fgChecks" :key="c.product_name">
                            <td class="px-2 py-2">{{ c.product_name }}</td>
                            <td class="px-2 py-2 text-right font-mono">
                                {{ c.requested }}
                            </td>
                            <td class="px-2 py-2 text-right font-mono">
                                {{ c.available_fg }}
                            </td>
                            <td class="px-2 py-2 text-center">
                                <span
                                    :class="
                                        c.sufficient
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-red-100 text-red-800'
                                    "
                                    class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                    >{{ c.sufficient ? 'OK' : 'Kurang' }}</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
            >
                <h3 class="mb-3 text-sm font-semibold uppercase text-gray-500">
                    Item & Info
                </h3>
                <div class="mb-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                    <div>
                        <span class="text-gray-500">Armada</span>
                        <div class="font-medium">
                            {{ plan.fleet?.nama_armada ?? '-' }} ({{
                                plan.fleet?.plat_number ?? '-'
                            }})
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-500">Driver</span>
                        <div class="font-medium">
                            {{
                                plan.driver_name ??
                                plan.fleet?.driver_name ??
                                '-'
                            }}
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-500">COA Revenue</span>
                        <div class="font-mono">
                            {{ plan.revenue_coa ?? '-' }}
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-500">Odoo SO</span>
                        <div class="font-mono">
                            {{ plan.odoo_so_id ?? '-' }}
                        </div>
                    </div>
                </div>
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700/60">
                        <tr>
                            <th class="px-2 py-2 text-left">Produk</th>
                            <th class="px-2 py-2 text-right">Qty</th>
                            <th class="px-2 py-2 text-left">UOM</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-700"
                    >
                        <tr v-for="it in plan.items" :key="it.id">
                            <td class="px-2 py-2">{{ it.product_name }}</td>
                            <td class="px-2 py-2 text-right font-mono">
                                {{ it.quantity }}
                            </td>
                            <td class="px-2 py-2">{{ it.uom_name ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Status flow -->
            <div
                v-if="canManage && nextStatus().length"
                class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
            >
                <h3 class="mb-3 text-sm font-semibold uppercase text-gray-500">
                    Update Status
                </h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="text-xs text-gray-500">Status baru</label>
                        <select
                            v-model="statusForm.status"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <option value="" disabled>Pilih</option>
                            <option
                                v-for="s in nextStatus()"
                                :key="s"
                                :value="s"
                            >
                                {{ s }}
                            </option>
                        </select>
                    </div>
                    <div v-if="statusForm.status === 'delivered'">
                        <label class="text-xs text-gray-500"
                            >Tanggal aktual</label
                        >
                        <input
                            v-model="statusForm.actual_delivery_date"
                            type="date"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        />
                    </div>
                    <div v-if="statusForm.status === 'delayed'">
                        <label class="text-xs text-gray-500"
                            >Tanggal aktual</label
                        >
                        <input
                            v-model="statusForm.actual_delivery_date"
                            type="date"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        />
                    </div>
                    <div
                        v-if="statusForm.status === 'delivered'"
                        class="flex flex-wrap items-center gap-3 pt-4 text-xs"
                    >
                        <label class="flex items-center gap-1"
                            ><input
                                v-model="statusForm.on_time"
                                type="checkbox"
                            />
                            On-Time</label
                        >
                        <label class="flex items-center gap-1"
                            ><input
                                v-model="statusForm.in_full"
                                type="checkbox"
                            />
                            In-Full</label
                        >
                        <label class="flex items-center gap-1"
                            ><input
                                v-model="statusForm.damage_free"
                                type="checkbox"
                            />
                            Damage-Free</label
                        >
                        <label class="flex items-center gap-1"
                            ><input
                                v-model="statusForm.doc_accuracy"
                                type="checkbox"
                            />
                            Doc OK</label
                        >
                        <label class="flex items-center gap-1"
                            ><input
                                v-model="statusForm.complaint"
                                type="checkbox"
                            />
                            Complaint</label
                        >
                    </div>
                </div>
                <div class="mt-3">
                    <PrimaryButton
                        class="py-1.5 text-xs"
                        :disabled="!statusForm.status"
                        @click="updateStatus"
                        >Simpan Status</PrimaryButton
                    >
                </div>
            </div>

            <div
                v-if="['delivered', 'delayed'].includes(plan.status)"
                class="rounded-xl border border-gray-200 bg-white p-4 text-xs dark:border-gray-700 dark:bg-gray-800"
            >
                <span class="text-gray-500">Actual delivery:</span>
                <strong>{{ plan.actual_delivery_date ?? '-' }}</strong>
                <span v-if="plan.on_time !== null" class="ml-3"
                    >OTD:
                    <strong
                        :class="
                            plan.on_time ? 'text-emerald-600' : 'text-red-600'
                        "
                        >{{ plan.on_time ? 'OK' : 'Late' }}</strong
                    ></span
                >
                <span
                    v-if="plan.complaint"
                    class="ml-3 font-semibold text-red-600"
                    >Complaint</span
                >
            </div>
        </div>
    </AuthenticatedLayout>
</template>
