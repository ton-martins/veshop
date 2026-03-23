<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    CalendarCheck2,
    ChevronRight,
    CircleHelp,
    Clock3,
    Heart,
    House,
    Info,
    MessageCircle,
    Search,
    Settings,
    ShieldCheck,
    Star,
    UserCircle2,
    Wallet,
    X,
} from 'lucide-vue-next';
import { useBranding } from '@/branding';

const props = defineProps({
    contractor: { type: Object, required: true },
    storefront: { type: Object, default: () => ({}) },
    store_availability: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    shop_auth: {
        type: Object,
        default: () => ({ authenticated: false, customer: null, email_verified: false, requires_email_verification: false }),
    },
    shop_account: { type: Object, default: () => ({ orders: [] }) },
    bookings: { type: Array, default: () => [] },
});

const page = usePage();
const { normalizeHex, primaryColor, withAlpha } = useBranding();

const flashStatus = computed(() => String(page.props?.flash?.status ?? '').trim());
const bookingWhatsappUrl = computed(() => String(page.props?.flash?.service_booking_whatsapp_url ?? '').trim());
const bookingWhatsappMessage = computed(() => String(page.props?.flash?.service_booking_whatsapp_message ?? '').trim());
const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Veshop'));
const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const registerUrl = computed(() => `/shop/${storeSlug.value}/cadastro`);
const accountUrl = computed(() => `/shop/${storeSlug.value}/conta`);

const isAuthenticated = computed(() => Boolean(props.shop_auth?.authenticated));
const requiresVerification = computed(() => Boolean(props.shop_auth?.requires_email_verification));
const isEmailVerified = computed(() => Boolean(props.shop_auth?.email_verified));
const customer = computed(() => props.shop_auth?.customer ?? null);

const accent = computed(() => normalizeHex(props.contractor?.primary_color || '', primaryColor.value));
const pageStyles = computed(() => ({
    '--service-accent': accent.value,
    '--service-accent-soft': withAlpha(accent.value, 0.14),
    '--service-accent-strong': withAlpha(accent.value, 0.9),
    '--service-border': withAlpha(accent.value, 0.24),
    '--service-hero-gradient': `linear-gradient(135deg, ${withAlpha(accent.value, 0.16)} 0%, rgba(255,255,255,0.98) 50%, ${withAlpha(accent.value, 0.07)} 100%)`,
}));

const storefrontConfig = computed(() => {
    const raw = props.storefront ?? {};
    const hero = raw.hero ?? {};
    const promotions = raw.promotions ?? {};
    const blocks = raw.blocks ?? {};

    return {
        blocks: {
            hero: Boolean(blocks.hero ?? true),
            promotions: Boolean(blocks.promotions ?? true),
            categories: Boolean(blocks.categories ?? true),
            catalog: Boolean(blocks.catalog ?? true),
        },
        hero_title: String(hero.title ?? '').trim(),
        hero_subtitle: String(hero.subtitle ?? '').trim(),
        hero_cta: String(hero.cta_label ?? '').trim(),
        promotions_title: String(promotions.title ?? '').trim(),
        promotions_subtitle: String(promotions.subtitle ?? '').trim(),
        promotion_service_ids: Array.isArray(promotions.service_ids)
            ? Array.from(new Set(
                promotions.service_ids
                    .map((id) => Number(id))
                    .filter((id) => Number.isFinite(id) && id > 0),
            ))
            : [],
    };
});

const storefrontBlocks = computed(() => storefrontConfig.value.blocks);
const heroTitle = computed(() =>
    storefrontConfig.value.hero_title || `Agende serviços com ${storeName.value}`,
);
const heroSubtitle = computed(() =>
    storefrontConfig.value.hero_subtitle || 'Escolha o serviço, selecione o horário e confirme em poucos passos.',
);
const heroCtaLabel = computed(() =>
    storefrontConfig.value.hero_cta || 'Agendar serviço',
);

const promotionsTitle = computed(() =>
    storefrontConfig.value.promotions_title || 'Serviços em destaque',
);
const promotionsSubtitle = computed(() =>
    storefrontConfig.value.promotions_subtitle || 'Seleção recomendada para agilizar seu atendimento.',
);

const storeAvailability = computed(() => {
    const raw = props.store_availability ?? {};
    const storeOnline = Boolean(raw.store_online ?? true);
    const nextOpen = String(raw.next_open_label ?? '').trim();
    const fallbackMessage = storeOnline
        ? (nextOpen ? `Loja fechada no momento. ${nextOpen}.` : 'Loja fechada no momento.')
        : 'Agendamentos temporariamente indisponíveis.';

    return {
        store_online: storeOnline,
        can_book: Boolean(raw.can_book ?? storeOnline),
        is_open_now: Boolean(raw.is_open_now ?? true),
        status_label: String(raw.status_label ?? '').trim(),
        message: String(raw.message ?? '').trim() || fallbackMessage,
    };
});

const promotionServices = computed(() => {
    const available = Array.isArray(props.services) ? props.services : [];
    if (!available.length) return [];

    const promotionIds = storefrontConfig.value.promotion_service_ids;
    if (!promotionIds.length) {
        return available.slice(0, 6);
    }

    const selected = available.filter((service) => promotionIds.includes(Number(service.id)));
    return (selected.length ? selected : available).slice(0, 6);
});

const storeLogo = computed(() =>
    String(props.contractor?.avatar_url || props.contractor?.logo_url || '').trim(),
);

const storeInitials = computed(() => {
    const safe = String(storeName.value ?? '').trim();
    if (!safe) return 'SV';
    const parts = safe.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) ?? 'S';
    const second = parts.length > 1 ? parts[parts.length - 1]?.charAt(0) ?? '' : '';
    return `${first}${second}`.toUpperCase();
});

const storeIconStyle = computed(() => {
    if (storeLogo.value) return null;

    return {
        background: 'var(--service-accent-soft)',
        color: 'var(--service-accent-strong)',
    };
});

const tabs = [
    { key: 'home', label: 'Início', icon: House },
    { key: 'bookings', label: 'Agendas', icon: CalendarCheck2 },
    { key: 'saved', label: 'Salvos', icon: Heart },
    { key: 'messages', label: 'Mensagens', icon: MessageCircle },
    { key: 'account', label: 'Conta', icon: UserCircle2 },
];

const activeTab = ref('home');
const search = ref('');
const selectedCategory = ref('all');
const favoriteIds = ref([]);

const categoryTabs = computed(() => [
    { id: 'all', name: 'Todos', services_count: props.services.length },
    ...(props.categories ?? []).map((category) => ({
        id: String(category.id),
        name: category.name,
        services_count: Number(category.services_count ?? 0),
    })),
]);

const normalizeText = (value) =>
    String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

const servicesFiltered = computed(() => {
    const term = normalizeText(search.value);

    return (props.services ?? []).filter((service) => {
        const categoryMatch = selectedCategory.value === 'all'
            ? true
            : String(service.service_category_id ?? '') === String(selectedCategory.value);

        if (!categoryMatch) return false;
        if (!term) return true;

        const haystack = [
            service.name,
            service.code,
            service.description,
            service.category_name,
        ].map(normalizeText).join(' ');

        return haystack.includes(term);
    });
});

const savedServices = computed(() =>
    (props.services ?? []).filter((service) => favoriteIds.value.includes(Number(service.id))),
);

const desktopServices = computed(() => {
    if (activeTab.value !== 'saved') {
        return servicesFiltered.value;
    }

    return servicesFiltered.value.filter((service) => favoriteIds.value.includes(Number(service.id)));
});

const bookings = computed(() => (Array.isArray(props.bookings) ? props.bookings : []));

const statsCards = computed(() => ([
    { key: 'services', label: 'Serviços ativos', value: String(props.services?.length ?? 0) },
    { key: 'categories', label: 'Categorias', value: String(Math.max(0, categoryTabs.value.length - 1)) },
    { key: 'favorites', label: 'Favoritos', value: String(savedServices.value.length) },
]));

const bookingModalOpen = ref(false);
const bookingTarget = ref(null);
const bookingDetailsOpen = ref(false);
const bookingDetails = ref(null);

const bookingForm = useForm({
    service_catalog_id: '',
    scheduled_for: '',
    notes: '',
});

const nowLocalIso = () => {
    const date = new Date(Date.now() + 60 * 60 * 1000);
    date.setSeconds(0, 0);

    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    const hh = String(date.getHours()).padStart(2, '0');
    const mi = String(date.getMinutes()).padStart(2, '0');

    return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
};

const openBookingModal = (service) => {
    bookingTarget.value = service;
    bookingForm.reset();
    bookingForm.clearErrors();
    bookingForm.service_catalog_id = String(service.id);
    bookingForm.scheduled_for = nowLocalIso();
    bookingForm.notes = '';

    if (!storeAvailability.value.can_book) {
        bookingForm.setError('booking', storeAvailability.value.message || 'Agendamentos indisponíveis no momento.');
    }

    bookingModalOpen.value = true;
};

const closeBookingModal = () => {
    bookingModalOpen.value = false;
    bookingTarget.value = null;
    bookingForm.clearErrors();
};

const submitBooking = () => {
    if (!bookingForm.service_catalog_id) return;
    if (!storeAvailability.value.can_book) {
        bookingForm.setError('booking', storeAvailability.value.message || 'Agendamentos indisponíveis no momento.');
        return;
    }

    bookingForm.post(route('shop.services.book', { slug: storeSlug.value }), {
        preserveScroll: true,
        onSuccess: () => {
            closeBookingModal();
            activeTab.value = 'bookings';
        },
    });
};

const openBookingDetails = (booking) => {
    bookingDetails.value = booking;
    bookingDetailsOpen.value = true;
};

const closeBookingDetails = () => {
    bookingDetailsOpen.value = false;
    bookingDetails.value = null;
};

const toggleFavorite = (serviceId) => {
    const id = Number(serviceId);
    if (!Number.isFinite(id) || id <= 0) return;

    if (favoriteIds.value.includes(id)) {
        favoriteIds.value = favoriteIds.value.filter((item) => item !== id);
        return;
    }

    favoriteIds.value = [...favoriteIds.value, id];
};

const openAccountOrLogin = () => {
    if (isAuthenticated.value) {
        activeTab.value = 'account';
        return;
    }

    if (typeof window !== 'undefined') {
        window.location.href = loginUrl.value;
    }
};

const openDesktopAccount = () => {
    if (typeof window === 'undefined') return;
    window.location.href = isAuthenticated.value ? accountUrl.value : loginUrl.value;
};

const resolveInitials = (label) => {
    const text = String(label ?? '').trim();
    if (!text) return 'SV';

    const parts = text.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) ?? 'S';
    const second = parts.length > 1 ? parts[1]?.charAt(0) ?? '' : '';

    return `${first}${second}`.toUpperCase();
};

const accountMenu = [
    { key: 'profile', label: 'Meu perfil', icon: UserCircle2 },
    { key: 'bookings', label: 'Meus agendamentos', icon: CalendarCheck2 },
    { key: 'wallet', label: 'Carteira', icon: Wallet },
    { key: 'settings', label: 'Configurações', icon: Settings },
    { key: 'privacy', label: 'Política de privacidade', icon: ShieldCheck },
    { key: 'about', label: 'Sobre o app', icon: Info },
    { key: 'faq', label: 'FAQ', icon: CircleHelp },
];

watch(
    () => page.url,
    (next) => {
        const query = String(next ?? '').split('?')[1] ?? '';
        const params = new URLSearchParams(query);
        if (params.get('conta') === '1') {
            activeTab.value = 'account';
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-900" :style="pageStyles">
        <Head :title="`${storeName} | Agendamentos online`" />

        <header class="sticky top-0 z-40 border-b border-white/70 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-slate-100" :style="storeIconStyle">
                        <img v-if="storeLogo" :src="storeLogo" :alt="storeName" class="h-full w-full object-cover">
                        <span v-else class="text-xs font-semibold">{{ storeInitials }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ storeName }}</p>
                        <p class="truncate text-xs text-slate-500">Loja pública de serviços</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="hidden items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition sm:inline-flex"
                        :class="activeTab === 'saved' ? 'border-transparent text-white shadow-inner shadow-black/10' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                        :style="activeTab === 'saved' ? { background: 'var(--service-accent-strong)' } : null"
                        @click="isAuthenticated ? activeTab = (activeTab === 'saved' ? 'home' : 'saved') : openAccountOrLogin()"
                    >
                        <Heart class="h-3.5 w-3.5" />
                        {{ activeTab === 'saved' ? 'Catálogo' : `Salvos (${savedServices.length})` }}
                    </button>
                    <button type="button" class="hidden items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:inline-flex" @click="openDesktopAccount">
                        <UserCircle2 class="h-3.5 w-3.5" />
                        {{ isAuthenticated ? 'Minha conta' : 'Entrar' }}
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl px-4 pb-24 pt-5 sm:px-6 lg:px-8 lg:pb-10">

            <div v-if="flashStatus" class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                {{ flashStatus }}
            </div>
            <div v-if="!storeAvailability.can_book || !storeAvailability.is_open_now" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <p class="font-semibold">{{ storeAvailability.status_label || 'Horário de atendimento' }}</p>
                <p class="mt-1">{{ storeAvailability.message }}</p>
            </div>

            <div v-if="bookingWhatsappUrl" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <p>Se preferir, envie a confirmação do agendamento também pelo WhatsApp.</p>
                <a
                    :href="bookingWhatsappUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-2 inline-flex rounded-lg bg-amber-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-amber-700"
                >
                    Enviar no WhatsApp
                </a>
                <p v-if="bookingWhatsappMessage" class="mt-1 text-[11px] text-amber-700">
                    Mensagem pronta: {{ bookingWhatsappMessage }}
                </p>
            </div>

            <section class="relative mt-5 hidden overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm md:block sm:p-6">
                <div class="pointer-events-none absolute inset-0 opacity-90" style="background: var(--service-hero-gradient)"></div>
                <div class="relative grid gap-3 md:grid-cols-[1fr,220px] md:items-center">
                    <div class="space-y-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ heroTitle }}</h1>
                        <p class="max-w-2xl text-sm text-slate-700">{{ heroSubtitle }}</p>
                        <label class="veshop-search-shell flex min-w-0 flex-1 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                            <Search class="veshop-search-icon h-4 w-4 text-slate-400" />
                            <input v-model="search" type="search" class="veshop-search-input text-sm text-slate-700" placeholder="Buscar serviço, código ou categoria" />
                        </label>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/90 p-3 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Resumo</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">{{ desktopServices.length }} serviço(s)</p>
                        <p class="mt-1 text-xs text-slate-500">Favoritos: {{ savedServices.length }}</p>
                        <button
                            type="button"
                            class="mt-2 inline-flex w-full items-center justify-center rounded-lg px-3 py-2 text-xs font-semibold text-white shadow-sm"
                            style="background: var(--service-accent-strong)"
                            @click="servicesFiltered.length ? openBookingModal(servicesFiltered[0]) : null"
                        >
                            {{ heroCtaLabel }}
                        </button>
                    </div>
                </div>
            </section>

            <section class="mt-6 hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm md:block sm:p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-slate-900">Categorias</h2>
                    <p class="text-xs text-slate-500">{{ desktopServices.length }} serviço(s)</p>
                </div>
                <div class="service-chip-scroll flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="category in categoryTabs"
                        :key="`desktop-category-${category.id}`"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition"
                        :class="selectedCategory === category.id ? 'text-white shadow-sm border-transparent' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        :style="selectedCategory === category.id ? { background: 'var(--service-accent-strong)' } : null"
                        @click="selectedCategory = category.id"
                    >
                        {{ category.name }}
                        <span class="inline-flex min-w-[20px] items-center justify-center rounded-full border px-1.5 py-0.5 text-[10px] font-bold" :class="selectedCategory === category.id ? 'border-white/35 bg-white/20 text-white' : 'border-slate-200 bg-slate-100 text-slate-600'">{{ category.services_count }}</span>
                    </button>
                </div>
            </section>

            <section v-if="storefrontBlocks.promotions && promotionServices.length" class="mt-6 hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm md:block sm:p-5">
                <div class="mb-3">
                    <h2 class="text-sm font-semibold text-slate-900">{{ promotionsTitle }}</h2>
                    <p class="text-xs text-slate-500">{{ promotionsSubtitle }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-3">
                    <article
                        v-for="service in promotionServices"
                        :key="`promo-service-${service.id}`"
                        class="group cursor-pointer overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        @click="openBookingModal(service)"
                    >
                        <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
                            <img v-if="service.image_url" :src="service.image_url" :alt="service.name" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                            <div v-else class="grid h-full w-full place-items-center bg-gradient-to-br from-slate-200 to-slate-300 text-sm font-bold text-slate-600">
                                {{ resolveInitials(service.name) }}
                            </div>
                            <span class="absolute left-2 top-2 rounded-full bg-white/95 px-2 py-1 text-[10px] font-semibold text-slate-700">Destaque</span>
                        </div>
                        <div class="space-y-1.5 p-3">
                            <h3 class="min-h-[2.4rem] text-sm font-semibold leading-tight text-slate-900">{{ service.name }}</h3>
                            <p class="text-xs text-slate-500">{{ service.category_name }}</p>
                            <p class="text-sm font-bold text-slate-900">{{ Number(service.base_price ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}</p>
                        </div>
                    </article>
                </div>
            </section>

            <section class="mt-6 hidden md:block">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ activeTab === 'saved' ? 'Serviços salvos' : 'Catálogo de serviços' }}</h2>
                        <p class="text-xs text-slate-500">Use os filtros para encontrar o atendimento ideal.</p>
                    </div>
                    <p class="text-xs text-slate-500">{{ desktopServices.length }} serviço(s)</p>
                </div>

                <div v-if="desktopServices.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <div
                        v-for="service in desktopServices"
                        :key="`desktop-service-${service.id}`"
                        class="group block cursor-pointer overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        @click="openBookingModal(service)"
                    >
                        <div class="relative aspect-square overflow-hidden bg-slate-100">
                            <img v-if="service.image_url" :src="service.image_url" :alt="service.name" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                            <div v-else class="grid h-full w-full place-items-center bg-gradient-to-br from-slate-200 to-slate-300 text-sm font-bold text-slate-600">
                                {{ resolveInitials(service.name) }}
                            </div>
                            <button
                                type="button"
                                class="absolute right-2 top-2 inline-flex h-8 w-8 items-center justify-center rounded-full border shadow-sm transition"
                                :class="favoriteIds.includes(Number(service.id)) ? 'border-rose-200 bg-rose-50 text-rose-600' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                                @click.stop.prevent="toggleFavorite(service.id)"
                            >
                                <Heart class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="space-y-2 p-3">
                            <h3 class="min-h-[2.5rem] text-sm font-semibold leading-tight text-slate-900">{{ service.name }}</h3>
                            <p class="text-[11px] text-slate-500">{{ service.category_name }}</p>
                            <div>
                                <p class="text-[11px] text-slate-500">Valor</p>
                                <p class="text-base font-bold text-slate-900">
                                    {{ Number(service.base_price ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
                                </p>
                            </div>
                            <p class="text-[11px] text-slate-500">{{ service.duration_label }}</p>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
                    Nenhum serviço encontrado para os filtros informados.
                </div>
            </section>

            <section class="space-y-3 pb-24 pt-3 md:hidden">
                <template v-if="activeTab === 'home'">
                    <div class="grid gap-4 xl:grid-cols-[18rem_minmax(0,1fr)]">
                        <aside class="space-y-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm xl:sticky xl:top-4 xl:self-start">
                            <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <Search class="h-4 w-4 text-slate-500" />
                                <input v-model="search" type="text" placeholder="Buscar serviço" class="w-full border-0 bg-transparent text-sm outline-none">
                            </div>

                            <div class="space-y-2">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Categorias</p>
                                <div class="max-h-64 space-y-1.5 overflow-y-auto pr-1">
                                    <button
                                        v-for="category in categoryTabs"
                                        :key="`sidebar-category-${category.id}`"
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-lg border px-2.5 py-2 text-left text-xs font-semibold"
                                        :class="selectedCategory === category.id ? 'border-transparent' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                        :style="selectedCategory === category.id ? { background: 'var(--service-accent-soft)', color: 'var(--service-accent-strong)' } : null"
                                        @click="selectedCategory = category.id"
                                    >
                                        <span class="truncate">{{ category.name }}</span>
                                        <span class="rounded-full bg-white/70 px-1.5 py-0.5 text-[10px]">{{ category.services_count }}</span>
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-2 pt-1">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-xl px-3 py-2 text-xs font-semibold text-white"
                                    :style="{ background: 'var(--service-accent-strong)' }"
                                    @click="servicesFiltered.length ? openBookingModal(servicesFiltered[0]) : null"
                                >
                                    {{ heroCtaLabel }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click="isAuthenticated ? activeTab = 'saved' : openAccountOrLogin()"
                                >
                                    Ver salvos
                                </button>
                            </div>
                        </aside>

                        <div class="space-y-4">
                            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5" :style="{ backgroundImage: 'var(--service-hero-gradient)' }">
                                <div class="grid gap-4 lg:grid-cols-[1.15fr,0.85fr] lg:items-center">
                                    <div class="space-y-2">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Agendamento online</p>
                                        <h2 class="text-xl font-bold leading-tight text-slate-900 md:text-2xl">{{ heroTitle }}</h2>
                                        <p class="max-w-2xl text-sm text-slate-600">{{ heroSubtitle }}</p>
                                    </div>
                                    <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-1">
                                        <article v-for="stat in statsCards" :key="`service-home-stat-${stat.key}`" class="rounded-lg border border-slate-200 bg-white/90 p-3">
                                            <p class="text-[11px] text-slate-500">{{ stat.label }}</p>
                                            <p class="mt-1 text-lg font-bold text-slate-900">{{ stat.value }}</p>
                                        </article>
                                    </div>
                                </div>
                            </article>

                            <section v-if="storefrontBlocks.promotions && promotionServices.length" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm md:p-4">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ promotionsTitle }}</p>
                                    <p class="text-[11px] text-slate-500">{{ promotionServices.length }} destaque(s)</p>
                                </div>
                                <div class="service-chip-scroll flex gap-2 overflow-x-auto pb-1">
                                    <button
                                        v-for="service in promotionServices"
                                        :key="`mobile-promo-${service.id}`"
                                        type="button"
                                        class="min-w-[180px] shrink-0 rounded-xl border border-slate-200 bg-white p-2 text-left shadow-sm"
                                        @click="openBookingModal(service)"
                                    >
                                        <p class="truncate text-xs font-semibold text-slate-900">{{ service.name }}</p>
                                        <p class="text-[11px] text-slate-500">{{ service.category_name }}</p>
                                        <p class="mt-1 text-xs font-bold text-slate-900">{{ Number(service.base_price ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}</p>
                                    </button>
                                </div>
                                <p class="mt-2 text-[11px] text-slate-500">{{ promotionsSubtitle }}</p>
                            </section>

                            <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm md:p-4">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Categorias</p>
                                    <p class="text-[11px] text-slate-500">{{ servicesFiltered.length }} serviço(s)</p>
                                </div>
                                <div class="service-chip-scroll flex gap-2 overflow-x-auto pb-1">
                                    <button
                                        v-for="category in categoryTabs"
                                        :key="`category-${category.id}`"
                                        type="button"
                                        class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold"
                                        :class="selectedCategory === category.id ? 'border-transparent' : 'border-slate-300 bg-white text-slate-700'"
                                        :style="selectedCategory === category.id ? { background: 'var(--service-accent-soft)', color: 'var(--service-accent-strong)' } : null"
                                        @click="selectedCategory = category.id"
                                    >
                                        {{ category.name }}
                                    </button>
                                </div>
                            </section>

                            <div class="grid gap-3 md:grid-cols-2">
                                <article
                                    v-for="service in servicesFiltered"
                                    :key="`service-${service.id}`"
                                    class="grid grid-cols-[5rem_minmax(0,1fr)] gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-[var(--service-border)]"
                                >
                                    <div class="grid h-20 w-20 place-items-center overflow-hidden rounded-xl bg-gradient-to-br from-slate-200 to-slate-300 text-sm font-bold text-slate-600">
                                        <img v-if="service.image_url" :src="service.image_url" :alt="service.name" class="h-full w-full object-cover">
                                        <span v-else>{{ resolveInitials(service.name) }}</span>
                                    </div>

                                    <div class="min-w-0">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <h2 class="text-sm font-bold text-slate-900">{{ service.name }}</h2>
                                                <p class="text-[11px] text-slate-500">{{ service.category_name }}</p>
                                            </div>
                                            <button
                                                type="button"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-full"
                                                :class="favoriteIds.includes(Number(service.id)) ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-400'"
                                                @click="toggleFavorite(service.id)"
                                            >
                                                <Heart class="h-4 w-4" />
                                            </button>
                                        </div>

                                        <p class="mt-1 text-[11px] text-slate-500">
                                            {{ service.description || 'Atendimento profissional com execução sob medida para sua necessidade.' }}
                                        </p>

                                        <div class="mt-1 inline-flex items-center gap-1 text-[11px] text-slate-500">
                                            <Star class="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                            <span>{{ Number(service.rating ?? 5).toFixed(1) }} ({{ service.reviews_label }})</span>
                                        </div>

                                        <div class="mt-2 flex items-center justify-between gap-2">
                                            <div>
                                                <p class="text-sm font-bold text-slate-900">
                                                    {{ Number(service.base_price ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}
                                                </p>
                                                <p class="text-[11px] text-slate-500">{{ service.duration_label }}</p>
                                            </div>
                                            <button type="button" class="rounded-xl px-3 py-2 text-xs font-semibold text-white" :style="{ background: 'var(--service-accent-strong)' }" @click="openBookingModal(service)">
                                                Agendar
                                            </button>
                                        </div>
                                    </div>
                                </article>

                                <div v-if="!servicesFiltered.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center text-xs text-slate-500 md:col-span-2">
                                    Nenhum serviço encontrado com os filtros atuais.
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else-if="activeTab === 'bookings'">
                    <div class="mb-1 flex items-center justify-between">
                        <h2 class="text-base font-bold">Meus agendamentos</h2>
                        <span class="rounded-full bg-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700">{{ bookings.length }}</span>
                    </div>

                    <div v-if="!isAuthenticated" class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center text-xs text-slate-500">
                        Faça login para acompanhar seus agendamentos.
                        <div class="mt-3">
                            <Link :href="loginUrl" class="inline-flex rounded-xl px-3 py-2 text-xs font-semibold text-white" :style="{ background: 'var(--service-accent-strong)' }">Entrar</Link>
                        </div>
                    </div>

                    <div v-else-if="!bookings.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center text-xs text-slate-500">
                        Você ainda não possui agendamentos.
                    </div>

                    <div v-else class="space-y-3">
                        <article v-for="booking in bookings" :key="`booking-${booking.id}`" class="rounded-2xl border border-slate-300 bg-white p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[11px] font-semibold text-slate-500">ID: #{{ booking.code }}</p>
                                <span class="rounded-full px-2 py-1 text-[10px] font-semibold" :class="booking.status?.tone">
                                    {{ booking.status?.label || 'Em andamento' }}
                                </span>
                            </div>

                            <h3 class="mt-2 text-sm font-bold text-slate-900">{{ booking.service_name }}</h3>
                            <p class="mt-1 inline-flex items-center gap-1 text-[11px] text-slate-500"><Clock3 class="h-3.5 w-3.5" />{{ booking.scheduled_label }}</p>

                            <div class="mt-3 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2">
                                <div>
                                    <p class="text-[11px] text-slate-500">Valor estimado</p>
                                    <p class="text-sm font-bold text-slate-900">{{ Number(booking.final_amount || booking.estimated_amount || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}</p>
                                </div>
                                <button type="button" class="rounded-xl px-3 py-2 text-xs font-semibold text-white" :style="{ background: 'var(--service-accent-strong)' }" @click="openBookingDetails(booking)">Ver detalhes</button>
                            </div>
                        </article>
                    </div>
                </template>

                <template v-else-if="activeTab === 'saved'">
                    <div class="mb-1 flex items-center justify-between">
                        <h2 class="text-base font-bold">Salvos</h2>
                        <span class="rounded-full bg-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700">{{ savedServices.length }}</span>
                    </div>

                    <div v-if="!savedServices.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center text-xs text-slate-500">
                        Nenhum serviço salvo ainda.
                    </div>

                    <div v-else class="space-y-3">
                        <article v-for="service in savedServices" :key="`saved-${service.id}`" class="rounded-2xl border border-slate-300 bg-white p-3">
                            <h3 class="text-sm font-bold text-slate-900">{{ service.name }}</h3>
                            <p class="text-[11px] text-slate-500">{{ service.category_name }}</p>
                            <div class="mt-2 flex items-center justify-between">
                                <p class="text-sm font-bold text-slate-900">{{ Number(service.base_price ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) }}</p>
                                <button type="button" class="rounded-xl px-3 py-2 text-xs font-semibold text-white" :style="{ background: 'var(--service-accent-strong)' }" @click="openBookingModal(service)">Agendar</button>
                            </div>
                        </article>
                    </div>
                </template>

                <template v-else-if="activeTab === 'messages'">
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center text-xs text-slate-500">
                        Central de mensagens disponível em breve.
                    </div>
                </template>

                <template v-else>
                    <div class="rounded-2xl border border-slate-300 bg-white p-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900">{{ customer?.name || 'Visitante' }}</p>
                                <p class="text-[11px] text-slate-500">{{ customer?.email || 'Conta pessoal' }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-semibold" :style="{ background: 'var(--service-accent-soft)', color: 'var(--service-accent-strong)' }">
                                <Wallet class="h-3.5 w-3.5" />Saldo R$ 0,00
                            </span>
                        </div>
                    </div>

                    <div v-if="!isAuthenticated" class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center text-xs text-slate-500">
                        Entre para acessar os dados da conta.
                        <div class="mt-3 flex items-center justify-center gap-2">
                            <Link :href="loginUrl" class="inline-flex rounded-xl px-3 py-2 text-xs font-semibold text-white" :style="{ background: 'var(--service-accent-strong)' }">Entrar</Link>
                            <Link :href="registerUrl" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700">Cadastrar</Link>
                        </div>
                    </div>

                    <div v-else class="overflow-hidden rounded-2xl border border-slate-300 bg-white">
                        <button
                            v-for="item in accountMenu"
                            :key="`account-item-${item.key}`"
                            type="button"
                            class="flex w-full items-center justify-between border-b border-slate-200 px-3 py-3 text-sm text-slate-800 last:border-b-0"
                            @click="item.key === 'bookings' ? activeTab = 'bookings' : null"
                        >
                            <span class="inline-flex items-center gap-2"><component :is="item.icon" class="h-4 w-4 text-slate-500" />{{ item.label }}</span>
                            <ChevronRight class="h-4 w-4 text-slate-400" />
                        </button>
                    </div>
                </template>
            </section>

            <nav class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-5 border-t border-slate-300 bg-white md:hidden">
                <button
                    v-for="tab in tabs"
                    :key="`tab-${tab.key}`"
                    type="button"
                    class="flex flex-col items-center gap-1 px-1 py-2 text-[11px] font-semibold"
                    :class="activeTab === tab.key ? 'text-slate-900' : 'text-slate-500'"
                    @click="activeTab = tab.key"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    <span>{{ tab.label }}</span>
                </button>
            </nav>
        </main>

        <div v-if="bookingModalOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 md:items-center" @click.self="closeBookingModal">
            <div class="w-full max-w-[560px] rounded-t-2xl bg-white p-4 md:rounded-2xl">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-base font-bold">Agendar serviço</h3>
                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100" @click="closeBookingModal"><X class="h-4 w-4" /></button>
                </div>

                <div v-if="bookingTarget" class="mb-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-sm font-semibold text-slate-900">{{ bookingTarget.name }}</p>
                    <p class="text-xs text-slate-500">{{ bookingTarget.category_name }}</p>
                </div>

                <div v-if="!isAuthenticated" class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-500">
                    Faça login para continuar o agendamento.
                    <div class="mt-3"><Link :href="loginUrl" class="inline-flex rounded-xl px-3 py-2 text-xs font-semibold text-white" :style="{ background: 'var(--service-accent-strong)' }">Entrar agora</Link></div>
                </div>

                <div v-else-if="requiresVerification && !isEmailVerified" class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-500">
                    Confirme seu e-mail para concluir o agendamento.
                </div>

                <div v-else-if="!storeAvailability.can_book" class="rounded-xl border border-dashed border-amber-300 bg-amber-50 p-4 text-center text-xs text-amber-700">
                    {{ storeAvailability.message }}
                </div>

                <form v-else class="space-y-3" @submit.prevent="submitBooking">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-slate-600">Data e hora</span>
                        <input v-model="bookingForm.scheduled_for" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                        <p v-if="bookingForm.errors.scheduled_for" class="mt-1 text-xs text-rose-600">{{ bookingForm.errors.scheduled_for }}</p>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-slate-600">Observações</span>
                        <textarea v-model="bookingForm.notes" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Detalhes importantes para o atendimento" />
                        <p v-if="bookingForm.errors.notes" class="mt-1 text-xs text-rose-600">{{ bookingForm.errors.notes }}</p>
                    </label>

                    <p v-if="bookingForm.errors.booking" class="text-xs text-rose-600">{{ bookingForm.errors.booking }}</p>

                    <button type="submit" class="w-full rounded-xl px-3 py-2 text-sm font-semibold text-white" :style="{ background: 'var(--service-accent-strong)' }" :disabled="bookingForm.processing">
                        {{ bookingForm.processing ? 'Enviando...' : 'Confirmar agendamento' }}
                    </button>
                </form>
            </div>
        </div>

        <div v-if="bookingDetailsOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 md:items-center" @click.self="closeBookingDetails">
            <div class="w-full max-w-[560px] rounded-t-2xl bg-white p-4 md:rounded-2xl">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-base font-bold">Detalhes do agendamento</h3>
                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100" @click="closeBookingDetails"><X class="h-4 w-4" /></button>
                </div>

                <div v-if="bookingDetails" class="space-y-3 text-sm text-slate-700">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold text-slate-500">Código</p>
                        <p class="font-semibold text-slate-900">#{{ bookingDetails.code }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Serviço</p>
                        <p class="font-semibold text-slate-900">{{ bookingDetails.service_name }}</p>
                        <p class="mt-2 text-xs text-slate-500">Data e hora</p>
                        <p class="font-semibold text-slate-900">{{ bookingDetails.scheduled_label }}</p>
                        <p class="mt-2 text-xs text-slate-500">Status</p>
                        <span class="rounded-full px-2 py-1 text-[10px] font-semibold" :class="bookingDetails.status?.tone">{{ bookingDetails.status?.label || 'Em andamento' }}</span>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Observações</p>
                        <p>{{ bookingDetails.notes || 'Sem observações.' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.service-chip-scroll {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.service-chip-scroll::-webkit-scrollbar {
    display: none;
}
</style>
