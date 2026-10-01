<script setup lang="ts">
import BrandIcon from '@/Components/BrandIcon.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const page = usePage();
const branding = computed(() => (page.props as any).branding || {});
const appName = computed(() => branding.value.app_name || 'LinePulse');
const appTagline = computed(
    () => branding.value.app_tagline || 'Monitoring Produksi · Herbatech',
);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head :title="`Masuk - ${appName}`" />

    <div class="login-page">
        <div class="login-card">
            <!-- Brand -->
            <div class="brand">
                <BrandIcon size="lg" />
                <div class="brand-text">
                    <span class="brand-name">{{ appName }}</span>
                    <span class="brand-sub">{{ appTagline }}</span>
                </div>
            </div>

            <!-- Status message -->
            <div v-if="status" class="status-msg">
                {{ status }}
            </div>
            <div
                v-else-if="($page.props as any).flash?.error"
                class="status-msg"
                style="background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2);"
            >
                {{ ($page.props as any).flash?.error }}
            </div>

            <!-- Form -->
            <form @submit.prevent="submit">
                <div class="field">
                    <label for="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="nama@herbatech.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        type="password"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="field-row">
                    <label class="checkbox-label">
                        <input type="checkbox" v-model="form.remember" />
                        <span>Ingat saya</span>
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="forgot-link"
                    >
                        Lupa password?
                    </Link>
                </div>

                <button
                    type="submit"
                    class="btn-primary"
                    :disabled="form.processing"
                    :class="{ processing: form.processing }"
                >
                    <span v-if="form.processing" class="spinner"></span>
                    <span v-else>Masuk</span>
                </button>
            </form>

            <!-- Footer -->
            <div class="card-footer">
                <span>Belum punya akun?</span>
                <Link :href="route('register')" class="register-link"
                    >Daftar sekarang</Link
                >
            </div>
        </div>

        <!-- Background decoration -->
        <div class="bg-decoration">
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
        </div>
    </div>
</template>

<style scoped>
/* ===== CSS Variables (match mockup) ===== */
.login-page {
    --bg: #f8fafc;
    --panel: #ffffff;
    --panel-2: #f1f5f9;
    --border: #e2e8f0;
    --text: #0f172a;
    --text-muted: #64748b;
    --accent-a: #2563eb;
    --accent-b: #1d4ed8;
    --accent-grad: linear-gradient(135deg, #2563eb, #1d4ed8);
    --good: #059669;
    --good-bg: #ecfdf5;
    --bad: #dc2626;
    --bad-bg: #fef2f2;
    --shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
    --radius: 8px;
}

html.dark .login-page,
.login-page[data-theme='dark'] {
    --bg: #090a0f;
    --panel: #12151f;
    --panel-2: #181c28;
    --border: #1e2330;
    --text: #f8fafc;
    --text-muted: #94a3b8;
    --accent-a: #3b82f6;
    --accent-b: #2563eb;
    --accent-grad: linear-gradient(135deg, #3b82f6, #1d4ed8);
    --good: #10b981;
    --good-bg: #062c1e;
    --bad: #ef4444;
    --bad-bg: #381111;
    --shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

/* ===== Base ===== */
.login-page {
    font-family:
        'IBM Plex Sans',
        system-ui,
        -apple-system,
        sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    position: relative;
    overflow: hidden;
}

/* ===== Login Card ===== */
.login-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 40px 36px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    position: relative;
    z-index: 10;
}

/* ===== Brand ===== */
.brand {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 32px;
}

.brand-mark {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--accent-grad);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.brand-mark svg {
    width: 26px;
    height: 26px;
    color: #fff;
}

.brand-text {
    display: flex;
    flex-direction: column;
}

.brand-name {
    font-family: 'Manrope', system-ui, sans-serif;
    font-weight: 800;
    font-size: 22px;
    letter-spacing: 0.2px;
    color: var(--text);
}

.brand-sub {
    font-size: 11px;
    font-weight: 500;
    color: var(--text-muted);
    letter-spacing: 0.4px;
    text-transform: uppercase;
    margin-top: 2px;
}

/* ===== Status Message ===== */
.status-msg {
    background: var(--good-bg);
    color: var(--good);
    padding: 10px 14px;
    border-radius: var(--radius);
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 20px;
}

/* ===== Form Fields ===== */
.field {
    margin-bottom: 18px;
}

.field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 6px;
    letter-spacing: 0.2px;
}

.field input {
    width: 100%;
    padding: 10px 14px;
    background: var(--panel-2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    color: var(--text);
    font-size: 14px;
    font-family: inherit;
    transition: all 0.15s ease;
}

.field input::placeholder {
    color: var(--text-muted);
    opacity: 0.6;
}

.field input:focus {
    outline: none;
    border-color: var(--accent-a);
    box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.15);
}

@media (prefers-color-scheme: dark) {
    .login-page:not([data-theme='light']) .field input:focus {
        box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.15);
    }
}

.field-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--text-muted);
    cursor: pointer;
}

.checkbox-label input[type='checkbox'] {
    width: 16px;
    height: 16px;
    accent-color: var(--accent-a);
    cursor: pointer;
}

.forgot-link {
    font-size: 13px;
    color: var(--accent-a);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.15s ease;
}

.forgot-link:hover {
    color: var(--accent-b);
    text-decoration: underline;
}

/* ===== Primary Button ===== */
.btn-primary {
    width: 100%;
    padding: 12px 20px;
    background: var(--accent-grad);
    border: none;
    border-radius: var(--radius);
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(8, 145, 178, 0.3);
}

.btn-primary:active:not(:disabled) {
    transform: translateY(0);
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.btn-primary.processing {
    pointer-events: none;
}

/* ===== Spinner ===== */
.spinner {
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* ===== Card Footer ===== */
.card-footer {
    text-align: center;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
    font-size: 13px;
    color: var(--text-muted);
}

.register-link {
    color: var(--accent-a);
    text-decoration: none;
    font-weight: 600;
    margin-left: 4px;
    transition: color 0.15s ease;
}

.register-link:hover {
    color: var(--accent-b);
    text-decoration: underline;
}

/* ===== Background Decoration ===== */
.bg-decoration {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
    z-index: 0;
}

.circle {
    position: absolute;
    border-radius: 50%;
    opacity: 0.15;
}

.circle-1 {
    width: 600px;
    height: 600px;
    background: var(--accent-a);
    top: -200px;
    right: -200px;
    filter: blur(100px);
}

.circle-2 {
    width: 500px;
    height: 500px;
    background: var(--accent-b);
    bottom: -150px;
    left: -150px;
    filter: blur(100px);
}

/* ===== Responsive ===== */
@media (max-width: 480px) {
    .login-card {
        padding: 32px 24px;
    }

    .brand-mark {
        width: 40px;
        height: 40px;
    }

    .brand-mark svg {
        width: 22px;
        height: 22px;
    }

    .brand-name {
        font-size: 20px;
    }
}
</style>
