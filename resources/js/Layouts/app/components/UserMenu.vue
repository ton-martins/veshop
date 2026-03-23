<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronUp } from 'lucide-vue-next';
import { Icon } from '@iconify/vue';
import userIcon from '@iconify-icons/iconoir/user';
import logOutIcon from '@iconify-icons/iconoir/log-out';

const props = defineProps({
    collapsed: { type: Boolean, default: false },
    user: { type: Object, default: null },
    userAvatarUrl: { type: String, default: '' },
    userInitial: { type: String, default: 'U' },
    profileHref: { type: String, default: '/profile' },
});

const emit = defineEmits(['logout']);

const page = usePage();
const userMenuOpen = ref(false);
const userMenuRef = ref(null);

const closeUserMenu = () => {
    userMenuOpen.value = false;
};

const handleUserMenuClickOutside = (event) => {
    if (!userMenuOpen.value) return;

    const container = userMenuRef.value;
    if (!container) return;
    if (container.contains(event.target)) return;

    closeUserMenu();
};

const handleGlobalKeydown = (event) => {
    if (event.key === 'Escape') {
        closeUserMenu();
    }
};

onMounted(() => {
    if (typeof document !== 'undefined') {
        document.addEventListener('mousedown', handleUserMenuClickOutside);
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleGlobalKeydown);
    }
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.removeEventListener('mousedown', handleUserMenuClickOutside);
    }

    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleGlobalKeydown);
    }
});

watch(
    () => page.url,
    () => {
        closeUserMenu();
    },
);

watch(
    () => props.collapsed,
    () => {
        closeUserMenu();
    },
);
</script>

<template>
    <div ref="userMenuRef" class="veshop-foot-popover">
        <button
            type="button"
            class="veshop-foot-user-trigger"
            :class="{ 'is-collapsed': collapsed, 'is-open': userMenuOpen }"
            title="Menu do usuário"
            aria-haspopup="menu"
            :aria-expanded="userMenuOpen ? 'true' : 'false'"
            @click="userMenuOpen = !userMenuOpen"
        >
            <div
                class="relative flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-slate-100 text-slate-700 ring-1 ring-slate-200"
            >
                <img
                    v-if="userAvatarUrl"
                    :src="userAvatarUrl"
                    :alt="user?.name ?? 'Avatar'"
                    class="h-full w-full object-cover"
                />
                <span v-else class="text-sm font-semibold text-slate-700">{{ userInitial }}</span>
                <span
                    class="absolute -bottom-1 -right-1 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"
                />
            </div>
            <div v-if="!collapsed" class="veshop-foot-user-meta min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900">
                    {{ user?.name ?? 'Usuário' }}
                </p>
                <p class="truncate text-xs text-slate-600">{{ user?.email ?? '' }}</p>
            </div>
            <span v-if="!collapsed" class="veshop-foot-user-arrow-btn" aria-hidden="true">
                <ChevronUp class="veshop-foot-user-arrow" />
            </span>
        </button>

        <transition name="fade">
            <div
                v-if="userMenuOpen"
                class="veshop-foot-menu-popover"
                :class="collapsed ? 'is-collapsed' : ''"
                role="menu"
            >
                <Link :href="profileHref" class="veshop-foot-menu-link" @click="closeUserMenu">
                    <span class="veshop-foot-menu-main">
                        <Icon :icon="userIcon" class="veshop-foot-menu-icon" />
                        <span class="truncate">Perfil</span>
                    </span>
                </Link>
                <button
                    type="button"
                    class="veshop-foot-menu-link is-danger"
                    @click="
                        closeUserMenu();
                        emit('logout');
                    "
                >
                    <span class="veshop-foot-menu-main">
                        <Icon :icon="logOutIcon" class="veshop-foot-menu-icon" />
                        <span class="truncate">Sair</span>
                    </span>
                </button>
            </div>
        </transition>
    </div>
</template>
