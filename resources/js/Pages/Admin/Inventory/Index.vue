<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Boxes, AlertTriangle, RotateCcw, CircleDollarSign, ArrowUpDown, Search, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const stats = [
    { key: 'skus', label: 'SKUs ativos', value: '0', icon: Boxes, tone: 'bg-slate-100 text-slate-700' },
    { key: 'critical', label: 'Estoque crítico', value: '0', icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
    { key: 'turnover', label: 'Giro médio', value: '0x', icon: RotateCcw, tone: 'bg-blue-100 text-blue-700' },
    { key: 'value', label: 'Valor em estoque', value: 'R$ 0,00', icon: CircleDollarSign, tone: 'bg-emerald-100 text-emerald-700' },
];

const movements = [];
const locations = [];
const movementSearch = ref('');

const filteredMovements = computed(() => {
    const query = String(movementSearch.value ?? '').trim().toLowerCase();
    if (!query) return movements;

    return movements.filter((movement) => {
        const item = String(movement?.item ?? '').toLowerCase();
        const type = String(movement?.type ?? '').toLowerCase();
        const ref = String(movement?.ref ?? '').toLowerCase();
        return item.includes(query) || type.includes(query) || ref.includes(query);
    });
});

const clearSearch = () => {
    movementSearch.value = '';
};
</script>

<template>
    <Head title="Estoque" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Estoque">
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
                    <div class="veshop-search-shell flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <Search class="veshop-search-icon h-4 w-4 text-slate-500" />
                        <input v-model="movementSearch" type="text" placeholder="Buscar item no estoque" class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none" />
                        <button
                            v-if="movementSearch"
                            type="button"
                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                            aria-label="Limpar pesquisa"
                            @click="clearSearch"
                        >
                            x
                        </button>
                    </div>
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                        <Plus class="h-3.5 w-3.5" />
                        Nova movimentação
                    </button>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Data</th>
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3">Movimento</th>
                                    <th class="px-4 py-3">Qtd</th>
                                    <th class="px-4 py-3">Ref</th>
                                </tr>
                            </thead>
                            <tbody v-if="filteredMovements.length" class="divide-y divide-slate-100 bg-white">
                                <tr v-for="movement in filteredMovements" :key="`${movement.date}-${movement.ref}`">
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ movement.date }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ movement.item }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ movement.type }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ movement.qty }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ movement.ref }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="!filteredMovements.length" class="px-4 py-8 text-center text-sm text-slate-500">
                            Nenhuma movimentação registrada.
                        </div>
                    </div>

                    <aside class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <h2 class="text-sm font-semibold text-slate-900">Locais de estoque</h2>
                        <ul v-if="locations.length" class="space-y-2">
                            <li v-for="location in locations" :key="location.name" class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-semibold text-slate-800">{{ location.name }}</p>
                                    <span class="text-xs font-semibold text-slate-500">{{ location.occupancy }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ location.skus }} SKUs</p>
                            </li>
                        </ul>
                        <div v-else class="rounded-lg bg-white px-3 py-6 text-center text-sm text-slate-500 ring-1 ring-slate-200">
                            Nenhum local configurado.
                        </div>

                        <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-800">
                            <ArrowUpDown class="h-3.5 w-3.5" />
                            Ajustar inventário
                        </button>
                    </aside>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
