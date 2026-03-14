<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    CalendarClock,
    AlertTriangle,
    CheckCircle2,
    WalletCards,
    Banknote,
    Search,
    Filter,
    Plus,
} from 'lucide-vue-next';

const props = defineProps({
    initialTab: {
        type: String,
        default: 'payables',
    },
});

const allowedTabs = new Set(['payables', 'receivables']);
const activeTab = ref(allowedTabs.has(props.initialTab) ? props.initialTab : 'payables');

const tabs = [
    {
        key: 'payables',
        label: 'Contas a pagar',
        description: 'Despesas, fornecedores e saídas previstas.',
        icon: WalletCards,
    },
    {
        key: 'receivables',
        label: 'Contas a receber',
        description: 'Cobranças, clientes e entradas previstas.',
        icon: Banknote,
    },
];

const payablesStats = [
    { key: 'next_7', label: 'A vencer (7 dias)', value: 'R$ 0,00', icon: CalendarClock, tone: 'bg-blue-100 text-blue-700' },
    { key: 'late', label: 'Vencido', value: 'R$ 0,00', icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
    { key: 'paid', label: 'Pago no mês', value: 'R$ 0,00', icon: CheckCircle2, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'projection', label: 'Saída projetada', value: 'R$ 0,00', icon: WalletCards, tone: 'bg-slate-100 text-slate-700' },
];

const receivablesStats = [
    { key: 'next_7', label: 'A receber (7 dias)', value: 'R$ 0,00', icon: CalendarClock, tone: 'bg-blue-100 text-blue-700' },
    { key: 'late', label: 'Atrasado', value: 'R$ 0,00', icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
    { key: 'received', label: 'Recebido no mês', value: 'R$ 0,00', icon: CheckCircle2, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'default', label: 'Inadimplência', value: '0%', icon: Banknote, tone: 'bg-slate-100 text-slate-700' },
];

const payables = [];
const receivables = [];
const searchQuery = ref('');

const activeStats = computed(() => (activeTab.value === 'receivables' ? receivablesStats : payablesStats));
const activeRows = computed(() => {
    const rows = activeTab.value === 'receivables' ? receivables : payables;
    const query = String(searchQuery.value ?? '').trim().toLowerCase();

    if (!query) return rows;

    return rows.filter((item) => {
        const primary = String(item?.primary ?? '').toLowerCase();
        const reference = String(item?.reference ?? '').toLowerCase();
        const status = String(item?.status ?? '').toLowerCase();
        return primary.includes(query) || reference.includes(query) || status.includes(query);
    });
});

const searchPlaceholder = computed(() =>
    activeTab.value === 'receivables'
        ? 'Buscar por cliente ou pedido'
        : 'Buscar por fornecedor ou documento'
);

const filterLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Cobrança'
        : 'Vencimento'
);

const actionLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Novo recebível'
        : 'Lançar título'
);

const firstColumnLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Cliente'
        : 'Fornecedor'
);

const secondColumnLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Pedido'
        : 'Documento'
);

const emptyStateLabel = computed(() =>
    activeTab.value === 'receivables'
        ? 'Nenhum título a receber cadastrado.'
        : 'Nenhum título a pagar cadastrado.'
);

const clearSearch = () => {
    searchQuery.value = '';
};
</script>

<template>
    <Head title="Contas" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Contas">
        <section class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-2xl border bg-white p-4 text-left shadow-sm transition"
                    :class="activeTab === tab.key ? 'border-slate-900 ring-1 ring-slate-900/10' : 'border-slate-200 hover:border-slate-300'"
                    @click="activeTab = tab.key"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ tab.label }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ tab.description }}</p>
                        </div>
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                            <component :is="tab.icon" class="h-4 w-4" />
                        </span>
                    </div>
                </button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="stat in activeStats"
                    :key="`${activeTab}-${stat.key}`"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
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
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="searchPlaceholder"
                            class="veshop-search-input w-full bg-transparent text-sm text-slate-700 outline-none"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold text-slate-500 transition hover:bg-slate-200 hover:text-slate-700"
                            aria-label="Limpar pesquisa"
                            @click="clearSearch"
                        >
                            x
                        </button>
                    </div>

                    <div class="veshop-toolbar-actions lg:justify-end">
                        <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                            <Filter class="h-3.5 w-3.5" />
                            {{ filterLabel }}
                        </button>
                        <button type="button" class="inline-flex w-full items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 sm:w-auto">
                            <Plus class="h-3.5 w-3.5" />
                            {{ actionLabel }}
                        </button>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">{{ firstColumnLabel }}</th>
                                <th class="px-4 py-3">{{ secondColumnLabel }}</th>
                                <th class="px-4 py-3">Vencimento</th>
                                <th class="px-4 py-3">Valor</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody v-if="activeRows.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="item in activeRows" :key="item.id">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ item.primary }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ item.reference }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ item.due }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ item.value }}</td>
                                <td class="px-4 py-3">{{ item.status }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!activeRows.length" class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ emptyStateLabel }}
                    </div>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
