<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Layers3, Tags, Box, AlertTriangle, Plus, Search, Filter } from 'lucide-vue-next';

const stats = [
    { key: 'categories', label: 'Categorias', value: '18', icon: Layers3, tone: 'bg-slate-100 text-slate-700' },
    { key: 'subcategories', label: 'Subcategorias', value: '46', icon: Tags, tone: 'bg-blue-100 text-blue-700' },
    { key: 'products', label: 'Produtos vinculados', value: '248', icon: Box, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'uncategorized', label: 'Sem categoria', value: '6', icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
];

const categories = [
    { name: 'Confeitaria', slug: 'confeitaria', products: 74, status: 'Ativa' },
    { name: 'Doces', slug: 'doces', products: 68, status: 'Ativa' },
    { name: 'Salgados', slug: 'salgados', products: 53, status: 'Ativa' },
    { name: 'Bebidas', slug: 'bebidas', products: 29, status: 'Ativa' },
    { name: 'Promoções sazonais', slug: 'promocoes-sazonais', products: 4, status: 'Rascunho' },
];
</script>

<template>
    <Head title="Categorias" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Categorias">
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
                        <input type="text" placeholder="Buscar categoria por nome ou slug" class="w-full bg-transparent text-sm text-slate-700 outline-none" />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Filter class="h-3.5 w-3.5" />
                            Filtros
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            <Plus class="h-3.5 w-3.5" />
                            Nova categoria
                        </button>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Categoria</th>
                                <th class="px-4 py-3">Slug</th>
                                <th class="px-4 py-3">Produtos</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="category in categories" :key="category.slug">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ category.name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ category.slug }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ category.products }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="category.status === 'Ativa' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                        {{ category.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Editar</button>
                                        <button type="button" class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Excluir</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
