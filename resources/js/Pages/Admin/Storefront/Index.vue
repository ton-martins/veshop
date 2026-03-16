<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    contractor: { type: Object, default: () => ({}) },
    storefront: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    shop_url: { type: String, default: '' },
});

const page = usePage();
const statusMessage = computed(() => page.props.flash?.status ?? null);

const form = useForm({
    template: 'comercio',
    hero_enabled: true,
    hero_title: '',
    hero_subtitle: '',
    promotions_enabled: true,
    promotions_title: '',
    promotions_subtitle: '',
    promotion_product_ids: [],
    categories_enabled: true,
    catalog_enabled: true,
    catalog_title: '',
    catalog_subtitle: '',
});

const hydrate = () => {
    const storefront = props.storefront ?? {};
    const blocks = storefront.blocks ?? {};
    const hero = storefront.hero ?? {};
    const promotions = storefront.promotions ?? {};
    const catalog = storefront.catalog ?? {};

    form.template = storefront.template ?? 'comercio';
    form.hero_enabled = blocks.hero ?? true;
    form.hero_title = hero.title ?? '';
    form.hero_subtitle = hero.subtitle ?? '';
    form.promotions_enabled = blocks.promotions ?? true;
    form.promotions_title = promotions.title ?? '';
    form.promotions_subtitle = promotions.subtitle ?? '';
    form.promotion_product_ids = Array.isArray(promotions.product_ids)
        ? promotions.product_ids.map((id) => String(id))
        : [];
    form.categories_enabled = blocks.categories ?? true;
    form.catalog_enabled = blocks.catalog ?? true;
    form.catalog_title = catalog.title ?? '';
    form.catalog_subtitle = catalog.subtitle ?? '';
};

watch(() => props.storefront, hydrate, { deep: true, immediate: true });

const productOptions = computed(() =>
    (props.products ?? []).map((product) => ({
        value: String(product.id),
        label: `${product.name} - ${Number(product.sale_price || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}`,
    })),
);

const selectedPromotionCount = computed(() => form.promotion_product_ids.length);
const previewBrandName = computed(() => props.contractor?.brand_name || props.contractor?.name || 'Loja');
const activeBlocksCount = computed(() =>
    [form.hero_enabled, form.promotions_enabled, form.categories_enabled, form.catalog_enabled]
        .filter(Boolean)
        .length,
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'put',
        banners_enabled: false,
        banners: [],
        promotion_product_ids: (data.promotion_product_ids ?? [])
            .map((id) => Number(id))
            .filter((id) => Number.isInteger(id) && id > 0),
    })).post(route('admin.storefront.update'), {
        preserveScroll: true,
        forceFormData: true,
    });
};
</script>

<template>
    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Loja Virtual">
        <Head title="Loja Virtual" />

        <div
            v-if="statusMessage"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            {{ statusMessage }}
        </div>

        <div class="space-y-6">
            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Loja pública</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ previewBrandName }}</p>
                    <p class="mt-1 text-xs text-slate-500">/shop/{{ props.contractor?.slug }}</p>
                    <a
                        v-if="shop_url"
                        :href="shop_url"
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
                    <p class="mt-1 text-xs text-slate-500">de 4 blocos disponíveis</p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Promoções</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ selectedPromotionCount }}</p>
                    <p class="mt-1 text-xs text-slate-500">itens selecionados</p>
                </article>
            </section>

            <form
                class="overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-sm"
                @submit.prevent="submit"
            >
                <header class="border-b border-emerald-100/80 px-6 py-5">
                    <h2 class="text-sm font-semibold text-emerald-900">Configurações da loja virtual</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Personalize títulos, promoções e a ordem dos blocos da vitrine pública.
                    </p>
                </header>

                <div class="space-y-6 px-6 py-6">
                    <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Modelo da loja</p>
                        <select
                            v-model="form.template"
                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                        >
                            <option
                                v-for="template in templates"
                                :key="`template-${template.value}`"
                                :value="template.value"
                            >
                                {{ template.label }}
                            </option>
                        </select>
                        <p class="mt-2 text-xs text-slate-500">
                            {{ templates.find((item) => item.value === form.template)?.description || 'Defina o foco da vitrine.' }}
                        </p>
                        <p v-if="form.errors.template" class="mt-2 text-[11px] text-rose-600">{{ form.errors.template }}</p>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Blocos ativos</p>
                        <div class="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
                            <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <span>Hero principal</span>
                                <input v-model="form.hero_enabled" type="checkbox" class="rounded border-slate-300">
                            </label>
                            <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <span>Promoções</span>
                                <input v-model="form.promotions_enabled" type="checkbox" class="rounded border-slate-300">
                            </label>
                            <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <span>Categorias</span>
                                <input v-model="form.categories_enabled" type="checkbox" class="rounded border-slate-300">
                            </label>
                            <label class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <span>Catálogo principal</span>
                                <input v-model="form.catalog_enabled" type="checkbox" class="rounded border-slate-300">
                            </label>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cabeçalho da vitrine</p>
                        <div class="mt-3 grid gap-3">
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Título</label>
                                <input
                                    v-model="form.hero_title"
                                    type="text"
                                    maxlength="120"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Ex.: Compre em nossa loja"
                                >
                                <p v-if="form.errors.hero_title" class="mt-1 text-[11px] text-rose-600">{{ form.errors.hero_title }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Subtítulo</label>
                                <textarea
                                    v-model="form.hero_subtitle"
                                    rows="2"
                                    maxlength="220"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                    placeholder="Mensagem principal da loja"
                                />
                                <p v-if="form.errors.hero_subtitle" class="mt-1 text-[11px] text-rose-600">{{ form.errors.hero_subtitle }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Promoções</p>
                        <div class="mt-3 grid gap-3">
                            <input
                                v-model="form.promotions_title"
                                type="text"
                                maxlength="80"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Título das promoções"
                            >
                            <textarea
                                v-model="form.promotions_subtitle"
                                rows="2"
                                maxlength="220"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Subtítulo das promoções"
                            />
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                    Itens em destaque ({{ selectedPromotionCount }} selecionado(s))
                                </label>
                                <select
                                    v-model="form.promotion_product_ids"
                                    multiple
                                    class="mt-1 h-48 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                >
                                    <option
                                        v-for="option in productOptions"
                                        :key="`promotion-item-${option.value}`"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <p class="mt-1 text-[11px] text-slate-500">Sem seleção, a loja usa os primeiros itens ativos como fallback.</p>
                            </div>
                        </div>
                        <p v-if="form.errors.promotion_product_ids" class="mt-2 text-[11px] text-rose-600">{{ form.errors.promotion_product_ids }}</p>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catálogo</p>
                        <div class="mt-3 grid gap-3">
                            <input
                                v-model="form.catalog_title"
                                type="text"
                                maxlength="80"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Título do catálogo"
                            >
                            <textarea
                                v-model="form.catalog_subtitle"
                                rows="2"
                                maxlength="220"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                placeholder="Subtítulo do catálogo"
                            />
                        </div>
                    </section>
                </div>

                <footer class="border-t border-emerald-100/80 bg-white px-6 py-4">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Salvando...' : 'Salvar configurações' }}
                    </button>
                </footer>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
