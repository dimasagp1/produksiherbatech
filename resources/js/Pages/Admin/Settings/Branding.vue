<script setup lang="ts">
import BrandIcon from '@/Components/BrandIcon.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    branding: {
        app_name: string;
        app_tagline: string;
        app_short_name: string;
        app_logo_url: string | null;
        app_favicon_url: string | null;
        app_icon_type: string;
        app_logo_bg: string;
        app_logo_bg_color: string;
        app_logo_size: 'sm' | 'md' | 'lg' | 'xl';
        app_logo_scale: number;
    };
}>();

const form = useForm({
    app_name: props.branding.app_name || 'LinePulse',
    app_tagline:
        props.branding.app_tagline || 'Monitoring Produksi · Herbatech',
    app_short_name: props.branding.app_short_name || 'LinePulse',
    app_icon_type: props.branding.app_icon_type || 'pulse',
    app_logo_bg: props.branding.app_logo_bg || 'white',
    app_logo_bg_color: props.branding.app_logo_bg_color || '#FFFFFF',
    app_logo_size: props.branding.app_logo_size || 'md',
    app_logo_scale: props.branding.app_logo_scale || 80,
    logo: null as File | null,
    favicon: null as File | null,
    remove_logo: false,
    remove_favicon: false,
});

const logoPreview = ref<string | null>(props.branding.app_logo_url);
const faviconPreview = ref<string | null>(props.branding.app_favicon_url);
const showResetModal = ref(false);

const bgOptions = [
    {
        value: 'white',
        label: 'Putih Bersih (Clean White)',
        desc: 'Netral & kontras tinggi untuk logo berwarna / gelap',
    },
    {
        value: 'transparent',
        label: 'Transparan (Tanpa Box)',
        desc: 'Logo langsung mengambang menyatu dengan sidebar',
    },
    {
        value: 'dark',
        label: 'Gelap Obsidian (Dark Slate)',
        desc: 'Sangat cocok untuk logo putih / terang',
    },
    {
        value: 'gradient',
        label: 'Gradien Biru Modern',
        desc: 'Aksen biru ke ungu profesional',
    },
    {
        value: 'custom',
        label: 'Kustom Warna Hex',
        desc: 'Pilih warna latar belakang logo sesuai brand Anda',
    },
];

const sizeOptions = [
    { value: 'sm', label: 'Kecil', px: '32px' },
    { value: 'md', label: 'Sedang (Standar)', px: '40px' },
    { value: 'lg', label: 'Besar', px: '48px' },
    { value: 'xl', label: 'Ekstra Besar', px: '56px' },
];

const iconOptions = [
    {
        type: 'pulse',
        label: 'Waveform Pulse',
        desc: 'Denyut Produksi Real-time',
    },
    { type: 'leaf', label: 'Herbal Leaf', desc: 'Produk Alami & Herbal' },
    { type: 'capsule', label: 'Pharma Capsule', desc: 'Farmasi & Suplemen' },
    {
        type: 'factory',
        label: 'Smart Factory',
        desc: 'Pabrik & Manufaktur MES',
    },
    {
        type: 'gauge',
        label: 'Performance Gauge',
        desc: 'Efisiensi & OEE Speed',
    },
];

function onLogoChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
        form.logo = file;
        form.remove_logo = false;
        logoPreview.value = URL.createObjectURL(file);
    }
}

function removeLogo() {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
}

function onFaviconChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
        form.favicon = file;
        form.remove_favicon = false;
        faviconPreview.value = URL.createObjectURL(file);
    }
}

function removeFavicon() {
    form.favicon = null;
    form.remove_favicon = true;
    faviconPreview.value = null;
}

function submit() {
    form.post(route('admin.settings.branding.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Flash notification handled by layout
        },
    });
}

function resetToDefault() {
    router.post(
        route('admin.settings.branding.reset'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                showResetModal.value = false;
                logoPreview.value = null;
                faviconPreview.value = null;
                form.app_name = 'LinePulse';
                form.app_tagline = 'Monitoring Produksi · Herbatech';
                form.app_short_name = 'LinePulse';
                form.app_icon_type = 'pulse';
                form.app_logo_bg = 'white';
                form.app_logo_bg_color = '#FFFFFF';
                form.app_logo_size = 'md';
                form.app_logo_scale = 80;
                form.logo = null;
                form.favicon = null;
                form.remove_logo = false;
                form.remove_favicon = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Identitas & Logo Sistem" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-bold leading-tight text-gray-800 dark:text-gray-200 sm:text-xl"
                    >
                        Identitas & Logo Sistem
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Ubah nama aplikasi, tagline pabrik, logo kustom, latar
                        logo, dan ukuran tampilan
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="showResetModal = true"
                        class="cursor-pointer rounded-lg border border-red-200 bg-red-50/60 px-3 py-1.5 text-xs font-semibold text-red-700 shadow-sm transition hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-900/50"
                    >
                        ↺ Reset ke Default
                    </button>
                    <Link
                        :href="route('dashboard')"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        ← Ke Dashboard
                    </Link>
                </div>
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
                                    class="text-base font-bold text-gray-900 dark:text-gray-100"
                                >
                                    Kustomisasi Nama & Teks
                                </h3>
                                <p
                                    class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Teks ini akan langsung muncul di sidebar,
                                    header, dan tab browser
                                </p>
                            </div>
                        </div>

                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Nama Sistem -->
                            <div>
                                <InputLabel
                                    for="app_name"
                                    value="Nama Aplikasi / Sistem"
                                />
                                <TextInput
                                    id="app_name"
                                    v-model="form.app_name"
                                    type="text"
                                    class="mt-1 block w-full text-sm font-semibold"
                                    placeholder="contoh: LinePulse atau HerbaFlow MES"
                                    required
                                />
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.app_name"
                                />
                            </div>

                            <!-- Tagline / Subtitle -->
                            <div>
                                <InputLabel
                                    for="app_tagline"
                                    value="Tagline / Subtext Pabrik"
                                />
                                <TextInput
                                    id="app_tagline"
                                    v-model="form.app_tagline"
                                    type="text"
                                    class="mt-1 block w-full text-sm"
                                    placeholder="contoh: Monitoring Produksi · Herbatech atau Smart MES Herbal"
                                />
                                <p
                                    class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"
                                >
                                    Ditampilkan di bawah nama aplikasi pada
                                    bilah navigasi samping
                                </p>
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.app_tagline"
                                />
                            </div>

                            <!-- Nama Pendek (Mobile Header) -->
                            <div>
                                <InputLabel
                                    for="app_short_name"
                                    value="Nama Pendek (Mobile / Compact Header)"
                                />
                                <TextInput
                                    id="app_short_name"
                                    v-model="form.app_short_name"
                                    type="text"
                                    class="mt-1 block w-full text-sm"
                                    placeholder="contoh: LinePulse"
                                />
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.app_short_name"
                                />
                            </div>

                            <!-- PENGATURAN WARNA LATAR LOGO & UKURAN -->
                            <div
                                class="border-t border-gray-100 pt-5 dark:border-gray-700"
                            >
                                <h4
                                    class="mb-1 text-sm font-bold text-gray-900 dark:text-gray-100"
                                >
                                    🎨 Latar Belakang & Ukuran Kotak Logo
                                </h4>
                                <p
                                    class="mb-3 text-[11px] text-gray-500 dark:text-gray-400"
                                >
                                    Sesuaikan warna latar belakang dan ukuran
                                    logo agar kontras dan terlihat jelas:
                                </p>

                                <!-- Pilihan Warna Background -->
                                <div class="mb-4 space-y-2">
                                    <label
                                        class="block text-xs font-semibold text-gray-700 dark:text-gray-300"
                                        >Warna Latar Belakang Kotak Logo:</label
                                    >
                                    <div
                                        class="grid grid-cols-1 gap-2 sm:grid-cols-2"
                                    >
                                        <label
                                            v-for="bg in bgOptions"
                                            :key="bg.value"
                                            class="flex cursor-pointer items-center gap-2.5 rounded-lg border p-2.5 transition"
                                            :class="
                                                form.app_logo_bg === bg.value
                                                    ? 'border-indigo-600 bg-indigo-50/60 ring-1 ring-indigo-500/30 dark:border-indigo-500 dark:bg-indigo-950/40'
                                                    : 'border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700/50'
                                            "
                                        >
                                            <input
                                                type="radio"
                                                name="logo_bg"
                                                :value="bg.value"
                                                v-model="form.app_logo_bg"
                                                class="text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="text-xs font-bold text-gray-900 dark:text-gray-100"
                                                >
                                                    {{ bg.label }}
                                                </p>
                                                <p
                                                    class="truncate text-[10px] text-gray-500 dark:text-gray-400"
                                                >
                                                    {{ bg.desc }}
                                                </p>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- Custom Color Picker if 'custom' selected -->
                                    <div
                                        v-if="form.app_logo_bg === 'custom'"
                                        class="mt-2.5 flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/50"
                                    >
                                        <input
                                            type="color"
                                            v-model="form.app_logo_bg_color"
                                            class="h-9 w-12 cursor-pointer rounded border border-gray-300 bg-transparent p-0.5 dark:border-gray-600"
                                        />
                                        <div class="flex-1">
                                            <span
                                                class="block text-xs font-semibold text-gray-700 dark:text-gray-300"
                                                >Pilih Warna Hex Kustom:</span
                                            >
                                            <span
                                                class="font-mono text-xs text-gray-500 dark:text-gray-400"
                                                >{{
                                                    form.app_logo_bg_color
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Pilihan Ukuran Kotak Logo -->
                                <div class="mb-4 space-y-2">
                                    <label
                                        class="block text-xs font-semibold text-gray-700 dark:text-gray-300"
                                        >Ukuran Kotak Luar Logo:</label
                                    >
                                    <div
                                        class="grid grid-cols-2 gap-2 sm:grid-cols-4"
                                    >
                                        <label
                                            v-for="sz in sizeOptions"
                                            :key="sz.value"
                                            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border p-2.5 text-center transition"
                                            :class="
                                                form.app_logo_size === sz.value
                                                    ? 'border-indigo-600 bg-indigo-50/60 ring-1 ring-indigo-500/30 dark:border-indigo-500 dark:bg-indigo-950/40'
                                                    : 'border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700/50'
                                            "
                                        >
                                            <input
                                                type="radio"
                                                name="logo_size"
                                                :value="sz.value"
                                                v-model="form.app_logo_size"
                                                class="sr-only"
                                            />
                                            <span
                                                class="text-xs font-bold text-gray-900 dark:text-gray-100"
                                                >{{ sz.label }}</span
                                            >
                                            <span
                                                class="font-mono text-[10px] text-gray-500"
                                                >{{ sz.px }}</span
                                            >
                                        </label>
                                    </div>
                                </div>

                                <!-- Pengaturan Ukuran Logo di Dalam Kotak (Scale / Inset Fit) -->
                                <div
                                    class="space-y-2 rounded-lg border border-gray-200 bg-gray-50/60 p-3.5 dark:border-gray-700 dark:bg-gray-900/40"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <label
                                            for="logo_scale"
                                            class="text-xs font-semibold text-gray-700 dark:text-gray-300"
                                        >
                                            🔍 Ukuran Logo di Dalam Kotak (Inner
                                            Fit):
                                        </label>
                                        <span
                                            class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-extrabold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                                        >
                                            {{ form.app_logo_scale }}%
                                        </span>
                                    </div>

                                    <input
                                        id="logo_scale"
                                        type="range"
                                        min="40"
                                        max="100"
                                        step="5"
                                        v-model.number="form.app_logo_scale"
                                        class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-gray-200 accent-indigo-600 dark:bg-gray-700"
                                    />

                                    <!-- Tombol Preset Cepat -->
                                    <div
                                        class="flex flex-wrap items-center gap-1.5 pt-1"
                                    >
                                        <span
                                            class="text-[10px] text-gray-500 dark:text-gray-400"
                                            >Preset Cepat:</span
                                        >
                                        <button
                                            type="button"
                                            @click="form.app_logo_scale = 60"
                                            class="rounded border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-medium text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                            :class="{
                                                '!border-indigo-500 !bg-indigo-50 font-bold text-indigo-700 dark:!bg-indigo-950 dark:!text-indigo-300':
                                                    form.app_logo_scale === 60,
                                            }"
                                        >
                                            60% (Kompak)
                                        </button>
                                        <button
                                            type="button"
                                            @click="form.app_logo_scale = 75"
                                            class="rounded border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-medium text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                            :class="{
                                                '!border-indigo-500 !bg-indigo-50 font-bold text-indigo-700 dark:!bg-indigo-950 dark:!text-indigo-300':
                                                    form.app_logo_scale === 75,
                                            }"
                                        >
                                            75% (Proporsional)
                                        </button>
                                        <button
                                            type="button"
                                            @click="form.app_logo_scale = 85"
                                            class="rounded border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-medium text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                            :class="{
                                                '!border-indigo-500 !bg-indigo-50 font-bold text-indigo-700 dark:!bg-indigo-950 dark:!text-indigo-300':
                                                    form.app_logo_scale === 85,
                                            }"
                                        >
                                            85% (Standar)
                                        </button>
                                        <button
                                            type="button"
                                            @click="form.app_logo_scale = 100"
                                            class="rounded border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-medium text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                            :class="{
                                                '!border-indigo-500 !bg-indigo-50 font-bold text-indigo-700 dark:!bg-indigo-950 dark:!text-indigo-300':
                                                    form.app_logo_scale === 100,
                                            }"
                                        >
                                            100% (Penuh)
                                        </button>
                                    </div>
                                    <InputError
                                        class="mt-1"
                                        :message="form.errors.app_logo_scale"
                                    />
                                </div>
                            </div>

                            <!-- Upload Logo Gambar Kustom -->
                            <div
                                class="border-t border-gray-100 pt-5 dark:border-gray-700"
                            >
                                <h4
                                    class="mb-1 text-sm font-bold text-gray-900 dark:text-gray-100"
                                >
                                    🖼️ Upload File Logo Gambar
                                </h4>
                                <p
                                    class="mb-3 text-[11px] text-gray-500 dark:text-gray-400"
                                >
                                    Format: PNG, SVG, JPG, WebP transparan
                                    (Maks. 2MB). Jika diunggah, logo ini
                                    menggantikan icon vektor.
                                </p>

                                <div
                                    class="flex flex-col items-start gap-4 sm:flex-row sm:items-center"
                                >
                                    <div
                                        class="rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-900/50"
                                    >
                                        <BrandIcon
                                            :custom-logo="logoPreview"
                                            :icon-type="form.app_icon_type"
                                            :bg-style="form.app_logo_bg"
                                            :custom-bg-color="
                                                form.app_logo_bg_color
                                            "
                                            :size="form.app_logo_size"
                                            :scale="form.app_logo_scale"
                                        />
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <input
                                            type="file"
                                            accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                            @change="onLogoChange"
                                            class="block w-full text-xs text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-gray-400 dark:file:bg-indigo-950/50 dark:file:text-indigo-300"
                                        />
                                        <div
                                            v-if="logoPreview"
                                            class="flex items-center gap-2"
                                        >
                                            <button
                                                type="button"
                                                @click="removeLogo"
                                                class="text-xs font-semibold text-red-600 hover:text-red-800 dark:text-red-400"
                                            >
                                                ✕ Hapus Logo Kustom
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.logo"
                                />
                            </div>

                            <!-- Pilihan Icon Vektor Preset -->
                            <div
                                class="border-t border-gray-100 pt-5 dark:border-gray-700"
                            >
                                <h4
                                    class="mb-1 text-sm font-bold text-gray-900 dark:text-gray-100"
                                >
                                    ✨ Pilihan Icon Vektor Alternatif
                                </h4>
                                <p
                                    class="mb-3 text-[11px] text-gray-500 dark:text-gray-400"
                                >
                                    Digunakan secara otomatis jika tidak
                                    mengupload file logo gambar:
                                </p>

                                <div
                                    class="grid grid-cols-1 gap-2.5 sm:grid-cols-2"
                                >
                                    <label
                                        v-for="opt in iconOptions"
                                        :key="opt.type"
                                        class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition"
                                        :class="
                                            form.app_icon_type === opt.type
                                                ? 'border-indigo-600 bg-indigo-50/60 ring-1 ring-indigo-500/30 dark:border-indigo-500 dark:bg-indigo-950/40'
                                                : 'border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700/50'
                                        "
                                    >
                                        <input
                                            type="radio"
                                            name="icon_type"
                                            :value="opt.type"
                                            v-model="form.app_icon_type"
                                            class="text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                        />
                                        <BrandIcon
                                            :icon-type="opt.type"
                                            :custom-logo="null"
                                            :bg-style="form.app_logo_bg"
                                            :custom-bg-color="
                                                form.app_logo_bg_color
                                            "
                                            :scale="form.app_logo_scale"
                                            size="sm"
                                        />
                                        <div>
                                            <p
                                                class="text-xs font-bold text-gray-900 dark:text-gray-100"
                                            >
                                                {{ opt.label }}
                                            </p>
                                            <p
                                                class="text-[10px] text-gray-500 dark:text-gray-400"
                                            >
                                                {{ opt.desc }}
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Upload Favicon -->
                            <div
                                class="border-t border-gray-100 pt-5 dark:border-gray-700"
                            >
                                <h4
                                    class="mb-1 text-sm font-bold text-gray-900 dark:text-gray-100"
                                >
                                    🌐 Upload Favicon Tab Browser
                                </h4>
                                <p
                                    class="mb-3 text-[11px] text-gray-500 dark:text-gray-400"
                                >
                                    Format: .ICO, .PNG, atau .SVG (Maks. 1MB)
                                </p>

                                <div
                                    class="flex flex-col items-start gap-4 sm:flex-row sm:items-center"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-1.5 dark:border-gray-600 dark:bg-gray-900/50"
                                    >
                                        <img
                                            v-if="faviconPreview"
                                            :src="faviconPreview"
                                            alt="Favicon Preview"
                                            class="h-6 w-6 object-contain"
                                        />
                                        <span
                                            v-else
                                            class="font-mono text-[10px] text-gray-400"
                                            >.ICO</span
                                        >
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <input
                                            type="file"
                                            accept=".ico,image/png,image/svg+xml"
                                            @change="onFaviconChange"
                                            class="block w-full text-xs text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200 dark:text-gray-400 dark:file:bg-gray-800 dark:file:text-gray-300"
                                        />
                                        <div
                                            v-if="faviconPreview"
                                            class="flex items-center gap-2"
                                        >
                                            <button
                                                type="button"
                                                @click="removeFavicon"
                                                class="text-xs font-semibold text-red-600 hover:text-red-800 dark:text-red-400"
                                            >
                                                ✕ Hapus Favicon Kustom
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.favicon"
                                />
                            </div>

                            <!-- Submit Button -->
                            <div
                                class="flex items-center justify-end border-t border-gray-100 pt-5 dark:border-gray-700"
                            >
                                <PrimaryButton :disabled="form.processing">
                                    {{
                                        form.processing
                                            ? 'Menyimpan Perubahan...'
                                            : 'Simpan Identitas Sistem'
                                    }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- LIVE PREVIEW SIDEBAR (1 COL) -->
                <div class="space-y-5">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                        >
                            👁️ Live Preview Sidebar
                        </h3>
                        <p
                            class="mb-4 text-[11px] text-gray-500 dark:text-gray-400"
                        >
                            Tampilan logo dan teks brand di bilah navigasi kiri:
                        </p>

                        <!-- Preview Sidebar Header Component -->
                        <div
                            class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900"
                        >
                            <div class="flex items-center gap-3">
                                <BrandIcon
                                    :custom-logo="logoPreview"
                                    :icon-type="form.app_icon_type"
                                    :bg-style="form.app_logo_bg"
                                    :custom-bg-color="form.app_logo_bg_color"
                                    :size="form.app_logo_size"
                                    :scale="form.app_logo_scale"
                                />
                                <div class="min-w-0">
                                    <h4
                                        class="truncate text-sm font-extrabold tracking-tight text-gray-900 dark:text-white"
                                    >
                                        {{ form.app_name || 'LinePulse' }}
                                    </h4>
                                    <p
                                        class="truncate text-[11px] font-medium text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            form.app_tagline ||
                                            'Monitoring Produksi · Herbatech'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Mobile Topbar -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                        >
                            📱 Live Preview Mobile Header
                        </h3>

                        <div
                            class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-3 py-2.5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                        >
                            <div class="flex items-center gap-2">
                                <BrandIcon
                                    :custom-logo="logoPreview"
                                    :icon-type="form.app_icon_type"
                                    :bg-style="form.app_logo_bg"
                                    :custom-bg-color="form.app_logo_bg_color"
                                    :scale="form.app_logo_scale"
                                    size="sm"
                                />
                                <span
                                    class="text-xs font-bold text-gray-900 dark:text-white"
                                    >{{
                                        form.app_short_name ||
                                        form.app_name ||
                                        'LinePulse'
                                    }}</span
                                >
                            </div>
                            <span
                                class="rounded bg-blue-100 px-1.5 py-0.5 text-[9px] font-bold text-blue-800 dark:bg-blue-900/60 dark:text-blue-200"
                                >LIVE</span
                            >
                        </div>
                    </div>

                    <!-- Preview Browser Tab -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                        >
                            🌐 Live Preview Tab Browser
                        </h3>

                        <div
                            class="flex items-center gap-2 rounded-t-lg bg-gray-200 px-3 py-2 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <img
                                v-if="faviconPreview"
                                :src="faviconPreview"
                                class="h-4 w-4 object-contain"
                            />
                            <BrandIcon
                                v-else
                                :custom-logo="logoPreview"
                                :icon-type="form.app_icon_type"
                                :as-badge="false"
                                size-class="h-3.5 w-3.5 text-blue-600"
                            />
                            <span class="truncate font-medium"
                                >{{ form.app_name || 'LinePulse' }} —
                                Dashboard</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RESET MODAL -->
        <Modal :show="showResetModal" @close="showResetModal = false">
            <div class="p-6">
                <h3
                    class="text-base font-bold text-gray-900 dark:text-gray-100"
                >
                    Reset Identitas Sistem ke Default?
                </h3>
                <p
                    class="mt-2 text-xs leading-relaxed text-gray-600 dark:text-gray-400"
                >
                    Nama sistem akan dikembalikan ke <b>LinePulse</b>, tagline
                    ke <b>Monitoring Produksi · Herbatech</b>, warna latar ke
                    <b>Putih</b>, dan logo kustom akan dihapus.
                </p>
                <div class="mt-5 flex justify-end gap-2.5">
                    <SecondaryButton @click="showResetModal = false"
                        >Batal</SecondaryButton
                    >
                    <DangerButton @click="resetToDefault"
                        >Ya, Reset ke Default</DangerButton
                    >
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
