<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Box, Boxes, CircleDollarSign, AlertTriangle, Plus, Search, Filter, ChevronRight, Tags } from 'lucide-vue-next';

const stats = [
    { key: 'products', label: 'Produtos cadastrados', value: '248', icon: Box, tone: 'bg-slate-100 text-slate-700' },
    { key: 'active', label: 'Produtos ativos', value: '232', icon: Boxes, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'stockout', label: 'Sem estoque', value: '12', icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
    { key: 'margin', label: 'Margem média', value: '36%', icon: CircleDollarSign, tone: 'bg-blue-100 text-blue-700' },
];

const allowedUnits = ['un', 'kg', 'lts'];
const normalizeQuantity = (value) => {
    const parsed = Number.parseInt(String(value ?? 0), 10);
    return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
};
const normalizeUnit = (value) => (allowedUnits.includes(value) ? value : 'un');
const formatStock = (quantity, unit) => `${normalizeQuantity(quantity)} ${normalizeUnit(unit)}`;

const products = [
    { sku: 'BOL-001', name: 'Bolo Piscina Chocolate', category: 'Confeitaria', quantity: 12, unit: 'un', price: 'R$ 89,90', status: 'Ativo', photo: 'https://picsum.photos/seed/bolopiscina/64/64' },
    { sku: 'DOC-014', name: 'Kit Brigadeiro Premium', category: 'Doces', quantity: 4, unit: 'kg', price: 'R$ 39,00', status: 'Ativo', photo: 'https://picsum.photos/seed/brigadeiro/64/64' },
    { sku: 'SAL-021', name: 'Torta Salgada Frango', category: 'Salgados', quantity: 0, unit: 'un', price: 'R$ 65,00', status: 'Sem estoque', photo: 'https://picsum.photos/seed/tortafrang0/64/64' },
    { sku: 'BEB-009', name: 'Suco Natural 1L', category: 'Bebidas', quantity: 18, unit: 'lts', price: 'R$ 14,00', status: 'Ativo', photo: 'https://picsum.photos/seed/suconatural/64/64' },
];

const categories = [
    { name: 'Confeitaria', qty: 74, trend: '+12%' },
    { name: 'Salgados', qty: 53, trend: '+4%' },
    { name: 'Doces', qty: 68, trend: '+9%' },
    { name: 'Bebidas', qty: 29, trend: '+2%' },
];
</script>

<template>
    <Head title="Produtos" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Produtos">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="stat in stats" :key="stat.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">{{ stat.label }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ stat.value }}</p>
                        </div>
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl" :class="stat.tone">
                            <component :is="stat.icon" class="h-4 w-4" />
                        </span>
                    </div>
                </article>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="h-4 w-4 text-slate-500" />
                        <input type="text" placeholder="Buscar por SKU ou nome do produto" class="w-full bg-transparent text-sm text-slate-700 outline-none" />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Filter class="h-3.5 w-3.5" />
                            Filtros
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            <Plus class="h-3.5 w-3.5" />
                            Novo produto
                        </button>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Produto</th>
                                    <th class="px-4 py-3">Categoria</th>
                                    <th class="px-4 py-3">Estoque</th>
                                    <th class="px-4 py-3">Preço</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="product in products" :key="product.sku">
                                    <td class="px-4 py-3">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <img
                                                :src="product.photo"
                                                :alt="product.name"
                                                class="h-10 w-10 rounded-lg border border-slate-200 object-cover"
                                                loading="lazy"
                                            />
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-slate-900">{{ product.name }}</p>
                                                <p class="text-xs text-slate-500">{{ product.sku }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ product.category }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ formatStock(product.quantity, product.unit) }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ product.price }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="product.status === 'Ativo' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                            {{ product.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <aside class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <h2 class="text-sm font-semibold text-slate-900">Categorias em destaque</h2>
                        <ul class="space-y-2">
                            <li v-for="category in categories" :key="category.name" class="flex items-center justify-between rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ category.name }}</p>
                                    <p class="text-xs text-slate-500">{{ category.qty }} produtos</p>
                                </div>
                                <span class="text-xs font-semibold text-emerald-700">{{ category.trend }}</span>
                            </li>
                        </ul>

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-800">
                                Ver catálogo completo
                                <ChevronRight class="h-3.5 w-3.5" />
                            </button>
                            <Link :href="route('admin.categories.index')" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                <Tags class="h-3.5 w-3.5" />
                                Categorias
                            </Link>
                        </div>
                    </aside>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
