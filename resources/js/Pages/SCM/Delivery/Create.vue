<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface Fleet {
    id: number;
    nama_armada: string;
    driver_name?: string | null;
}
interface Produk {
    id: number;
    nama_produk: string;
}

const props = defineProps<{
    fleets: Fleet[];
    produks: Produk[];
    coaRevenue: string[];
}>();

const form = useForm({
    customer_name: '',
    planned_date: new Date().toISOString().slice(0, 10),
    fleet_id: '',
    driver_name: '',
    revenue_coa: props.coaRevenue[0] ?? '',
    notes: '',
    items: [
        { produk_id: '', product_name: '', quantity: '', uom_name: 'PCS' },
    ] as Array<{
        produk_id: string;
        product_name: string;
        quantity: string;
        uom_name: string;
    }>,
});

function addItem() {
    form.items.push({
        produk_id: '',
        product_name: '',
        quantity: '',
        uom_name: 'PCS',
    });
}

function onProdukChange(idx: number) {
    const p = props.produks.find(
        (x) => String(x.id) === form.items[idx].produk_id,
    );
    if (p) form.items[idx].product_name = p.nama_produk;
}

function onFleetChange() {
    const f = props.fleets.find((x) => String(x.id) === String(form.fleet_id));
    if (f?.driver_name) form.driver_name = f.driver_name;
}

function submit() {
    form.post(route('scm.delivery.store'));
}
</script>

<template>
    <Head title="Delivery Baru" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-lg font-bold text-gray-800 dark:text-gray-200"
                    >
                        Delivery Plan Baru
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        COA revenue: {{ coaRevenue.join(' / ') }}
                    </p>
                </div>
                <Link
                    :href="route('scm.delivery.index')"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-300"
                    >Kembali</Link
                >
            </div>
        </template>

        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form class="space-y-6" @submit.prevent="submit">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
                >
                    <h3
                        class="mb-4 text-sm font-semibold uppercase text-gray-500"
                    >
                        Info Delivery
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Pelanggan" />
                            <TextInput
                                v-model="form.customer_name"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                :message="form.errors.customer_name"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Tanggal Rencana" />
                            <TextInput
                                v-model="form.planned_date"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                :message="form.errors.planned_date"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Armada" />
                            <select
                                v-model="form.fleet_id"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                @change="onFleetChange"
                            >
                                <option value="">— pilih armada —</option>
                                <option
                                    v-for="f in fleets"
                                    :key="f.id"
                                    :value="f.id"
                                >
                                    {{ f.nama_armada }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Driver" />
                            <TextInput
                                v-model="form.driver_name"
                                class="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel value="COA Revenue" />
                            <select
                                v-model="form.revenue_coa"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                            >
                                <option
                                    v-for="c in coaRevenue"
                                    :key="c"
                                    :value="c"
                                >
                                    {{ c }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Notes" />
                            <TextInput
                                v-model="form.notes"
                                class="mt-1 block w-full"
                            />
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <h3
                            class="text-sm font-semibold uppercase text-gray-500"
                        >
                            Item Pengiriman
                        </h3>
                        <SecondaryButton
                            type="button"
                            class="py-1 text-xs"
                            @click="addItem"
                            >+ Item</SecondaryButton
                        >
                    </div>
                    <div
                        v-for="(item, idx) in form.items"
                        :key="idx"
                        class="mb-2 grid grid-cols-12 items-end gap-2"
                    >
                        <div class="col-span-4">
                            <select
                                v-model="item.produk_id"
                                class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                @change="onProdukChange(idx)"
                            >
                                <option value="">— FG —</option>
                                <option
                                    v-for="p in produks"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.nama_produk }}
                                </option>
                            </select>
                        </div>
                        <div class="col-span-4">
                            <TextInput
                                v-model="item.product_name"
                                class="block w-full"
                                placeholder="Nama produk"
                                required
                            />
                        </div>
                        <div class="col-span-2">
                            <TextInput
                                v-model="item.quantity"
                                type="number"
                                step="0.001"
                                min="0.001"
                                class="block w-full"
                                placeholder="Qty"
                                required
                            />
                        </div>
                        <div class="col-span-2">
                            <TextInput
                                v-model="item.uom_name"
                                class="block w-full"
                                placeholder="UOM"
                            />
                        </div>
                    </div>
                    <InputError
                        :message="form.errors.items as string"
                        class="mt-2"
                    />
                </div>

                <PrimaryButton
                    :disabled="form.processing"
                    class="w-full justify-center"
                    >Simpan Delivery Plan</PrimaryButton
                >
            </form>
        </div>
    </AuthenticatedLayout>
</template>
