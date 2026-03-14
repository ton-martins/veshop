<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { CircleDollarSign, TrendingUp, ReceiptText, TrendingDown } from 'lucide-vue-next';

const stats = [
    { key: 'mrr', label: 'MRR', value: 'R$ 18.420', icon: CircleDollarSign, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'arr', label: 'ARR', value: 'R$ 221.040', icon: TrendingUp, tone: 'bg-blue-100 text-blue-700' },
    { key: 'open', label: 'Faturas em aberto', value: '6', icon: ReceiptText, tone: 'bg-slate-100 text-slate-700' },
    { key: 'churn', label: 'Receita em risco', value: 'R$ 1.290', icon: TrendingDown, tone: 'bg-amber-100 text-amber-700' },
];

const invoices = [
    { contractor: 'Veshop Mix', plan: 'Pro', due: '18/03/2026', amount: 'R$ 399,00', status: 'Pendente' },
    { contractor: 'Veshop Store', plan: 'Business', due: '20/03/2026', amount: 'R$ 799,00', status: 'Pendente' },
    { contractor: 'Doce Encanto', plan: 'Start', due: '12/03/2026', amount: 'R$ 199,00', status: 'Atrasada' },
    { contractor: 'Atacado Litoral', plan: 'Pro', due: '05/03/2026', amount: 'R$ 399,00', status: 'Atrasada' },
];
</script>

<template>
    <Head title="Faturamento SaaS" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Faturamento SaaS">
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
                <h2 class="text-lg font-semibold text-slate-900">Faturas por contratante</h2>
                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Contratante</th>
                                <th class="px-4 py-3">Plano</th>
                                <th class="px-4 py-3">Vencimento</th>
                                <th class="px-4 py-3">Valor</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="invoice in invoices" :key="`${invoice.contractor}-${invoice.due}`">
                                <td class="px-4 py-3 font-semibold text-slate-900">{{ invoice.contractor }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ invoice.plan }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ invoice.due }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ invoice.amount }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="invoice.status === 'Pendente' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'">
                                        {{ invoice.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
