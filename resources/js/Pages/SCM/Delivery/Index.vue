<script setup lang="ts">
import Pagination from '@/Components/Pagination.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Plan {
    id: number;
    delivery_number: string;
    customer_name: string;
    planned_date: string;
    status: string;
    actual_delivery_date?: string | null;
    on_time?: boolean | null;
    in_full?: boolean | null;
    damage_free?: boolean | null;
    doc_accuracy?: boolean | null;
    complaint?: boolean;
    fleet?: { nama_armada?: string } | null;
    items?: Array<{ product_name: string; quantity: number }>;
}

const props = defineProps<{
    plans: {
        data: Plan[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    status: string | null;
    view: string;
    fleets: Array<{
        id: number;
        nama_armada: string;
        plat_number?: string | null;
        driver_name?: string | null;
    }>;
    kpi: {
        otd: number;
        in_full: number;
        damage_free: number;
        doc_accuracy: number;
        complaint_rate: number;
    };
    coaRevenue: string[];
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const status = ref(props.status ?? '');
const view = ref(props.view ?? 'table');
const canManage = computed(() =>
    ['warehouse_admin', 'superadmin', 'admin', 'ppic'].includes(
        (usePage().props as any).auth?.user?.role ?? '',
    ),
);

const statusColor: Record<string, string> = {
    planned: 'bg-gray-100 text-gray-700',
    in_transit: 'bg-sky-100 text-sky-800',
    delivered: 'bg-emerald-100 text-emerald-800',
    delayed: 'bg-red-100 text-red-800',
};

const weekDays = computed(() => {
    const start = new Date();
    start.setHours(0, 0, 0, 0);
    const day = start.getDay();
    start.setDate(start.getDate() - day + (day === 0 ? -6 : 1));
    return Array.from({ length: 5 }, (_, i) => {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        return {
            date: d.toISOString().slice(0, 10),
            label: d.toLocaleDateString('id-ID', {
                weekday: 'short',
                day: 'numeric',
                month: 'short',
            }),
            plans: props.plans.data.filter(
                (p) => p.planned_date === d.toISOString().slice(0, 10),
            ),
        };
    });
});

function applyFilter() {
    router.get(
        route('scm.delivery.index'),
        { status: status.value || undefined, view: view.value },
        { preserveState: true },
    );
}

function syncOdoo() {
    router.post(route('scm.delivery.sync-odoo'), {}, { preserveScroll: true });
}

function goToPage(page: number) {
    router.get(
        route('scm.delivery.index'),
        { page, status: status.value || undefined, view: view.value },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="Delivery Schedule" />
    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-bold text-gray-800 dark:text-gray-200"
                    >
                        Plan Delivery Schedule
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        COA revenue: {{ coaRevenue.join(' / ') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        :href="route('scm.delivery.export.performance')"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
                        >Export Performance</a
                    >
                    <SecondaryButton
                        v-if="canManage"
                        class="py-1.5 text-xs"
                        @click="syncOdoo"
                        >Sync SO Odoo</SecondaryButton
                    >
                    <Link
                        v-if="canManage"
                        :href="route('scm.delivery.create')"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
                        >+ Delivery</Link
                    >
                    <div
                        class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5 dark:border-gray-600 dark:bg-gray-800"
                    >
                        <button
                            type="button"
                            class="rounded-md px-3 py-1 text-xs font-semibold"
                            :class="
                                view === 'table'
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-gray-600 dark:text-gray-300'
                            "
                            @click="
                                view = 'table';
                                applyFilter();
                            "
                        >
                            Table
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-1 text-xs font-semibold"
                            :class="
                                view === 'calendar'
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-gray-600 dark:text-gray-300'
                            "
                            @click="
                                view = 'calendar';
                                applyFilter();
                            "
                        >
                            Calendar
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
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

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">On-Time Delivery</div>
                    <div
                        class="text-xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ kpi.otd }}%
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">In-Full</div>
                    <div
                        class="text-xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ kpi.in_full }}%
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">Damage-Free</div>
                    <div
                        class="text-xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ kpi.damage_free }}%
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">Doc Accuracy</div>
                    <div
                        class="text-xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ kpi.doc_accuracy }}%
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">Complaint Rate</div>
                    <div
                        class="text-xl font-bold"
                        :class="
                            kpi.complaint_rate > 5
                                ? 'text-red-600'
                                : 'text-gray-900 dark:text-gray-100'
                        "
                    >
                        {{ kpi.complaint_rate }}%
                    </div>
                </div>
            </div>

            <select
                v-model="status"
                class="rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                @change="applyFilter"
            >
                <option value="">Semua status</option>
                <option
                    v-for="s in [
                        'planned',
                        'in_transit',
                        'delivered',
                        'delayed',
                    ]"
                    :key="s"
                    :value="s"
                >
                    {{ s }}
                </option>
            </select>

            <!-- Calendar view -->
            <div
                v-if="view === 'calendar'"
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5"
            >
                <div
                    v-for="d in weekDays"
                    :key="d.date"
                    class="rounded-xl border border-gray-200 bg-gray-50/60 p-3 dark:border-gray-700 dark:bg-gray-800/50"
                >
                    <div
                        class="mb-2 text-xs font-bold text-gray-800 dark:text-gray-200"
                    >
                        {{ d.label }}
                    </div>
                    <div class="space-y-2">
                        <p
                            v-if="d.plans.length === 0"
                            class="py-4 text-center text-[11px] text-gray-400"
                        >
                            —
                        </p>
                        <Link
                            v-for="p in d.plans"
                            :key="p.id"
                            :href="route('scm.delivery.show', p.id)"
                            class="block rounded-lg border border-gray-200 bg-white p-2 shadow-sm hover:border-indigo-400 dark:border-gray-600 dark:bg-gray-700/60"
                        >
                            <div
                                class="font-mono text-[11px] font-bold text-indigo-700 dark:text-indigo-300"
                            >
                                {{ p.delivery_number }}
                            </div>
                            <div
                                class="text-xs font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ p.customer_name }}
                            </div>
                            <span
                                :class="statusColor[p.status]"
                                class="mt-1 inline-block rounded px-1.5 py-0.5 text-[9px] font-bold uppercase"
                                >{{ p.status }}</span
                            >
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Table view -->
            <div
                v-else
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700/60">
                        <tr>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                No
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Pelanggan
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Rencana
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Armada
                            </th>
                            <th
                                class="px-3 py-2 text-center font-semibold text-gray-500"
                            >
                                Status
                            </th>
                            <th
                                class="px-3 py-2 text-center font-semibold text-gray-500"
                            >
                                KPI
                            </th>
                            <th
                                class="px-3 py-2 text-center font-semibold text-gray-500"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-700"
                    >
                        <tr v-for="p in plans.data" :key="p.id">
                            <td
                                class="px-3 py-2 font-mono font-semibold text-gray-900 dark:text-gray-100"
                            >
                                {{ p.delivery_number }}
                            </td>
                            <td class="px-3 py-2">{{ p.customer_name }}</td>
                            <td class="px-3 py-2">{{ p.planned_date }}</td>
                            <td class="px-3 py-2">
                                {{ p.fleet?.nama_armada ?? '-' }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span
                                    :class="statusColor[p.status]"
                                    class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                    >{{ p.status }}</span
                                >
                            </td>
                            <td class="px-3 py-2 text-center text-[10px]">
                                <span
                                    v-if="p.on_time !== null"
                                    :class="
                                        p.on_time
                                            ? 'text-emerald-600'
                                            : 'text-red-600'
                                    "
                                    >OTD
                                </span>
                                <span
                                    v-if="p.in_full !== null"
                                    :class="
                                        p.in_full
                                            ? 'text-emerald-600'
                                            : 'text-red-600'
                                    "
                                    >IF
                                </span>
                                <span
                                    v-if="p.damage_free !== null"
                                    :class="
                                        p.damage_free
                                            ? 'text-emerald-600'
                                            : 'text-red-600'
                                    "
                                    >DF
                                </span>
                                <span v-if="p.complaint" class="text-red-600"
                                    >Cmp</span
                                >
                                <span
                                    v-if="
                                        p.on_time === null &&
                                        p.in_full === null &&
                                        !p.complaint
                                    "
                                    class="text-gray-400"
                                    >-</span
                                >
                            </td>
                            <td class="px-3 py-2 text-center">
                                <Link
                                    :href="route('scm.delivery.show', p.id)"
                                    class="font-semibold text-indigo-600 hover:underline"
                                    >Buka</Link
                                >
                            </td>
                        </tr>
                        <tr v-if="plans.data.length === 0">
                            <td
                                colspan="7"
                                class="px-3 py-8 text-center text-gray-400"
                            >
                                Belum ada delivery plan
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div
                    class="border-t border-gray-200 px-4 py-2 dark:border-gray-700"
                >
                    <Pagination
                        :current-page="plans.current_page"
                        :last-page="plans.last_page"
                        :total="plans.total"
                        :per-page="plans.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
