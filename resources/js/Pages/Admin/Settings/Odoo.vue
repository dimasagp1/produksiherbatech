<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import OdooProductSyncModal from '@/Components/OdooProductSyncModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps<{
    settings: {
        odoo_host: string;
        odoo_db: string;
        odoo_username: string;
        odoo_api_key: string;
        odoo_timeout: number;
    };
    stats: {
        synced_products: number;
        total_products: number;
        last_synced_at: string | null;
    };
}>();

const form = useForm({
    odoo_host: props.settings.odoo_host || '',
    odoo_db: props.settings.odoo_db || '',
    odoo_username: props.settings.odoo_username || '',
    odoo_api_key: props.settings.odoo_api_key || '',
    odoo_timeout: props.settings.odoo_timeout || 15,
});

const showApiKey = ref(false);
const isTesting = ref(false);
const testResult = ref<{
    success?: boolean;
    message?: string;
    server_version?: string;
    uid?: number;
} | null>(null);
const showSyncModal = ref(false);
const syncSuccessMessage = ref<string | null>(null);
const showResetModal = ref(false);
const isResetting = ref(false);
const resetErrorMessage = ref<string | null>(null);

function submit() {
    form.post(route('admin.odoo.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            testResult.value = null;
        },
    });
}

async function runTestConnection() {
    isTesting.value = true;
    testResult.value = null;
    try {
        const response = await axios.post(route('admin.odoo.test-connection'), {
            odoo_host: form.odoo_host,
            odoo_db: form.odoo_db,
            odoo_username: form.odoo_username,
            odoo_api_key: form.odoo_api_key,
            odoo_timeout: form.odoo_timeout,
        });
        testResult.value = response.data;
    } catch (err: any) {
        testResult.value = {
            success: false,
            message:
                err.response?.data?.message ||
                err.message ||
                'Gagal menghubungi server.',
        };
    } finally {
        isTesting.value = false;
    }
}

function handleSynced(result: any) {
    syncSuccessMessage.value = result.message;
    router.reload({ only: ['stats'] });
}

async function handleResetProducts() {
    isResetting.value = true;
    resetErrorMessage.value = null;
    try {
        const res = await axios.post(route('admin.odoo.reset-products'));
        if (res.data.success) {
            syncSuccessMessage.value = res.data.message;
            showResetModal.value = false;
            router.reload({ only: ['stats'] });
        } else {
            resetErrorMessage.value =
                res.data.message || 'Gagal mereset produk.';
        }
    } catch (err: any) {
        resetErrorMessage.value =
            err.response?.data?.message ||
            err.message ||
            'Gagal menghubungi server.';
    } finally {
        isResetting.value = false;
    }
}

function formatDate(iso: string | null) {
    if (!iso) return 'Belum pernah';
    return new Date(iso).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Pengaturan Odoo ERP" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-bold leading-tight text-gray-800 dark:text-gray-200 sm:text-xl"
                    >
                        Integrasi Odoo ERP
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Konfigurasi koneksi langsung ke Odoo untuk sinkronisasi
                        Produk & Reject Scrap
                    </p>
                </div>
                <Link
                    :href="route('admin.produk.index')"
                    class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    ← Kembali ke Master Produk
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-3 py-2 sm:px-6 sm:py-4">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- FORM SETTINGS (2 COLS) -->
                <div class="space-y-6 lg:col-span-2">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div
                            class="mb-5 flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-700"
                        >
                            <div>
                                <h3
                                    class="text-base font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    Kredensial Server Odoo
                                </h3>
                                <p
                                    class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Pengaturan ini disimpan di database dan
                                    otomatis menimpa file .env
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-600/20 dark:bg-purple-950/50 dark:text-purple-300"
                            >
                                JSON-RPC v2.0
                            </span>
                        </div>

                        <form @submit.prevent="submit" class="space-y-4">
                            <!-- Host URL -->
                            <div>
                                <InputLabel
                                    for="odoo_host"
                                    value="Odoo Server URL"
                                />
                                <div class="relative mt-1">
                                    <TextInput
                                        id="odoo_host"
                                        v-model="form.odoo_host"
                                        type="url"
                                        class="block w-full font-mono text-sm"
                                        placeholder="https://perusahaan.odoo.com atau http://192.168.1.100:8069"
                                        required
                                    />
                                </div>
                                <p
                                    class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                                >
                                    Gunakan protokol lengkap (http:// atau
                                    https://). Jangan akhiri dengan garis miring
                                    (/)
                                </p>
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.odoo_host"
                                />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <!-- Database Name -->
                                <div>
                                    <InputLabel
                                        for="odoo_db"
                                        value="Nama Database Odoo"
                                    />
                                    <TextInput
                                        id="odoo_db"
                                        v-model="form.odoo_db"
                                        type="text"
                                        class="mt-1 block w-full font-mono text-sm"
                                        placeholder="contoh: odoo_production"
                                        required
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="form.errors.odoo_db"
                                    />
                                </div>

                                <!-- Username / Email -->
                                <div>
                                    <InputLabel
                                        for="odoo_username"
                                        value="Username / Email Login"
                                    />
                                    <TextInput
                                        id="odoo_username"
                                        v-model="form.odoo_username"
                                        type="text"
                                        class="mt-1 block w-full text-sm"
                                        placeholder="admin@herbatech.com"
                                        required
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="form.errors.odoo_username"
                                    />
                                </div>
                            </div>

                            <!-- API Key / Password -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <InputLabel
                                        for="odoo_api_key"
                                        value="API Key / Password Odoo"
                                    />
                                    <button
                                        type="button"
                                        @click="showApiKey = !showApiKey"
                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                    >
                                        {{
                                            showApiKey
                                                ? 'Sembunyikan'
                                                : 'Tampilkan Kunci'
                                        }}
                                    </button>
                                </div>
                                <div class="relative mt-1">
                                    <TextInput
                                        id="odoo_api_key"
                                        v-model="form.odoo_api_key"
                                        :type="showApiKey ? 'text' : 'password'"
                                        class="block w-full pr-10 font-mono text-sm"
                                        placeholder="Ketik atau paste API Key Odoo di sini..."
                                    />
                                </div>
                                <p
                                    class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                                >
                                    Direkomendasikan menggunakan
                                    <b>API Key</b> resmi dari akun user Odoo.
                                </p>
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.odoo_api_key"
                                />
                            </div>

                            <div
                                class="grid grid-cols-1 gap-4 pt-2 sm:grid-cols-2"
                            >
                                <!-- Timeout -->
                                <div>
                                    <InputLabel
                                        for="odoo_timeout"
                                        value="Timeout Request (Detik)"
                                    />
                                    <TextInput
                                        id="odoo_timeout"
                                        v-model.number="form.odoo_timeout"
                                        type="number"
                                        min="5"
                                        max="60"
                                        class="mt-1 block w-full text-sm"
                                    />
                                    <InputError
                                        class="mt-1"
                                        :message="form.errors.odoo_timeout"
                                    />
                                </div>

                                <!-- One-Way Integration Notice -->
                                <div class="flex items-center pt-6">
                                    <div
                                        class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800 dark:border-blue-900/40 dark:bg-blue-950/30 dark:text-blue-300"
                                    >
                                        <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Komunikasi 1 Arah: Sistem Produksi hanya menarik data dari Odoo (Read-Only).</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Test Result Alert Box -->
                            <div
                                v-if="testResult"
                                class="mt-4 rounded-lg p-3.5 text-xs transition"
                                :class="
                                    testResult.success
                                        ? 'border border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'border border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300'
                                "
                            >
                                <div class="flex items-start gap-2">
                                    <span class="text-base leading-none">{{
                                        testResult.success ? '✓' : '⚠️'
                                    }}</span>
                                    <div>
                                        <p class="font-semibold">
                                            {{
                                                testResult.success
                                                    ? 'Tes Koneksi Berhasil!'
                                                    : 'Tes Koneksi Gagal'
                                            }}
                                        </p>
                                        <p class="mt-0.5 opacity-90">
                                            {{ testResult.message }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div
                                class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-5 dark:border-gray-700"
                            >
                                <button
                                    type="button"
                                    @click="runTestConnection"
                                    :disabled="isTesting"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                >
                                    <svg
                                        :class="[
                                            'h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400',
                                            isTesting ? 'animate-spin' : '',
                                        ]"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="2"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z"
                                        />
                                    </svg>
                                    {{
                                        isTesting
                                            ? 'Menguji Koneksi...'
                                            : '⚡ Uji Koneksi Sekarang'
                                    }}
                                </button>

                                <PrimaryButton :disabled="form.processing">
                                    {{
                                        form.processing
                                            ? 'Menyimpan...'
                                            : 'Simpan Pengaturan'
                                    }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- SIDEBAR STATUS & PANDUAN (1 COL) -->
                <div class="space-y-6">
                    <!-- STATUS CARD -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100"
                        >
                            Status Sinkronisasi
                        </h3>
                        <div class="space-y-3 text-xs">
                            <div
                                class="flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-700"
                            >
                                <span class="text-gray-500 dark:text-gray-400"
                                    >Produk Terhubung:</span
                                >
                                <span
                                    class="font-mono font-bold text-gray-900 dark:text-gray-100"
                                    >{{ stats.synced_products }} /
                                    {{ stats.total_products }} produk</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-700"
                            >
                                <span class="text-gray-500 dark:text-gray-400"
                                    >Sinkron Terakhir:</span
                                >
                                <span
                                    class="font-medium text-gray-800 dark:text-gray-200"
                                    >{{
                                        formatDate(stats.last_synced_at)
                                    }}</span
                                >
                            </div>
                            <div
                                v-if="syncSuccessMessage"
                                class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-2.5 text-[11px] text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                            >
                                ✓ {{ syncSuccessMessage }}
                            </div>
                        </div>

                        <div class="mt-5 space-y-2.5">
                            <button
                                @click="showSyncModal = true"
                                type="button"
                                class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-purple-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:bg-purple-500 dark:hover:bg-purple-600"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
                                    />
                                </svg>
                                ⚡ Tarik & Pilih Master Produk Odoo
                            </button>

                            <button
                                v-if="stats.synced_products > 0"
                                @click="showResetModal = true"
                                type="button"
                                class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50/50 px-4 py-2 text-xs font-semibold text-red-700 shadow-sm transition hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300 dark:hover:bg-red-900/40"
                            >
                                <svg
                                    class="h-3.5 w-3.5 text-red-500"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                                    />
                                </svg>
                                Hapus / Reset Produk Odoo ({{
                                    stats.synced_products
                                }})
                            </button>
                        </div>
                    </div>

                    <!-- PANDUAN API KEY -->
                    <div
                        class="rounded-xl border border-gray-200 bg-slate-50 p-5 dark:border-gray-700 dark:bg-gray-800/60"
                    >
                        <h3
                            class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300"
                        >
                            💡 Cara Buat API Key di Odoo
                        </h3>
                        <ol
                            class="list-inside list-decimal space-y-1.5 text-[11.5px] leading-relaxed text-gray-600 dark:text-gray-400"
                        >
                            <li>Buka aplikasi <b>Odoo ERP</b> Anda.</li>
                            <li>
                                Klik foto profil di kanan atas $\rightarrow$
                                <b>Preferences / My Profile</b>.
                            </li>
                            <li>Pilih tab <b>Account Security</b>.</li>
                            <li>
                                Pada bagian <i>API Keys</i>, klik
                                <b>New API Key</b>.
                            </li>
                            <li>
                                Beri nama (misal: <code>LinePulse</code>) lalu
                                salin kode kunci ke formulir di sebelah kiri.
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- POPUP MODAL PILIH PRODUK ODOO -->
        <OdooProductSyncModal
            :show="showSyncModal"
            @close="showSyncModal = false"
            @synced="handleSynced"
        />

        <!-- POPUP KONFIRMASI RESET PRODUK ODOO -->
        <Modal
            :show="showResetModal"
            @close="!isResetting && (showResetModal = false)"
        >
            <div class="p-6">
                <div
                    class="mb-3 flex items-center gap-3 text-red-600 dark:text-red-400"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 dark:bg-red-950/80"
                    >
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                            />
                        </svg>
                    </div>
                    <div>
                        <h3
                            class="text-base font-bold text-gray-900 dark:text-gray-100"
                        >
                            Reset Master Produk Odoo?
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Hapus produk yang telah ditarik dari Odoo ERP
                        </p>
                    </div>
                </div>

                <div
                    v-if="resetErrorMessage"
                    class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                >
                    ⚠️ {{ resetErrorMessage }}
                </div>

                <p
                    class="text-xs leading-relaxed text-gray-600 dark:text-gray-300"
                >
                    Tindakan ini akan
                    <b>menghapus seluruh produk yang berasal dari Odoo ERP</b>
                    (total {{ stats.synced_products }} produk) yang belum pernah
                    digunakan dalam Laporan Produksi. Produk yang dibuat manual
                    tetap aman.
                </p>

                <div class="mt-6 flex justify-end gap-2.5">
                    <SecondaryButton
                        :disabled="isResetting"
                        @click="showResetModal = false"
                    >
                        Batal
                    </SecondaryButton>
                    <DangerButton
                        :disabled="isResetting"
                        @click="handleResetProducts"
                    >
                        {{
                            isResetting
                                ? 'Menghapus...'
                                : 'Ya, Hapus Semua Produk Odoo'
                        }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
