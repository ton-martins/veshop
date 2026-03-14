<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ShoppingBag, Boxes, CircleDollarSign, AlertTriangle, Search, Filter, Plus } from 'lucide-vue-next';

const stats = [
    { key: 'new', label: 'Novos pedidos', value: '14', icon: ShoppingBag, tone: 'bg-slate-100 text-slate-700' },
    { key: 'picking', label: 'Em separação', value: '9', icon: Boxes, tone: 'bg-blue-100 text-blue-700' },
    { key: 'invoiced', label: 'Faturados hoje', value: '11', icon: CircleDollarSign, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'late', label: 'Atrasados', value: '2', icon: AlertTriangle, tone: 'bg-amber-100 text-amber-700' },
];

const orders = [
    { code: '#PED-3041', client: 'Cristina Nascimento', channel: 'PDV', total: 'R$ 89,90', status: 'Separação' },
    { code: '#PED-3040', client: 'Cakeflow Eventos', channel: 'Online', total: 'R$ 320,00', status: 'Faturado' },
    { code: '#PED-3039', client: 'João Martins', channel: 'WhatsApp', total: 'R$ 52,00', status: 'Novo' },
    { code: '#PED-3038', client: 'Buffet Estrela', channel: 'PDV', total: 'R$ 640,00', status: 'Atrasado' },
];

const kanban = [
    { name: 'Novo', qty: 14, tone: 'bg-slate-100 text-slate-700' },
    { name: 'Separação', qty: 9, tone: 'bg-blue-100 text-blue-700' },
    { name: 'Entrega', qty: 7, tone: 'bg-amber-100 text-amber-700' },
    { name: 'Concluído', qty: 34, tone: 'bg-emerald-100 text-emerald-700' },
];
</script>

<template>
    <Head title="Pedidos" />

    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Pedidos">
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
                        <input type="text" placeholder="Buscar pedido por código ou cliente" class="w-full bg-transparent text-sm text-slate-700 outline-none" />
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            <Filter class="h-3.5 w-3.5" />
                            Status
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            <Plus class="h-3.5 w-3.5" />
                            Novo pedido
                        </button>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-[1.7fr_1fr]">
                    <div class="overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Pedido</th>
                                    <th class="px-4 py-3">Canal</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="order in orders" :key="order.code">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ order.code }}</p>
                                        <p class="text-xs text-slate-500">{{ order.client }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ order.channel }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ order.total }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="order.status === 'Faturado' ? 'bg-emerald-100 text-emerald-700' : order.status === 'Atrasado' ? 'bg-amber-100 text-amber-700' : order.status === 'Separação' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700'">
                                            {{ order.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <aside class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <h2 class="text-sm font-semibold text-slate-900">Pipeline de pedidos</h2>
                        <ul class="space-y-2">
                            <li v-for="item in kanban" :key="item.name" class="flex items-center justify-between rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
                                <span class="text-sm font-semibold text-slate-700">{{ item.name }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="item.tone">{{ item.qty }}</span>
                            </li>
                        </ul>
                    </aside>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
