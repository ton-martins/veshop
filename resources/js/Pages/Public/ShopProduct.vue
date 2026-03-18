<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Heart, Home, LogIn, Menu, Minus, Plus, ShoppingCart, UserCircle2, X } from 'lucide-vue-next';
import { useBranding } from '@/branding';

const props = defineProps({
    contractor: { type: Object, required: true },
    product: { type: Object, required: true },
    related_products: { type: Array, default: () => [] },
    shop_auth: { type: Object, default: () => ({ authenticated: false, customer: null }) },
});

const { normalizeHex, primaryColor, publicFaviconHref, publicFaviconType, themeStyles, withAlpha } = useBranding();

const storeName = computed(() => String(props.contractor?.brand_name || props.contractor?.name || 'Loja'));
const storeSlug = computed(() => String(props.contractor?.slug || 'shop'));
const loginUrl = computed(() => `/shop/${storeSlug.value}/entrar`);
const accountUrl = computed(() => `/shop/${storeSlug.value}/conta`);
const verifyEmailUrl = computed(() => `/shop/${storeSlug.value}/verificar-email`);
const isShopAuthenticated = computed(() => Boolean(props.shop_auth?.authenticated));
const requiresEmailVerification = computed(() => Boolean(props.shop_auth?.requires_email_verification ?? true));
const isShopEmailVerified = computed(() => Boolean(props.shop_auth?.email_verified ?? false));
const accountOrLoginUrl = computed(() => {
    if (!isShopAuthenticated.value) return loginUrl.value;
    if (requiresEmailVerification.value && !isShopEmailVerified.value) return verifyEmailUrl.value;

    return accountUrl.value;
});
const normalizeBrandAsset = (value) => {
    const safe = String(value ?? '').trim();
    return safe !== '' ? safe : null;
};
const storeLogoLoadFailed = ref(false);
const rawStoreLogo = computed(() =>
    normalizeBrandAsset(props.contractor?.avatar_url) || normalizeBrandAsset(props.contractor?.logo_url),
);
const storeLogo = computed(() => (storeLogoLoadFailed.value ? null : rawStoreLogo.value));
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
const shopUrl = computed(() => {
    if (typeof route === 'function') {
        try {
            return route('shop.show', { slug: storeSlug.value });
        } catch {
            // fallback below
        }
    }
    return `/shop/${storeSlug.value}`;
});

const pageStyles = computed(() => {
    const c = storePrimaryColor.value;
    return {
        ...themeStyles.value,
        '--catalog-primary': c,
        '--catalog-primary-soft': withAlpha(c, 0.14),
        '--catalog-primary-border': withAlpha(c, 0.28),
        '--catalog-primary-strong': withAlpha(c, 0.92),
        '--catalog-gradient': `linear-gradient(135deg, ${withAlpha(c, 0.16)} 0%, rgba(255,255,255,0.96) 54%, ${withAlpha(c, 0.05)} 100%)`,
    };
});

const currency = (value) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const productImage = (product) => {
    const raw = String(product?.image_url || '').trim();
    return raw || `https://placehold.co/720x720/e2e8f0/475569?text=${encodeURIComponent(String(product?.name || 'Produto'))}`;
};

const quantity = ref(1);
const cartCount = ref(0);
const favoritesOnly = ref(false);
const justAdded = ref(false);
const addToCartProcessing = ref(false);
const leftMenuOpen = ref(false);
let justAddedTimer = null;
let addToCartTimer = null;

const cartStorageKey = computed(() => `veshop:shop-cart:${storeSlug.value}`);
const favoritesStorageKey = computed(() => `veshop:shop-favorites:${storeSlug.value}`);
const favoriteProductIds = ref([]);
const favoriteProductIdSet = computed(() => new Set(favoriteProductIds.value));
const favoriteSyncingIds = ref([]);

watch(
    () => [props.contractor?.avatar_url, props.contractor?.logo_url],
    () => {
        storeLogoLoadFailed.value = false;
    },
);

const handleStoreLogoError = () => {
    storeLogoLoadFailed.value = true;
};

const normalizeFavoriteIds = (values) => {
    if (!Array.isArray(values)) return [];

    return Array.from(new Set(
        values
            .map((id) => Number(id))
            .filter((id) => Number.isFinite(id) && id > 0),
    ));
};

const serverFavoriteProductIds = computed(() =>
    normalizeFavoriteIds(props.shop_auth?.favorite_product_ids ?? []),
);

const loadFavoritesFromStorage = () => {
    if (typeof window === 'undefined') return;

    try {
        const raw = window.localStorage.getItem(favoritesStorageKey.value);
        if (!raw) {
            favoriteProductIds.value = [];
            return;
        }

        favoriteProductIds.value = normalizeFavoriteIds(JSON.parse(raw));
    } catch {
        favoriteProductIds.value = [];
    }
};

const setFavoriteSyncing = (productId, syncing) => {
    const safeProductId = Number(productId);
    const next = new Set(favoriteSyncingIds.value);

    if (syncing) {
        next.add(safeProductId);
    } else {
        next.delete(safeProductId);
    }

    favoriteSyncingIds.value = Array.from(next);
};

const isFavoriteSyncing = (productId) => new Set(favoriteSyncingIds.value).has(Number(productId));

const persistFavoriteOnServer = async (productId, shouldFavorite) => {
    if (typeof window === 'undefined' || !window.axios) return false;

    const endpoint = `/shop/${storeSlug.value}/favoritos/${productId}`;

    try {
        const response = shouldFavorite
            ? await window.axios.post(endpoint)
            : await window.axios.delete(endpoint);

        return response?.data?.ok === true;
    } catch {
        return false;
    }
};

const loadCartCount = () => {
    if (typeof window === 'undefined') return;

    try {
        const raw = window.localStorage.getItem(cartStorageKey.value);
        if (!raw) {
            cartCount.value = 0;
            return;
        }

        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            cartCount.value = 0;
            return;
        }

        cartCount.value = parsed.reduce((acc, item) => acc + Math.max(1, Number(item?.quantity || 1)), 0);
    } catch {
        cartCount.value = 0;
    }
};

const loadFavorites = () => {
    if (isShopAuthenticated.value) {
        favoriteProductIds.value = serverFavoriteProductIds.value;
    } else {
        loadFavoritesFromStorage();
    }
};

onMounted(() => {
    loadCartCount();
    loadFavorites();
});

watch(
    [isShopAuthenticated, serverFavoriteProductIds],
    ([authenticated]) => {
        if (authenticated) {
            favoriteProductIds.value = serverFavoriteProductIds.value;
            return;
        }

        loadFavoritesFromStorage();
    },
    { immediate: true },
);

watch(
    favoriteProductIds,
    (value) => {
        if (typeof window === 'undefined') return;
        if (isShopAuthenticated.value) return;
        window.localStorage.setItem(favoritesStorageKey.value, JSON.stringify(value));
    },
    { deep: true },
);

const isFavorite = (productId) => favoriteProductIdSet.value.has(Number(productId));

const toggleFavorite = async (productId) => {
    const targetId = Number(productId);
    if (!targetId) return;
    if (isShopAuthenticated.value && isFavoriteSyncing(targetId)) return;

    const wasFavorite = isFavorite(targetId);
    const previous = [...favoriteProductIds.value];
    favoriteProductIds.value = wasFavorite
        ? favoriteProductIds.value.filter((id) => Number(id) !== targetId)
        : normalizeFavoriteIds([...favoriteProductIds.value, targetId]);

    if (!isShopAuthenticated.value) return;

    setFavoriteSyncing(targetId, true);
    const saved = await persistFavoriteOnServer(targetId, !wasFavorite);
    setFavoriteSyncing(targetId, false);

    if (!saved) {
        favoriteProductIds.value = previous;
        return;
    }
};

const relatedProducts = computed(() => {
    if (!favoritesOnly.value) return props.related_products;
    return props.related_products.filter((item) => favoriteProductIdSet.value.has(Number(item.id)));
});

const productDetailsUrl = (productId) => {
    if (typeof route === 'function') {
        try {
            return route('shop.product.show', { slug: storeSlug.value, product: productId });
        } catch {
            // fallback below
        }
    }
    return `/shop/${storeSlug.value}/produto/${productId}`;
};

const addCurrentToCart = () => {
    if (typeof window === 'undefined') return;
    if (addToCartProcessing.value) return;

    const targetId = Number(props.product?.id);
    if (!targetId) return;

    const stock = Number(props.product?.stock_quantity || 0);
    if (stock <= 0) return;

    addToCartProcessing.value = true;

    addToCartTimer = window.setTimeout(() => {
        let current = [];

        try {
            const raw = window.localStorage.getItem(cartStorageKey.value);
            const parsed = raw ? JSON.parse(raw) : [];
            current = Array.isArray(parsed) ? parsed : [];
        } catch {
            current = [];
        }

        const existing = current.find((item) => Number(item?.product_id) === targetId);
        const qtyToAdd = Math.max(1, Number(quantity.value || 1));

        if (!existing) {
            current.push({ product_id: targetId, quantity: Math.min(stock, qtyToAdd) });
        } else {
            existing.quantity = Math.min(stock, Math.max(1, Number(existing.quantity || 1)) + qtyToAdd);
        }

        window.localStorage.setItem(cartStorageKey.value, JSON.stringify(current));
        loadCartCount();

        if (justAddedTimer) {
            clearTimeout(justAddedTimer);
            justAddedTimer = null;
        }

        justAdded.value = true;
        justAddedTimer = window.setTimeout(() => {
            justAdded.value = false;
            justAddedTimer = null;
        }, 1200);

        addToCartProcessing.value = false;
        addToCartTimer = null;
    }, 350);
};

const incrementQty = () => {
    const max = Math.max(1, Number(props.product?.stock_quantity || 1));
    quantity.value = Math.min(max, Number(quantity.value || 1) + 1);
};

const decrementQty = () => {
    quantity.value = Math.max(1, Number(quantity.value || 1) - 1);
};

const hasStock = computed(() => Number(props.product?.stock_quantity || 0) > 0);

onBeforeUnmount(() => {
    if (justAddedTimer) {
        clearTimeout(justAddedTimer);
        justAddedTimer = null;
    }
    if (addToCartTimer) {
        clearTimeout(addToCartTimer);
        addToCartTimer = null;
    }
});
</script>

<template>
    <Head :title="`${product?.name || 'Produto'} | ${storeName}`">
        <link v-if="publicFaviconHref" rel="icon" :href="publicFaviconHref" :type="publicFaviconType" />
    </Head>

    <div class="min-h-screen bg-slate-100 text-slate-900" :style="pageStyles">
        <header class="sticky top-0 z-40 border-b border-white/70 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="hidden h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 sm:inline-flex"
                        @click="leftMenuOpen = true"
                    >
                        <Menu class="h-4 w-4" />
                    </button>
                    <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-xl bg-slate-100" :style="storeIconStyle">
                        <img
                            v-if="storeLogo"
                            :src="storeLogo"
                            :alt="storeName"
                            class="h-full w-full object-cover"
                            @error="handleStoreLogoError"
                        />
                        <span v-else class="text-xs font-semibold">{{ storeInitials }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ storeName }}</p>
                        <p class="truncate text-xs text-slate-500">Detalhes do produto</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">
                        <ShoppingCart class="h-3.5 w-3.5" />
                        {{ cartCount }}
                    </span>
                    <Link
                        :href="accountOrLoginUrl"
                        class="hidden items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:inline-flex"
                    >
                        <LogIn class="h-3.5 w-3.5" />
                        {{ isShopAuthenticated ? 'Minha conta' : 'Entrar' }}
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl space-y-6 px-4 pb-24 pt-5 sm:px-6 lg:px-8 lg:pb-10">
            <section class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="pointer-events-none absolute inset-0 opacity-90" style="background: var(--catalog-gradient)"></div>
                <div class="relative grid gap-5 md:grid-cols-[minmax(0,1fr),minmax(0,1fr)] md:items-start">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                        <img :src="productImage(product)" :alt="product.name" class="h-full w-full object-cover" />
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-medium text-slate-500">{{ product.category_name || 'Sem categoria' }}</p>
                                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ product.name }}</h1>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60"
                                :class="isFavorite(product.id) ? 'border-rose-200 bg-rose-50 text-rose-600' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                                :disabled="isFavoriteSyncing(product.id)"
                                @click="toggleFavorite(product.id)"
                            >
                                <Heart class="h-5 w-5" />
                            </button>
                        </div>

                        <p v-if="product.description" class="text-sm leading-relaxed text-slate-600">
                            {{ product.description }}
                        </p>

                        <div class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">Preço</p>
                                <p class="mt-1 text-xl font-bold text-slate-900">{{ currency(product.sale_price) }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">Estoque</p>
                                <p class="mt-1 text-sm font-semibold" :class="hasStock ? 'text-emerald-700' : 'text-rose-600'">
                                    {{ hasStock ? `${product.stock_quantity} ${product.unit || 'un'}` : 'Indisponível' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">SKU</p>
                                <p class="mt-1 text-sm font-semibold text-slate-700">{{ product.sku || '-' }}</p>
                            </div>
                        </div>

                        <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="inline-flex items-center rounded-xl border border-slate-200 bg-white">
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center text-slate-600 hover:bg-slate-50"
                                        @click="decrementQty"
                                    >
                                        <Minus class="h-4 w-4" />
                                    </button>
                                    <span class="min-w-[40px] text-center text-sm font-semibold text-slate-800">{{ quantity }}</span>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 w-10 items-center justify-center text-slate-600 hover:bg-slate-50"
                                        @click="incrementQty"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-40"
                                    style="background: var(--catalog-primary-strong)"
                                    :disabled="!hasStock || addToCartProcessing"
                                    @click="addCurrentToCart"
                                >
                                    <span v-if="addToCartProcessing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                                    {{ addToCartProcessing ? 'Adicionando...' : 'Adicionar ao carrinho' }}
                                </button>
                            </div>

                            <p v-if="justAdded" class="text-xs font-semibold text-emerald-700">Produto adicionado ao carrinho.</p>
                            <p v-if="!hasStock" class="text-xs font-semibold text-rose-600">Este produto está sem estoque no momento.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900">Produtos relacionados</h2>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        @click="favoritesOnly = !favoritesOnly"
                    >
                        {{ favoritesOnly ? 'Mostrar todos' : 'Somente favoritos' }}
                    </button>
                </div>

                <div v-if="relatedProducts.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <Link
                        v-for="item in relatedProducts"
                        :key="`related-${item.id}`"
                        :href="productDetailsUrl(item.id)"
                        class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <div class="relative aspect-square overflow-hidden bg-slate-100">
                            <img :src="productImage(item)" :alt="item.name" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]" />
                            <button
                                type="button"
                                class="absolute right-2 top-2 inline-flex h-8 w-8 items-center justify-center rounded-full border shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60"
                                :class="isFavorite(item.id) ? 'border-rose-200 bg-rose-50 text-rose-600' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'"
                                :disabled="isFavoriteSyncing(item.id)"
                                @click.stop.prevent="toggleFavorite(item.id)"
                            >
                                <Heart class="h-4 w-4" />
                            </button>
                        </div>
                        <div class="space-y-1 p-3">
                            <h3 class="min-h-[2.5rem] text-sm font-semibold leading-tight text-slate-900">{{ item.name }}</h3>
                            <p class="text-base font-bold text-slate-900">{{ currency(item.sale_price) }}</p>
                        </div>
                    </Link>
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                    Nenhum produto relacionado encontrado.
                </div>
            </section>
        </main>

        <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-2 pb-[max(env(safe-area-inset-bottom),0.45rem)] pt-2 shadow-[0_-10px_30px_-20px_rgba(15,23,42,0.22)] backdrop-blur sm:hidden">
            <div class="mx-auto flex max-w-md items-end gap-1">
                <button type="button" class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold text-slate-600 hover:bg-slate-100" @click="leftMenuOpen = true"><Menu class="h-4 w-4" />Menu</button>
                <Link :href="shopUrl" class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold text-slate-600 hover:bg-slate-100"><Home class="h-4 w-4" />Início</Link>
                <button
                    type="button"
                    class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold transition"
                    :class="favoritesOnly ? 'text-white shadow-inner shadow-black/10' : 'text-slate-600 hover:bg-slate-100'"
                    :style="favoritesOnly ? { background: 'var(--catalog-primary-strong)' } : null"
                    @click="favoritesOnly = !favoritesOnly"
                >
                    <Heart class="h-4 w-4" />
                    Favoritos
                </button>
                <Link :href="shopUrl" class="relative flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold text-slate-600 hover:bg-slate-100">
                    <ShoppingCart class="h-4 w-4" />Carrinho
                    <span v-if="cartCount > 0" class="absolute right-2 top-1 inline-flex min-w-[16px] items-center justify-center rounded-full px-1 py-0.5 text-[9px] font-bold text-white" style="background: var(--catalog-primary-strong)">{{ cartCount }}</span>
                </Link>
                <Link :href="accountOrLoginUrl" class="flex min-w-0 flex-1 flex-col items-center justify-center gap-1 rounded-xl px-1 py-2 text-[10px] font-semibold text-slate-600 hover:bg-slate-100"><UserCircle2 class="h-4 w-4" />Conta</Link>
            </div>
        </nav>

        <transition name="menu-overlay">
            <div v-if="leftMenuOpen" class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-slate-900/45 backdrop-blur-[1px]" @click="leftMenuOpen = false"></div>
                <aside class="absolute left-0 top-0 flex h-full w-full max-w-sm flex-col border-r border-slate-200 bg-white shadow-2xl">
                    <header class="flex items-center justify-between border-b border-slate-200 px-4 py-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Menu</p>
                            <p class="text-xs text-slate-500">{{ storeName }}</p>
                        </div>
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50" @click="leftMenuOpen = false">
                            <X class="h-4 w-4" />
                        </button>
                    </header>

                    <div class="flex-1 space-y-4 overflow-y-auto p-4">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-sm font-semibold text-slate-900">{{ storeName }}</p>
                            <p class="mt-1 text-xs text-slate-500">Navegação da loja pública.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <Link :href="shopUrl" class="inline-flex items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="leftMenuOpen = false">
                                <Home class="h-3.5 w-3.5" />
                                Início
                            </Link>
                            <button type="button" class="inline-flex items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="favoritesOnly = !favoritesOnly">
                                <Heart class="h-3.5 w-3.5" />
                                Favoritos
                            </button>
                            <Link :href="shopUrl" class="inline-flex items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="leftMenuOpen = false">
                                <ShoppingCart class="h-3.5 w-3.5" />
                                Carrinho
                            </Link>
                            <Link :href="accountOrLoginUrl" class="inline-flex items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="leftMenuOpen = false">
                                <UserCircle2 class="h-3.5 w-3.5" />
                                Conta
                            </Link>
                        </div>
                    </div>
                </aside>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.menu-overlay-enter-active,
.menu-overlay-leave-active {
    transition: opacity 160ms ease;
}
.menu-overlay-enter-from,
.menu-overlay-leave-to {
    opacity: 0;
}
</style>
