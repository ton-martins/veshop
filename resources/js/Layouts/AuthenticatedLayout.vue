<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useSlots, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import { useBranding } from '@/branding';
import { masterMenuGroups } from '@/navigation/masterMenu';
import { adminMenuGroups } from '@/navigation/adminMenu';
import {
    UserCircle2,
    LogOut,
    Menu,
    X,
    ChevronDown,
    ChevronUp,
    ChevronLeft,
    ChevronRight,
    List,
    LayoutGrid,
    Bell,
} from 'lucide-vue-next';
import { Icon } from '@iconify/vue';
import homeSimpleIcon from '@iconify-icons/iconoir/home-simple';
import candlestickChartIcon from '@iconify-icons/iconoir/candlestick-chart';
import boxIsoIcon from '@iconify-icons/iconoir/box-iso';
import boxIcon from '@iconify-icons/iconoir/box';
import onTagIcon from '@iconify-icons/iconoir/on-tag';
import largeSuitcaseIcon from '@iconify-icons/iconoir/large-suitcase';
import settingsIcon from '@iconify-icons/iconoir/settings';
import clipboardCheckIcon from '@iconify-icons/iconoir/clipboard-check';
import shoppingBagIcon from '@iconify-icons/iconoir/shopping-bag';
import groupIcon from '@iconify-icons/iconoir/group';
import userIcon from '@iconify-icons/iconoir/user';
import cityIcon from '@iconify-icons/iconoir/city';
import serverConnectionIcon from '@iconify-icons/iconoir/server-connection';
import truckIcon from '@iconify-icons/iconoir/truck';
import networkIcon from '@iconify-icons/iconoir/network';
import lifebeltIcon from '@iconify-icons/iconoir/lifebelt';
import clockIcon from '@iconify-icons/iconoir/clock';
import walletIcon from '@iconify-icons/iconoir/wallet';
import percentageCircleIcon from '@iconify-icons/iconoir/percentage-circle';
import journalPageIcon from '@iconify-icons/iconoir/journal-page';
import checkCircleIcon from '@iconify-icons/iconoir/check-circle';
import calendarIcon from '@iconify-icons/iconoir/calendar';
import notesIcon from '@iconify-icons/iconoir/notes';
import paletteIcon from '@iconify-icons/iconoir/palette';
import shopIcon from '@iconify-icons/iconoir/shop';
import packageIcon from '@iconify-icons/iconoir/package';
import bellIcon from '@iconify-icons/iconoir/bell';
import compactDiscIcon from '@iconify-icons/iconoir/compact-disc';
import peaceHandIcon from '@iconify-icons/iconoir/peace-hand';
import tableRowsIcon from '@iconify-icons/iconoir/table-rows';
import trophyIcon from '@iconify-icons/iconoir/trophy';
import navigatorAltIcon from '@iconify-icons/iconoir/navigator-alt';
import sendMailIcon from '@iconify-icons/iconoir/send-mail';
import circleIcon from '@iconify-icons/iconoir/circle';
import logOutIcon from '@iconify-icons/iconoir/log-out';
import angleDownIcon from '@iconify-icons/la/angle-down';
import angleRightIcon from '@iconify-icons/la/angle-right';

const props = defineProps({
    hideNav: { type: Boolean, default: false },
    area: { type: String, default: null },
    headerVariant: { type: String, default: 'compact' },
    headerTitle: { type: String, default: '' },
    headerIcon: { type: String, default: '' },
    showTableViewToggle: { type: Boolean, default: true },
});

const menuArrowIcons = {
    down: angleDownIcon,
    right: angleRightIcon,
};

const menuIconMap = {
    LayoutDashboard: homeSimpleIcon,
    BarChart3: candlestickChartIcon,
    ChartNoAxesCombined: candlestickChartIcon,
    Box: boxIsoIcon,
    Boxes: boxIcon,
    Tags: onTagIcon,
    Briefcase: largeSuitcaseIcon,
    Cog: settingsIcon,
    ClipboardList: clipboardCheckIcon,
    ShoppingBag: shoppingBagIcon,
    UsersRound: groupIcon,
    Users: groupIcon,
    Users2: groupIcon,
    UserRound: userIcon,
    Building2: cityIcon,
    ServerCog: serverConnectionIcon,
    Truck: truckIcon,
    Network: networkIcon,
    LifeBuoy: lifebeltIcon,
    History: clockIcon,
    WalletCards: walletIcon,
    CreditCard: walletIcon,
    CircleDollarSign: walletIcon,
    Banknote: walletIcon,
    PieChart: percentageCircleIcon,
    Clock3: clockIcon,
    FileText: journalPageIcon,
    CircleCheckBig: checkCircleIcon,
    CalendarClock: calendarIcon,
    ReceiptText: notesIcon,
    Palette: paletteIcon,
    Store: shopIcon,
    Package: packageIcon,
    BookOpenCheck: journalPageIcon,
    Bell: bellIcon,
    CompactDisc: compactDiscIcon,
    PeaceHand: peaceHandIcon,
    TableRows: tableRowsIcon,
    Trophy: trophyIcon,
    Navigator: navigatorAltIcon,
    SendMail: sendMailIcon,
};

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const unreadNotifications = computed(() => {
    const raw = Number(page.props.notifications?.unread_count ?? 0);
    if (!Number.isFinite(raw) || raw < 0) return 0;

    return Math.floor(raw);
});
const notificationItems = computed(() => {
    const items = page.props.notifications?.items;
    return Array.isArray(items) ? items : [];
});
const contractorContext = computed(() => page.props.contractorContext ?? { current: null, available: [] });
const currentContractor = computed(() => contractorContext.value.current ?? null);
const availableContractors = computed(() => contractorContext.value.available ?? []);
const canSwitchContractor = computed(
    () => currentArea.value === 'admin' && availableContractors.value.length > 1,
);
const showContractorContext = computed(
    () => currentArea.value === 'admin' && Boolean(currentContractor.value),
);
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
const notificationsEnabled = computed(() =>
    currentArea.value !== 'admin' || contractorEnabledModules.value.includes('notifications'),
);

const systemContextLabel = computed(() => {
    if (showContractorContext.value) {
        return contractorNicheLabel.value;
    }

    return currentArea.value === 'master' ? 'Área Master' : 'Área Admin';
});

const {
    brandName,
    systemIconUrl,
    withAlpha,
    publicFaviconHref,
    publicFaviconType,
    userAvatarUrl,
    themeStyles,
    normalizeHex,
    primaryColor,
    secondaryColor,
} = useBranding();

const systemBrandName = computed(() => String(brandName.value || 'Veshop'));
const menuAccentColor = computed(() =>
    normalizeHex(currentContractor.value?.brand_primary_color || '', secondaryColor.value),
);
const sidebarMenuThemeStyles = computed(() => {
    const baseColor = menuAccentColor.value;

    return {
        '--veshop-menu-active': baseColor,
        '--veshop-menu-active-soft': withAlpha(baseColor, 0.08),
    };
});
const activeMenuBackground = computed(() => {
    const baseColor = menuAccentColor.value;

    return {
        background: `linear-gradient(145deg, ${withAlpha(baseColor, 0.96)} 0%, ${withAlpha(baseColor, 0.86)} 55%, ${withAlpha(baseColor, 0.8)} 100%)`,
        border: `1px solid ${withAlpha(baseColor, 0.62)}`,
        boxShadow: `inset 0 1px 0 rgba(255, 255, 255, 0.28), inset 0 -1px 0 rgba(15, 23, 42, 0.18), 0 8px 18px ${withAlpha(baseColor, 0.24)}, 0 2px 6px rgba(15, 23, 42, 0.14)`,
        transform: 'translateY(-1px)',
    };
});
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
    if (currentArea.value !== 'admin') return;

    const targetId = getContractorRouteKey(contractor);
    if (!targetId) return;

    const currentKey = getContractorRouteKey(currentContractor.value);
    if (String(targetId) === String(currentKey)) return;

    try {
        router.post(route('contractor.switch'), { contractor_id: targetId }, {
            preserveScroll: false,
            onSuccess: () => {
                router.visit(safeRoute('admin.home', '/app/home'));
            },
        });
    } catch {
        // ignore
    }
};

const toMenuWithIcons = (groups) =>
    groups.map((group) => ({
        ...group,
        iconToken: menuIconMap[group.icon] ?? circleIcon,
        links: (group.links ?? []).map((link) => ({
            ...link,
            iconToken: menuIconMap[link.icon] ?? circleIcon,
        })),
    }));

const hasEnabledModule = (required) => {
    if (!required) return true;

    const enabled = contractorEnabledModules.value;
    if (Array.isArray(required)) {
        return required.some((item) => enabled.includes(String(item ?? '').trim().toLowerCase()));
    }

    return enabled.includes(String(required ?? '').trim().toLowerCase());
};

const filteredAdminMenuGroups = computed(() =>
    adminMenuGroups
        .filter((group) => hasEnabledModule(group.module))
        .map((group) => ({
            ...group,
            links: (group.links ?? []).filter((link) => hasEnabledModule(link.module)),
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
const isGroupActive = (group) => (group?.links ?? []).some((link) => isLinkActive(link));

const mobileQuickLinks = computed(() => {
    const allLinks = collapsedLinks.value;
    if (!allLinks.length) return [];

    const preferredFixedKeys =
        currentArea.value === 'master'
            ? ['master-dashboard', 'master-support']
            : ['admin-dashboard', 'admin-orders'];

    const fixedLinks = preferredFixedKeys
        .map((key) => allLinks.find((link) => link.key === key))
        .filter(Boolean);

    if (fixedLinks.length < 2) {
        for (const link of allLinks) {
            if (fixedLinks.length >= 2) break;
            if (fixedLinks.some((item) => item.key === link.key)) continue;
            fixedLinks.push(link);
        }
    }

    let dynamicLink = allLinks.find(
        (link) => isLinkActive(link) && !fixedLinks.some((item) => item.key === link.key),
    );

    if (!dynamicLink) {
        dynamicLink = allLinks.find((link) => !fixedLinks.some((item) => item.key === link.key));
    }

    const quickLinks = [...fixedLinks];
    if (dynamicLink && !quickLinks.some((item) => item.key === dynamicLink.key)) {
        quickLinks.push(dynamicLink);
    }

    for (const link of allLinks) {
        if (quickLinks.length >= 3) break;
        if (quickLinks.some((item) => item.key === link.key)) continue;
        quickLinks.push(link);
    }

    return quickLinks.slice(0, 3);
});

const sidebarOpen = ref(false);
const notificationsPanelOpen = ref(false);
const sidebarCollapsed = ref(false);
const expandedGroups = ref(new Set());
const slots = useSlots();
const appMainRef = ref(null);
const markNotificationsForm = useForm({ id: '' });
const userMenuOpen = ref(false);
const userMenuRef = ref(null);

const TABLE_VIEW_STORAGE_KEY = 'veshop:table-view-mode';
const allowedTableViewModes = new Set(['list', 'cards']);
const tableViewMode = ref('list');
const hasAdaptiveTables = ref(false);
let tableMutationObserver = null;
let tableHydrationFrame = null;

const normalizeTableViewMode = (value) => {
    const normalized = String(value ?? '').trim().toLowerCase();
    return allowedTableViewModes.has(normalized) ? normalized : 'list';
};

const applyTableViewModeAttribute = () => {
    if (typeof document === 'undefined') return;
    document.documentElement.setAttribute('data-table-view-mode', tableViewMode.value);
};

const ensureTableScrollWrapper = (table) => {
    const parent = table.parentElement;
    if (!parent) return;

    if (parent.classList.contains('veshop-table-scroll')) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'veshop-table-scroll';
    parent.insertBefore(wrapper, table);
    wrapper.appendChild(table);
};

const hydrateAdaptiveTables = () => {
    const scope = appMainRef.value;
    if (!scope) {
        hasAdaptiveTables.value = false;
        return;
    }

    const tables = Array.from(scope.querySelectorAll('table'));
    hasAdaptiveTables.value = tables.length > 0;

    tables.forEach((table) => {
        table.classList.add('veshop-adaptive-table');
        ensureTableScrollWrapper(table);

        const headerLabels = Array.from(table.querySelectorAll('thead th')).map((th) =>
            String(th.textContent ?? '')
                .replace(/\s+/g, ' ')
                .trim(),
        );

        const rows = Array.from(table.querySelectorAll('tbody tr'));
        rows.forEach((row) => {
            const cells = Array.from(row.querySelectorAll('td'));
            if (!cells.length) return;

            const isEmptyStateRow =
                cells.length === 1 &&
                cells[0].hasAttribute('colspan');

            if (isEmptyStateRow) return;

            cells.forEach((cell, index) => {
                const currentLabel = String(cell.getAttribute('data-label') ?? '').trim();
                if (currentLabel) return;

                const label = headerLabels[index] || `Coluna ${index + 1}`;
                cell.setAttribute('data-label', label);
            });
        });
    });
};

const scheduleAdaptiveTablesHydration = () => {
    if (typeof window === 'undefined') return;

    if (tableHydrationFrame !== null) {
        window.cancelAnimationFrame(tableHydrationFrame);
    }

    tableHydrationFrame = window.requestAnimationFrame(() => {
        tableHydrationFrame = null;
        hydrateAdaptiveTables();
    });
};

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
const shouldShowTableViewToggle = computed(
    () => props.showTableViewToggle && hasAdaptiveTables.value,
);

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

    try {
        tableViewMode.value = normalizeTableViewMode(window.localStorage.getItem(TABLE_VIEW_STORAGE_KEY));
    } catch {
        tableViewMode.value = 'list';
    }

    ensureExpandedGroups();
    applyTableViewModeAttribute();
    scheduleAdaptiveTablesHydration();

    if (typeof window !== 'undefined' && window.MutationObserver && appMainRef.value) {
        tableMutationObserver = new window.MutationObserver(() => {
            scheduleAdaptiveTablesHydration();
        });

        tableMutationObserver.observe(appMainRef.value, {
            childList: true,
            subtree: true,
        });
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('keydown', handleGlobalKeydown);
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('mousedown', handleUserMenuClickOutside);
    }
});

watch(sidebarCollapsed, () => {
    try {
        window.localStorage.setItem('veshop:sidebar-collapsed', sidebarCollapsed.value ? '1' : '0');
    } catch {
        // ignore
    }

    closeUserMenu();
});

watch(tableViewMode, (mode) => {
    const normalizedMode = normalizeTableViewMode(mode);
    if (normalizedMode !== mode) {
        tableViewMode.value = normalizedMode;
        return;
    }

    applyTableViewModeAttribute();

    try {
        window.localStorage.setItem(TABLE_VIEW_STORAGE_KEY, normalizedMode);
    } catch {
        // ignore
    }
});

watch(
    () => page.url,
    () => {
        scheduleAdaptiveTablesHydration();
        closeUserMenu();
    },
    { flush: 'post' },
);

watch(notificationsEnabled, (enabled) => {
    if (!enabled) {
        notificationsPanelOpen.value = false;
    }
});

onBeforeUnmount(() => {
    if (tableMutationObserver) {
        tableMutationObserver.disconnect();
        tableMutationObserver = null;
    }

    if (typeof window !== 'undefined' && tableHydrationFrame !== null) {
        window.cancelAnimationFrame(tableHydrationFrame);
        tableHydrationFrame = null;
    }

    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleGlobalKeydown);
    }

    if (typeof document !== 'undefined') {
        document.removeEventListener('mousedown', handleUserMenuClickOutside);
    }
});

const toggleSidebarCollapsed = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const closeNotificationsPanel = () => {
    notificationsPanelOpen.value = false;
};

const doLogout = () => {
    router.post(safeRoute('logout', '/logout'));
};

const hasNotificationsActions = computed(() => {
    if (!notificationsEnabled.value) return false;
    if (typeof route !== 'function') return false;

    try {
        return route().has('notifications.read');
    } catch {
        return false;
    }
});

const isNotificationsActive = computed(() => {
    if (!notificationsEnabled.value) return false;
    if (notificationsPanelOpen.value) return true;

    return safeRouteCurrent('notifications.index') || safeRouteCurrent('notifications.*');
});

const openNotifications = () => {
    if (!notificationsEnabled.value || !hasNotificationsActions.value) return;
    notificationsPanelOpen.value = true;
};

const markAllNotificationsAsRead = () => {
    if (!hasNotificationsActions.value || unreadNotifications.value <= 0) return;

    markNotificationsForm.transform(() => ({ id: '' })).post(route('notifications.read'), {
        preserveScroll: true,
        preserveState: true,
    });
};

const markOneNotificationAsRead = (id) => {
    if (!hasNotificationsActions.value || !id) return;

    markNotificationsForm.transform(() => ({ id: String(id) })).post(route('notifications.read'), {
        preserveScroll: true,
        preserveState: true,
    });
};

const openNotificationTarget = (item) => {
    if (!item) return;

    const targetUrl = String(item.target_url ?? '').trim();
    if (!targetUrl) return;

    const notificationId = String(item.id ?? '').trim();
    const isUnread = !item.read_at;

    if (hasNotificationsActions.value && isUnread && notificationId) {
        markNotificationsForm.transform(() => ({ id: notificationId })).post(route('notifications.read'), {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                closeNotificationsPanel();
                router.visit(targetUrl);
            },
        });
        return;
    }

    closeNotificationsPanel();
    router.visit(targetUrl);
};

const handleGlobalKeydown = (event) => {
    if (event.key !== 'Escape') return;

    if (notificationsPanelOpen.value) {
        closeNotificationsPanel();
    }

    if (userMenuOpen.value) {
        closeUserMenu();
    }

    if (sidebarOpen.value) {
        closeSidebar();
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
            <main ref="appMainRef" class="px-4 py-6 sm:px-6 lg:px-8"><slot /></main>
        </template>

        <template v-else>
            <div class="flex min-h-screen min-w-0 md:h-screen md:overflow-hidden">
                <aside class="veshop-startbar relative hidden md:flex md:sticky md:top-0 md:h-screen flex-col border-r border-slate-200/80 bg-white transition-all duration-300" :class="sidebarCollapsed ? 'w-20' : 'w-72'" :style="sidebarMenuThemeStyles">
                    <button type="button" class="absolute -right-3 top-6 hidden h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow transition hover:bg-slate-100 md:flex" :title="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'" @click="toggleSidebarCollapsed">
                        <component :is="sidebarCollapsed ? ChevronRight : ChevronLeft" class="h-4 w-4" />
                    </button>

                    <div class="veshop-sidebar-head">
                        <Link
                            :href="safeRoute('home', '/home')"
                            class="veshop-head-brand"
                            :class="sidebarCollapsed ? 'justify-center' : ''"
                        >
                            <div class="veshop-head-logo">
                                <img :src="systemIconUrl" :alt="systemBrandName" class="h-6 w-6 object-contain" />
                            </div>
                            <div v-if="!sidebarCollapsed" class="veshop-head-brand-meta">
                                <span class="veshop-head-brand-name">{{ systemBrandName }}</span>
                                <span class="veshop-head-chip">
                                    {{ systemContextLabel }}
                                </span>
                            </div>
                        </Link>

                        <div
                            v-if="showContractorContext"
                            class="veshop-head-context"
                            :class="sidebarCollapsed ? 'is-collapsed' : ''"
                        >
                            <div class="flex items-center gap-3" :class="sidebarCollapsed ? 'justify-center' : ''">
                                <div class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg ring-1 ring-emerald-200/70" :style="contractorLogoUrl ? null : { background: 'var(--contractor-primary)' }">
                                    <img v-if="contractorLogoUrl" :src="contractorLogoUrl" :alt="contractorName" class="h-full w-full rounded-lg object-cover" />
                                    <span v-else class="text-xs font-semibold text-white">{{ contractorInitials }}</span>
                                </div>
                                <div v-if="!sidebarCollapsed" class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-semibold text-slate-900">{{ contractorName }}</p>
                                    <span class="veshop-head-context-chip mt-1">
                                        {{ contractorPlanName }}
                                    </span>
                                </div>

                                <Dropdown v-if="canSwitchContractor && !sidebarCollapsed" align="right" width="48" content-classes="py-2 bg-white" class="ml-auto">
                                    <template #trigger>
                                        <button type="button" class="veshop-head-action" title="Trocar contratante" aria-label="Trocar contratante">
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
                        <nav class="veshop-sidebar-nav" :class="sidebarCollapsed ? 'is-collapsed' : ''">
                            <template v-if="sidebarCollapsed">
                                <Link
                                    v-for="link in collapsedLinks"
                                    :key="link.key"
                                    :href="safeRoute(link.route, '#')"
                                    class="veshop-menu-collapsed-link"
                                    :class="isLinkActive(link) ? 'is-active' : ''"
                                    :title="link.label"
                                    :aria-label="link.label"
                                >
                                    <Icon :icon="link.iconToken" class="veshop-menu-collapsed-icon" />
                                </Link>
                            </template>
                            <template v-else>
                                <div v-for="group in menuGroups" :key="group.key" class="veshop-menu-group">
                                    <button
                                        type="button"
                                        class="veshop-menu-trigger"
                                        :class="{
                                            'is-active': isGroupActive(group),
                                            'is-expanded': isGroupExpanded(group.key),
                                        }"
                                        @click="toggleGroup(group.key)"
                                    >
                                        <span class="veshop-menu-trigger-main">
                                            <Icon :icon="group.iconToken" class="veshop-menu-icon" />
                                            <span class="truncate">{{ group.label }}</span>
                                        </span>
                                        <Icon
                                            :icon="isGroupExpanded(group.key) ? menuArrowIcons.down : menuArrowIcons.right"
                                            class="veshop-menu-arrow"
                                        />
                                    </button>
                                    <transition name="fade">
                                        <ul v-show="isGroupExpanded(group.key)" class="veshop-submenu">
                                            <li v-for="link in group.links" :key="link.key" class="veshop-submenu-item">
                                                <Link
                                                    :href="safeRoute(link.route, '#')"
                                                    class="veshop-submenu-link"
                                                    :class="isLinkActive(link) ? 'is-active' : ''"
                                                >
                                                    <span class="truncate">{{ link.label }}</span>
                                                </Link>
                                            </li>
                                        </ul>
                                    </transition>
                                </div>
                            </template>
                        </nav>
                    </div>

                    <div class="veshop-sidebar-foot" :class="sidebarCollapsed ? 'is-collapsed' : ''">
                        <div ref="userMenuRef" class="veshop-foot-popover">
                            <button
                                type="button"
                                class="veshop-foot-user-trigger"
                                :class="{ 'is-collapsed': sidebarCollapsed, 'is-open': userMenuOpen }"
                                title="Menu do usuário"
                                aria-haspopup="menu"
                                :aria-expanded="userMenuOpen ? 'true' : 'false'"
                                @click="userMenuOpen = !userMenuOpen"
                            >
                                <div class="relative flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-slate-100 text-slate-700 ring-1 ring-slate-200">
                                    <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user?.name ?? 'Avatar'" class="h-full w-full object-cover" />
                                    <span v-else class="text-sm font-semibold text-slate-700">{{ userInitial }}</span>
                                    <span class="absolute -bottom-1 -right-1 h-3 w-3 rounded-full border-2 border-white bg-emerald-500" />
                                </div>
                                <div v-if="!sidebarCollapsed" class="veshop-foot-user-meta min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ user?.name ?? 'Usuário' }}</p>
                                    <p class="truncate text-xs text-slate-600">{{ user?.email ?? '' }}</p>
                                </div>
                                <span v-if="!sidebarCollapsed" class="veshop-foot-user-arrow-btn" aria-hidden="true">
                                    <ChevronUp class="veshop-foot-user-arrow" />
                                </span>
                            </button>

                            <transition name="fade">
                                <div
                                    v-if="userMenuOpen"
                                    class="veshop-foot-menu-popover"
                                    :class="sidebarCollapsed ? 'is-collapsed' : ''"
                                    role="menu"
                                >
                                    <Link :href="safeRoute('profile.edit', '/profile')" class="veshop-foot-menu-link" @click="closeUserMenu">
                                        <span class="veshop-foot-menu-main">
                                            <Icon :icon="userIcon" class="veshop-foot-menu-icon" />
                                            <span class="truncate">Perfil</span>
                                        </span>
                                    </Link>
                                    <button type="button" @click="closeUserMenu(); doLogout()" class="veshop-foot-menu-link is-danger">
                                        <span class="veshop-foot-menu-main">
                                            <Icon :icon="logOutIcon" class="veshop-foot-menu-icon" />
                                            <span class="truncate">Sair</span>
                                        </span>
                                    </button>
                                </div>
                            </transition>
                        </div>
                    </div>
                </aside>

                <div class="flex min-h-0 min-w-0 flex-1 flex-col">
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

                    <main ref="appMainRef" class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto bg-slate-100/80">
                        <div class="px-4 py-6 pb-24 sm:px-6 lg:px-8 md:pb-6">
                            <template v-if="hasHeaderSlot">
                                <slot name="header" />
                            </template>

                            <template v-else-if="showDefaultHeader">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <h1 v-if="resolvedHeaderTitle" :class="defaultHeaderTitleClass">{{ resolvedHeaderTitle }}</h1>

                                    <div v-if="shouldShowTableViewToggle" class="flex justify-end">
                                        <div class="veshop-table-view-toggle">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                                :class="tableViewMode === 'list' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
                                                @click="tableViewMode = 'list'"
                                            >
                                                <List class="h-3.5 w-3.5" />
                                                Lista
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                                :class="tableViewMode === 'cards' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
                                                @click="tableViewMode = 'cards'"
                                            >
                                                <LayoutGrid class="h-3.5 w-3.5" />
                                                Cards
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div v-if="shouldShowTableViewToggle && !showDefaultHeader" class="mt-4 flex justify-end">
                                <div class="veshop-table-view-toggle">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                        :class="tableViewMode === 'list' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
                                        @click="tableViewMode = 'list'"
                                    >
                                        <List class="h-3.5 w-3.5" />
                                        Lista
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                        :class="tableViewMode === 'cards' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
                                        @click="tableViewMode = 'cards'"
                                    >
                                        <LayoutGrid class="h-3.5 w-3.5" />
                                        Cards
                                    </button>
                                </div>
                            </div>

                            <div :class="showHeader ? 'mt-6' : ''"><slot /></div>
                        </div>
                    </main>

                    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-2 pt-2 pb-[max(env(safe-area-inset-bottom),0.45rem)] shadow-[0_-10px_30px_-20px_rgba(15,23,42,0.2)] backdrop-blur md:hidden">
                        <div class="mx-auto flex max-w-lg items-end gap-1">
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
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
                                :class="isLinkActive(link) ? 'text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                                :style="isLinkActive(link) ? activeMenuBackground : null"
                            >
                                <Icon :icon="link.iconToken" class="h-4 w-4" />
                                <span class="truncate">{{ link.label }}</span>
                            </Link>

                            <button
                                v-if="notificationsEnabled"
                                type="button"
                                class="relative flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold transition"
                                :class="isNotificationsActive ? 'text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                                :style="isNotificationsActive ? activeMenuBackground : null"
                                @click="openNotifications"
                            >
                                <Bell class="h-4 w-4" />
                                <span class="truncate">Notificações</span>
                                <span
                                    v-if="unreadNotifications > 0"
                                    class="absolute right-2 top-1 inline-flex min-w-[16px] items-center justify-center rounded-full bg-rose-500 px-1 py-0.5 text-[9px] font-semibold text-white"
                                >
                                    {{ unreadNotifications > 99 ? '99+' : unreadNotifications }}
                                </span>
                            </button>
                        </div>
                    </nav>
                </div>

                <transition name="fade">
                    <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm md:hidden" @click.self="closeSidebar">
                        <div class="veshop-startbar absolute left-0 top-0 flex h-full w-72 flex-col bg-white shadow-xl" :style="sidebarMenuThemeStyles">
                            <div class="border-b border-slate-200 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-slate-900 text-xs font-semibold text-white">
                                            <img :src="systemIconUrl" :alt="systemBrandName" class="h-5 w-5 object-contain" />
                                        </div>
                                        <div class="flex flex-col leading-tight">
                                            <span class="text-sm font-semibold text-slate-900">{{ systemBrandName }}</span>
                                            <span class="mt-1 inline-flex w-fit items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200">
                                                {{ systemContextLabel }}
                                            </span>
                                        </div>
                                    </div>
                                    <button type="button" class="rounded-full bg-slate-100 p-2 text-slate-700" @click="closeSidebar" title="Fechar">
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div class="flex-1 overflow-y-auto p-4">
                                <div
                                    v-if="showContractorContext"
                                    class="mb-4 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg ring-1 ring-emerald-200/70" :style="contractorLogoUrl ? null : { background: 'var(--contractor-primary)' }">
                                            <img v-if="contractorLogoUrl" :src="contractorLogoUrl" :alt="contractorName" class="h-full w-full rounded-lg object-cover" />
                                            <span v-else class="text-xs font-semibold text-white">{{ contractorInitials }}</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-xs font-semibold text-slate-900">{{ contractorName }}</p>
                                            <span class="mt-1 inline-flex w-fit items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                {{ contractorPlanName }}
                                            </span>
                                        </div>

                                        <Dropdown v-if="canSwitchContractor" align="right" width="48" content-classes="py-2 bg-white" class="ml-auto">
                                            <template #trigger>
                                                <button
                                                    type="button"
                                                    class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50"
                                                    title="Trocar contratante"
                                                    aria-label="Trocar contratante"
                                                >
                                                    <ChevronDown class="h-4 w-4" />
                                                </button>
                                            </template>
                                            <template #content>
                                                <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                                    Trocar contratante
                                                </p>
                                                <div class="space-y-1">
                                                    <button
                                                        v-for="contractor in availableContractors"
                                                        :key="contractor.uuid ?? contractor.id"
                                                        type="button"
                                                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-xs font-semibold transition"
                                                        :class="isCurrentContractorOption(contractor) ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                                                        @click="switchContractorTo(contractor); closeSidebar()"
                                                    >
                                                        <span
                                                            class="flex h-6 w-6 items-center justify-center overflow-hidden rounded-md text-[10px] font-semibold text-white"
                                                            :style="contractor.brand_avatar_url ? null : { background: resolveContractorColor(contractor) }"
                                                        >
                                                            <img
                                                                v-if="contractor.brand_avatar_url"
                                                                :src="contractor.brand_avatar_url"
                                                                :alt="contractor.brand_name ?? contractor.name"
                                                                class="h-full w-full object-cover"
                                                            />
                                                            <span v-else>{{ resolveContractorInitials(contractor.brand_name ?? contractor.name) }}</span>
                                                        </span>
                                                        <span class="min-w-0 flex-1 truncate">{{ contractor.brand_name ?? contractor.name }}</span>
                                                        <span
                                                            v-if="isCurrentContractorOption(contractor)"
                                                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                                                        >
                                                            Atual
                                                        </span>
                                                    </button>
                                                </div>
                                            </template>
                                        </Dropdown>
                                    </div>
                                </div>
                                <div class="veshop-sidebar-nav veshop-sidebar-nav--mobile">
                                    <div v-for="group in menuGroups" :key="group.key" class="veshop-menu-group">
                                        <button
                                            type="button"
                                            class="veshop-menu-trigger"
                                            :class="{
                                                'is-active': isGroupActive(group),
                                                'is-expanded': isGroupExpanded(group.key),
                                            }"
                                            @click="toggleGroup(group.key)"
                                        >
                                            <span class="veshop-menu-trigger-main">
                                                <Icon :icon="group.iconToken" class="veshop-menu-icon" />
                                                <span class="truncate">{{ group.label }}</span>
                                            </span>
                                            <Icon
                                                :icon="isGroupExpanded(group.key) ? menuArrowIcons.down : menuArrowIcons.right"
                                                class="veshop-menu-arrow"
                                            />
                                        </button>
                                        <transition name="fade">
                                            <ul v-show="isGroupExpanded(group.key)" class="veshop-submenu">
                                                <li v-for="link in group.links" :key="link.key" class="veshop-submenu-item">
                                                    <Link
                                                        :href="safeRoute(link.route, '#')"
                                                        class="veshop-submenu-link"
                                                        :class="isLinkActive(link) ? 'is-active' : ''"
                                                        @click="closeSidebar"
                                                    >
                                                        <span class="truncate">{{ link.label }}</span>
                                                    </Link>
                                                </li>
                                            </ul>
                                        </transition>
                                    </div>
                                </div>
                            </div>
                            <div class="border-t border-slate-200 px-4 py-3">
                                <div class="rounded-2xl border border-slate-200/70 bg-white p-3 shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-slate-100 text-slate-700 ring-1 ring-slate-200">
                                            <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="user?.name ?? 'Avatar'" class="h-full w-full object-cover" />
                                            <span v-else class="text-sm font-semibold text-slate-700">{{ userInitial }}</span>
                                            <span class="absolute -bottom-1 -right-1 h-3 w-3 rounded-full border-2 border-white bg-emerald-500" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ user?.name ?? 'Usuário' }}</p>
                                            <p class="truncate text-xs text-slate-600">{{ user?.email ?? '' }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-3 grid gap-2">
                                        <Link :href="safeRoute('profile.edit', '/profile')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200/80 transition hover:bg-slate-100" @click="closeSidebar">
                                            <UserCircle2 class="h-4 w-4" />
                                            Perfil
                                        </Link>
                                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 ring-1 ring-rose-200/80 transition hover:bg-rose-100" @click="closeSidebar(); doLogout()">
                                            <LogOut class="h-4 w-4" />
                                            Sair
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </template>

        <transition name="drawer">
            <div
                v-if="notificationsEnabled && notificationsPanelOpen"
                class="fixed inset-0 z-50 bg-slate-900/45 backdrop-blur-sm"
                @click.self="closeNotificationsPanel"
            >
                <aside class="notifications-drawer absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
                    <div class="border-b border-slate-200 px-4 py-4 sm:px-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Notificações</p>
                                <p class="mt-1 text-xs text-slate-500">{{ unreadNotifications }} não lida(s)</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200"
                                title="Fechar notificações"
                                aria-label="Fechar notificações"
                                @click="closeNotificationsPanel"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <button
                            type="button"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="markNotificationsForm.processing || unreadNotifications <= 0"
                            @click="markAllNotificationsAsRead"
                        >
                            Marcar todas como lidas
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                        <div v-if="notificationItems.length" class="space-y-3">
                            <article
                                v-for="item in notificationItems"
                                :key="item.id"
                                class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm"
                                :class="!item.read_at ? 'ring-1 ring-blue-100' : ''"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
                                        <p v-if="item.message" class="mt-1 text-sm text-slate-600">{{ item.message }}</p>
                                        <p class="mt-2 text-xs text-slate-400">{{ item.created_at }}</p>
                                    </div>
                                    <span
                                        v-if="!item.read_at"
                                        class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700"
                                    >
                                        Nova
                                    </span>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <button
                                        v-if="item.target_url"
                                        type="button"
                                        class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
                                        :disabled="markNotificationsForm.processing"
                                        @click="openNotificationTarget(item)"
                                    >
                                        Abrir
                                    </button>
                                    <button
                                        v-if="!item.read_at"
                                        type="button"
                                        class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-[11px] font-semibold text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="markNotificationsForm.processing"
                                        @click="markOneNotificationAsRead(item.id)"
                                    >
                                        Marcar como lida
                                    </button>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-semibold text-emerald-700"
                                    >
                                        Lida
                                    </span>
                                </div>
                            </article>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500"
                        >
                            Você ainda não possui notificações.
                        </div>
                    </div>
                </aside>
            </div>
        </transition>

        <button
            v-if="notificationsEnabled"
            type="button"
            class="fixed right-4 bottom-5 z-30 hidden h-12 w-12 items-center justify-center rounded-full bg-slate-900 text-white shadow-xl transition hover:bg-slate-800 md:inline-flex"
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
.veshop-startbar {
    --veshop-menu-active: #22c55e;
    --veshop-menu-active-soft: rgba(34, 197, 94, 0.08);
    --veshop-menu-ink: #061237;
    --veshop-menu-icon: #96a0b5;
    --veshop-menu-dot: #8997bd;
    font-family: 'Be Vietnam Pro', sans-serif;
}

.veshop-sidebar-head {
    border-bottom: 1px solid #e2e8f0;
    padding: 14px 10px 12px;
}

.veshop-head-brand {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 12px;
    border-radius: 10px;
    padding: 6px 10px;
    color: var(--veshop-menu-ink);
}

.veshop-head-logo {
    display: flex;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background-color: #0f172a;
    color: #fff;
}

.veshop-head-brand-meta {
    display: flex;
    min-width: 0;
    flex-direction: column;
    line-height: 1.15;
}

.veshop-head-brand-name {
    color: var(--veshop-menu-ink);
    font-size: 13px;
    font-weight: 600;
}

.veshop-head-chip {
    margin-top: 6px;
    display: inline-flex;
    width: fit-content;
    align-items: center;
    border-radius: 9999px;
    border: 1px solid #dbe2ee;
    background-color: #f8fafc;
    padding: 2px 8px;
    color: #64748b;
    font-size: 10px;
    font-weight: 600;
}

.veshop-head-context {
    margin-top: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background-color: #f8fafc;
    padding: 8px 10px;
}

.veshop-head-context.is-collapsed {
    padding: 8px;
}

.veshop-head-context-chip {
    display: inline-flex;
    width: fit-content;
    align-items: center;
    border-radius: 9999px;
    background-color: #dcfce7;
    padding: 2px 8px;
    color: #15803d;
    font-size: 10px;
    font-weight: 600;
}

.veshop-head-action {
    display: flex;
    width: 28px;
    height: 28px;
    align-items: center;
    justify-content: center;
    border: 1px solid #dbe2ee;
    border-radius: 9999px;
    background-color: #ffffff;
    color: #64748b;
    transition: background-color 160ms ease, color 160ms ease;
}

.veshop-head-action:hover {
    background-color: #f1f5f9;
    color: #0f172a;
}

.veshop-sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 16px 10px;
    font-family: 'Be Vietnam Pro', sans-serif;
}

.veshop-sidebar-nav.is-collapsed {
    align-items: center;
    gap: 8px;
    padding: 12px 8px;
}

.veshop-sidebar-nav--mobile {
    padding: 2px 0 0;
}

.veshop-menu-group {
    position: relative;
}

.veshop-menu-trigger {
    position: relative;
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    border: 1px solid transparent;
    border-radius: 10px;
    padding: 10px 16px;
    margin-bottom: 3px;
    background-color: transparent;
    color: var(--veshop-menu-ink);
    font-size: 13px;
    font-weight: 500;
    line-height: 1.3;
    text-align: left;
    transition: background-color 160ms ease, color 160ms ease;
}

.veshop-menu-trigger::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 8px;
    bottom: 8px;
    width: 6px;
    border-radius: 5px;
    background-color: transparent;
}

.veshop-menu-trigger:hover {
    color: var(--veshop-menu-active);
}

.veshop-menu-trigger.is-active,
.veshop-menu-trigger.is-expanded {
    background-color: var(--veshop-menu-active-soft);
    color: var(--veshop-menu-active);
    font-weight: 600;
}

.veshop-menu-trigger.is-active::before,
.veshop-menu-trigger.is-expanded::before {
    background-color: var(--veshop-menu-active);
}

.veshop-menu-trigger-main {
    display: flex;
    min-width: 0;
    align-items: center;
}

.veshop-menu-icon {
    margin-right: 16px;
    font-size: 20px;
    color: var(--veshop-menu-icon);
    flex-shrink: 0;
    transition: color 160ms ease;
}

.veshop-menu-trigger:hover .veshop-menu-icon,
.veshop-menu-trigger.is-active .veshop-menu-icon,
.veshop-menu-trigger.is-expanded .veshop-menu-icon {
    color: var(--veshop-menu-active);
}

.veshop-menu-arrow {
    font-size: 14px;
    color: currentColor;
    flex-shrink: 0;
}

.veshop-submenu {
    margin-top: 1px;
}

.veshop-submenu-item {
    padding-left: 12px;
}

.veshop-submenu-link {
    position: relative;
    display: flex;
    align-items: center;
    min-height: 32px;
    border-radius: 8px;
    padding: 6px 16px 6px 40px;
    color: var(--veshop-menu-ink);
    font-size: 13px;
    font-weight: 500;
    line-height: 1.25;
    transition: color 160ms ease, background-color 160ms ease;
}

.veshop-submenu-link::before {
    content: '';
    position: absolute;
    left: 12px;
    top: 50%;
    width: 4px;
    height: 4px;
    border-radius: 9999px;
    border: 1px solid var(--veshop-menu-dot);
    background-color: rgba(137, 151, 189, 0.2);
    transform: translateY(-50%);
}

.veshop-submenu-link:hover {
    color: var(--veshop-menu-active);
}

.veshop-submenu-link.is-active {
    color: var(--veshop-menu-active);
}

.veshop-submenu-link.is-active::before {
    border-color: var(--veshop-menu-active);
    background-color: var(--veshop-menu-active);
}

.veshop-menu-collapsed-link {
    position: relative;
    display: flex;
    width: 44px;
    height: 44px;
    align-items: center;
    justify-content: center;
    border: 1px solid transparent;
    border-radius: 10px;
    color: var(--veshop-menu-icon);
    transition: background-color 160ms ease, color 160ms ease;
}

.veshop-menu-collapsed-link::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 7px;
    bottom: 7px;
    width: 6px;
    border-radius: 5px;
    background-color: transparent;
}

.veshop-menu-collapsed-link:hover {
    color: var(--veshop-menu-active);
}

.veshop-menu-collapsed-link.is-active {
    background-color: var(--veshop-menu-active-soft);
    color: var(--veshop-menu-active);
}

.veshop-menu-collapsed-link.is-active::before {
    background-color: var(--veshop-menu-active);
}

.veshop-menu-collapsed-icon {
    font-size: 20px;
}

.veshop-sidebar-foot {
    border-top: 1px solid #e2e8f0;
    padding: 12px 10px;
}

.veshop-sidebar-foot.is-collapsed {
    padding: 12px 8px;
}

.veshop-foot-popover {
    position: relative;
}

.veshop-foot-user-trigger {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 12px;
    border-radius: 10px;
    border: 1px solid transparent;
    padding: 6px 10px;
    color: var(--veshop-menu-ink);
    text-align: left;
    transition: background-color 160ms ease, color 160ms ease;
}

.veshop-foot-user-trigger:hover,
.veshop-foot-user-trigger.is-open {
    background-color: var(--veshop-menu-active-soft);
    color: var(--veshop-menu-active);
}

.veshop-foot-user-trigger.is-collapsed {
    justify-content: center;
    padding: 6px;
}

.veshop-foot-user-meta {
    text-align: left;
}

.veshop-foot-user-arrow-btn {
    display: inline-flex;
    width: 24px;
    height: 24px;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
    border-radius: 9999px;
    background-color: #f8fafc;
    flex-shrink: 0;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.veshop-foot-user-trigger:hover .veshop-foot-user-arrow-btn,
.veshop-foot-user-trigger.is-open .veshop-foot-user-arrow-btn {
    border-color: #cbd5e1;
    background-color: #eef2f7;
}

.veshop-foot-user-arrow {
    color: var(--veshop-menu-icon);
    transition: color 160ms ease;
}

.veshop-foot-user-trigger:hover .veshop-foot-user-arrow,
.veshop-foot-user-trigger.is-open .veshop-foot-user-arrow {
    color: var(--veshop-menu-active);
}

.veshop-foot-menu-popover {
    position: absolute;
    left: 0;
    right: 0;
    bottom: calc(100% + 8px);
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background-color: #ffffff;
    padding: 4px;
    box-shadow: 0 16px 32px -24px rgba(15, 23, 42, 0.85);
    z-index: 30;
}

.veshop-foot-menu-popover.is-collapsed {
    left: 50%;
    right: auto;
    width: 200px;
    transform: translateX(-50%);
}

.veshop-foot-menu-link {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 9px 12px;
    color: var(--veshop-menu-ink);
    font-size: 13px;
    font-weight: 500;
    line-height: 1.3;
    transition: background-color 160ms ease, color 160ms ease;
}

.veshop-foot-menu-link:hover {
    background-color: var(--veshop-menu-active-soft);
    color: var(--veshop-menu-active);
}

.veshop-foot-menu-main {
    display: flex;
    min-width: 0;
    align-items: center;
}

.veshop-foot-menu-icon {
    margin-right: 16px;
    font-size: 20px;
    color: var(--veshop-menu-icon);
    flex-shrink: 0;
    transition: color 160ms ease;
}

.veshop-foot-menu-link:hover .veshop-foot-menu-icon {
    color: var(--veshop-menu-active);
}

.veshop-foot-menu-link.is-danger:hover {
    background-color: rgba(225, 29, 72, 0.08);
    color: #be123c;
}

.veshop-foot-menu-link.is-danger:hover .veshop-foot-menu-icon {
    color: #be123c;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 150ms ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.drawer-enter-active,
.drawer-leave-active {
    transition: background-color 180ms ease, backdrop-filter 180ms ease;
}

.drawer-enter-from,
.drawer-leave-to {
    background-color: rgba(15, 23, 42, 0);
    backdrop-filter: blur(0);
}

.notifications-drawer {
    transform: translateX(0);
    transition: transform 240ms cubic-bezier(0.22, 1, 0.36, 1);
}

.drawer-enter-from .notifications-drawer,
.drawer-leave-to .notifications-drawer {
    transform: translateX(100%);
}
</style>
