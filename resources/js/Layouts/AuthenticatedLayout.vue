<script setup lang="ts">
import BrandIcon from '@/Components/BrandIcon.vue';
import FloatingActiveTimer from '@/Components/FloatingActiveTimer.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const page = usePage();
const user = computed(() => (page.props as any).auth?.user);
const branding = computed(() => (page.props as any).branding || {});
const appName = computed(() => branding.value.app_name || 'LinePulse');
const appTagline = computed(
    () => branding.value.app_tagline || 'Monitoring Produksi · Herbatech',
);
const appShortName = computed(
    () => branding.value.app_short_name || appName.value,
);
const currentRole = computed(() => user.value?.role ?? 'leader');
const initials = computed(() => {
    const name = user.value?.name ?? 'U';
    return name
        .split(' ')
        .map((n: string) => n[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
});

const isDark = ref(false);
const mobileNavOpen = ref(false);

// Close mobile drawer when URL changes
watch(
    () => page.url,
    () => {
        mobileNavOpen.value = false;
    },
);

onMounted(() => {
    isDark.value =
        document.documentElement.classList.contains('dark') ||
        document.documentElement.getAttribute('data-theme') === 'dark';
});

function toggleTheme() {
    isDark.value = !isDark.value;
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        document.documentElement.setAttribute('data-theme', 'light');
        localStorage.setItem('theme', 'light');
    }
}

function hasRole(roles: string[]): boolean {
    const r = currentRole.value;
    return roles.includes(r);
}

function isActive(routeName: string): boolean {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
}

function closeMobileNav() {
    mobileNavOpen.value = false;
}
</script>

<template>
    <div class="app-layout" :data-role="currentRole">
        <!-- MOBILE TOP BAR (Visible only on mobile/tablet < 1024px) -->
        <header
            class="mobile-topbar sticky top-0 z-30 flex items-center justify-between border-b border-gray-200 bg-white/95 px-4 py-3 backdrop-blur-md dark:border-gray-800 dark:bg-gray-900/95 lg:hidden"
        >
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="mobileNavOpen = !mobileNavOpen"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    aria-label="Buka Menu"
                >
                    <svg
                        v-if="!mobileNavOpen"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                    <svg
                        v-else
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>

                <div class="flex items-center gap-2">
                    <BrandIcon size="sm" />
                    <span
                        class="text-sm font-bold tracking-tight text-gray-900 dark:text-white"
                        >{{ appShortName }}</span
                    >
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Quick Theme Toggle Icon -->
                <button
                    @click="toggleTheme"
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    :title="isDark ? 'Mode Terang' : 'Mode Gelap'"
                >
                    <svg
                        v-if="isDark"
                        class="h-4 w-4 text-amber-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                        />
                    </svg>
                    <svg
                        v-else
                        class="h-4 w-4 text-slate-700"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                        />
                    </svg>
                </button>

                <div
                    class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-[11px] font-bold text-white shadow-sm"
                >
                    {{ initials }}
                </div>
            </div>
        </header>

        <!-- BACKDROP OVERLAY FOR MOBILE DRAWER -->
        <transition
            enter-active-class="transition-opacity ease-linear duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-linear duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileNavOpen"
                @click="closeMobileNav"
                class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"
            />
        </transition>

        <!-- SIDEBAR / MOBILE DRAWER -->
        <aside class="sidebar" :class="{ 'mobile-open': mobileNavOpen }">
            <!-- Brand Block (Desktop & Drawer Header) -->
            <div class="brand-block flex items-center justify-between">
                <div class="flex min-w-0 items-center gap-2.5">
                    <BrandIcon />
                    <div class="brand truncate">
                        <span class="block truncate">{{ appName }}</span>
                        <small class="block truncate">{{ appTagline }}</small>
                    </div>
                </div>

                <!-- Close button for mobile drawer -->
                <button
                    type="button"
                    @click="closeMobileNav"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300 lg:hidden"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav @click="closeMobileNav">
                <Link
                    :href="route('dashboard')"
                    class="nav-item"
                    :class="{ active: isActive('dashboard') }"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <rect x="3" y="3" width="7" height="9" rx="1.5" />
                        <rect x="14" y="3" width="7" height="5" rx="1.5" />
                        <rect x="14" y="12" width="7" height="9" rx="1.5" />
                        <rect x="3" y="16" width="7" height="5" rx="1.5" />
                    </svg>
                    Dashboard
                </Link>
                <Link
                    v-if="hasRole(['ppic', 'superadmin'])"
                    :href="route('ppic.weekly-plan.index')"
                    class="nav-item"
                    :class="{ active: isActive('ppic.weekly-plan.*') }"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <rect x="3" y="4" width="18" height="17" rx="2" />
                        <path d="M3 9h18M8 2v4M16 2v4" />
                    </svg>
                    Plan Produksi / WPS
                </Link>
                <Link
                    v-if="hasRole(['ppic', 'superadmin', 'admin', 'manager', 'warehouse_admin'])"
                    :href="route('ppic.mrp.index')"
                    class="nav-item"
                    :class="{ active: isActive('ppic.mrp.*') }"
                >
                    <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20v-6M6 20V10M18 20V4" /></svg>
                    MRP & Red Warning
                </Link>
                <Link
                    v-if="hasRole(['ppic', 'superadmin', 'admin', 'manager'])"
                    :href="route('ppic.mps.index')"
                    class="nav-item"
                    :class="{ active: isActive('ppic.mps.*') }"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M9 11l3 3L22 4"
                        />
                        <path
                            d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"
                        />
                    </svg>
                    Master Production Schedule (MPS)
                </Link>
                <Link
                    v-if="
                        hasRole([
                            'leader',
                            'operator',
                            'spv',
                            'superadmin',
                            'admin',
                        ])
                    "
                    :href="route('leader.laporan-harian.index')"
                    class="nav-item"
                    :class="{ active: isActive('leader.laporan-harian.*') }"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M8 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-2"
                        />
                        <rect x="8" y="2" width="8" height="4" rx="1" />
                        <path d="M9 13h6M9 17h6M9 9h2" />
                    </svg>
                    Laporan Harian
                </Link>
                <Link
                    v-if="
                        hasRole([
                            'leader',
                            'operator',
                            'spv',
                            'superadmin',
                            'admin',
                            'manager',
                        ])
                    "
                    :href="route('reject.index')"
                    class="nav-item"
                    :class="{ active: isActive('reject.*') }"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"
                        />
                        <path d="M12 9v4" />
                        <path d="M12 17h.01" />
                    </svg>
                    Reject Produk
                </Link>

                <!-- SCM Section -->
                <div
                    v-if="
                        hasRole([
                            'warehouse_admin',
                            'ppic',
                            'superadmin',
                            'admin',
                            'manager',
                            'leader',
                            'operator',
                            'spv',
                        ])
                    "
                    class="nav-section-label"
                >
                    Supply Chain (SCM)
                </div>

                <Link
                    v-if="
                        hasRole([
                            'ppic',
                            'superadmin',
                            'admin',
                            'warehouse_admin',
                            'manager',
                        ])
                    "
                    :href="route('scm.bom.index')"
                    class="nav-item"
                    :class="{ active: isActive('scm.bom.*') }"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <polygon points="12 2 2 7 12 12 22 7 12 2" />
                        <polyline points="2 17 12 22 22 17" />
                        <polyline points="2 12 12 17 22 12" />
                    </svg>
                    BOM
                </Link>

                <Link
                    v-if="
                        hasRole([
                            'operator',
                            'warehouse_admin',
                            'manager',
                            'superadmin',
                            'admin',
                            'leader',
                            'spv',
                            'ppic',
                        ])
                    "
                    :href="route('scm.material-usage.index')"
                    class="nav-item"
                    :class="{ active: isActive('scm.material-usage.*') }"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"
                        />
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                        <line x1="12" y1="22.08" x2="12" y2="12" />
                    </svg>
                    Material Usage
                </Link>

                <Link
                    v-if="
                        hasRole([
                            'operator',
                            'warehouse_admin',
                            'manager',
                            'superadmin',
                            'admin',
                            'leader',
                            'spv',
                            'ppic',
                        ])
                    "
                    :href="route('scm.scrap-material.index')"
                    class="nav-item"
                    :class="{ active: isActive('scm.scrap-material.*') }"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <polyline points="3 6 5 6 21 6" />
                        <path
                            d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
                        />
                        <line x1="10" y1="11" x2="10" y2="17" />
                        <line x1="14" y1="11" x2="14" y2="17" />
                    </svg>
                    Scrap Material
                </Link>

                <Link
                    v-if="
                        hasRole([
                            'warehouse_admin',
                            'manager',
                            'superadmin',
                            'admin',
                            'ppic',
                        ])
                    "
                    :href="route('scm.saldo-stok.index')"
                    class="nav-item"
                    :class="{ active: isActive('scm.saldo-stok.*') }"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                        />
                    </svg>
                    Saldo Stok
                </Link>

                <Link
                    v-if="
                        hasRole([
                            'warehouse_admin',
                            'manager',
                            'superadmin',
                            'admin',
                            'spv',
                        ])
                    "
                    :href="route('scm.stock-opname.index')"
                    class="nav-item"
                    :class="{ active: isActive('scm.stock-opname.*') }"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M9 11l3 3L22 4" />
                        <path
                            d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"
                        />
                    </svg>
                    Stock Opname
                </Link>

                <Link
                    v-if="
                        hasRole([
                            'warehouse_admin',
                            'manager',
                            'superadmin',
                            'admin',
                            'ppic',
                            'spv',
                        ])
                    "
                    :href="route('scm.delivery.index')"
                    class="nav-item"
                    :class="{ active: isActive('scm.delivery.*') }"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <rect x="1" y="3" width="15" height="13" rx="1" />
                        <polygon
                            points="16 8 20 8 23 11 23 16 16 16 16 8"
                        />
                        <circle cx="5.5" cy="18.5" r="2.5" />
                        <circle cx="18.5" cy="18.5" r="2.5" />
                    </svg>
                    Delivery Schedule
                </Link>

                <!-- Master Data & Settings Section -->
                <div
                    v-if="hasRole(['superadmin', 'admin', 'ppic', 'manager'])"
                    class="nav-section-label"
                >
                    Master Data & Pengaturan
                </div>

                <template
                    v-if="hasRole(['superadmin', 'admin', 'ppic', 'manager'])"
                >
                    <Link
                        :href="route('admin.produk.index')"
                        class="nav-item"
                        :class="{ active: isActive('admin.produk.*') }"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"
                            />
                            <line x1="7" y1="7" x2="7.01" y2="7" />
                        </svg>
                        Produk
                    </Link>

                    <template
                        v-if="hasRole(['superadmin', 'admin', 'manager'])"
                    >
                        <Link
                            :href="route('admin.mesin.index')"
                            class="nav-item"
                            :class="{ active: isActive('admin.mesin.*') }"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect
                                    x="4"
                                    y="4"
                                    width="16"
                                    height="16"
                                    rx="2"
                                />
                                <rect x="9" y="9" width="6" height="6" />
                                <line x1="9" y1="1" x2="9" y2="4" />
                                <line x1="15" y1="1" x2="15" y2="4" />
                            </svg>
                            Mesin
                        </Link>
                        <Link
                            :href="route('admin.line.index')"
                            class="nav-item"
                            :class="{ active: isActive('admin.line.*') }"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <polyline
                                    points="22 12 18 12 15 21 9 3 6 12 2 12"
                                />
                            </svg>
                            Line
                        </Link>
                        <Link
                            :href="route('admin.alasan-downtime.index')"
                            class="nav-item"
                            :class="{
                                active: isActive('admin.alasan-downtime.*'),
                            }"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                            Alasan Downtime
                        </Link>
                        <Link
                            :href="route('admin.users.index')"
                            class="nav-item"
                            :class="{ active: isActive('admin.users.*') }"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"
                                />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                            Manajemen User
                        </Link>
                        <Link
                            v-if="hasRole(['superadmin', 'admin', 'manager'])"
                            :href="route('admin.odoo.settings')"
                            class="nav-item"
                            :class="{ active: isActive('admin.odoo.*') }"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"
                                />
                                <path
                                    d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"
                                />
                            </svg>
                            Integrasi Odoo
                        </Link>
                        <Link
                            v-if="hasRole(['superadmin', 'admin', 'manager'])"
                            :href="route('admin.settings.hris.index')"
                            class="nav-item"
                            :class="{
                                active: isActive('admin.settings.hris.*'),
                            }"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                />
                                <circle cx="9" cy="7" r="4" />
                                <polyline points="16 11 18 13 22 9" />
                            </svg>
                            Integrasi HRIS
                        </Link>
                        <Link
                            v-if="hasRole(['superadmin', 'admin', 'manager'])"
                            :href="route('admin.settings.finance.index')"
                            class="nav-item"
                            :class="{
                                active: isActive('admin.settings.finance.*'),
                            }"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <line x1="12" y1="1" x2="12" y2="23" />
                                <path
                                    d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"
                                />
                            </svg>
                            Integrasi Finance
                        </Link>
                        <Link
                            v-if="hasRole(['superadmin', 'admin', 'manager'])"
                            :href="route('admin.settings.branding.index')"
                            class="nav-item"
                            :class="{
                                active: isActive('admin.settings.branding.*'),
                            }"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="12" cy="12" r="10" />
                                <path
                                    d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"
                                />
                            </svg>
                            Identitas & Logo
                        </Link>
                        <Link
                            :href="route('admin.history.index')"
                            class="nav-item"
                            :class="{ active: isActive('admin.history.*') }"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 14 14" />
                                <path
                                    d="M3.05 11a9 9 0 0 1 .5-2m17.4 2a9 9 0 0 0-.5-2"
                                />
                            </svg>
                            History
                        </Link>
                    </template>
                </template>
                <div
                    v-else
                    style="
                        font-size: 11px;
                        color: var(--text-muted);
                        padding: 0 10px;
                        line-height: 1.6;
                    "
                >
                    Menu menyesuaikan RBAC role kamu ({{ currentRole }}).
                </div>
            </nav>

            <div class="sidebar-foot">
                <!-- Theme Toggle Button -->
                <button
                    @click="toggleTheme"
                    type="button"
                    class="theme-toggle-btn"
                    :title="
                        isDark
                            ? 'Beralih ke Mode Terang (Light Mode)'
                            : 'Beralih ke Mode Gelap (Dark Mode)'
                    "
                >
                    <span v-if="isDark" class="flex items-center gap-2">
                        <svg
                            class="h-4 w-4 text-amber-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                            />
                        </svg>
                        <span class="text-xs font-medium">Mode Terang</span>
                    </span>
                    <span v-else class="flex items-center gap-2">
                        <svg
                            class="h-4 w-4 text-slate-700"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                            />
                        </svg>
                        <span class="text-xs font-medium text-slate-700"
                            >Mode Gelap</span
                        >
                    </span>
                    <span
                        class="rounded bg-black/5 px-1.5 py-0.5 font-mono text-[10px] uppercase text-gray-500 dark:bg-white/10 dark:text-gray-400"
                    >
                        {{ isDark ? 'Dark' : 'Light' }}
                    </span>
                </button>

                <div class="user-card">
                    <div class="avatar">{{ initials }}</div>
                    <div style="min-width: 0">
                        <div
                            style="
                                font-size: 12.5px;
                                font-weight: 600;
                                white-space: nowrap;
                                overflow: hidden;
                                text-overflow: ellipsis;
                            "
                        >
                            {{ user?.name }}
                        </div>
                        <div
                            style="
                                font-size: 11px;
                                color: var(--text-muted);
                                text-transform: capitalize;
                            "
                        >
                            {{ currentRole }} · {{ user?.email }}
                        </div>
                    </div>
                </div>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="nav-item"
                    style="margin-top: 2px"
                    @click="closeMobileNav"
                >
                    <svg
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                    Logout
                </Link>

                <div class="status-led" style="margin-top: 4px">
                    <span class="dot"></span> Realtime · tersambung
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="main-content">
            <header
                v-if="$slots.header"
                class="border-b border-gray-200 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-800 sm:px-6"
            >
                <div class="mx-auto max-w-7xl">
                    <slot name="header" />
                </div>
            </header>
            <div class="py-4 sm:py-6">
                <slot />
            </div>
        </main>

        <!-- Floating Active Production Timer Widget -->
        <FloatingActiveTimer />
    </div>
</template>
