<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Bell, Check, Heart, Home, LogOut, ShoppingBag } from 'lucide-vue-next';
import { useBranding } from '@/branding';

const props = defineProps({
    contractor: { type: Object, required: true },
    customer: { type: Object, required: true },
    orders: { type: Array, default: () => [] },
    favorites: { type: Array, default: () => [] },
    notifications: { type: Array, default: () => [] },
    notifications_unread_count: { type: Number, default: 0 },
});

const { normalizeHex, primaryColor, withAlpha, themeStyles } = useBranding();

const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const storeLogo = computed(() => props.contractor?.avatar_url || props.contractor?.logo_url || null);
const storePrimaryColor = computed(() => normalizeHex(props.contractor?.primary_color || '', primaryColor.value));

const storeInitials = computed(() => {
    const safe = String(storeName.value || '').trim();
    if (!safe) return 'LJ';

    const parts = safe.split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) || '';
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';

    return `${first}${last}`.toUpperCase() || 'LJ';
});

const storeInitialsColor = computed(() => {
    const normalized = storePrimaryColor.value.slice(1);
    const red = Number.parseInt(normalized.slice(0, 2), 16);
    const green = Number.parseInt(normalized.slice(2, 4), 16);
    const blue = Number.parseInt(normalized.slice(4, 6), 16);
    const luminance = ((red * 299) + (green * 587) + (blue * 114)) / 255000;

    return luminance > 0.62 ? '#0f172a' : '#ffffff';
});

const storeIconStyle = computed(() => {
    if (storeLogo.value) return null;

    return {
        background: storePrimaryColor.value,
        color: storeInitialsColor.value,
    };
});

const pageStyles = computed(() => {
    const c = storePrimaryColor.value;

    return {
        ...themeStyles.value,
        '--shop-primary': c,
        '--shop-primary-soft': withAlpha(c, 0.12),
        '--shop-primary-strong': withAlpha(c, 0.92),
    };
});

const shopUrl = computed(() => `/shop/${storeSlug.value}`);
const shopFavoritesUrl = computed(() => `/shop/${storeSlug.value}?favoritos=1`);
const logoutUrl = computed(() => `/shop/${storeSlug.value}/sair`);
const asCurrency = (value) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const logoutForm = useForm({});
const doLogout = () => {
    logoutForm.post(logoutUrl.value);
};

const markNotificationsForm = useForm({ id: '' });
const markAllNotificationsAsRead = () => {
    markNotificationsForm.transform(() => ({ id: '' })).post(`/shop/${storeSlug.value}/conta/notificacoes/ler`, {
        preserveScroll: true,
    });
};
const markOneNotificationAsRead = (id) => {
    if (!id) return;
    markNotificationsForm.transform(() => ({ id })).post(`/shop/${storeSlug.value}/conta/notificacoes/ler`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Minha conta | ${storeName}`" />

    <div class="min-h-screen bg-slate-100 px-4 py-6 text-slate-900 sm:px-6 lg:px-8" :style="pageStyles">
        <div class="mx-auto w-full max-w-5xl space-y-4">
            <header class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-slate-100" :style="storeIconStyle">
                            <img v-if="storeLogo" :src="storeLogo" :alt="storeName" class="h-full w-full object-cover" />
                            <span v-else class="text-xs font-semibold">{{ storeInitials }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ storeName }}</p>
                            <p class="text-xs text-slate-500">Minha conta</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Link :href="shopUrl" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Home class="h-3.5 w-3.5" />
                            Ir para loja
                        </Link>
                        <Link :href="shopFavoritesUrl" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Heart class="h-3.5 w-3.5" />
                            Favoritos
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                            :disabled="logoutForm.processing"
                            @click="doLogout"
                        >
                            <LogOut class="h-3.5 w-3.5" />
                            Sair
                        </button>
                    </div>
                </div>
            </header>

            <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <h1 class="text-base font-semibold text-slate-900">{{ customer.name }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ customer.email || 'E-mail não informado' }}</p>
                <p class="text-sm text-slate-600">{{ customer.phone || 'Telefone não informado' }}</p>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Bell class="h-4 w-4 text-slate-500" />
                        <h2 class="text-base font-semibold text-slate-900">Notificações</h2>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                        :disabled="markNotificationsForm.processing || notifications_unread_count <= 0"
                        @click="markAllNotificationsAsRead"
                    >
                        <Check class="h-3 w-3" />
                        Marcar lidas
                    </button>
                </div>

                <div v-if="notifications.length" class="space-y-2">
                    <article
                        v-for="item in notifications"
                        :key="item.id"
                        class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2"
                        :class="!item.read_at ? 'ring-1 ring-blue-100' : ''"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-slate-900">{{ item.title }}</p>
                                <p class="text-xs text-slate-600">{{ item.message }}</p>
                                <p class="text-[11px] text-slate-400">{{ item.created_at }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link
                                    v-if="item.target_url"
                                    :href="item.target_url"
                                    class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Abrir
                                </Link>
                                <button
                                    v-if="!item.read_at"
                                    type="button"
                                    class="rounded-lg border border-blue-200 bg-blue-50 px-2 py-1 text-[11px] font-semibold text-blue-700 hover:bg-blue-100"
                                    :disabled="markNotificationsForm.processing"
                                    @click="markOneNotificationAsRead(item.id)"
                                >
                                    Lida
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <p v-else class="text-sm text-slate-500">Sem notificações no momento.</p>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <Heart class="h-4 w-4 text-slate-500" />
                        <h2 class="text-base font-semibold text-slate-900">Favoritos</h2>
                    </div>
                    <span class="text-xs font-semibold text-slate-500">{{ favorites.length }} item(ns)</span>
                </div>

                <div v-if="favorites.length" class="space-y-2">
                    <article
                        v-for="favorite in favorites"
                        :key="favorite.id"
                        class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2"
                    >
                        <div class="flex items-center gap-3">
                            <img
                                :src="favorite.image_url || `https://placehold.co/120x120/e2e8f0/475569?text=${encodeURIComponent(favorite.name || 'Produto')}`"
                                :alt="favorite.name"
                                class="h-12 w-12 rounded-lg object-cover"
                            >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ favorite.name }}</p>
                                <p class="text-xs text-slate-600">{{ asCurrency(favorite.sale_price) }}</p>
                                <p class="text-[11px]" :class="favorite.is_active && favorite.stock_quantity > 0 ? 'text-emerald-700' : 'text-rose-600'">
                                    {{ favorite.is_active && favorite.stock_quantity > 0 ? 'Disponível' : 'Indisponível no momento' }}
                                </p>
                            </div>
                            <Link
                                :href="favorite.url"
                                class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Abrir
                            </Link>
                        </div>
                    </article>
                </div>

                <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                    Você ainda não favoritou produtos nesta loja.
                </div>

                <div class="mt-3">
                    <Link :href="shopFavoritesUrl" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        <Heart class="h-3.5 w-3.5" />
                        Ver favoritos na loja
                    </Link>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900">Meus pedidos</h2>
                    <span class="text-xs font-semibold text-slate-500">{{ orders.length }} pedido(s)</span>
                </div>

                <div v-if="orders.length" class="space-y-3">
                    <article
                        v-for="order in orders"
                        :key="order.id"
                        class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ order.code }}</p>
                                <p class="text-xs text-slate-500">{{ order.created_at }}</p>
                            </div>
                            <span class="inline-flex w-fit rounded-full px-2 py-0.5 text-xs font-semibold" :class="order.status.tone">
                                {{ order.status.label }}
                            </span>
                        </div>

                        <div class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-3">
                            <p>Total: <span class="font-semibold">{{ asCurrency(order.total_amount) }}</span></p>
                            <p>Pagamento: <span class="font-semibold">{{ order.payment_label }}</span></p>
                            <p>Itens: <span class="font-semibold">{{ order.items.length }}</span></p>
                        </div>

                        <ul class="mt-3 space-y-1 text-xs text-slate-700">
                            <li v-for="item in order.items" :key="`${order.id}-${item.description}`" class="flex items-center justify-between gap-2">
                                <span class="truncate">{{ item.description }} x{{ item.quantity }}</span>
                                <span class="font-semibold">{{ asCurrency(item.total_amount) }}</span>
                            </li>
                        </ul>
                    </article>
                </div>

                <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                    <ShoppingBag class="mx-auto mb-2 h-5 w-5 text-slate-400" />
                    Você ainda não possui pedidos nesta loja.
                </div>
            </section>
        </div>
    </div>
</template>
