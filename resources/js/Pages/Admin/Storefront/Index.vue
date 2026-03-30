<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import TableViewToggle from '@/Components/App/TableViewToggle.vue';
import { BRAZIL_STATES, normalizeStateCode } from '@/utils/br';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { ChevronDown, Clock3, Plus, Search, Store, Truck } from 'lucide-vue-next';

const props = defineProps({
    initialTab: { type: String, default: 'vitrine' },
    supportsShipping: { type: Boolean, default: true },
    contractor: { type: Object, default: () => ({}) },
    storefront: { type: Object, default: () => ({}) },
    shopShipping: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    shop_url: { type: String, default: '' },
    addressDirectory: { type: Object, default: () => ({ states: [], routes: {} }) },
});

const WEEK_DAYS = [
    { key: 'monday', label: 'Segunda' },
    { key: 'tuesday', label: 'Terça' },
    { key: 'wednesday', label: 'Quarta' },
    { key: 'thursday', label: 'Quinta' },
    { key: 'friday', label: 'Sexta' },
    { key: 'saturday', label: 'Sábado' },
    { key: 'sunday', label: 'Domingo' },
];

const emptyBusinessHours = () =>
    Object.fromEntries(
        WEEK_DAYS.map((day) => [day.key, {
            enabled: true,
            open: '00:00',
            close: '23:59',
        }]),
    );

const normalizeHour = (value, fallback = '00:00') => {
    const safe = String(value ?? '').trim();
    return /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(safe) ? safe : fallback;
};

const normalizeCitySearchKey = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9\s-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const normalizeHexColor = (value, fallback = '#073341') => {
    const safe = String(value ?? '').trim();
    if (/^#[0-9a-fA-F]{3}$/.test(safe) || /^#[0-9a-fA-F]{6}$/.test(safe)) return safe.toUpperCase();
    if (/^[0-9a-fA-F]{3}$/.test(safe) || /^[0-9a-fA-F]{6}$/.test(safe)) return `#${safe.toUpperCase()}`;
    return fallback.toUpperCase();
};

const hexToRgb = (hex) => {
    const safe = normalizeHexColor(hex, '#0F172A').replace('#', '');
    const parsed = Number.parseInt(safe, 16);

    return {
        r: (parsed >> 16) & 255,
        g: (parsed >> 8) & 255,
        b: parsed & 255,
    };
};

const withAlpha = (hex, alpha = 1) => {
    const { r, g, b } = hexToRgb(hex);
    const safeAlpha = Math.max(0, Math.min(1, Number(alpha)));

    return `rgba(${r}, ${g}, ${b}, ${safeAlpha})`;
};

const MAX_BANNERS = 6;

const createEmptyBanner = () => ({
    title: '',
    subtitle: '',
    badge: '',
    existing_image_path: '',
    image_url: '',
    image_file: null,
    remove_image: false,
    cta_label: '',
    use_original_image_colors: false,
    background_color: normalizeHexColor(props.contractor?.primary_color || '#073341'),
    preview_url: '',
});

const createEmptyShippingCityRate = () => ({
    city: '',
    city_search: '',
    state: '',
    fee: '',
    free_over: '',
    estimated_days: '',
    is_free: false,
    active: true,
});

const createEmptyShippingStateRate = (state = '') => ({
    state: normalizeStateCode(state),
    fee: '',
    free_over: '',
    active: false,
});

const normalizeBusinessHours = (value) => {
    const base = emptyBusinessHours();
    if (!value || typeof value !== 'object') {
        return base;
    }

    for (const day of WEEK_DAYS) {
        const row = value?.[day.key] ?? {};
        base[day.key] = {
            enabled: Boolean(row?.enabled ?? true),
            open: normalizeHour(row?.open, '00:00'),
            close: normalizeHour(row?.close, '23:59'),
        };
    }

    return base;
};

const allTabs = [
    { key: 'vitrine', label: 'Vitrine', icon: Store },
    { key: 'horario', label: 'Horário de funcionamento', icon: Clock3 },
    { key: 'frete', label: 'Frete', icon: Truck },
];
const tabs = computed(() => allTabs.filter((tab) => props.supportsShipping || tab.key !== 'frete'));
const allowedTabs = computed(() => new Set(tabs.value.map((tab) => tab.key)));
const resolveTabKey = (tab) => (allowedTabs.value.has(tab) ? tab : 'vitrine');
const activeTab = ref(resolveTabKey(props.initialTab));
const tabAccentColor = computed(() => normalizeHexColor(props.contractor?.primary_color || '#0F172A', '#0F172A'));
const storefrontTabStyles = computed(() => ({
    '--storefront-tab-active': tabAccentColor.value,
    '--storefront-tab-active-soft': withAlpha(tabAccentColor.value, 0.12),
    '--storefront-tab-active-border': withAlpha(tabAccentColor.value, 0.28),
}));
const vitrineMiniTabs = [
    { key: 'geral', label: 'Geral' },
    { key: 'banners', label: 'Banners' },
    { key: 'promocoes', label: 'Promoções' },
    { key: 'aparencia', label: 'Aparência' },
];
const allowedVitrineMiniTabs = new Set(vitrineMiniTabs.map((tab) => tab.key));
const activeVitrineMiniTab = ref('geral');

const setActiveVitrineMiniTab = (tab) => {
    if (!allowedVitrineMiniTabs.has(tab)) return;
    activeVitrineMiniTab.value = tab;
};

watch(
    () => [props.initialTab, props.supportsShipping],
    () => {
        activeTab.value = resolveTabKey(props.initialTab);
    },
);

const setActiveTab = (tab) => {
    if (!allowedTabs.value.has(tab)) return;
    activeTab.value = tab;

    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState(window.history.state, '', url.toString());
    }
};

const storefrontForm = useForm({
    slug: '',
    store_online: true,
    customer_whatsapp_contact_enabled: false,
    offline_message: '',
    business_hours: emptyBusinessHours(),
    hero_enabled: true,
    hero_title: '',
    hero_subtitle: '',
    hero_cta_label: '',
    banners_enabled: true,
    banners: [createEmptyBanner()],
    promotions_enabled: true,
    promotions_title: '',
    promotions_subtitle: '',
    promotion_product_ids: [],
    promotion_service_ids: [],
    categories_enabled: true,
    catalog_enabled: true,
    catalog_title: '',
    catalog_subtitle: '',
    theme_menu_button_color: normalizeHexColor(props.contractor?.primary_color || '#FF5C35'),
    theme_cart_button_color: '#F58D1D',
    theme_favorite_button_color: '#FF3B30',
    theme_add_button_color: '#F59E0B',
});

const shippingForm = useForm({
    shipping_pickup_enabled: true,
    shipping_delivery_enabled: true,
    shipping_nationwide_enabled: false,
    shipping_nationwide_fee: 0,
    shipping_nationwide_free_over: '',
    shipping_state_rates: BRAZIL_STATES.map((state) => createEmptyShippingStateRate(state.code)),
    shipping_estimated_days: 2,
    shipping_city_rates: [createEmptyShippingCityRate()],
});

const hydrateStorefront = () => {
    const storefront = props.storefront ?? {};
    const blocks = storefront.blocks ?? {};
    const hero = storefront.hero ?? {};
    const promotions = storefront.promotions ?? {};
    const catalog = storefront.catalog ?? {};
    const theme = storefront.theme ?? {};
    storefrontForm.slug = String(props.contractor?.slug ?? '').trim();

    storefrontForm.store_online = storefront.store_online ?? true;
    storefrontForm.customer_whatsapp_contact_enabled = storefront.customer_whatsapp_contact_enabled ?? false;
    storefrontForm.offline_message = storefront.offline_message ?? '';
    storefrontForm.business_hours = normalizeBusinessHours(storefront.business_hours ?? {});
    storefrontForm.hero_enabled = blocks.hero ?? true;
    storefrontForm.hero_title = hero.title ?? '';
    storefrontForm.hero_subtitle = hero.subtitle ?? '';
    storefrontForm.hero_cta_label = hero.cta_label ?? '';
    storefrontForm.banners_enabled = blocks.banners ?? true;
    storefrontForm.banners = Array.isArray(storefront.banners) && storefront.banners.length
        ? storefront.banners.map((banner) => ({
            title: String(banner?.title ?? ''),
            subtitle: String(banner?.subtitle ?? ''),
            badge: String(banner?.badge ?? ''),
            existing_image_path: String(banner?.image_path ?? ''),
            image_url: String(banner?.image_url ?? ''),
            image_file: null,
            remove_image: false,
            cta_label: String(banner?.cta_label ?? ''),
            use_original_image_colors: Boolean(banner?.use_original_image_colors ?? false),
            background_color: normalizeHexColor(banner?.background_color ?? props.contractor?.primary_color ?? '#073341'),
            preview_url: String(banner?.image_url ?? ''),
        }))
        : [createEmptyBanner(), createEmptyBanner()];
    storefrontForm.promotions_enabled = blocks.promotions ?? true;
    storefrontForm.promotions_title = promotions.title ?? '';
    storefrontForm.promotions_subtitle = promotions.subtitle ?? '';
    storefrontForm.promotion_product_ids = Array.isArray(promotions.product_ids)
        ? promotions.product_ids.map((id) => String(id))
        : [];
    storefrontForm.promotion_service_ids = Array.isArray(promotions.service_ids)
        ? promotions.service_ids.map((id) => String(id))
        : [];
    storefrontForm.categories_enabled = blocks.categories ?? true;
    storefrontForm.catalog_enabled = blocks.catalog ?? true;
    storefrontForm.catalog_title = catalog.title ?? '';
    storefrontForm.catalog_subtitle = catalog.subtitle ?? '';
    storefrontForm.theme_menu_button_color = normalizeHexColor(
        theme.menu_button_color ?? props.contractor?.primary_color ?? '#FF5C35',
        '#FF5C35'
    );
    storefrontForm.theme_cart_button_color = normalizeHexColor(theme.cart_button_color ?? '#F58D1D', '#F58D1D');
    storefrontForm.theme_favorite_button_color = normalizeHexColor(theme.favorite_button_color ?? '#FF3B30', '#FF3B30');
    storefrontForm.theme_add_button_color = normalizeHexColor(theme.add_button_color ?? '#F59E0B', '#F59E0B');
};

const hydrateShipping = () => {
    shippingForm.shipping_pickup_enabled = props.shopShipping?.pickup_enabled ?? true;
    shippingForm.shipping_delivery_enabled = props.shopShipping?.delivery_enabled ?? true;
    shippingForm.shipping_nationwide_enabled = props.shopShipping?.nationwide_enabled ?? false;
    shippingForm.shipping_nationwide_fee = props.shopShipping?.nationwide_fee ?? 0;
    shippingForm.shipping_nationwide_free_over = props.shopShipping?.nationwide_free_over ?? '';
    shippingForm.shipping_estimated_days = props.shopShipping?.estimated_days ?? 2;
    const incomingStateRates = Array.isArray(props.shopShipping?.state_rates)
        ? props.shopShipping.state_rates
        : [];
    const fallbackStatewideState = normalizeStateCode(props.shopShipping?.statewide_state ?? '');
    const fallbackStatewideActive = Boolean(props.shopShipping?.statewide_enabled && fallbackStatewideState);
    const fallbackStatewideFee = props.shopShipping?.statewide_fee ?? 0;
    const fallbackStatewideFreeOver = props.shopShipping?.statewide_free_over ?? 0;

    const stateRateMap = new Map(
        incomingStateRates
            .map((row) => ({
                state: normalizeStateCode(row?.state ?? ''),
                fee: row?.fee ?? '',
                free_over: row?.free_over ?? '',
                active: Boolean(row?.active ?? false),
            }))
            .filter((row) => row.state !== '')
            .map((row) => [row.state, row]),
    );

    shippingForm.shipping_state_rates = BRAZIL_STATES.map((state) => {
        const current = stateRateMap.get(state.code);
        if (current) {
            return {
                state: state.code,
                fee: current.fee,
                free_over: current.free_over,
                active: current.active,
            };
        }

        if (fallbackStatewideActive && fallbackStatewideState === state.code) {
            return {
                state: state.code,
                fee: fallbackStatewideFee,
                free_over: fallbackStatewideFreeOver,
                active: true,
            };
        }

        return createEmptyShippingStateRate(state.code);
    });

    shippingForm.shipping_city_rates = Array.isArray(props.shopShipping?.city_rates) && props.shopShipping.city_rates.length
        ? props.shopShipping.city_rates.map((row) => {
            return {
                city: String(row?.city ?? ''),
                city_search: '',
                state: String(row?.state ?? '').toUpperCase(),
                fee: row?.fee ?? '',
                free_over: row?.free_over ?? '',
                estimated_days: row?.estimated_days ?? '',
                is_free: row?.is_free !== undefined ? Boolean(row.is_free) : false,
                active: row?.active !== undefined ? Boolean(row.active) : true,
            };
        })
        : [createEmptyShippingCityRate()];
    syncShippingStateRates();
};

watch(() => props.storefront, hydrateStorefront, { deep: true, immediate: true });
watch(() => props.shopShipping, hydrateShipping, { deep: true, immediate: true });
watch(
    () => props.contractor?.slug,
    (slug) => {
        storefrontForm.slug = String(slug ?? '').trim();
    },
);

const normalizeStateOptionRows = (rows) => {
    if (!Array.isArray(rows)) return [];

    return rows
        .map((row) => {
            const value = normalizeStateCode(row?.value ?? row?.code ?? row?.sigla ?? '');
            const label = String(row?.label ?? '').trim();
            const stateName = String(row?.name ?? '').trim();
            if (!value) return null;

            return {
                value,
                label: label !== '' ? label : `${value} - ${stateName || value}`,
            };
        })
        .filter(Boolean);
};

const locationRoutes = computed(() => ({
    states: String(props.addressDirectory?.routes?.states ?? '').trim(),
    cities: String(props.addressDirectory?.routes?.cities ?? '').trim(),
}));

const shippingStatesLoading = ref(false);
const shippingStatesError = ref('');
const shippingStateOptions = ref(
    normalizeStateOptionRows(props.addressDirectory?.states).length
        ? normalizeStateOptionRows(props.addressDirectory?.states)
        : BRAZIL_STATES.map((state) => ({
            value: state.code,
            label: `${state.code} - ${state.name}`,
        })),
);
watch(
    () => props.addressDirectory?.states,
    (rows) => {
        const normalized = normalizeStateOptionRows(rows);
        if (normalized.length > 0) {
            shippingStateOptions.value = normalized;
        }
    },
    { deep: true },
);
const shippingCitiesByState = ref({});
const shippingCitiesLoadingByState = ref({});
const shippingCitiesErrorByState = ref({});
const shippingCitiesRequestByState = new Map();

const shippingCityTableEnabled = computed(() => shippingForm.shipping_delivery_enabled);

const activeShippingStateRatesCount = computed(() =>
    (Array.isArray(shippingForm.shipping_state_rates) ? shippingForm.shipping_state_rates : [])
        .filter((rate) => Boolean(rate?.active))
        .length,
);

const shippingCoverageStatus = computed(() => {
    if (!shippingForm.shipping_delivery_enabled && !shippingForm.shipping_pickup_enabled) {
        return 'Sem retirada e sem entrega, a loja ficará sem checkout.';
    }

    if (!shippingForm.shipping_delivery_enabled) {
        return 'Entrega desativada. Apenas retirada na loja estará disponível.';
    }

    if (shippingForm.shipping_nationwide_enabled) {
        return 'Entrega habilitada para todo o Brasil.';
    }

    if (activeShippingStateRatesCount.value > 0) {
        return `Entrega habilitada por estado (${activeShippingStateRatesCount.value} estado(s) ativo(s)).`;
    }

    return 'Configure a tabela por cidade para liberar entrega.';
});

const shippingStateFilters = ref({
    search: '',
    active: 'all',
});

const shippingStateFilterActiveOptions = [
    { value: 'all', label: 'Todos os estados' },
    { value: 'active', label: 'Ativos' },
    { value: 'inactive', label: 'Inativos' },
];

const shippingStateRateEntries = computed(() => {
    const rates = Array.isArray(shippingForm.shipping_state_rates) ? shippingForm.shipping_state_rates : [];
    const byState = new Map(
        rates
            .map((rate, index) => [normalizeStateCode(rate?.state ?? ''), index])
            .filter(([state]) => state !== ''),
    );

    return BRAZIL_STATES.map((state) => {
        const index = byState.get(state.code);
        return {
            index: typeof index === 'number' ? index : -1,
            label: `${state.code} - ${state.name}`,
            stateName: state.name,
            row: typeof index === 'number' ? rates[index] : createEmptyShippingStateRate(state.code),
        };
    });
});

const filteredShippingStateRateEntries = computed(() => {
    const searchTerm = normalizeCitySearchKey(shippingStateFilters.value.search);
    const activeFilter = String(shippingStateFilters.value.active ?? 'all').trim().toLowerCase();

    return shippingStateRateEntries.value.filter((entry) => {
        const stateCode = normalizeStateCode(entry.row?.state ?? '');
        const stateLabel = String(entry.label ?? '').trim();
        const searchable = normalizeCitySearchKey(`${stateCode} ${stateLabel}`);
        const isActive = Boolean(entry.row?.active);

        if (searchTerm !== '' && !searchable.includes(searchTerm)) return false;
        if (activeFilter === 'active' && !isActive) return false;
        if (activeFilter === 'inactive' && isActive) return false;
        return true;
    });
});

const clearShippingStateFilters = () => {
    shippingStateFilters.value = {
        search: '',
        active: 'all',
    };
};

const shippingStateStats = computed(() => ({
    total: shippingStateRateEntries.value.length,
    filtered: filteredShippingStateRateEntries.value.length,
    activeCount: activeShippingStateRatesCount.value,
}));

const shippingCityFilters = ref({
    search: '',
    state: '',
    active: 'all',
    free: 'all',
});

const shippingCityFilterStateOptions = computed(() => ([
    { value: '', label: 'Todas UFs' },
    ...shippingStateOptions.value,
]));

const shippingCityFilterActiveOptions = [
    { value: 'all', label: 'Todas (status)' },
    { value: 'active', label: 'Ativas' },
    { value: 'inactive', label: 'Inativas' },
];

const shippingCityFilterFreeOptions = [
    { value: 'all', label: 'Todas (frete)' },
    { value: 'free', label: 'Grátis' },
    { value: 'paid', label: 'Com valor' },
];

const shippingCityRateEntries = computed(() =>
    (Array.isArray(shippingForm.shipping_city_rates) ? shippingForm.shipping_city_rates : [])
        .map((rate, index) => ({ index, rate })),
);

const filteredShippingCityRateEntries = computed(() => {
    const searchTerm = normalizeCitySearchKey(shippingCityFilters.value.search);
    const stateFilter = normalizeStateCode(shippingCityFilters.value.state);
    const activeFilter = String(shippingCityFilters.value.active ?? 'all').trim().toLowerCase();
    const freeFilter = String(shippingCityFilters.value.free ?? 'all').trim().toLowerCase();

    return shippingCityRateEntries.value.filter((entry) => {
        const rate = entry.rate ?? {};
        const state = normalizeStateCode(rate.state ?? '');
        const city = String(rate.city ?? rate.city_search ?? '').trim();
        const citySearchKey = normalizeCitySearchKey(`${city} ${state}`);

        if (stateFilter !== '' && state !== stateFilter) return false;
        if (searchTerm !== '' && !citySearchKey.includes(searchTerm)) return false;
        if (activeFilter === 'active' && !rate.active) return false;
        if (activeFilter === 'inactive' && rate.active) return false;
        if (freeFilter === 'free' && !rate.is_free) return false;
        if (freeFilter === 'paid' && rate.is_free) return false;

        return true;
    });
});

const shippingCityStats = computed(() => {
    const rows = shippingCityRateEntries.value;
    const activeCount = rows.filter((entry) => entry.rate?.active).length;
    const freeCount = rows.filter((entry) => entry.rate?.is_free).length;

    return {
        total: rows.length,
        filtered: filteredShippingCityRateEntries.value.length,
        activeCount,
        freeCount,
    };
});

const clearShippingCityFilters = () => {
    shippingCityFilters.value = {
        search: '',
        state: '',
        active: 'all',
        free: 'all',
    };
};

const citySearchOptionsForRate = (rate) => {
    const state = normalizeStateCode(rate?.state ?? '');
    if (!state) return [];

    const allCities = Array.isArray(shippingCitiesByState.value?.[state])
        ? shippingCitiesByState.value[state]
        : [];

    const query = normalizeCitySearchKey(rate?.city_search ?? '');
    const filtered = query === ''
        ? allCities
        : allCities.filter((city) => normalizeCitySearchKey(city).includes(query));

    const ranked = query === ''
        ? filtered
        : [...filtered].sort((a, b) => {
            const aKey = normalizeCitySearchKey(a);
            const bKey = normalizeCitySearchKey(b);

            const aRank = aKey === query ? 0 : (aKey.startsWith(query) ? 1 : 2);
            const bRank = bKey === query ? 0 : (bKey.startsWith(query) ? 1 : 2);
            if (aRank !== bRank) return aRank - bRank;
            return a.localeCompare(b, 'pt-BR');
        });

    const limited = query === '' ? ranked.slice(0, 120) : ranked.slice(0, 300);
    const selectedCity = String(rate?.city ?? '').trim();

    const withSelected = selectedCity !== '' && !limited.includes(selectedCity)
        ? [selectedCity, ...limited]
        : limited;

    return withSelected.map((city) => ({ value: city, label: city }));
};

const isCityLoadingForRate = (rate) => {
    const state = normalizeStateCode(rate?.state ?? '');
    if (!state) return false;
    return Boolean(shippingCitiesLoadingByState.value?.[state]);
};

const cityErrorForRate = (rate) => {
    const state = normalizeStateCode(rate?.state ?? '');
    if (!state) return '';
    return String(shippingCitiesErrorByState.value?.[state] ?? '');
};

const openCityPickerIndex = ref(null);
let closeCityPickerTimeout = null;

const clearCloseCityPickerTimeout = () => {
    if (closeCityPickerTimeout === null || typeof window === 'undefined') return;
    window.clearTimeout(closeCityPickerTimeout);
    closeCityPickerTimeout = null;
};

const scheduleCloseCityPicker = () => {
    clearCloseCityPickerTimeout();
    if (typeof window === 'undefined') {
        openCityPickerIndex.value = null;
        return;
    }

    closeCityPickerTimeout = window.setTimeout(() => {
        openCityPickerIndex.value = null;
        closeCityPickerTimeout = null;
    }, 120);
};

const openCityPicker = async (index, rate) => {
    clearCloseCityPickerTimeout();
    const state = normalizeStateCode(rate?.state ?? '');
    if (!state) return;

    await loadCitiesByState(state);
    openCityPickerIndex.value = index;
};

const toggleCityPicker = async (index, rate) => {
    clearCloseCityPickerTimeout();
    if (openCityPickerIndex.value === index) {
        openCityPickerIndex.value = null;
        return;
    }

    await openCityPicker(index, rate);
};

const selectCityFromPicker = (index, city) => {
    onShippingCityRateSelectCity(index, city);
    openCityPickerIndex.value = null;
};

const loadShippingStatesFromDirectory = async () => {
    const statesUrl = locationRoutes.value.states;
    if (statesUrl === '') {
        return;
    }

    shippingStatesLoading.value = true;
    shippingStatesError.value = '';

    try {
        const response = await fetch(statesUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) throw new Error('states_fetch_failed');

        const payload = await response.json();
        const sourceRows = Array.isArray(payload?.states) ? payload.states : payload;
        const options = normalizeStateOptionRows(sourceRows)
            .sort((a, b) => a.label.localeCompare(b.label, 'pt-BR'));

        if (options.length > 0) {
            shippingStateOptions.value = options;
        } else {
            shippingStatesError.value = 'Não foi possível carregar a lista de estados.';
        }
    } catch {
        shippingStatesError.value = 'Não foi possível carregar a lista de estados.';
    } finally {
        shippingStatesLoading.value = false;
    }
};

const loadCitiesByState = async (stateCode) => {
    const state = normalizeStateCode(stateCode);
    if (!state) return [];

    const citiesUrl = locationRoutes.value.cities;
    if (citiesUrl === '') {
        shippingCitiesErrorByState.value = {
            ...shippingCitiesErrorByState.value,
            [state]: 'Não foi possível consultar cidades agora.',
        };
        return [];
    }

    const cached = shippingCitiesByState.value?.[state];
    if (Array.isArray(cached) && cached.length > 0) {
        return cached;
    }

    const inflight = shippingCitiesRequestByState.get(state);
    if (inflight) {
        return await inflight;
    }

    const requestPromise = (async () => {
        shippingCitiesLoadingByState.value = {
            ...shippingCitiesLoadingByState.value,
            [state]: true,
        };
        shippingCitiesErrorByState.value = {
            ...shippingCitiesErrorByState.value,
            [state]: '',
        };

        try {
            const response = await fetch(`${citiesUrl}?state=${encodeURIComponent(state)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!response.ok) throw new Error('cities_fetch_failed');

            const payload = await response.json();
            const sourceRows = Array.isArray(payload?.cities) ? payload.cities : payload;
            const cities = Array.isArray(sourceRows)
                ? Array.from(new Set(
                    sourceRows
                        .map((item) => String(item?.name ?? item ?? '').trim())
                        .filter((city) => city !== ''),
                )).sort((a, b) => a.localeCompare(b, 'pt-BR'))
                : [];

            shippingCitiesByState.value = {
                ...shippingCitiesByState.value,
                [state]: cities,
            };

            return cities;
        } catch {
            shippingCitiesErrorByState.value = {
                ...shippingCitiesErrorByState.value,
                [state]: 'Não foi possível consultar cidades para esta UF.',
            };

            return [];
        } finally {
            shippingCitiesLoadingByState.value = {
                ...shippingCitiesLoadingByState.value,
                [state]: false,
            };
            shippingCitiesRequestByState.delete(state);
        }
    })();

    shippingCitiesRequestByState.set(state, requestPromise);
    return await requestPromise;
};

const resolveCityFromQuery = (cities, queryRaw) => {
    if (!Array.isArray(cities) || cities.length === 0) return null;

    const query = normalizeCitySearchKey(queryRaw);
    if (query === '') return null;

    const exact = cities.find((city) => normalizeCitySearchKey(city) === query);
    return exact ?? null;
};

const onShippingCityRateStateChange = async (index, value) => {
    const nextState = normalizeStateCode(value);
    const current = Array.isArray(shippingForm.shipping_city_rates)
        ? shippingForm.shipping_city_rates[index]
        : null;
    if (!current) return;

    current.state = nextState;
    current.city = '';
    current.city_search = '';
    openCityPickerIndex.value = null;

    if (nextState) {
        await loadCitiesByState(nextState);
    }
};

const onShippingCityRateSelectCity = (index, value) => {
    const current = Array.isArray(shippingForm.shipping_city_rates)
        ? shippingForm.shipping_city_rates[index]
        : null;
    if (!current) return;

    current.city = String(value ?? '').trim();
    current.city_search = current.city;
};

const onShippingCityRateSearchInput = async (index, value) => {
    const current = Array.isArray(shippingForm.shipping_city_rates)
        ? shippingForm.shipping_city_rates[index]
        : null;
    if (!current) return;

    const queryRaw = String(value ?? '');
    current.city_search = queryRaw;
    openCityPickerIndex.value = index;

    const state = normalizeStateCode(current.state ?? '');
    if (!state) {
        current.city = '';
        return;
    }

    const cities = await loadCitiesByState(state);
    if (!Array.isArray(cities) || cities.length === 0) {
        current.city = '';
        return;
    }

    const query = normalizeCitySearchKey(queryRaw);
    if (query === '') {
        current.city = '';
        return;
    }

    const resolvedCity = resolveCityFromQuery(cities, queryRaw);
    if (resolvedCity) {
        current.city = resolvedCity;
        return;
    }

    current.city = '';
};

if (props.supportsShipping) {
    loadShippingStatesFromDirectory();
}

const WEEKDAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
const DEFAULT_BUSINESS_OPEN = '08:00';
const DEFAULT_BUSINESS_CLOSE = '18:00';

const formatCurrency = (value) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const productOptions = computed(() =>
    (props.products ?? []).map((product) => ({
        value: String(product.id),
        title: String(product.name ?? 'Produto'),
        subtitle: formatCurrency(product.sale_price),
        meta: `Estoque: ${Number(product.stock_quantity ?? 0)}`,
        search: `${String(product.name ?? '')} ${String(product.id ?? '')}`.toLowerCase(),
    })),
);

const serviceOptions = computed(() =>
    (props.services ?? []).map((service) => ({
        value: String(service.id),
        title: String(service.name ?? 'Serviço'),
        subtitle: formatCurrency(service.base_price),
        meta: `${Number(service.duration_minutes ?? 0)} min`,
        search: `${String(service.name ?? '')} ${String(service.id ?? '')} ${Number(service.duration_minutes ?? 0)}`.toLowerCase(),
    })),
);

const currentTemplate = computed(() => String(props.storefront?.template ?? '').trim().toLowerCase());
const isServiceTemplate = computed(() => currentTemplate.value === 'servicos');
const currentTemplateMeta = computed(() =>
    (props.templates ?? []).find((item) => String(item?.value ?? '').trim().toLowerCase() === currentTemplate.value) ?? null,
);

const promotionOptions = computed(() =>
    isServiceTemplate.value ? serviceOptions.value : productOptions.value,
);

const promotionFieldLabel = computed(() =>
    isServiceTemplate.value ? 'Serviços em destaque' : 'Produtos em destaque',
);

const promotionSearch = ref('');
const promotionSelectValue = ref('');

const selectedPromotionIds = computed({
    get: () => (isServiceTemplate.value
        ? storefrontForm.promotion_service_ids
        : storefrontForm.promotion_product_ids)
        .map((id) => String(id)),
    set: (values) => {
        const sanitized = Array.from(new Set(
            (values ?? [])
                .map((id) => String(id).trim())
                .filter((id) => id !== ''),
        ));

        if (isServiceTemplate.value) {
            storefrontForm.promotion_service_ids = sanitized;
            return;
        }

        storefrontForm.promotion_product_ids = sanitized;
    },
});

const selectedPromotionCount = computed(() => selectedPromotionIds.value.length);
const promotionSearchPlaceholder = computed(() =>
    isServiceTemplate.value
        ? 'Buscar serviços para destacar...'
        : 'Buscar produtos para destacar...',
);
const promotionSelectableOptions = computed(() => {
    const query = promotionSearch.value.trim().toLowerCase();
    const options = promotionOptions.value ?? [];
    const selectedSet = new Set(selectedPromotionIds.value);

    return options
        .filter((option) => {
            if (selectedSet.has(option.value)) return false;
            if (!query) return true;
            return option.search.includes(query);
        })
        .slice(0, 120);
});
const promotionSelectableSelectOptions = computed(() =>
    (promotionSelectableOptions.value ?? []).map((option) => ({
        value: option.value,
        label: `${option.title} - ${option.subtitle}`,
    })),
);
const selectedPromotionCards = computed(() => {
    const optionMap = new Map((promotionOptions.value ?? []).map((option) => [option.value, option]));

    return selectedPromotionIds.value
        .map((id) => optionMap.get(id))
        .filter(Boolean);
});

const promotionErrorMessage = computed(() =>
    storefrontForm.errors.promotion_service_ids || storefrontForm.errors.promotion_product_ids || '',
);

const bannerErrorMessage = computed(() => {
    if (storefrontForm.errors.banners) return storefrontForm.errors.banners;

    const dynamicErrorKey = Object.keys(storefrontForm.errors).find((key) => key.startsWith('banners.'));
    return dynamicErrorKey ? storefrontForm.errors[dynamicErrorKey] : '';
});

const previewBrandName = computed(() => props.contractor?.brand_name || props.contractor?.name || 'Loja');
const previewSlug = computed(() => String(storefrontForm.slug ?? props.contractor?.slug ?? '').trim().toLowerCase());
const previewShopPath = computed(() => `/shop/${previewSlug.value || String(props.contractor?.slug ?? '').trim()}`);
const previewShopUrl = computed(() => {
    const slug = previewSlug.value || String(props.contractor?.slug ?? '').trim();
    if (!slug) return '';

    const original = String(props.shop_url ?? '').trim();
    if (original.includes('/shop/')) {
        return `${original.split('/shop/')[0]}/shop/${slug}`;
    }

    if (typeof window !== 'undefined') {
        return `${window.location.origin}/shop/${slug}`;
    }

    return original || `/shop/${slug}`;
});
const activeBlocksCount = computed(() =>
    [
        storefrontForm.hero_enabled,
        storefrontForm.banners_enabled,
        storefrontForm.promotions_enabled,
        storefrontForm.categories_enabled,
        storefrontForm.catalog_enabled,
    ].filter(Boolean).length,
);

const canAddBanner = computed(() => storefrontForm.banners.length < MAX_BANNERS);

const revokePreview = (url) => {
    const safe = String(url ?? '').trim();
    if (!safe.startsWith('blob:')) return;
    URL.revokeObjectURL(safe);
};

const addBanner = () => {
    if (!canAddBanner.value) return;
    storefrontForm.banners = [...storefrontForm.banners, createEmptyBanner()];
};

const removeBanner = (index) => {
    const next = [...storefrontForm.banners];
    revokePreview(next[index]?.preview_url);
    next.splice(index, 1);
    storefrontForm.banners = next.length ? next : [createEmptyBanner()];
};

const onBannerFileChange = (index, event) => {
    const file = event?.target?.files?.[0] ?? null;
    if (!file) return;

    revokePreview(storefrontForm.banners[index].preview_url);
    storefrontForm.banners[index].image_file = file;
    storefrontForm.banners[index].remove_image = false;
    storefrontForm.banners[index].preview_url = URL.createObjectURL(file);
};

const clearBannerImage = (index) => {
    revokePreview(storefrontForm.banners[index].preview_url);
    storefrontForm.banners[index].image_file = null;
    storefrontForm.banners[index].preview_url = '';
    storefrontForm.banners[index].image_url = '';
    storefrontForm.banners[index].remove_image = true;
};

const addShippingCityRate = () => {
    shippingForm.shipping_city_rates = [
        ...(Array.isArray(shippingForm.shipping_city_rates) ? shippingForm.shipping_city_rates : []),
        createEmptyShippingCityRate(),
    ];
    clearShippingCityFilters();
};

const removeShippingCityRate = (index) => {
    const next = Array.isArray(shippingForm.shipping_city_rates)
        ? [...shippingForm.shipping_city_rates]
        : [];
    next.splice(index, 1);
    shippingForm.shipping_city_rates = next.length ? next : [createEmptyShippingCityRate()];
};

function syncShippingStateRates() {
    const currentRows = Array.isArray(shippingForm.shipping_state_rates)
        ? shippingForm.shipping_state_rates
        : [];
    const rowByState = new Map(
        currentRows
            .map((row) => ({
                state: normalizeStateCode(row?.state ?? ''),
                fee: row?.fee ?? '',
                free_over: row?.free_over ?? '',
                active: Boolean(row?.active ?? false),
            }))
            .filter((row) => row.state !== '')
            .map((row) => [row.state, row]),
    );

    shippingForm.shipping_state_rates = BRAZIL_STATES.map((state) => {
        const current = rowByState.get(state.code);
        if (current) {
            return {
                state: state.code,
                fee: current.fee,
                free_over: current.free_over,
                active: current.active,
            };
        }

        return createEmptyShippingStateRate(state.code);
    });
}

watch(
    () => shippingForm.shipping_state_rates,
    (rows) => {
        if (!Array.isArray(rows)) {
            syncShippingStateRates();
            return;
        }

        rows.forEach((row) => {
            row.state = normalizeStateCode(row?.state ?? '');
        });
    },
    { deep: true, immediate: true },
);

watch(
    () => shippingForm.shipping_city_rates,
    (rows) => {
        if (!Array.isArray(rows)) return;

        rows.forEach((row) => {
            row.state = normalizeStateCode(row?.state ?? '');
            if (!row.city_search && row.city) {
                row.city_search = row.city;
            }

            if (row.state) {
                loadCitiesByState(row.state);
            }
        });
    },
    { deep: true },
);

const addPromotionFromSelect = () => {
    const value = String(promotionSelectValue.value || '').trim();
    if (!value) return;
    if (selectedPromotionIds.value.includes(value)) {
        promotionSelectValue.value = '';
        return;
    }

    selectedPromotionIds.value = [...selectedPromotionIds.value, value];
    promotionSelectValue.value = '';
};

const removePromotionSelection = (value) => {
    selectedPromotionIds.value = selectedPromotionIds.value.filter((id) => id !== String(value));
};

const clearPromotionSelection = () => {
    selectedPromotionIds.value = [];
};

watch(isServiceTemplate, () => {
    promotionSearch.value = '';
    promotionSelectValue.value = '';
});

const withBusinessHoursUpdate = (callback) => {
    const next = normalizeBusinessHours(storefrontForm.business_hours ?? {});
    callback(next);
    storefrontForm.business_hours = next;
};

const applyBusinessHoursPreset = (preset) => {
    withBusinessHoursUpdate((hours) => {
        for (const day of WEEK_DAYS) {
            const current = hours[day.key] ?? { enabled: true, open: '00:00', close: '23:59' };

            if (preset === 'business') {
                const isWeekday = WEEKDAY_KEYS.includes(day.key);
                hours[day.key] = {
                    enabled: isWeekday,
                    open: DEFAULT_BUSINESS_OPEN,
                    close: DEFAULT_BUSINESS_CLOSE,
                };
                continue;
            }

            if (preset === 'everyday') {
                hours[day.key] = {
                    enabled: true,
                    open: DEFAULT_BUSINESS_OPEN,
                    close: DEFAULT_BUSINESS_CLOSE,
                };
                continue;
            }

            if (preset === 'always') {
                hours[day.key] = {
                    enabled: true,
                    open: '00:00',
                    close: '23:59',
                };
                continue;
            }

            if (preset === 'close-weekend') {
                const isWeekend = day.key === 'saturday' || day.key === 'sunday';
                hours[day.key] = {
                    enabled: !isWeekend,
                    open: current.open || DEFAULT_BUSINESS_OPEN,
                    close: current.close || DEFAULT_BUSINESS_CLOSE,
                };
            }
        }
    });
};

const submitStorefront = () => {
    storefrontForm.transform((data) => ({
        ...data,
        _method: 'put',
        section: 'storefront',
        slug: String(data.slug || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9-]/g, '-')
            .replace(/-{2,}/g, '-')
            .replace(/^-+|-+$/g, ''),
        hero_cta_label: String(data.hero_cta_label || '').trim(),
        customer_whatsapp_contact_enabled: Boolean(data.customer_whatsapp_contact_enabled),
        banners_enabled: Boolean(data.banners_enabled),
        banners: (data.banners ?? [])
            .map((banner) => ({
                title: String(banner?.title || '').trim(),
                subtitle: String(banner?.subtitle || '').trim(),
                badge: String(banner?.badge || '').trim(),
                existing_image_path: String(banner?.existing_image_path || '').trim(),
                image_file: banner?.image_file ?? null,
                remove_image: Boolean(banner?.remove_image),
                image_url: String(banner?.image_url || '').trim(),
                cta_label: String(banner?.cta_label || '').trim(),
                use_original_image_colors: Boolean(banner?.use_original_image_colors),
                background_color: normalizeHexColor(
                    banner?.background_color || props.contractor?.primary_color || '#073341'
                ),
            }))
            .slice(0, MAX_BANNERS),
        promotion_product_ids: (data.promotion_product_ids ?? [])
            .map((id) => Number(id))
            .filter((id) => Number.isInteger(id) && id > 0),
        promotion_service_ids: (data.promotion_service_ids ?? [])
            .map((id) => Number(id))
            .filter((id) => Number.isInteger(id) && id > 0),
        business_hours: Object.fromEntries(
            WEEK_DAYS.map((day) => {
                const row = data.business_hours?.[day.key] ?? {};
                return [day.key, {
                    enabled: Boolean(row.enabled),
                    open: normalizeHour(row.open, '00:00'),
                    close: normalizeHour(row.close, '23:59'),
                }];
            }),
        ),
        theme: {
            menu_button_color: normalizeHexColor(data.theme_menu_button_color, '#FF5C35'),
            cart_button_color: normalizeHexColor(data.theme_cart_button_color, '#F58D1D'),
            favorite_button_color: normalizeHexColor(data.theme_favorite_button_color, '#FF3B30'),
            add_button_color: normalizeHexColor(data.theme_add_button_color, '#F59E0B'),
        },
    })).post(route('admin.storefront.update'), {
        preserveScroll: true,
        forceFormData: true,
    });
};

const submitShipping = () => {
    shippingForm.transform((data) => ({
        ...data,
        _method: 'put',
        section: 'shipping',
        shipping_nationwide_enabled: Boolean(data.shipping_nationwide_enabled),
        shipping_state_rates: (Array.isArray(data.shipping_state_rates) ? data.shipping_state_rates : [])
            .map((row) => ({
                state: normalizeStateCode(row?.state ?? ''),
                fee: row?.fee === '' || row?.fee === null ? null : Number(row.fee),
                free_over: row?.free_over === '' || row?.free_over === null ? null : Number(row.free_over),
                active: Boolean(row?.active),
            }))
            .filter((row) => row.state !== ''),
        shipping_city_rates: (Array.isArray(data.shipping_city_rates) ? data.shipping_city_rates : [])
            .map((row) => ({
                city: String(row?.city ?? '').trim(),
                state: normalizeStateCode(row?.state ?? ''),
                fee: row?.fee === '' || row?.fee === null ? null : Number(row.fee),
                free_over: row?.free_over === '' || row?.free_over === null ? null : Number(row.free_over),
                estimated_days: row?.estimated_days === '' || row?.estimated_days === null ? null : Number(row.estimated_days),
                is_free: Boolean(row?.is_free),
                active: row?.active !== undefined ? Boolean(row.active) : true,
            }))
            .filter((row) => row.city !== '' && row.state !== ''),
    })).post(route('admin.storefront.update'), {
        preserveScroll: true,
    });
};

onBeforeUnmount(() => {
    clearCloseCityPickerTimeout();
    storefrontForm.banners.forEach((banner) => revokePreview(banner?.preview_url));
});
</script>

<template>
    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Loja Virtual" :show-table-view-toggle="false">
        <Head title="Loja Virtual" />

        <section class="space-y-4" :style="storefrontTabStyles">
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Loja pública</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ previewBrandName }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ previewShopPath }}</p>
                    <a
                        v-if="previewShopUrl"
                        :href="previewShopUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-3 inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Abrir loja virtual
                    </a>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Blocos ativos</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ activeBlocksCount }}</p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Destaques</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ selectedPromotionCount }}</p>
                </article>
            </section>

            <div class="storefront-tabs-shell">
                <div class="storefront-tabs-track">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="storefront-tab"
                        :class="activeTab === tab.key ? 'is-active' : ''"
                        @click="setActiveTab(tab.key)"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        <span class="truncate">{{ tab.label }}</span>
                    </button>
                </div>
            </div>

            <form v-if="activeTab === 'vitrine'" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="submitStorefront">
                <div class="storefront-mini-tabs-shell">
                    <div class="storefront-mini-tabs-track">
                        <button
                            v-for="tab in vitrineMiniTabs"
                            :key="`mini-${tab.key}`"
                            type="button"
                            class="storefront-mini-tab"
                            :class="activeVitrineMiniTab === tab.key ? 'is-active' : ''"
                            @click="setActiveVitrineMiniTab(tab.key)"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <template v-if="activeVitrineMiniTab === 'geral'">
                    <section class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Modelo da loja</p>
                        <div class="mt-2 flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2">
                            <p class="text-sm font-semibold text-slate-800">{{ currentTemplateMeta?.label || 'Modelo padrão' }}</p>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600">Somente leitura</span>
                        </div>
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Slug da loja virtual</label>
                        <div class="mt-2 flex items-center overflow-hidden rounded-lg border border-slate-200 bg-white">
                            <span class="border-r border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-500">/shop/</span>
                            <input
                                v-model="storefrontForm.slug"
                                type="text"
                                class="w-full border-0 px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-0"
                                placeholder="minha-loja"
                                autocomplete="off"
                            >
                        </div>
                        <p class="mt-2 text-[11px] text-slate-500">Use apenas letras minúsculas, números e hífen.</p>
                        <p v-if="storefrontForm.errors.slug" class="mt-1 text-[11px] text-rose-600">{{ storefrontForm.errors.slug }}</p>
                    </section>

                    <section class="grid gap-3 md:grid-cols-2">
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                            <span>Loja online ativa</span>
                            <input v-model="storefrontForm.store_online" type="checkbox" class="rounded border-slate-300">
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                            <span>Contato no WhatsApp em pedidos/agendamentos</span>
                            <input v-model="storefrontForm.customer_whatsapp_contact_enabled" type="checkbox" class="rounded border-slate-300">
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                            <span>Hero principal</span>
                            <input v-model="storefrontForm.hero_enabled" type="checkbox" class="rounded border-slate-300">
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                            <span>Banners</span>
                            <input v-model="storefrontForm.banners_enabled" type="checkbox" class="rounded border-slate-300">
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                            <span>Promoções</span>
                            <input v-model="storefrontForm.promotions_enabled" type="checkbox" class="rounded border-slate-300">
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                            <span>Categorias</span>
                            <input v-model="storefrontForm.categories_enabled" type="checkbox" class="rounded border-slate-300">
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm md:col-span-2">
                            <span>Catálogo</span>
                            <input v-model="storefrontForm.catalog_enabled" type="checkbox" class="rounded border-slate-300">
                        </label>
                    </section>

                    <section class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mensagem quando offline</label>
                        <textarea
                            v-model="storefrontForm.offline_message"
                            rows="2"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            placeholder="Ex.: Loja em manutenção. Tente novamente em alguns minutos."
                        />
                    </section>

                    <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Textos principais da vitrine</p>
                        <div class="grid gap-3 md:grid-cols-2">
                            <input
                                v-model="storefrontForm.hero_title"
                                type="text"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                placeholder="Título da vitrine"
                            >
                            <input
                                v-model="storefrontForm.promotions_title"
                                type="text"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                placeholder="Título dos destaques"
                            >
                            <textarea
                                v-model="storefrontForm.hero_subtitle"
                                rows="2"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                placeholder="Subtítulo da vitrine"
                            ></textarea>
                            <textarea
                                v-model="storefrontForm.promotions_subtitle"
                                rows="2"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                placeholder="Subtítulo dos destaques"
                            ></textarea>
                            <input
                                v-model="storefrontForm.hero_cta_label"
                                type="text"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm md:col-span-2"
                                placeholder="Texto do botão principal (ex.: Ver catálogo)"
                            >
                        </div>
                    </section>
                </template>

                <template v-if="activeVitrineMiniTab === 'banners'">
                    <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Banners da loja</p>
                                <p class="mt-1 text-[11px] text-slate-500">
                                    Use os mesmos banners em desktop e mobile. No app mobile, eles aparecem em rolagem horizontal.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!canAddBanner"
                                @click="addBanner"
                            >
                                Adicionar banner
                            </button>
                        </div>

                        <p class="text-[11px] text-slate-500">
                            Banners configurados: {{ storefrontForm.banners.length }} de {{ MAX_BANNERS }}
                        </p>

                        <div class="space-y-3">
                            <article
                                v-for="(banner, index) in storefrontForm.banners"
                                :key="`banner-${index}`"
                                class="rounded-xl border border-slate-200 bg-white p-3"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Banner {{ index + 1 }}</p>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="storefrontForm.banners.length <= 1"
                                        @click="removeBanner(index)"
                                    >
                                        Remover
                                    </button>
                                </div>

                                <div class="mt-3 grid gap-2 md:grid-cols-2">
                                    <input
                                        v-model="banner.title"
                                        type="text"
                                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                        placeholder="Título do banner"
                                    >
                                    <input
                                        v-model="banner.badge"
                                        type="text"
                                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                        placeholder="Badge (ex.: Oferta)"
                                    >
                                    <textarea
                                        v-model="banner.subtitle"
                                        rows="2"
                                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm md:col-span-2"
                                        placeholder="Subtítulo do banner"
                                    ></textarea>
                                    <input
                                        v-model="banner.cta_label"
                                        type="text"
                                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm md:col-span-2"
                                        placeholder="Texto do CTA (ex.: Ver mais)"
                                    >
                                </div>

                                <div class="mt-3 grid gap-2 md:grid-cols-[180px_minmax(260px,1fr)_auto]">
                                    <label class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-600">
                                        <span class="font-semibold uppercase tracking-wide text-slate-500">Cor</span>
                                        <div class="mt-1 flex items-center gap-2">
                                            <input
                                                v-model="banner.background_color"
                                                type="color"
                                                class="h-9 w-9 cursor-pointer rounded border border-slate-300 bg-white p-0.5 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="banner.use_original_image_colors"
                                            >
                                            <input
                                                v-model="banner.background_color"
                                                type="text"
                                                class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-1 text-xs disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                                placeholder="#FF5C35"
                                                :disabled="banner.use_original_image_colors"
                                            >
                                        </div>
                                        <label class="mt-2 inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-600">
                                            <input v-model="banner.use_original_image_colors" type="checkbox" class="rounded border-slate-300">
                                            Usar apenas cores da imagem (sem sobretom)
                                        </label>
                                    </label>

                                    <label class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-600 min-h-[108px]">
                                        <span class="font-semibold uppercase tracking-wide text-slate-500">Imagem do banner</span>
                                        <p class="mt-1 text-[11px] text-slate-500">Proporção recomendada: 4:3</p>
                                        <input
                                            type="file"
                                            accept="image/png,image/jpeg,image/jpg,image/webp"
                                            class="mt-2 block w-full text-[12px]"
                                            @change="onBannerFileChange(index, $event)"
                                        >
                                    </label>

                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                        @click="clearBannerImage(index)"
                                    >
                                        Limpar imagem
                                    </button>
                                </div>

                                <div v-if="banner.preview_url || banner.image_url" class="mt-3 overflow-hidden rounded-xl border border-slate-200">
                                    <img :src="banner.preview_url || banner.image_url" :alt="banner.title || `Banner ${index + 1}`" class="h-40 w-full object-cover">
                                </div>
                            </article>
                        </div>
                    </section>

                    <p v-if="bannerErrorMessage" class="text-xs font-medium text-rose-600">{{ bannerErrorMessage }}</p>
                </template>

                <template v-if="activeVitrineMiniTab === 'promocoes'">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ promotionFieldLabel }} ({{ selectedPromotionCount }})
                            </label>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-rose-200 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="!selectedPromotionCount"
                                @click="clearPromotionSelection"
                            >
                                Limpar seleção
                            </button>
                        </div>

                        <section class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="grid gap-2 lg:grid-cols-[1fr,280px,auto]">
                                <input
                                    v-model="promotionSearch"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                    :placeholder="promotionSearchPlaceholder"
                                >
                                <UiSelect
                                    v-model="promotionSelectValue"
                                    :options="promotionSelectableSelectOptions"
                                    placeholder="Selecione um item"
                                    button-class="w-full text-sm"
                                />
                                <button
                                    type="button"
                                    class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="!promotionSelectValue"
                                    @click="addPromotionFromSelect"
                                >
                                    Adicionar
                                </button>
                            </div>

                            <p class="text-[11px] text-slate-500">
                                {{ promotionSelectableOptions.length }} opção(ões) disponível(is) para seleção.
                            </p>
                        </section>

                        <section class="rounded-xl border border-slate-200 bg-white p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selecionados</p>
                            <div v-if="selectedPromotionCards.length" class="mt-2 grid gap-1.5 sm:grid-cols-2 xl:grid-cols-3">
                                <article
                                    v-for="option in selectedPromotionCards"
                                    :key="`promotion-selected-${option.value}`"
                                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-2"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="truncate pr-1 text-xs font-semibold text-slate-800">{{ option.title }}</p>
                                        <button
                                            type="button"
                                            class="shrink-0 rounded-md border border-emerald-200 bg-white px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 hover:border-rose-200 hover:text-rose-600"
                                            @click="removePromotionSelection(option.value)"
                                        >
                                            Remover
                                        </button>
                                    </div>
                                    <p class="mt-0.5 text-[11px] font-semibold text-slate-600">{{ option.subtitle }}</p>
                                    <p class="mt-0.5 truncate text-[10px] text-slate-500">{{ option.meta }}</p>
                                </article>
                            </div>
                            <p v-else class="mt-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-xs text-slate-500">
                                Selecione itens no campo acima para montar sua vitrine de destaque.
                            </p>
                        </section>

                        <p v-if="promotionErrorMessage" class="text-xs font-medium text-rose-600">{{ promotionErrorMessage }}</p>
                    </div>
                </template>

                <template v-if="activeVitrineMiniTab === 'aparencia'">
                    <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cores dos botões da loja</p>
                            <p class="mt-1 text-[11px] text-slate-500">
                                Personalize as cores dos botões principais do app da loja.
                            </p>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">
                                <span class="font-semibold uppercase tracking-wide text-slate-500">Botão de menu</span>
                                <div class="mt-1 flex items-center gap-2">
                                    <input v-model="storefrontForm.theme_menu_button_color" type="color" class="h-9 w-9 cursor-pointer rounded border border-slate-300 bg-white p-0.5">
                                    <input v-model="storefrontForm.theme_menu_button_color" type="text" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-1 text-xs" placeholder="#FF5C35">
                                </div>
                            </label>
                            <label class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">
                                <span class="font-semibold uppercase tracking-wide text-slate-500">Botão de carrinho</span>
                                <div class="mt-1 flex items-center gap-2">
                                    <input v-model="storefrontForm.theme_cart_button_color" type="color" class="h-9 w-9 cursor-pointer rounded border border-slate-300 bg-white p-0.5">
                                    <input v-model="storefrontForm.theme_cart_button_color" type="text" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-1 text-xs" placeholder="#F58D1D">
                                </div>
                            </label>
                            <label class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">
                                <span class="font-semibold uppercase tracking-wide text-slate-500">Botão de favoritos</span>
                                <div class="mt-1 flex items-center gap-2">
                                    <input v-model="storefrontForm.theme_favorite_button_color" type="color" class="h-9 w-9 cursor-pointer rounded border border-slate-300 bg-white p-0.5">
                                    <input v-model="storefrontForm.theme_favorite_button_color" type="text" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-1 text-xs" placeholder="#FF3B30">
                                </div>
                            </label>
                            <label class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700">
                                <span class="font-semibold uppercase tracking-wide text-slate-500">Botão de adicionar</span>
                                <div class="mt-1 flex items-center gap-2">
                                    <input v-model="storefrontForm.theme_add_button_color" type="color" class="h-9 w-9 cursor-pointer rounded border border-slate-300 bg-white p-0.5">
                                    <input v-model="storefrontForm.theme_add_button_color" type="text" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-1 text-xs" placeholder="#F59E0B">
                                </div>
                            </label>
                        </div>
                    </section>
                </template>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white" :disabled="storefrontForm.processing">
                        {{ storefrontForm.processing ? 'Salvando...' : 'Salvar vitrine' }}
                    </button>
                </div>
            </form>

            <form v-else-if="activeTab === 'horario'" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="submitStorefront">
                <section class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Horário de funcionamento</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-emerald-200 hover:text-emerald-700" @click="applyBusinessHoursPreset('business')">
                                Seg-Sex
                            </button>
                            <button type="button" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-emerald-200 hover:text-emerald-700" @click="applyBusinessHoursPreset('everyday')">
                                Todos os dias
                            </button>
                            <button type="button" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-emerald-200 hover:text-emerald-700" @click="applyBusinessHoursPreset('always')">
                                24h
                            </button>
                            <button type="button" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:border-emerald-200 hover:text-emerald-700" @click="applyBusinessHoursPreset('close-weekend')">
                                Fechar fim de semana
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-2 md:grid-cols-2">
                        <article
                            v-for="day in WEEK_DAYS"
                            :key="`hour-${day.key}`"
                            class="cursor-pointer rounded-xl border border-slate-200 bg-white p-3 transition hover:border-emerald-200"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ day.label }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{ storefrontForm.business_hours[day.key].enabled ? 'Dia com atendimento ativo' : 'Dia sem atendimento' }}
                                    </p>
                                </div>
                                <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-600">
                                    <input v-model="storefrontForm.business_hours[day.key].enabled" type="checkbox" class="rounded border-slate-300">
                                    Aberto
                                </label>
                            </div>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <label class="space-y-1">
                                    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Abre</span>
                                    <input
                                        v-model="storefrontForm.business_hours[day.key].open"
                                        type="time"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                                        :class="storefrontForm.business_hours[day.key].enabled ? 'bg-white text-slate-700' : 'cursor-not-allowed bg-slate-100 text-slate-400'"
                                        :disabled="!storefrontForm.business_hours[day.key].enabled"
                                    >
                                </label>
                                <label class="space-y-1">
                                    <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Fecha</span>
                                    <input
                                        v-model="storefrontForm.business_hours[day.key].close"
                                        type="time"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs"
                                        :class="storefrontForm.business_hours[day.key].enabled ? 'bg-white text-slate-700' : 'cursor-not-allowed bg-slate-100 text-slate-400'"
                                        :disabled="!storefrontForm.business_hours[day.key].enabled"
                                    >
                                </label>
                            </div>
                        </article>
                    </div>
                </section>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white" :disabled="storefrontForm.processing">
                        {{ storefrontForm.processing ? 'Salvando...' : 'Salvar horário' }}
                    </button>
                </div>
            </form>
            <form v-else-if="activeTab === 'frete' && props.supportsShipping" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm" @submit.prevent="submitShipping">
                <div class="grid gap-3 xl:grid-cols-[320px_minmax(0,1fr)]">
                    <div class="space-y-2">
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">
                            <span>Retirar na loja</span>
                            <input v-model="shippingForm.shipping_pickup_enabled" type="checkbox" class="rounded border-slate-300">
                        </label>
                        <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">
                            <span>Entrega</span>
                            <input v-model="shippingForm.shipping_delivery_enabled" type="checkbox" class="rounded border-slate-300">
                        </label>
                    </div>

                    <section class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Todo o Brasil</p>
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    Configure a taxa padrão para cobertura nacional.
                                </p>
                            </div>
                            <input v-model="shippingForm.shipping_nationwide_enabled" type="checkbox" class="mt-0.5 rounded border-slate-300">
                        </div>
                        <div class="mt-2 grid gap-2 md:grid-cols-3">
                            <label class="space-y-1">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Taxa de entrega</span>
                                <BrlMoneyInput
                                    v-model="shippingForm.shipping_nationwide_fee"
                                    :allow-empty="false"
                                    class="rounded-lg border border-slate-200 px-2.5 py-2 text-xs"
                                    placeholder="R$ 0,00"
                                    :disabled="!shippingForm.shipping_nationwide_enabled"
                                />
                            </label>
                            <label class="space-y-1">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Grátis acima de</span>
                                <BrlMoneyInput
                                    v-model="shippingForm.shipping_nationwide_free_over"
                                    class="rounded-lg border border-slate-200 px-2.5 py-2 text-xs"
                                    placeholder="R$ 0,00"
                                    :disabled="!shippingForm.shipping_nationwide_enabled"
                                />
                            </label>
                            <label class="space-y-1">
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Prazo padrão (dias)</span>
                                <input
                                    v-model="shippingForm.shipping_estimated_days"
                                    type="number"
                                    min="1"
                                    max="60"
                                    class="w-full rounded-lg border border-slate-200 px-2.5 py-2 text-xs"
                                    placeholder="Ex.: 2"
                                >
                            </label>
                        </div>
                    </section>
                </div>

                <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                    {{ shippingCoverageStatus }}
                </p>

                <p v-if="shippingStatesError" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                    {{ shippingStatesError }}
                </p>

                <div v-if="shippingCityTableEnabled" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tabelas de cobertura de entrega</p>
                    <TableViewToggle />
                </div>

                <section v-if="shippingCityTableEnabled" class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Todo o estado</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                Liste todas as UFs e ative somente as que terão entrega.
                            </p>
                        </div>
                    </div>

                    <div class="mt-2 grid items-stretch gap-2 md:grid-cols-[minmax(0,1fr)_180px_auto]">
                        <div class="veshop-search-shell flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5">
                            <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                            <input
                                v-model="shippingStateFilters.search"
                                type="text"
                                placeholder="Buscar estado..."
                                class="veshop-search-input w-full bg-transparent text-xs text-slate-700 outline-none"
                            >
                        </div>
                        <UiSelect
                            v-model="shippingStateFilters.active"
                            :options="shippingStateFilterActiveOptions"
                            button-class="h-9 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs"
                        />
                        <button
                            type="button"
                            class="h-9 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                            @click="clearShippingStateFilters()"
                        >
                            Limpar filtros
                        </button>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] font-medium text-slate-500">
                        <span>{{ shippingStateStats.filtered }} de {{ shippingStateStats.total }} estado(s)</span>
                        <span>Ativos: {{ shippingStateStats.activeCount }}</span>
                    </div>

                    <div class="mt-2 rounded-xl border border-slate-200 bg-white">
                        <div class="overflow-x-auto">
                            <table class="min-w-[860px] w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">UF</th>
                                        <th class="px-3 py-2">Estado</th>
                                        <th class="px-3 py-2">Taxa</th>
                                        <th class="px-3 py-2">Grátis acima</th>
                                        <th class="px-3 py-2 text-center">Ativo</th>
                                    </tr>
                                </thead>
                                <tbody v-if="filteredShippingStateRateEntries.length" class="divide-y divide-slate-100 bg-white">
                                    <tr
                                        v-for="entry in filteredShippingStateRateEntries"
                                        :key="`state-rate-${entry.row.state || entry.label}`"
                                        class="align-top"
                                    >
                                        <td class="px-3 py-2 font-semibold text-slate-700">
                                            {{ entry.row.state }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{ entry.stateName }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <BrlMoneyInput
                                                v-model="entry.row.fee"
                                                class="rounded-lg border border-slate-200 px-2.5 py-2 text-xs"
                                                placeholder="Taxa (R$)"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <BrlMoneyInput
                                                v-model="entry.row.free_over"
                                                class="rounded-lg border border-slate-200 px-2.5 py-2 text-xs"
                                                placeholder="Grátis acima"
                                            />
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input v-model="entry.row.active" type="checkbox" class="rounded border-slate-300">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="!filteredShippingStateRateEntries.length" class="px-4 py-6 text-center text-xs text-slate-500">
                            Nenhum estado encontrado para os filtros aplicados.
                        </div>
                    </div>
                </section>

                <section v-if="shippingCityTableEnabled" class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tabela de frete por cidade</p>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                Mantenha a lista de cidades atendidas e filtre rapidamente.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                            @click="addShippingCityRate()"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Adicionar cidade
                        </button>
                    </div>

                    <div class="mt-2 grid items-stretch gap-2 lg:grid-cols-[minmax(0,1fr)_160px_150px_150px_auto]">
                        <div class="veshop-search-shell flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5">
                            <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                            <input
                                v-model="shippingCityFilters.search"
                                type="text"
                                placeholder="Buscar cidade..."
                                class="veshop-search-input w-full bg-transparent text-xs text-slate-700 outline-none"
                            >
                        </div>
                        <UiSelect
                            v-model="shippingCityFilters.state"
                            :options="shippingCityFilterStateOptions"
                            button-class="h-9 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs"
                        />
                        <UiSelect
                            v-model="shippingCityFilters.active"
                            :options="shippingCityFilterActiveOptions"
                            button-class="h-9 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs"
                        />
                        <UiSelect
                            v-model="shippingCityFilters.free"
                            :options="shippingCityFilterFreeOptions"
                            button-class="h-9 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs"
                        />
                        <button
                            type="button"
                            class="h-9 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                            @click="clearShippingCityFilters()"
                        >
                            Limpar filtros
                        </button>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] font-medium text-slate-500">
                        <span>{{ shippingCityStats.filtered }} de {{ shippingCityStats.total }} cidade(s)</span>
                        <span>Ativas: {{ shippingCityStats.activeCount }} • Grátis: {{ shippingCityStats.freeCount }}</span>
                    </div>

                    <div class="mt-2 rounded-xl border border-slate-200 bg-white">
                        <div class="overflow-x-auto">
                            <table class="min-w-[1180px] w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">UF</th>
                                        <th class="px-3 py-2">Cidade</th>
                                        <th class="px-3 py-2">Taxa</th>
                                        <th class="px-3 py-2">Grátis acima</th>
                                        <th class="px-3 py-2">Prazo</th>
                                        <th class="px-3 py-2 text-center">Grátis</th>
                                        <th class="px-3 py-2 text-center">Ativa</th>
                                        <th class="px-3 py-2 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody v-if="filteredShippingCityRateEntries.length" class="divide-y divide-slate-100 bg-white">
                                    <tr
                                        v-for="entry in filteredShippingCityRateEntries"
                                        :key="`city-rate-${entry.index}`"
                                        class="align-top"
                                    >
                                        <td class="px-3 py-2">
                                            <UiSelect
                                                v-model="entry.rate.state"
                                                :options="[{ value: '', label: shippingStatesLoading ? 'Carregando estados...' : 'Selecione a UF' }, ...shippingStateOptions]"
                                                button-class="rounded-lg border border-slate-200 px-2.5 py-2 text-xs"
                                                :disabled="shippingStatesLoading"
                                                @change="onShippingCityRateStateChange(entry.index, $event)"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="space-y-1.5">
                                                <div class="relative">
                                                    <input
                                                        v-model="entry.rate.city_search"
                                                        type="text"
                                                        class="w-full rounded-lg border border-slate-200 px-2.5 py-2 pr-9 text-xs"
                                                        placeholder="Digite para buscar cidade"
                                                        :disabled="!entry.rate.state"
                                                        @focus="openCityPicker(entry.index, entry.rate)"
                                                        @blur="scheduleCloseCityPicker()"
                                                        @input="onShippingCityRateSearchInput(entry.index, $event?.target?.value)"
                                                    >
                                                    <button
                                                        type="button"
                                                        class="absolute inset-y-0 right-1 my-auto inline-flex h-6 w-6 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                                        :disabled="!entry.rate.state || isCityLoadingForRate(entry.rate)"
                                                        @mousedown.prevent
                                                        @click="toggleCityPicker(entry.index, entry.rate)"
                                                    >
                                                        <ChevronDown class="h-3.5 w-3.5" />
                                                    </button>

                                                    <div
                                                        v-if="openCityPickerIndex === entry.index && entry.rate.state"
                                                        class="absolute left-0 right-0 z-20 mt-1 max-h-52 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-[0_16px_30px_-20px_rgba(15,23,42,0.55)]"
                                                        @mousedown.prevent
                                                    >
                                                        <p v-if="isCityLoadingForRate(entry.rate)" class="px-2.5 py-2 text-[11px] font-medium text-slate-500">
                                                            Carregando cidades...
                                                        </p>
                                                        <template v-else>
                                                            <button
                                                                v-for="option in citySearchOptionsForRate(entry.rate)"
                                                                :key="`city-option-${entry.index}-${option.value}`"
                                                                type="button"
                                                                class="flex w-full items-center justify-start rounded-lg px-2.5 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                                @click="selectCityFromPicker(entry.index, option.value)"
                                                            >
                                                                {{ option.label }}
                                                            </button>
                                                            <p
                                                                v-if="!citySearchOptionsForRate(entry.rate).length"
                                                                class="px-2.5 py-2 text-[11px] font-medium text-slate-500"
                                                            >
                                                                Nenhuma cidade encontrada.
                                                            </p>
                                                        </template>
                                                    </div>
                                                </div>
                                                <p v-if="cityErrorForRate(entry.rate)" class="text-[11px] font-semibold text-amber-700">
                                                    {{ cityErrorForRate(entry.rate) }}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <BrlMoneyInput
                                                v-model="entry.rate.fee"
                                                class="rounded-lg border border-slate-200 px-2.5 py-2 text-xs"
                                                placeholder="Taxa (R$)"
                                                :disabled="entry.rate.is_free"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <BrlMoneyInput
                                                v-model="entry.rate.free_over"
                                                class="rounded-lg border border-slate-200 px-2.5 py-2 text-xs"
                                                placeholder="Grátis acima"
                                                :disabled="entry.rate.is_free"
                                            />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-model="entry.rate.estimated_days"
                                                type="number"
                                                min="1"
                                                max="60"
                                                class="w-full rounded-lg border border-slate-200 px-2.5 py-2 text-xs"
                                                placeholder="Dias"
                                            >
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input v-model="entry.rate.is_free" type="checkbox" class="rounded border-slate-300">
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input v-model="entry.rate.active" type="checkbox" class="rounded border-slate-300">
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button
                                                type="button"
                                                class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-rose-700 hover:bg-rose-50"
                                                @click="removeShippingCityRate(entry.index)"
                                            >
                                                Remover
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="!filteredShippingCityRateEntries.length" class="px-4 py-6 text-center text-xs text-slate-500">
                            {{ shippingCityStats.total > 0 ? 'Nenhuma cidade encontrada para os filtros aplicados.' : 'Nenhuma cidade cadastrada. Clique em \"Adicionar cidade\".' }}
                        </div>
                    </div>
                </section>

                <p v-else-if="shippingForm.shipping_delivery_enabled" class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700">
                    A tabela por cidade é opcional quando a entrega nacional ou estadual estiver ativa.
                </p>

                <p v-if="!shippingForm.shipping_pickup_enabled && !shippingForm.shipping_delivery_enabled" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700">
                    Atenção: sem retirada e sem entrega ativas, o checkout da loja ficará indisponível.
                </p>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white" :disabled="shippingForm.processing">
                        {{ shippingForm.processing ? 'Salvando...' : 'Salvar frete' }}
                    </button>
                </div>
            </form>
        </section>
    </AuthenticatedLayout>
</template>
<style scoped>
.storefront-tabs-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.storefront-tabs-shell::-webkit-scrollbar {
    height: 6px;
}

.storefront-tabs-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

.storefront-tabs-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #ffffff;
    padding: 0.3rem;
}

.storefront-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid transparent;
    border-radius: 0.72rem;
    min-height: 38px;
    padding: 0.6rem 0.95rem;
    color: #334155;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.storefront-tab:hover {
    background: #f8fafc;
    color: #0f172a;
}

.storefront-tab.is-active {
    border-color: var(--storefront-tab-active-border);
    background: var(--storefront-tab-active);
    color: #ffffff;
}

.storefront-mini-tabs-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.storefront-mini-tabs-shell::-webkit-scrollbar {
    height: 6px;
}

.storefront-mini-tabs-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.35);
}

.storefront-mini-tabs-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.4rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.9rem;
    background: #f8fafc;
    padding: 0.25rem;
}

.storefront-mini-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid transparent;
    border-radius: 0.62rem;
    min-height: 34px;
    padding: 0.5rem 0.8rem;
    color: #475569;
    font-size: 0.76rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.storefront-mini-tab:hover {
    background: #ffffff;
    color: #1e293b;
}

.storefront-mini-tab.is-active {
    border-color: #0f172a;
    background: #0f172a;
    color: #ffffff;
}
</style>
