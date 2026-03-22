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

const isAuthenticated = computed(() => Boolean(props.shop_auth?.authenticated));
const requiresVerification = computed(() => Boolean(props.shop_auth?.requires_email_verification));
const isEmailVerified = computed(() => Boolean(props.shop_auth?.email_verified));
const customer = computed(() => props.shop_auth?.customer ?? null);

const accent = computed(() => normalizeHex(props.contractor?.primary_color || '', primaryColor.value));
const pageStyles = computed(() => ({
    '--service-accent': accent.value,
    '--service-accent-soft': withAlpha(accent.value, 0.18),
    '--service-accent-strong': withAlpha(accent.value, 0.86),
}));

const tabs = [
    { key: 'home', label: 'In\u00edcio', icon: House },
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

const bookings = computed(() => (Array.isArray(props.bookings) ? props.bookings : []));

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
    bookingModalOpen.value = true;
};

const closeBookingModal = () => {
    bookingModalOpen.value = false;
    bookingTarget.value = null;
    bookingForm.clearErrors();
};

const submitBooking = () => {
    if (!bookingForm.service_catalog_id) return;

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
    { key: 'settings', label: 'Configura\u00e7\u00f5es', icon: Settings },
    { key: 'privacy', label: 'Pol\u00edtica de privacidade', icon: ShieldCheck },
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
    <div class="min-h-screen bg-slate-200" :style="pageStyles">
        <Head :title="`${storeName} | Agendamentos online`" />

        <main class="relative mx-auto min-h-screen w-full max-w-[1200px] bg-slate-100 text-slate-900 md:my-4 md:overflow-hidden md:rounded-3xl md:border md:border-slate-300 md:shadow-sm">
            <header class="border-b border-slate-200 bg-white px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.15em] text-slate-500">Loja de servi&#231;os</p>
                        <h1 class="text-lg font-bold">{{ storeName }}</h1>
                    </div>
                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :style="{ background: 'var(--service-accent-soft)', color: 'var(--service-accent-strong)' }">Online</span>
                </div>
            </header>

            <div v-if="flashStatus" class="mx-4 mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                {{ flashStatus }}
            </div>

            <div v-if="bookingWhatsappUrl" class="mx-4 mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
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

            <div class="mx-4 mt-3 hidden rounded-xl border border-slate-300 bg-white p-2 md:block">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="tab in tabs"
                        :key="`desktop-tab-${tab.key}`"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold"
                        :class="activeTab === tab.key ? 'text-white' : 'border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100'"
                        :style="activeTab === tab.key ? { background: 'var(--service-accent-strong)' } : null"
                        @click="activeTab = tab.key"
                    >
                        <component :is="tab.icon" class="h-3.5 w-3.5" />
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <section class="space-y-3 px-4 pb-24 pt-3 md:pb-6">
                <template v-if="activeTab === 'home'">
                    <div class="flex gap-2 overflow-x-auto pb-1">
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

                    <div class="flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2">
                        <Search class="h-4 w-4 text-slate-500" />
                        <input v-model="search" type="text" placeholder="Buscar servi&#231;o" class="w-full border-0 bg-transparent text-sm outline-none">
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <article
                            v-for="service in servicesFiltered"
                            :key="`service-${service.id}`"
                            class="grid grid-cols-[4.25rem_minmax(0,1fr)] gap-3 rounded-2xl border border-slate-300 bg-white p-3"
                        >
                            <div class="grid h-[4.25rem] w-[4.25rem] place-items-center overflow-hidden rounded-xl bg-gradient-to-br from-slate-200 to-slate-300 text-sm font-bold text-slate-600">
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

                                <div class="mt-1 inline-flex items-center gap-1 text-[11px] text-slate-500">
                                    <Star class="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                    <span>{{ Number(service.rating ?? 5).toFixed(1) }} ({{ service.reviews_label }})</span>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-500">{{ service.coupon_label }}</p>

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
                            Nenhum servi&#231;o encontrado com os filtros atuais.
                        </div>
                    </div>
                </template>

                <template v-else-if="activeTab === 'bookings'">
                    <div class="mb-1 flex items-center justify-between">
                        <h2 class="text-base font-bold">Meus agendamentos</h2>
                        <span class="rounded-full bg-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700">{{ bookings.length }}</span>
                    </div>

                    <div v-if="!isAuthenticated" class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center text-xs text-slate-500">
                        Fa&#231;a login para acompanhar seus agendamentos.
                        <div class="mt-3">
                            <Link :href="loginUrl" class="inline-flex rounded-xl px-3 py-2 text-xs font-semibold text-white" :style="{ background: 'var(--service-accent-strong)' }">Entrar</Link>
                        </div>
                    </div>

                    <div v-else-if="!bookings.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center text-xs text-slate-500">
                        Voc&#234; ainda n&#227;o possui agendamentos.
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
                        Nenhum servi&#231;o salvo ainda.
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
                        Central de mensagens em breve.
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

            <nav class="absolute bottom-0 left-0 right-0 grid grid-cols-5 border-t border-slate-300 bg-white md:hidden">
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
                    <h3 class="text-base font-bold">Agendar servi&#231;o</h3>
                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100" @click="closeBookingModal"><X class="h-4 w-4" /></button>
                </div>

                <div v-if="bookingTarget" class="mb-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-sm font-semibold text-slate-900">{{ bookingTarget.name }}</p>
                    <p class="text-xs text-slate-500">{{ bookingTarget.category_name }}</p>
                </div>

                <div v-if="!isAuthenticated" class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-500">
                    Fa&#231;a login para continuar o agendamento.
                    <div class="mt-3"><Link :href="loginUrl" class="inline-flex rounded-xl px-3 py-2 text-xs font-semibold text-white" :style="{ background: 'var(--service-accent-strong)' }">Entrar agora</Link></div>
                </div>

                <div v-else-if="requiresVerification && !isEmailVerified" class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-500">
                    Confirme seu e-mail para concluir o agendamento.
                </div>

                <form v-else class="space-y-3" @submit.prevent="submitBooking">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-slate-600">Data e hora</span>
                        <input v-model="bookingForm.scheduled_for" type="datetime-local" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                        <p v-if="bookingForm.errors.scheduled_for" class="mt-1 text-xs text-rose-600">{{ bookingForm.errors.scheduled_for }}</p>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-slate-600">Observa&#231;&#245;es</span>
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
                        <p class="text-xs font-semibold text-slate-500">C&#243;digo</p>
                        <p class="font-semibold text-slate-900">#{{ bookingDetails.code }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Servi&#231;o</p>
                        <p class="font-semibold text-slate-900">{{ bookingDetails.service_name }}</p>
                        <p class="mt-2 text-xs text-slate-500">Data e hora</p>
                        <p class="font-semibold text-slate-900">{{ bookingDetails.scheduled_label }}</p>
                        <p class="mt-2 text-xs text-slate-500">Status</p>
                        <span class="rounded-full px-2 py-1 text-[10px] font-semibold" :class="bookingDetails.status?.tone">{{ bookingDetails.status?.label || 'Em andamento' }}</span>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Observa&#231;&#245;es</p>
                        <p>{{ bookingDetails.notes || 'Sem observa\u00e7\u00f5es.' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
