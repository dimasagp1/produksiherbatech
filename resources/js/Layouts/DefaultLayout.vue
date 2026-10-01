<script setup>
import { Link, router } from '@inertiajs/vue3';

defineProps({
    user: Object,
});
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Navigation -->
        <nav class="bg-white shadow">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex">
                        <Link
                            href="/"
                            class="flex items-center px-4 text-sm font-medium text-gray-900"
                        >
                            LinePulse
                        </Link>
                    </div>
                    <div class="flex items-center space-x-4">
                        <template v-if="user">
                            <span class="text-sm text-gray-700">{{
                                user.name
                            }}</span>
                            <button
                                @click="router.post('/logout')"
                                class="text-sm text-red-600 hover:text-red-800"
                            >
                                Logout
                            </button>
                        </template>
                        <template v-else>
                            <Link
                                href="/login"
                                class="text-sm text-gray-700 hover:text-gray-900"
                            >
                                Login
                            </Link>
                            <Link
                                href="/register"
                                class="text-sm text-gray-700 hover:text-gray-900"
                            >
                                Register
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main>
            <slot />
        </main>
    </div>
</template>
