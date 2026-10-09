<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface WorkCenter {
    id: number;
    code: string;
    name: string;
    type: 'mixing' | 'filling' | 'secondary';
    standard_ct_seconds: number;
    fit_mp: number;
    shift_hours: number;
    saturday_shift_hours: number;
    is_active: boolean;
    notes: string | null;
    mesins_count: number;
    lines_count: number;
    weekly_plans_count: number;
}

const props = defineProps<{
    workCenters: {
        data: WorkCenter[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    search: string;
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const showCreate = ref(false);
const editItem = ref<WorkCenter | null>(null);
const deleteId = ref<number | null>(null);

const createForm = useForm({
    code: '',
    name: '',
    type: 'mixing',
    standard_ct_seconds: 0,
    fit_mp: 0,
    shift_hours: 6.5,
    is_active: true,
    notes: '',
});

const editForm = useForm({
    code: '',
    name: '',
    type: 'mixing',
    standard_ct_seconds: 0,
    fit_mp: 0,
    shift_hours: 6.5,
    is_active: true,
    notes: '',
});

function openCreate() {
    createForm.reset();
    showCreate.value = true;
}

function openEdit(item: WorkCenter) {
    editItem.value = item;
    editForm.code = item.code;
    editForm.name = item.name;
    editForm.type = item.type;
    editForm.standard_ct_seconds = item.standard_ct_seconds;
    editForm.fit_mp = item.fit_mp;
    editForm.shift_hours = item.shift_hours;
    editForm.is_active = item.is_active;
    editForm.notes = item.notes ?? '';
}

function submitCreate() {
    createForm.post(route('admin.work-center.store'), {
        onSuccess: () => {
            showCreate.value = false;
            createForm.reset();
        },
    });
}

function submitEdit() {
    if (!editItem.value) return;
    editForm.put(route('admin.work-center.update', editItem.value.id), {
        onSuccess: () => {
            editItem.value = null;
        },
    });
}

function openDelete(id: number) {
    deleteId.value = id;
}

function destroy() {
    if (deleteId.value) {
        router.delete(route('admin.work-center.destroy', deleteId.value), {
            onSuccess: () => {
                deleteId.value = null;
            },
        });
    }
}

const typeOptions = [
    { value: 'mixing', label: 'Mixing' },
    { value: 'filling', label: 'Filling' },
    { value: 'secondary', label: 'Secondary (Packing/Labeling)' },
];

const typeColor: Record<string, string> = {
    mixing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
    filling: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
    secondary: 'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800',
};

const typeIcon: Record<string, string> = {
    mixing: '🥣',
    filling: '🥤',
    secondary: '📦',
};
</script>

<template>
    <Head title="Work Center Management" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        Work Center Management
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Kelola Work Center (Mixing, Filling, Secondary) untuk perhitungan target berbasis CT
                    </p>
                </div>
                <PrimaryButton @click="openCreate" class="whitespace-nowrap">
                    + Tambah Work Center
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
            <div v-if="flash.success" class="mb-4 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm dark:border-emerald-800/80 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ flash.success }}
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 font-semibold uppercase tracking-wider text-gray-600 dark:bg-gray-900/60 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3 text-right">CT (s)</th>
                                <th class="px-4 py-3 text-right">MP</th>
                                <th class="px-4 py-3 text-right">Shift (j)</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Refs</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-if="props.workCenters.data.length === 0" class="text-center">
                                <td colspan="9" class="py-12 text-gray-400 dark:text-gray-500">
                                    Belum ada Work Center. Klik "Tambah Work Center" untuk memulai.
                                </td>
                            </tr>
                            <tr
                                v-for="wc in props.workCenters.data"
                                :key="wc.id"
                                class="hover:bg-gray-50/70 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-4 py-3 font-mono text-xs font-bold text-gray-900 dark:text-gray-100">
                                    {{ wc.code }}
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                    {{ wc.name }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-bold',
                                            typeColor[wc.type],
                                        ]"
                                    >
                                        <span>{{ typeIcon[wc.type] }}</span>
                                        {{ typeOptions.find(t => t.value === wc.type)?.label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-gray-100">
                                    {{ wc.standard_ct_seconds }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-gray-100">
                                    {{ wc.fit_mp }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-gray-100">
                                    {{ wc.shift_hours }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold',
                                            wc.is_active
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300'
                                                : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                        ]"
                                    >
                                        {{ wc.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-xs text-gray-500 dark:text-gray-400">
                                    <div>Mesin: {{ wc.mesins_count }}</div>
                                    <div>Line: {{ wc.lines_count }}</div>
                                    <div>Plan: {{ wc.weekly_plans_count }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 active:scale-95 dark:bg-indigo-950/60 dark:text-indigo-300"
                                            @click="openEdit(wc)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 active:scale-95 dark:bg-rose-950/60 dark:text-rose-300"
                                            @click="openDelete(wc.id)"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    <nav class="flex items-center justify-between" aria-label="Pagination">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Menampilkan {{ (props.workCenters.current_page - 1) * props.workCenters.per_page + 1 }} sampai
                            {{ Math.min(props.workCenters.current_page * props.workCenters.per_page, props.workCenters.total) }}
                            dari {{ props.workCenters.total }}
                        </div>
                        <div class="flex gap-1">
                            <button
                                v-if="props.workCenters.current_page > 1"
                                @click="router.get(route('admin.work-center.index'), { page: props.workCenters.current_page - 1 }, { preserveScroll: true })"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Sebelumnya
                            </button>
                            <button
                                v-if="props.workCenters.current_page < props.workCenters.last_page"
                                @click="router.get(route('admin.work-center.index'), { page: props.workCenters.current_page + 1 }, { preserveScroll: true })"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Selanjutnya
                            </button>
                        </div>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <Modal :show="showCreate" @close="showCreate = false">
            <template #title>Tambah Work Center</template>
            <template #content>
                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div>
                        <InputLabel value="Code" />
                        <TextInput
                            v-model="createForm.code"
                            class="mt-1 block w-full text-xs"
                            maxlength="50"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="createForm.errors.code" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Nama" />
                        <TextInput
                            v-model="createForm.name"
                            class="mt-1 block w-full text-xs"
                            maxlength="100"
                            required
                        />
                        <InputError :message="createForm.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Tipe" />
                        <select
                            v-model="createForm.type"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            required
                        >
                            <option value="mixing">Mixing</option>
                            <option value="filling">Filling</option>
                            <option value="secondary">Secondary (Packing/Labeling)</option>
                        </select>
                        <InputError :message="createForm.errors.type" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="CT (detik/pcs)" />
                            <TextInput
                                v-model="createForm.standard_ct_seconds"
                                type="number"
                                class="mt-1 block w-full text-xs"
                                min="0"
                                step="1"
                                required
                            />
                            <InputError :message="createForm.errors.standard_ct_seconds" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel value="MP Standar" />
                            <TextInput
                                v-model="createForm.fit_mp"
                                type="number"
                                class="mt-1 block w-full text-xs"
                                min="0"
                                step="1"
                                required
                            />
                            <InputError :message="createForm.errors.fit_mp" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Jam Shift" />
                            <TextInput
                                v-model="createForm.shift_hours"
                                type="number"
                                class="mt-1 block w-full text-xs"
                                min="0"
                                max="24"
                                step="0.5"
                                required
                            />
                            <InputError :message="createForm.errors.shift_hours" class="mt-1" />
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="createForm.is_active"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Aktif</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Catatan" />
                        <textarea
                            v-model="createForm.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                        ></textarea>
                    </div>
                </form>
            </template>
            <template #footer>
                <SecondaryButton @click="showCreate = false">Batal</SecondaryButton>
                <PrimaryButton :disabled="createForm.processing" type="submit" form="create-form">
                    {{ createForm.processing ? 'Menyimpan...' : 'Simpan' }}
                </PrimaryButton>
            </template>
        </Modal>

        <!-- Edit Modal -->
        <Modal :show="!!editItem" @close="editItem = null">
            <template #title>Edit Work Center</template>
            <template #content>
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <InputLabel value="Code" />
                        <TextInput
                            v-model="editForm.code"
                            class="mt-1 block w-full text-xs"
                            maxlength="50"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="editForm.errors.code" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Nama" />
                        <TextInput
                            v-model="editForm.name"
                            class="mt-1 block w-full text-xs"
                            maxlength="100"
                            required
                        />
                        <InputError :message="editForm.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Tipe" />
                        <select
                            v-model="editForm.type"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            required
                        >
                            <option value="mixing">Mixing</option>
                            <option value="filling">Filling</option>
                            <option value="secondary">Secondary (Packing/Labeling)</option>
                        </select>
                        <InputError :message="editForm.errors.type" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="CT (detik/pcs)" />
                            <TextInput
                                v-model="editForm.standard_ct_seconds"
                                type="number"
                                class="mt-1 block w-full text-xs"
                                min="0"
                                step="1"
                                required
                            />
                            <InputError :message="editForm.errors.standard_ct_seconds" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel value="MP Standar" />
                            <TextInput
                                v-model="editForm.fit_mp"
                                type="number"
                                class="mt-1 block w-full text-xs"
                                min="0"
                                step="1"
                                required
                            />
                            <InputError :message="editForm.errors.fit_mp" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Jam Shift" />
                            <TextInput
                                v-model="editForm.shift_hours"
                                type="number"
                                class="mt-1 block w-full text-xs"
                                min="0"
                                max="24"
                                step="0.5"
                                required
                            />
                            <InputError :message="editForm.errors.shift_hours" class="mt-1" />
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="editForm.is_active"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Aktif</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Catatan" />
                        <textarea
                            v-model="editForm.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                        ></textarea>
                    </div>
                </form>
            </template>
            <template #footer>
                <SecondaryButton @click="editItem = null">Batal</SecondaryButton>
                <PrimaryButton :disabled="editForm.processing" type="submit" form="edit-form">
                    {{ editForm.processing ? 'Menyimpan...' : 'Simpan' }}
                </PrimaryButton>
            </template>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="!!deleteId" @close="deleteId = null">
            <template #title>Hapus Work Center</template>
            <template #content>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Apakah Anda yakin ingin menghapus Work Center ini? Tindakan ini tidak dapat dibatalkan.
                </p>
            </template>
            <template #footer>
                <SecondaryButton @click="deleteId = null">Batal</SecondaryButton>
                <DangerButton @click="destroy" :disabled="deleteId === null">
                    Hapus
                </DangerButton>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>