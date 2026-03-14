<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Layers, Users2, CircleDollarSign, TrendingDown } from 'lucide-vue-next';

const stats = [
    { key: 'plans', label: 'Planos ativos', value: '4', icon: Layers, tone: 'bg-slate-100 text-slate-700' },
    { key: 'subs', label: 'Assinaturas', value: '42', icon: Users2, tone: 'bg-blue-100 text-blue-700' },
    { key: 'arpa', label: 'ARPA médio', value: 'R$ 438,00', icon: CircleDollarSign, tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'churn', label: 'Churn (mês)', value: '3,8%', icon: TrendingDown, tone: 'bg-amber-100 text-amber-700' },
];

const plans = [
    { name: 'Start', price: 'R$ 199/mês', contractors: 11, features: ['1 usuário admin', 'PDV básico', 'Relatórios essenciais'] },
    { name: 'Pro', price: 'R$ 399/mês', contractors: 18, features: ['Até 5 admins', 'Financeiro completo', 'Estoque avançado'] },
    { name: 'Business', price: 'R$ 799/mês', contractors: 10, features: ['Admins ilimitados', 'Relatórios BI', 'Prioridade suporte'] },
    { name: 'Enterprise', price: 'Sob consulta', contractors: 3, features: ['Customizações', 'SLA dedicado', 'Acompanhamento CS'] },
];
</script>

<template>
    <Head title="Planos e Assinaturas" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Planos e Assinaturas">
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
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <article v-for="plan in plans" :key="plan.name" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold text-slate-900">{{ plan.name }}</h2>
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">{{ plan.contractors }}</span>
                        </div>
                        <p class="mt-1 text-sm font-semibold text-slate-800">{{ plan.price }}</p>
                        <ul class="mt-3 space-y-1 text-xs text-slate-600">
                            <li v-for="feature in plan.features" :key="feature">• {{ feature }}</li>
                        </ul>
                        <button type="button" class="mt-4 inline-flex rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">
                            Editar plano
                        </button>
                    </article>
                </div>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
