<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Expand, Minimize, LogOut, ArrowLeft, UserCircle2 } from 'lucide-vue-next';
import { useBranding } from '@/branding';

const props = defineProps({
    title: {
        type: String,
        default: 'PDV',
    },
    subtitle: {
        type: String,
        default: 'Frente de caixa',
    },
    confirmLeave: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const { systemIconUrl, publicFaviconHref, publicFaviconType, userAvatarUrl, themeStyles, brandName } = useBranding();

const isFullscreen = ref(false);

const safeRoute = (name, fallback = '/') => {
    if (typeof route !== 'function') return fallback;

    try {
        return route(name);
    } catch {
        return fallback;
    }
};

const portalUrl = computed(() => {
    const role = String(user.value?.role ?? '').toLowerCase();
    if (role === 'master') return safeRoute('master.home', '/master/home');
    return safeRoute('admin.home', '/app/home');
});

const userInitial = computed(() => {
    const safe = String(user.value?.name ?? '').trim();
    return safe ? safe.charAt(0).toUpperCase() : 'U';
});

const refreshFullscreenState = () => {
    if (typeof document === 'undefined') return;
    isFullscreen.value = Boolean(document.fullscreenElement);
};

const toggleFullscreen = async () => {
    if (typeof document === 'undefined') return;

    try {
        if (document.fullscreenElement) {
            await document.exitFullscreen();
        } else {
            await document.documentElement.requestFullscreen();
        }
    } catch {
        // ignore
    }
};

const doLogout = () => {
    router.post(safeRoute('logout', '/logout'));
};

const shouldContinueLeaving = () => {
    if (!props.confirmLeave) return true;
    return window.confirm('Existe um carrinho em andamento. Deseja sair do PDV mesmo assim?');
};

const leavePdv = () => {
    if (!shouldContinueLeaving()) return;
    router.visit(portalUrl.value);
};

const openProfile = () => {
    if (!shouldContinueLeaving()) return;
    router.visit(safeRoute('profile.edit', '/profile'));
};

onMounted(() => {
    refreshFullscreenState();
    document.addEventListener('fullscreenchange', refreshFullscreenState);
});

onBeforeUnmount(() => {
    document.removeEventListener('fullscreenchange', refreshFullscreenState);
});
</script>

<template>
    <Head>
        <link v-if="publicFaviconHref" rel="icon" :href="publicFaviconHref" :type="publicFaviconType" />
    </Head>

    <div class="min-h-screen bg-slate-100 text-slate-900 md:h-screen md:overflow-hidden" :style="themeStyles">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 w-full max-w-[1920px] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white">
                            <img :src="systemIconUrl" :alt="brandName" class="h-6 w-6 object-contain" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ props.title }}</p>
                            <p class="truncate text-xs text-slate-500">{{ props.subtitle }}</p>
                        </div>
                    </div>
                </div>

                <div class="hidden min-w-0 flex-1 items-center justify-center md:flex">
                    <slot name="status" />
                </div>

                <div class="flex items-center gap-2">
                    <slot name="actions" />
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                        :title="isFullscreen ? 'Sair da tela cheia' : 'Tela cheia'"
                        @click="toggleFullscreen"
                    >
                        <component :is="isFullscreen ? Minimize : Expand" class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="hidden items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 sm:inline-flex"
                        @click="leavePdv"
                    >
                        <ArrowLeft class="h-3.5 w-3.5" />
                        Sair do PDV
                    </button>
                    <button
                        type="button"
                        class="hidden items-center gap-2 rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-700 sm:inline-flex"
                        @click="doLogout"
                    >
                        <LogOut class="h-3.5 w-3.5" />
                        Sair
                    </button>
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                        title="Perfil"
                        @click="openProfile"
                    >
                        <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user?.name ?? 'Avatar'" class="h-full w-full object-cover" />
                        <span v-else class="text-xs font-semibold">{{ userInitial }}</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100 sm:hidden"
                        title="Sair"
                        @click="doLogout"
                    >
                        <LogOut class="h-4 w-4" />
                    </button>
                </div>
            </div>
            <div class="border-t border-slate-100 bg-white px-4 py-2 md:hidden">
                <slot name="status" />
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1920px] px-4 py-4 sm:px-6 lg:px-8 md:h-[calc(100vh-4rem)] md:overflow-hidden">
            <slot />
        </main>
    </div>
</template>
