<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useSlots, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useBranding } from '@/branding';
import { masterMenuGroups } from '@/navigation/masterMenu';
import { adminMenuGroups } from '@/navigation/adminMenu';
import {
    UserCircle2,
    LogOut,
    Menu,
    X,
    ChevronLeft,
    ChevronRight,
    List,
    LayoutGrid,
    Bell,
} from 'lucide-vue-next';
import { Icon } from '@iconify/vue';
import NotificationsDrawer from './components/NotificationsDrawer.vue';
import ContractorSwitcher from './components/ContractorSwitcher.vue';
import SidebarNavigation from './components/SidebarNavigation.vue';
import TopbarBranding from './components/TopbarBranding.vue';
import UserMenu from './components/UserMenu.vue';
import './app-layout-shell.css';
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
import openBookIcon from '@iconify-icons/iconoir/open-book';
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
    BookOpen: openBookIcon,
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
const contractorContext = computed(
    () => page.props.contractorContext ?? { current: null, available: [] },
);
const currentContractor = computed(() => contractorContext.value.current ?? null);
const availableContractors = computed(() => contractorContext.value.available ?? []);
const canSwitchContractor = computed(
    () => currentArea.value === 'admin' && availableContractors.value.length > 1,
);
const showContractorContext = computed(
    () => currentArea.value === 'admin' && Boolean(currentContractor.value),
);
const contractorNiche = computed(() =>
    String(currentContractor.value?.business_niche ?? 'commercial').toLowerCase(),
);
const contractorBusinessType = computed(() =>
    String(currentContractor.value?.business_type ?? '').toLowerCase(),
);
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
        .map((module) =>
            String(module ?? '')
                .trim()
                .toLowerCase(),
        )
        .filter(Boolean);
});

const currentArea = computed(() => {
    if (props.area === 'master' || props.area === 'admin') {
        return props.area;
    }

    return user.value?.role === 'master' ? 'master' : 'admin';
});
const notificationsEnabled = computed(
    () => currentArea.value !== 'admin' || contractorEnabledModules.value.includes('notifications'),
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
const contractorName = computed(
    () => currentContractor.value?.brand_name || currentContractor.value?.name || '',
);
const contractorLogoUrl = computed(
    () =>
        currentContractor.value?.brand_avatar_url || currentContractor.value?.brand_logo_url || '',
);

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

const safeRoute = (name, fallback = '/', params = undefined) => {
    if (typeof route !== 'function') return fallback;

    try {
        return params !== undefined ? route(name, params) : route(name);
    } catch {
        return fallback;
    }
};

const resolveMenuHref = (link) => {
    if (typeof link?.href === 'string' && link.href.trim() !== '') {
        return link.href;
    }

    return safeRoute(link?.route, '#', link?.params);
};

const safeRouteCurrent = (pattern) => {
    if (typeof route !== 'function') return false;

    try {
        return route().current(pattern);
    } catch {
        return false;
    }
};

const contractorBrandingHref = computed(() => {
    if (!showContractorContext.value) return '#';

    const routeName =
        currentArea.value === 'master' ? 'master.branding.index' : 'admin.branding.index';

    return safeRoute(routeName, '#');
});

const hasContractorBrandingLink = computed(() => contractorBrandingHref.value !== '#');

const getContractorRouteKey = (contractor) => contractor?.uuid || contractor?.id || null;

const resolveContractorColor = (contractor) =>
    normalizeHex(contractor?.brand_primary_color || '', primaryColor.value);

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
        router.post(
            route('contractor.switch'),
            { contractor_id: targetId },
            {
                preserveScroll: false,
                onSuccess: () => {
                    router.visit(safeRoute('admin.home', '/app/home'));
                },
            },
        );
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
        return required.some((item) =>
            enabled.includes(
                String(item ?? '')
                    .trim()
                    .toLowerCase(),
            ),
        );
    }

    return enabled.includes(
        String(required ?? '')
            .trim()
            .toLowerCase(),
    );
};

const hasAllowedNiche = (group) => {
    const allowedNiches = Array.isArray(group?.niches)
        ? group.niches
              .map((item) =>
                  String(item ?? '')
                      .trim()
                      .toLowerCase(),
              )
              .filter(Boolean)
        : [];

    if (!allowedNiches.length) {
        return true;
    }

    return allowedNiches.includes(contractorNiche.value);
};

const hasAllowedBusinessType = (item) => {
    const allowedBusinessTypes = Array.isArray(item?.businessTypes)
        ? item.businessTypes
              .map((value) =>
                  String(value ?? '')
                      .trim()
                      .toLowerCase(),
              )
              .filter(Boolean)
        : [];

    if (!allowedBusinessTypes.length) {
        return true;
    }

    const currentBusinessType = contractorBusinessType.value;
    if (!currentBusinessType) {
        return false;
    }

    return allowedBusinessTypes.includes(currentBusinessType);
};

const filteredAdminMenuGroups = computed(() =>
    adminMenuGroups
        .filter((group) => hasAllowedNiche(group))
        .filter((group) => hasAllowedBusinessType(group))
        .filter((group) => hasEnabledModule(group.module))
        .map((group) => ({
            ...group,
            links: (group.links ?? [])
                .filter((link) => hasAllowedBusinessType(link))
                .filter((link) => hasEnabledModule(link.module)),
        }))
        .filter((group) => (group.links ?? []).length > 0),
);

const menuGroups = computed(() =>
    toMenuWithIcons(
        currentArea.value === 'master' ? masterMenuGroups : filteredAdminMenuGroups.value,
    ),
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

const TABLE_VIEW_STORAGE_KEY = 'veshop:table-view-mode';
const allowedTableViewModes = new Set(['list', 'cards']);
const tableViewMode = ref('list');
const hasAdaptiveTables = ref(false);
let tableMutationObserver = null;
let tableHydrationFrame = null;

const normalizeTableViewMode = (value) => {
    const normalized = String(value ?? '')
        .trim()
        .toLowerCase();
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

            const isEmptyStateRow = cells.length === 1 && cells[0].hasAttribute('colspan');

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
const hasDefaultHeaderContent = computed(() => Boolean(resolvedHeaderTitle.value));
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

const defaultHeaderTitleClass = computed(() => 'text-xl font-semibold text-slate-900');

const isGroupExpanded = (key) => expandedGroups.value.has(key);

const persistExpandedGroups = () => {
    try {
        window.localStorage.setItem(
            'veshop:sidebar-groups',
            JSON.stringify(Array.from(expandedGroups.value)),
        );
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

        const storedGroups = JSON.parse(
            window.localStorage.getItem('veshop:sidebar-groups') ?? '[]',
        );
        expandedGroups.value = new Set(Array.isArray(storedGroups) ? storedGroups : []);
    } catch {
        sidebarCollapsed.value = false;
        expandedGroups.value = new Set();
    }

    try {
        tableViewMode.value = normalizeTableViewMode(
            window.localStorage.getItem(TABLE_VIEW_STORAGE_KEY),
        );
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
});

watch(sidebarCollapsed, () => {
    try {
        window.localStorage.setItem('veshop:sidebar-collapsed', sidebarCollapsed.value ? '1' : '0');
    } catch {
        // ignore
    }
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

const hasNotificationsClearAction = computed(() => {
    if (!notificationsEnabled.value) return false;
    if (typeof route !== 'function') return false;

    try {
        return route().has('notifications.clear');
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

    markNotificationsForm
        .transform(() => ({ id: '' }))
        .post(route('notifications.read'), {
            preserveScroll: true,
            preserveState: true,
        });
};

const markOneNotificationAsRead = (id) => {
    if (!hasNotificationsActions.value || !id) return;

    markNotificationsForm
        .transform(() => ({ id: String(id) }))
        .post(route('notifications.read'), {
            preserveScroll: true,
            preserveState: true,
        });
};

const clearAllNotifications = () => {
    if (!hasNotificationsClearAction.value || notificationItems.value.length <= 0) return;

    markNotificationsForm
        .transform(() => ({ id: '' }))
        .post(route('notifications.clear'), {
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
        markNotificationsForm
            .transform(() => ({ id: notificationId }))
            .post(route('notifications.read'), {
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

    if (sidebarOpen.value) {
        closeSidebar();
    }
};
</script>

<template>
    <Head>
        <link
            v-if="publicFaviconHref"
            rel="icon"
            :href="publicFaviconHref"
            :type="publicFaviconType"
        />
    </Head>

    <div class="min-h-screen bg-slate-100/80 text-slate-900" :style="themeStyles">
        <template v-if="props.hideNav">
            <header
                class="sticky top-0 z-40 flex items-center justify-between border-b border-white/60 bg-white/90 px-4 py-3 shadow-sm backdrop-blur"
            >
                <Link :href="safeRoute('home', '/home')" class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-md bg-slate-900 text-base font-semibold text-white"
                    >
                        <img
                            :src="systemIconUrl"
                            :alt="systemBrandName"
                            class="h-6 w-6 object-contain"
                        />
                    </div>
                    <span class="text-sm font-semibold text-slate-900">{{ systemBrandName }}</span>
                </Link>
                <div class="flex items-center gap-2">
                    <Link
                        :href="safeRoute('profile.edit', '/profile')"
                        class="rounded-full bg-white/90 px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-white"
                        >Perfil</Link
                    >
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm ring-1 ring-rose-500/30 hover:bg-rose-700"
                        @click="doLogout"
                    >
                        <LogOut class="h-4 w-4" />
                        Sair
                    </button>
                </div>
            </header>
            <main ref="appMainRef" class="px-4 py-6 sm:px-6 lg:px-8"><slot /></main>
        </template>

        <template v-else>
            <div class="flex min-h-screen min-w-0 md:h-screen md:overflow-hidden">
                <aside
                    class="veshop-startbar relative hidden md:flex md:sticky md:top-0 md:h-screen flex-col border-r border-slate-200/80 bg-white transition-all duration-300"
                    :class="sidebarCollapsed ? 'w-20' : 'w-72'"
                    :style="sidebarMenuThemeStyles"
                >
                    <button
                        type="button"
                        class="absolute -right-3 top-6 hidden h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow transition hover:bg-slate-100 md:flex"
                        :title="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'"
                        @click="toggleSidebarCollapsed"
                    >
                        <component
                            :is="sidebarCollapsed ? ChevronRight : ChevronLeft"
                            class="h-4 w-4"
                        />
                    </button>

                    <div class="veshop-sidebar-head">
                        <Link
                            :href="safeRoute('home', '/home')"
                            class="veshop-head-brand"
                            :class="sidebarCollapsed ? 'justify-center' : ''"
                        >
                            <div class="veshop-head-logo">
                                <img
                                    :src="systemIconUrl"
                                    :alt="systemBrandName"
                                    class="h-6 w-6 object-contain"
                                />
                            </div>
                            <div v-if="!sidebarCollapsed" class="veshop-head-brand-meta">
                                <span class="veshop-head-brand-name">{{ systemBrandName }}</span>
                                <span class="veshop-head-chip">
                                    {{ systemContextLabel }}
                                </span>
                            </div>
                        </Link>

                        <ContractorSwitcher
                            mode="desktop"
                            :collapsed="sidebarCollapsed"
                            :show-contractor-context="showContractorContext"
                            :has-branding-link="hasContractorBrandingLink"
                            :branding-href="contractorBrandingHref"
                            :contractor-logo-url="contractorLogoUrl"
                            :contractor-name="contractorName"
                            :contractor-initials="contractorInitials"
                            :contractor-plan-name="contractorPlanName"
                            :can-switch-contractor="canSwitchContractor"
                            :available-contractors="availableContractors"
                            :is-current-contractor-option="isCurrentContractorOption"
                            :resolve-contractor-color="resolveContractorColor"
                            :resolve-contractor-initials="resolveContractorInitials"
                            :switch-contractor-to="switchContractorTo"
                        />
                    </div>

                    <div class="flex-1 overflow-y-auto">
                        <SidebarNavigation
                            mode="desktop"
                            :collapsed="sidebarCollapsed"
                            :menu-groups="menuGroups"
                            :collapsed-links="collapsedLinks"
                            :menu-arrow-icons="menuArrowIcons"
                            :resolve-menu-href="resolveMenuHref"
                            :is-link-active="isLinkActive"
                            :is-group-active="isGroupActive"
                            :is-group-expanded="isGroupExpanded"
                            :toggle-group="toggleGroup"
                        />
                    </div>

                    <div
                        class="veshop-sidebar-foot"
                        :class="sidebarCollapsed ? 'is-collapsed' : ''"
                    >
                        <UserMenu
                            :collapsed="sidebarCollapsed"
                            :user="user"
                            :user-avatar-url="userAvatarUrl"
                            :user-initial="userInitial"
                            :profile-href="safeRoute('profile.edit', '/profile')"
                            @logout="doLogout"
                        />
                    </div>
                </aside>

                <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                    <TopbarBranding
                        :home-href="safeRoute('home', '/home')"
                        :system-icon-url="systemIconUrl"
                        :system-brand-name="systemBrandName"
                        :system-context-label="systemContextLabel"
                    />

                    <main
                        ref="appMainRef"
                        class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto bg-slate-100/80"
                    >
                        <div class="px-4 py-6 pb-24 sm:px-6 lg:px-8 md:pb-6">
                            <template v-if="hasHeaderSlot">
                                <slot name="header" />
                            </template>

                            <template v-else-if="showDefaultHeader">
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <h1 v-if="resolvedHeaderTitle" :class="defaultHeaderTitleClass">
                                        {{ resolvedHeaderTitle }}
                                    </h1>

                                    <div v-if="shouldShowTableViewToggle" class="flex justify-end">
                                        <div class="veshop-table-view-toggle">
                                            <button
                                                type="button"
                                                class="veshop-table-view-btn inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                                :class="tableViewMode === 'list' ? 'is-active' : ''"
                                                @click="tableViewMode = 'list'"
                                            >
                                                <List class="h-3.5 w-3.5" />
                                                Lista
                                            </button>
                                            <button
                                                type="button"
                                                class="veshop-table-view-btn inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                                :class="
                                                    tableViewMode === 'cards' ? 'is-active' : ''
                                                "
                                                @click="tableViewMode = 'cards'"
                                            >
                                                <LayoutGrid class="h-3.5 w-3.5" />
                                                Cards
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div
                                v-if="shouldShowTableViewToggle && !showDefaultHeader"
                                class="mt-4 flex justify-end"
                            >
                                <div class="veshop-table-view-toggle">
                                    <button
                                        type="button"
                                        class="veshop-table-view-btn inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                        :class="tableViewMode === 'list' ? 'is-active' : ''"
                                        @click="tableViewMode = 'list'"
                                    >
                                        <List class="h-3.5 w-3.5" />
                                        Lista
                                    </button>
                                    <button
                                        type="button"
                                        class="veshop-table-view-btn inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                        :class="tableViewMode === 'cards' ? 'is-active' : ''"
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

                    <nav
                        class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-2 pt-2 pb-[max(env(safe-area-inset-bottom),0.45rem)] shadow-[0_-10px_30px_-20px_rgba(15,23,42,0.2)] backdrop-blur md:hidden"
                    >
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
                                :href="resolveMenuHref(link)"
                                class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold transition"
                                :class="
                                    isLinkActive(link)
                                        ? 'text-white'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                "
                                :style="isLinkActive(link) ? activeMenuBackground : null"
                            >
                                <Icon :icon="link.iconToken" class="h-4 w-4" />
                                <span class="truncate">{{ link.label }}</span>
                            </Link>

                            <button
                                v-if="notificationsEnabled"
                                type="button"
                                class="relative flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold transition"
                                :class="
                                    isNotificationsActive
                                        ? 'text-white'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                "
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
                    <div
                        v-if="sidebarOpen"
                        class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm md:hidden"
                        @click.self="closeSidebar"
                    >
                        <div
                            class="veshop-startbar absolute left-0 top-0 flex h-full w-72 flex-col bg-white shadow-xl"
                            :style="sidebarMenuThemeStyles"
                        >
                            <div class="border-b border-slate-200 px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-9 w-9 items-center justify-center rounded-md bg-slate-900 text-xs font-semibold text-white"
                                        >
                                            <img
                                                :src="systemIconUrl"
                                                :alt="systemBrandName"
                                                class="h-5 w-5 object-contain"
                                            />
                                        </div>
                                        <div class="flex flex-col leading-tight">
                                            <span class="text-sm font-semibold text-slate-900">{{
                                                systemBrandName
                                            }}</span>
                                            <span
                                                class="mt-1 inline-flex w-fit items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200"
                                            >
                                                {{ systemContextLabel }}
                                            </span>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded-full bg-slate-100 p-2 text-slate-700"
                                        title="Fechar"
                                        @click="closeSidebar"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                            <div class="flex-1 overflow-y-auto p-4">
                                <ContractorSwitcher
                                    mode="mobile"
                                    :show-contractor-context="showContractorContext"
                                    :has-branding-link="hasContractorBrandingLink"
                                    :branding-href="contractorBrandingHref"
                                    :contractor-logo-url="contractorLogoUrl"
                                    :contractor-name="contractorName"
                                    :contractor-initials="contractorInitials"
                                    :contractor-plan-name="contractorPlanName"
                                    :can-switch-contractor="canSwitchContractor"
                                    :available-contractors="availableContractors"
                                    :is-current-contractor-option="isCurrentContractorOption"
                                    :resolve-contractor-color="resolveContractorColor"
                                    :resolve-contractor-initials="resolveContractorInitials"
                                    :switch-contractor-to="switchContractorTo"
                                    @action="closeSidebar"
                                />

                                <SidebarNavigation
                                    mode="mobile"
                                    :menu-groups="menuGroups"
                                    :collapsed-links="collapsedLinks"
                                    :menu-arrow-icons="menuArrowIcons"
                                    :resolve-menu-href="resolveMenuHref"
                                    :is-link-active="isLinkActive"
                                    :is-group-active="isGroupActive"
                                    :is-group-expanded="isGroupExpanded"
                                    :toggle-group="toggleGroup"
                                    @navigate="closeSidebar"
                                />
                            </div>
                            <div class="border-t border-slate-200 px-4 py-3">
                                <div
                                    class="rounded-2xl border border-slate-200/70 bg-white p-3 shadow-sm"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="relative flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-slate-100 text-slate-700 ring-1 ring-slate-200"
                                        >
                                            <img
                                                v-if="userAvatarUrl"
                                                :src="userAvatarUrl"
                                                :alt="user?.name ?? 'Avatar'"
                                                class="h-full w-full object-cover"
                                            />
                                            <span
                                                v-else
                                                class="text-sm font-semibold text-slate-700"
                                                >{{ userInitial }}</span
                                            >
                                            <span
                                                class="absolute -bottom-1 -right-1 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"
                                            />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-sm font-semibold text-slate-900"
                                            >
                                                {{ user?.name ?? 'Usuário' }}
                                            </p>
                                            <p class="truncate text-xs text-slate-600">
                                                {{ user?.email ?? '' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-3 grid gap-2">
                                        <Link
                                            :href="safeRoute('profile.edit', '/profile')"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200/80 transition hover:bg-slate-100"
                                            @click="closeSidebar"
                                        >
                                            <UserCircle2 class="h-4 w-4" />
                                            Perfil
                                        </Link>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 ring-1 ring-rose-200/80 transition hover:bg-rose-100"
                                            @click="
                                                closeSidebar();
                                                doLogout();
                                            "
                                        >
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
                <NotificationsDrawer
                    :unread-notifications="unreadNotifications"
                    :notification-items="notificationItems"
                    :processing="markNotificationsForm.processing"
                    :can-clear="hasNotificationsClearAction"
                    @close="closeNotificationsPanel"
                    @mark-all="markAllNotificationsAsRead"
                    @clear-all="clearAllNotifications"
                    @mark-one="markOneNotificationAsRead"
                    @open-target="openNotificationTarget"
                />
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
