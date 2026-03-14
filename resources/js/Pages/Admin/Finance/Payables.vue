<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { CalendarClock, AlertTriangle, CheckCircle2, WalletCards, Search, Filter, Plus } from 'lucide-vue-next';

const stats = [
    { key: 'next_7', label: 'A vencer (7 dias)', value: 'R$ 0,00', icon: CalendarClock, tone: 'bg-blue-100 text-blue-700' },
    { key: 'late', label: 'Vencido', value: 'R$ 0,00', icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
    { key: 'paid', label: 'Pago no mês', value: 'R$ 0,00', icon: CheckCircle2, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'projection', label: 'Saída projetada', value: 'R$ 0,00', icon: WalletCards, tone: 'bg-slate-100 text-slate-700' },
];

const payables = [];
</script>

<template>
    <Head title="Contas a Pagar" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Contas a Pagar">
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
                        <input type="text" placeholder="Buscar por fornecedor ou documento" class="w-full bg-transparent text-sm text-slate-700 outline-none" />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Filter class="h-3.5 w-3.5" />
                            Vencimento
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            <Plus class="h-3.5 w-3.5" />
                            Lançar título
                        </button>
                    </div>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Fornecedor</th>
                                <th class="px-4 py-3">Documento</th>
                                <th class="px-4 py-3">Vencimento</th>
                                <th class="px-4 py-3">Valor</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody v-if="payables.length" class="divide-y divide-slate-100 bg-white">
                            <tr v-for="item in payables" :key="`${item.document}-${item.supplier}`">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ item.supplier }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ item.document }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ item.due }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ item.value }}</td>
                                <td class="px-4 py-3">{{ item.status }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="!payables.length" class="px-4 py-8 text-center text-sm text-slate-500">
                        Nenhum título a pagar cadastrado.
                    </div>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
