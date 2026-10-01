<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        customLogo?: string | null;
        iconType?: string;
        bgStyle?: string;
        customBgColor?: string;
        size?: 'sm' | 'md' | 'lg' | 'xl' | 'auto';
        scale?: number | string;
        sizeClass?: string;
        asBadge?: boolean;
    }>(),
    {
        customLogo: undefined,
        iconType: undefined,
        bgStyle: undefined,
        customBgColor: undefined,
        size: undefined,
        scale: undefined,
        sizeClass: '',
        asBadge: true,
    },
);

const page = usePage();
const branding = computed(() => (page.props as any).branding || {});

const logoUrl = computed(() =>
    props.customLogo !== undefined
        ? props.customLogo
        : branding.value.app_logo_url,
);
const type = computed(
    () => props.iconType || branding.value.app_icon_type || 'pulse',
);
const bg = computed(
    () =>
        props.bgStyle ||
        branding.value.app_logo_bg ||
        (logoUrl.value ? 'white' : 'gradient'),
);
const customHex = computed(
    () => props.customBgColor || branding.value.app_logo_bg_color || '#FFFFFF',
);
const logoSize = computed(
    () => props.size || branding.value.app_logo_size || 'md',
);
const logoScale = computed(() => {
    if (
        props.scale !== undefined &&
        props.scale !== null &&
        props.scale !== ''
    ) {
        return Number(props.scale);
    }
    return Number(branding.value.app_logo_scale ?? 80);
});

const badgeDimension = computed(() => {
    switch (logoSize.value) {
        case 'sm':
            return 'h-8 w-8 rounded-lg';
        case 'lg':
            return 'h-12 w-12 rounded-xl';
        case 'xl':
            return 'h-14 w-14 rounded-2xl';
        case 'auto':
            return 'h-auto w-auto p-1';
        case 'md':
        default:
            return 'h-10 w-10 rounded-xl';
    }
});

const badgeStyle = computed(() => {
    switch (bg.value) {
        case 'white':
            return {
                backgroundColor: '#FFFFFF',
                border: '1px solid rgba(226, 232, 240, 0.9)',
                boxShadow: '0 1px 3px rgba(0, 0, 0, 0.08)',
            };
        case 'transparent':
            return {
                backgroundColor: 'transparent',
                border: 'none',
                boxShadow: 'none',
            };
        case 'dark':
            return {
                backgroundColor: '#111827',
                border: '1px solid rgba(255, 255, 255, 0.12)',
                boxShadow: '0 2px 4px rgba(0, 0, 0, 0.3)',
            };
        case 'custom':
            return {
                backgroundColor: customHex.value,
                border: '1px solid rgba(0, 0, 0, 0.1)',
                boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)',
            };
        case 'gradient':
        default:
            return {
                background: 'linear-gradient(135deg, #2563EB 0%, #4F46E5 100%)',
                color: '#FFFFFF',
                boxShadow: '0 2px 6px rgba(37, 99, 235, 0.3)',
            };
    }
});

const innerIconClass = computed(() => {
    if (props.sizeClass) return props.sizeClass;
    switch (logoSize.value) {
        case 'sm':
            return 'h-4 w-4';
        case 'lg':
            return 'h-7 w-7';
        case 'xl':
            return 'h-8 w-8';
        case 'md':
        default:
            return 'h-5 w-5';
    }
});

const vectorIconColor = computed(() => {
    if (bg.value === 'white') return 'text-indigo-600 dark:text-indigo-500';
    if (bg.value === 'dark') return 'text-indigo-400';
    if (bg.value === 'transparent')
        return 'text-indigo-600 dark:text-indigo-400';
    if (bg.value === 'gradient') return 'text-white';
    return 'text-gray-900';
});
</script>

<template>
    <!-- Badge Box Container -->
    <div
        v-if="asBadge"
        class="brand-mark-badge flex shrink-0 items-center justify-center overflow-hidden transition-all duration-200"
        :class="[badgeDimension]"
        :style="badgeStyle"
    >
        <!-- Custom Uploaded Logo Image -->
        <img
            v-if="logoUrl"
            :src="logoUrl"
            alt="Logo"
            class="select-none object-contain transition-all duration-150"
            :style="{
                maxWidth: `${logoScale}%`,
                maxHeight: `${logoScale}%`,
                width: `${logoScale}%`,
                height: `${logoScale}%`,
            }"
        />

        <!-- Preset Vector Icons -->
        <template v-else>
            <!-- 1. Leaf / Herbal -->
            <svg
                v-if="type === 'leaf'"
                :class="[
                    innerIconClass,
                    vectorIconColor,
                    'transition-transform duration-150',
                ]"
                :style="{ transform: `scale(${logoScale / 80})` }"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path
                    d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"
                />
                <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12" />
            </svg>

            <!-- 2. Capsule / Pharma -->
            <svg
                v-else-if="type === 'capsule'"
                :class="[
                    innerIconClass,
                    vectorIconColor,
                    'transition-transform duration-150',
                ]"
                :style="{ transform: `scale(${logoScale / 80})` }"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path
                    d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"
                />
                <path d="m8.5 8.5 7 7" />
            </svg>

            <!-- 3. Factory / MES -->
            <svg
                v-else-if="type === 'factory'"
                :class="[
                    innerIconClass,
                    vectorIconColor,
                    'transition-transform duration-150',
                ]"
                :style="{ transform: `scale(${logoScale / 80})` }"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path
                    d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"
                />
                <path d="M17 18h1" />
                <path d="M12 18h1" />
                <path d="M7 18h1" />
            </svg>

            <!-- 4. Gauge / Speedometer -->
            <svg
                v-else-if="type === 'gauge'"
                :class="[
                    innerIconClass,
                    vectorIconColor,
                    'transition-transform duration-150',
                ]"
                :style="{ transform: `scale(${logoScale / 80})` }"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="m12 14 4-4" />
                <path d="M3.34 19a10 10 0 1 1 17.32 0" />
            </svg>

            <!-- 5. Default Pulse Waveform -->
            <svg
                v-else
                :class="[
                    innerIconClass,
                    vectorIconColor,
                    'transition-transform duration-150',
                ]"
                :style="{ transform: `scale(${logoScale / 80})` }"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M3 13h3l2 7 4-16 2 9h3l2-4 2 4h2" />
            </svg>
        </template>
    </div>

    <!-- Standalone Icon without Badge Box (e.g. for small headers / lists) -->
    <div v-else class="inline-flex items-center justify-center">
        <img
            v-if="logoUrl"
            :src="logoUrl"
            alt="Logo"
            class="object-contain"
            :class="sizeClass || 'h-5 w-5'"
        />
        <template v-else>
            <svg
                v-if="type === 'leaf'"
                :class="[sizeClass || 'h-5 w-5', vectorIconColor]"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path
                    d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"
                />
                <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12" />
            </svg>
            <svg
                v-else-if="type === 'capsule'"
                :class="[sizeClass || 'h-5 w-5', vectorIconColor]"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path
                    d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"
                />
                <path d="m8.5 8.5 7 7" />
            </svg>
            <svg
                v-else-if="type === 'factory'"
                :class="[sizeClass || 'h-5 w-5', vectorIconColor]"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path
                    d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"
                />
                <path d="M17 18h1" />
                <path d="M12 18h1" />
                <path d="M7 18h1" />
            </svg>
            <svg
                v-else-if="type === 'gauge'"
                :class="[sizeClass || 'h-5 w-5', vectorIconColor]"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="m12 14 4-4" />
                <path d="M3.34 19a10 10 0 1 1 17.32 0" />
            </svg>
            <svg
                v-else
                :class="[sizeClass || 'h-5 w-5', vectorIconColor]"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M3 13h3l2 7 4-16 2 9h3l2-4 2 4h2" />
            </svg>
        </template>
    </div>
</template>
