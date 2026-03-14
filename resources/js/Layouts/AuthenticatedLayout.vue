<script setup>
import { computed, onMounted, ref, useSlots, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import { useBranding } from '@/branding';
import { masterMenuGroups } from '@/navigation/masterMenu';
import { adminMenuGroups } from '@/navigation/adminMenu';
import {
    LayoutDashboard,
    Briefcase,
    Cog,
    ClipboardList,
    UsersRound,
    Users,
    Building2,
    ServerCog,
    Network,
    LifeBuoy,
    UserCircle2,
    History,
    LogOut,
    Menu,
    X,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Palette,
    Store,
    Package,
    BookOpenCheck,
    Bell,
} from 'lucide-vue-next';

const props = defineProps({
    hideNav: { type: Boolean, default: false },
    area: { type: String, default: null },
    headerVariant: { type: String, default: 'compact' },
    headerTitle: { type: String, default: '' },
    headerIcon: { type: String, default: '' },
});

const iconMap = {
    LayoutDashboard,
    Briefcase,
    Cog,
    ClipboardList,
    UsersRound,
    Users,
    Building2,
    ServerCog,
    Network,
    LifeBuoy,
    UserCircle2,
    History,
    LogOut,
    Menu,
    X,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Palette,
    Store,
    Package,
    BookOpenCheck,
    Bell,
};

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const unreadNotifications = computed(() => {
    const raw = Number(page.props.notifications?.unread_count ?? 0);
    if (!Number.isFinite(raw) || raw < 0) return 0;

    return Math.floor(raw);
});
const contractorContext = computed(() => page.props.contractorContext ?? { current: null, available: [] });
const currentContractor = computed(() => contractorContext.value.current ?? null);
const availableContractors = computed(() => contractorContext.value.available ?? []);
const canSwitchContractor = computed(() => availableContractors.value.length > 1);
const contractorNiche = computed(() => String(currentContractor.value?.business_niche ?? 'commercial').toLowerCase());
const contractorNicheLabel = computed(() => {
    const explicitLabel = String(currentContractor.value?.business_niche_label ?? '').trim();
    if (explicitLabel) return explicitLabel;

    return contractorNiche.value === 'services' ? 'Serviços' : 'Comércio';
});
const contractorPlanName = computed(() => {
    const raw = String(currentContractor.value?.active_plan_name ?? '').trim();
    return raw || 'Sem plano';
});
const contractorEnabledModules = computed(() => {
    const raw = currentContractor.value?.enabled_modules;
    if (!Array.isArray(raw) || raw.length === 0) {
        return contractorNiche.value === 'services' ? ['services'] : ['commercial'];
    }

    return raw
        .map((module) => String(module ?? '').trim().toLowerCase())
        .filter(Boolean);
});

const currentArea = computed(() => {
    if (props.area === 'master' || props.area === 'admin') {
        return props.area;
    }

    return user.value?.role === 'master' ? 'master' : 'admin';
});

const systemContextLabel = computed(() => {
    if (currentContractor.value) {
        return contractorNicheLabel.value;
    }

    return currentArea.value === 'master' ? 'Área Master' : 'Área Admin';
});

const systemBrandName = 'Veshop';
const systemIconUrl = '/brand/icone-veshop.png';

const {
    contractorActiveGradient,
    publicFaviconHref,
    publicFaviconType,
    userAvatarUrl,
    themeStyles,
    normalizeHex,
    primaryColor,
} = useBranding();

const contractorName = computed(() => currentContractor.value?.brand_name || currentContractor.value?.name || '');
const contractorLogoUrl = computed(() => currentContractor.value?.brand_avatar_url || currentContractor.value?.brand_logo_url || '');

const contractorInitials = computed(() => {
    const safe = String(contractorName.value || '').trim();
    if (!safe) return 'CT';

    const parts = safe.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) || '';
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';

    return `${first}${last}`.toUpperCase() || 'CT';
});

const userInitial = computed(() => {
    const safe = String(user.value?.name ?? '').trim();
    return safe ? safe.charAt(0).toUpperCase() : 'U';
});

const safeRoute = (name, fallback = '/') => {
    if (typeof route !== 'function') return fallback;

    try {
        return route(name);
    } catch {
        return fallback;
    }
};

const safeRouteCurrent = (pattern) => {
    if (typeof route !== 'function') return false;

    try {
        return route().current(pattern);
    } catch {
        return false;
    }
};

const getContractorRouteKey = (contractor) => contractor?.uuid || contractor?.id || null;

const selectedContractorId = ref(null);

watch(
    [currentContractor, availableContractors],
    () => {
        selectedContractorId.value =
            getContractorRouteKey(currentContractor.value) ||
            getContractorRouteKey(availableContractors.value[0]);
    },
    { immediate: true },
);

const resolveContractorColor = (contractor) => normalizeHex(contractor?.brand_primary_color || '', primaryColor.value);

const resolveContractorInitials = (value) => {
    const safe = String(value ?? '').trim();
    if (!safe) return 'CT';

    const parts = safe.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) || '';
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';

    return `${first}${last}`.toUpperCase() || 'CT';
};

const isCurrentContractorOption = (candidate) => {
    const currentKey = getContractorRouteKey(currentContractor.value);
    const candidateKey = getContractorRouteKey(candidate);

    return Boolean(currentKey && candidateKey && String(currentKey) === String(candidateKey));
};

const switchContractorTo = (contractor) => {
    const targetId = getContractorRouteKey(contractor);
    if (!targetId) return;

    const currentKey = getContractorRouteKey(currentContractor.value);
    if (String(targetId) === String(currentKey)) return;

    try {
        router.post(route('contractor.switch'), { contractor_id: targetId }, { preserveScroll: true });
    } catch {
        // ignore
    }
};

const toMenuWithIcons = (groups) =>
    groups.map((group) => ({
        ...group,
        iconComponent: iconMap[group.icon] ?? LayoutDashboard,
        links: group.links.map((link) => ({
            ...link,
            iconComponent: iconMap[link.icon] ?? LayoutDashboard,
        })),
    }));

const filteredAdminMenuGroups = computed(() =>
    adminMenuGroups
        .filter((group) => {
            if (!group.module) return true;

            return contractorEnabledModules.value.includes(group.module);
        })
        .map((group) => ({
            ...group,
            links: (group.links ?? []).filter((link) => {
                if (!link.module) return true;

                return contractorEnabledModules.value.includes(link.module);
            }),
        }))
        .filter((group) => (group.links ?? []).length > 0),
);

const menuGroups = computed(() =>
    toMenuWithIcons(currentArea.value === 'master' ? masterMenuGroups : filteredAdminMenuGroups.value),
);

const collapsedLinks = computed(() => menuGroups.value.flatMap((group) => group.links));

const isLinkActive = (link) => {
    const patterns = Array.isArray(link.match)
        ? link.match
        : link.match
          ? [link.match]
          : [link.route];

    return patterns.some((pattern) => safeRouteCurrent(pattern));
};

const mobileQuickLinks = computed(() => {
    const allLinks = collapsedLinks.value;
    if (!allLinks.length) return [];

    const initialLinks = allLinks.slice(0, 4);
    const activeLink = allLinks.find((link) => isLinkActive(link));

    if (!activeLink) {
        return initialLinks;
    }

    const alreadyIncluded = initialLinks.some((link) => link.key === activeLink.key);
    if (alreadyIncluded) {
        return initialLinks;
    }

    if (!initialLinks.length) {
        return [activeLink];
    }

    return [...initialLinks.slice(0, Math.max(initialLinks.length - 1, 0)), activeLink];
});

const sidebarOpen = ref(false);
const sidebarCollapsed = ref(false);
const expandedGroups = ref(new Set());
const slots = useSlots();

const supportedHeaderVariants = ['compact', 'none'];

const normalizedHeaderVariant = computed(() =>
    supportedHeaderVariants.includes(props.headerVariant) ? props.headerVariant : 'compact',
);

const resolvedHeaderTitle = computed(() => String(props.headerTitle ?? '').trim());

const hasHeaderSlot = computed(() => Boolean(slots.header));
const hasDefaultHeaderContent = computed(
    () => Boolean(resolvedHeaderTitle.value),
);
const showDefaultHeader = computed(
    () =>
        !hasHeaderSlot.value &&
        normalizedHeaderVariant.value !== 'none' &&
        hasDefaultHeaderContent.value,
);
const showHeader = computed(() => hasHeaderSlot.value || showDefaultHeader.value);

const defaultHeaderTitleClass = computed(
    () => 'text-xl font-semibold text-slate-900',
);

const isGroupExpanded = (key) => expandedGroups.value.has(key);

const persistExpandedGroups = () => {
    try {
        window.localStorage.setItem('veshop:sidebar-groups', JSON.stringify(Array.from(expandedGroups.value)));
    } catch {
        // ignore
    }
};

const toggleGroup = (key) => {
    const next = new Set(expandedGroups.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    expandedGroups.value = next;
    persistExpandedGroups();
};

const ensureExpandedGroups = () => {
    const groupKeys = menuGroups.value.map((group) => group.key);
    const next = new Set(expandedGroups.value);

    Array.from(next).forEach((key) => {
        if (!groupKeys.includes(key)) next.delete(key);
    });

    const activeGroup = menuGroups.value.find((group) =>
        group.links.some((link) => isLinkActive(link)),
    );

    if (activeGroup) {
        next.add(activeGroup.key);
    }

    if (!next.size && groupKeys.length) {
        next.add(groupKeys[0]);
    }

    expandedGroups.value = next;
    persistExpandedGroups();
};

watch(menuGroups, ensureExpandedGroups, { immediate: true });

onMounted(() => {
    try {
        sidebarCollapsed.value = window.localStorage.getItem('veshop:sidebar-collapsed') === '1';

        const storedGroups = JSON.parse(window.localStorage.getItem('veshop:sidebar-groups') ?? '[]');
        expandedGroups.value = new Set(Array.isArray(storedGroups) ? storedGroups : []);
    } catch {
        sidebarCollapsed.value = false;
        expandedGroups.value = new Set();
    }

    ensureExpandedGroups();
});

watch(sidebarCollapsed, () => {
    try {
        window.localStorage.setItem('veshop:sidebar-collapsed', sidebarCollapsed.value ? '1' : '0');
    } catch {
        // ignore
    }
});

const toggleSidebarCollapsed = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const doLogout = () => {
    router.post(safeRoute('logout', '/logout'));
};

const openNotifications = () => {
    if (typeof route !== 'function') return;

    try {
        if (route().has('notifications.index')) {
            router.visit(route('notifications.index'));
        }
    } catch {
        // ignore when notifications route does not exist yet
    }
};
</script>

<template>
    <Head>
        <link v-if="publicFaviconHref" rel="icon" :href="publicFaviconHref" :type="publicFaviconType" />
    </Head>

    <div class="min-h-screen bg-slate-100/80 text-slate-900" :style="themeStyles">
        <template v-if="props.hideNav">
            <header class="sticky top-0 z-40 flex items-center justify-between border-b border-white/60 bg-white/90 px-4 py-3 shadow-sm backdrop-blur">
                <Link :href="safeRoute('home', '/home')" class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-md bg-slate-900 text-base font-semibold text-white">
                        <img :src="systemIconUrl" :alt="systemBrandName" class="h-6 w-6 object-contain" />
                    </div>
                    <span class="text-sm font-semibold text-slate-900">{{ systemBrandName }}</span>
                </Link>
                <div class="flex items-center gap-2">
                    <Link :href="safeRoute('profile.edit', '/profile')" class="rounded-full bg-white/90 px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-white">Perfil</Link>
                    <button type="button" @click="doLogout" class="inline-flex items-center gap-2 rounded-full bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm ring-1 ring-rose-500/30 hover:bg-rose-700">
                        <LogOut class="h-4 w-4" />
                        Sair
                    </button>
                </div>
            </header>
            <main class="px-4 py-6 sm:px-6 lg:px-8"><slot /></main>
        </template>

        <template v-else>
            <div class="flex min-h-screen">
                <aside class="relative hidden md:flex md:sticky md:top-0 md:h-screen flex-col border-r border-slate-800 bg-slate-900 shadow-2xl transition-all duration-300" :class="sidebarCollapsed ? 'w-20' : 'w-72'">
                    <button type="button" class="absolute -right-3 top-6 hidden h-8 w-8 items-center justify-center rounded-full border border-slate-700 bg-slate-800 text-slate-200 shadow transition hover:bg-slate-700 md:flex" :title="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'" @click="toggleSidebarCollapsed">
                        <component :is="sidebarCollapsed ? ChevronRight : ChevronLeft" class="h-4 w-4" />
                    </button>

                    <div class="border-b border-slate-800 px-4 py-5">
                        <Link :href="safeRoute('home', '/home')" class="flex w-full items-center gap-3" :class="sidebarCollapsed ? 'justify-center' : ''">
                            <div class="flex h-10 w-10 items-center justify-center rounded-md bg-slate-800 text-base font-semibold text-white ring-1 ring-slate-700">
                                <img :src="systemIconUrl" :alt="systemBrandName" class="h-6 w-6 object-contain" />
                            </div>
                            <div v-if="!sidebarCollapsed" class="flex flex-col leading-tight">
                                <span class="text-sm font-semibold text-slate-100">{{ systemBrandName }}</span>
                                <span class="mt-1 inline-flex w-fit items-center rounded-full bg-slate-800 px-2 py-0.5 text-[10px] font-semibold text-slate-200 ring-1 ring-slate-700">
                                    {{ systemContextLabel }}
                                </span>
                            </div>
                        </Link>

                        <div v-if="currentContractor" class="mt-4 rounded-xl border border-slate-700 bg-slate-800/60" :class="sidebarCollapsed ? 'px-2 py-2' : 'px-3 py-2'">
                            <div class="flex items-center gap-3" :class="sidebarCollapsed ? 'justify-center' : ''">
                                <div class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg ring-1 ring-emerald-200/70" :style="contractorLogoUrl ? null : { background: 'var(--contractor-primary)' }">
                                    <img v-if="contractorLogoUrl" :src="contractorLogoUrl" :alt="contractorName" class="h-full w-full rounded-lg object-cover" />
                                    <span v-else class="text-xs font-semibold text-white">{{ contractorInitials }}</span>
                                </div>
                                <div v-if="!sidebarCollapsed" class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-100">{{ contractorName }}</p>
                                    <span class="mt-1 inline-flex w-fit items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                        {{ contractorPlanName }}
                                    </span>
                                </div>

                                <Dropdown v-if="canSwitchContractor && !sidebarCollapsed" align="right" width="48" content-classes="py-2 bg-white" class="ml-auto">
                                    <template #trigger>
                                        <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-700 bg-slate-900 text-slate-200 shadow-sm transition hover:bg-slate-700" title="Trocar contratante" aria-label="Trocar contratante">
                                            <ChevronDown class="h-4 w-4" />
                                        </button>
                                    </template>
                                    <template #content>
                                        <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Trocar contratante</p>
                                        <div class="space-y-1">
                                            <button v-for="contractor in availableContractors" :key="contractor.uuid ?? contractor.id" type="button" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-xs font-semibold transition" :class="isCurrentContractorOption(contractor) ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'" @click="switchContractorTo(contractor)">
                                                <span class="flex h-6 w-6 items-center justify-center overflow-hidden rounded-md text-[10px] font-semibold text-white" :style="contractor.brand_avatar_url ? null : { background: resolveContractorColor(contractor) }">
                                                    <img v-if="contractor.brand_avatar_url" :src="contractor.brand_avatar_url" :alt="contractor.brand_name ?? contractor.name" class="h-full w-full object-cover" />
                                                    <span v-else>{{ resolveContractorInitials(contractor.brand_name ?? contractor.name) }}</span>
                                                </span>
                                                <span class="min-w-0 flex-1 truncate">{{ contractor.brand_name ?? contractor.name }}</span>
                                                <span v-if="isCurrentContractorOption(contractor)" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Atual</span>
                                            </button>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto">
                        <nav :class="sidebarCollapsed ? 'flex flex-col items-center gap-3 p-3' : 'space-y-3 p-4'">
                            <template v-if="sidebarCollapsed">
                                <Link v-for="link in collapsedLinks" :key="link.key" :href="safeRoute(link.route, '#')" class="flex h-10 w-10 items-center justify-center rounded-xl text-slate-300 transition" :class="isLinkActive(link) ? 'text-white shadow-inner shadow-black/10' : 'hover:bg-slate-800 hover:text-slate-100'" :style="isLinkActive(link) ? { background: contractorActiveGradient } : null" :title="link.label" :aria-label="link.label">
                                    <component :is="link.iconComponent" class="h-4 w-4 opacity-90" />
                                </Link>
                            </template>
                            <template v-else>
                                <div v-for="group in menuGroups" :key="group.key" class="relative">
                                    <button type="button" class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-100 transition hover:bg-slate-800" @click="toggleGroup(group.key)">
                                        <span class="flex items-center gap-3">
                                            <component :is="group.iconComponent" class="h-4 w-4 text-slate-300" />
                                            {{ group.label }}
                                        </span>
                                        <ChevronDown class="h-4 w-4 text-slate-400 transition" :class="isGroupExpanded(group.key) ? 'rotate-180' : ''" />
                                    </button>
                                    <transition name="fade">
                                        <ul v-show="isGroupExpanded(group.key)" class="mt-2 space-y-1 pl-2">
                                            <li v-for="link in group.links" :key="link.key">
                                                <Link :href="safeRoute(link.route, '#')" class="group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition" :class="isLinkActive(link) ? 'text-white shadow-inner shadow-black/10' : 'text-slate-300 hover:bg-slate-800 hover:text-slate-100'" :style="isLinkActive(link) ? { background: contractorActiveGradient } : null">
                                                    <component :is="link.iconComponent" class="h-4 w-4 opacity-90" />
                                                    <span class="text-sm">{{ link.label }}</span>
                                                </Link>
                                            </li>
                                        </ul>
                                    </transition>
                                </div>
                            </template>
                        </nav>
                    </div>

                    <div class="border-t border-slate-800" :class="sidebarCollapsed ? 'p-3' : 'p-4'">
                        <div class="rounded-2xl border border-slate-700 bg-slate-800 p-3 shadow-sm" :class="sidebarCollapsed ? 'flex flex-col items-center gap-3' : ''">
                            <div class="flex items-center gap-3" :class="sidebarCollapsed ? 'justify-center' : ''">
                                <div class="relative flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-slate-700 text-slate-100 ring-1 ring-slate-600">
                                    <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user?.name ?? 'Avatar'" class="h-full w-full object-cover" />
                                    <span v-else class="text-sm font-semibold text-slate-100">{{ userInitial }}</span>
                                    <span class="absolute -bottom-1 -right-1 h-3 w-3 rounded-full border-2 border-slate-800 bg-emerald-500" />
                                </div>
                                <div v-if="!sidebarCollapsed" class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-100">{{ user?.name ?? 'Usuário' }}</p>
                                    <p class="truncate text-xs text-slate-300">{{ user?.email ?? '' }}</p>
                                </div>
                            </div>

                            <template v-if="!sidebarCollapsed">
                                <div class="mt-3 grid gap-2">
                                    <Link :href="safeRoute('profile.edit', '/profile')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-700 px-2.5 py-1.5 text-xs font-medium text-slate-100 ring-1 ring-slate-600 transition hover:bg-slate-600">
                                        <UserCircle2 class="h-4 w-4" />
                                        Perfil
                                    </Link>
                                    <button type="button" @click="doLogout" class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 ring-1 ring-rose-200/80 transition hover:bg-rose-100">
                                        <LogOut class="h-4 w-4" />
                                        Sair
                                    </button>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex flex-col items-center gap-2">
                                    <Link :href="safeRoute('profile.edit', '/profile')" class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-700 text-slate-100 ring-1 ring-slate-600 transition hover:bg-slate-600" title="Perfil" aria-label="Perfil">
                                        <UserCircle2 class="h-4 w-4" />
                                    </Link>
                                    <button type="button" @click="doLogout" class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-700 ring-1 ring-rose-200/80 transition hover:bg-rose-100" title="Sair" aria-label="Sair">
                                        <LogOut class="h-4 w-4" />
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </aside>

                <div class="flex flex-1 flex-col">
                    <header class="flex items-center border-b border-white/60 bg-white/80 px-4 py-4 shadow-sm backdrop-blur md:hidden">
                        <Link :href="safeRoute('home', '/home')" class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-md bg-slate-900 text-sm font-semibold text-white">
                                <img :src="systemIconUrl" :alt="systemBrandName" class="h-6 w-6 object-contain" />
                            </div>
                            <div class="flex flex-col leading-tight">
                                <span class="text-[13px] font-semibold text-slate-900">{{ systemBrandName }}</span>
                                <span class="mt-1 inline-flex w-fit items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                    {{ systemContextLabel }}
                                </span>
                            </div>
                        </Link>
                    </header>

                    <main class="flex-1 overflow-y-auto bg-slate-100/80">
                        <div class="px-4 py-6 pb-24 sm:px-6 lg:px-8 md:pb-6">
                            <template v-if="hasHeaderSlot">
                                <slot name="header" />
                            </template>

                            <template v-else-if="showDefaultHeader">
                                <h1 v-if="resolvedHeaderTitle" :class="defaultHeaderTitleClass">{{ resolvedHeaderTitle }}</h1>
                            </template>

                            <div :class="showHeader ? 'mt-6' : ''"><slot /></div>
                        </div>
                    </main>

                    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-800 bg-slate-900/95 px-2 pt-2 pb-[max(env(safe-area-inset-bottom),0.45rem)] shadow-[0_-10px_30px_-20px_rgba(15,23,42,0.6)] backdrop-blur md:hidden">
                        <div class="mx-auto flex max-w-lg items-end gap-1">
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold text-slate-300 transition hover:bg-slate-800 hover:text-slate-100"
                                @click="sidebarOpen = true"
                            >
                                <Menu class="h-4 w-4" />
                                <span class="truncate">Menu</span>
                            </button>

                            <Link
                                v-for="link in mobileQuickLinks"
                                :key="`mobile-${link.key}`"
                                :href="safeRoute(link.route, '#')"
                                class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold transition"
                                :class="isLinkActive(link) ? 'text-white shadow-inner shadow-black/10' : 'text-slate-300 hover:bg-slate-800 hover:text-slate-100'"
                                :style="isLinkActive(link) ? { background: contractorActiveGradient } : null"
                            >
                                <component :is="link.iconComponent" class="h-4 w-4" />
                                <span class="truncate">{{ link.label }}</span>
                            </Link>
                        </div>
                    </nav>
                </div>

                <transition name="fade">
                    <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm md:hidden" @click.self="closeSidebar">
                        <div class="absolute left-0 top-0 flex h-full w-72 flex-col bg-slate-900 shadow-xl">
                            <div class="border-b border-slate-800 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-slate-900 text-xs font-semibold text-white">
                                            <img :src="systemIconUrl" :alt="systemBrandName" class="h-5 w-5 object-contain" />
                                        </div>
                                        <div class="flex flex-col leading-tight">
                                            <span class="text-sm font-semibold text-slate-100">{{ systemBrandName }}</span>
                                            <span class="mt-1 inline-flex w-fit items-center rounded-full bg-slate-800 px-2 py-0.5 text-[10px] font-semibold text-slate-200 ring-1 ring-slate-700">
                                                {{ systemContextLabel }}
                                            </span>
                                        </div>
                                    </div>
                                    <button type="button" class="rounded-full bg-slate-800 p-2 text-slate-200" @click="closeSidebar" title="Fechar">
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div class="flex-1 overflow-y-auto p-4">
                                <div
                                    v-if="currentContractor"
                                    class="mb-4 rounded-xl border border-slate-700 bg-slate-800/60 px-3 py-2"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg ring-1 ring-emerald-200/70" :style="contractorLogoUrl ? null : { background: 'var(--contractor-primary)' }">
                                            <img v-if="contractorLogoUrl" :src="contractorLogoUrl" :alt="contractorName" class="h-full w-full rounded-lg object-cover" />
                                            <span v-else class="text-xs font-semibold text-white">{{ contractorInitials }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-semibold text-slate-100">{{ contractorName }}</p>
                                            <span class="mt-1 inline-flex w-fit items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                {{ contractorPlanName }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div v-for="group in menuGroups" :key="group.key" class="mb-3 space-y-2 border-b border-slate-800 pb-3 last:mb-0 last:border-b-0 last:pb-0">
                                    <button type="button" class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-semibold text-slate-100 transition hover:bg-slate-800" @click="toggleGroup(group.key)">
                                        <span class="flex items-center gap-3">
                                            <component :is="group.iconComponent" class="h-4 w-4 text-slate-300" />
                                            {{ group.label }}
                                        </span>
                                        <ChevronDown class="h-4 w-4 text-slate-400 transition" :class="isGroupExpanded(group.key) ? 'rotate-180' : ''" />
                                    </button>
                                    <transition name="fade">
                                        <ul v-show="isGroupExpanded(group.key)" class="mt-2 space-y-1 pl-3">
                                            <li v-for="link in group.links" :key="link.key">
                                                <Link :href="safeRoute(link.route, '#')" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition" :class="isLinkActive(link) ? 'text-white shadow-inner shadow-black/10' : 'text-slate-300 hover:bg-slate-800 hover:text-slate-100'" :style="isLinkActive(link) ? { background: contractorActiveGradient } : null" @click="closeSidebar">
                                                    <component :is="link.iconComponent" class="h-4 w-4 opacity-90" />
                                                    {{ link.label }}
                                                </Link>
                                            </li>
                                        </ul>
                                    </transition>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </template>

        <button
            type="button"
            class="fixed right-4 z-30 inline-flex h-12 w-12 items-center justify-center rounded-full bg-slate-900 text-white shadow-xl transition hover:bg-slate-800 md:bottom-5 bottom-24"
            title="Notificações"
            aria-label="Notificações"
            @click="openNotifications"
        >
            <Bell class="h-5 w-5" />
            <span
                v-if="unreadNotifications > 0"
                class="absolute -right-1 -top-1 inline-flex min-w-[18px] items-center justify-center rounded-full bg-rose-500 px-1 py-0.5 text-[10px] font-semibold text-white"
            >
                {{ unreadNotifications > 99 ? '99+' : unreadNotifications }}
            </span>
        </button>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 150ms ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
