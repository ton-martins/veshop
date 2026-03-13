<script setup>
import { computed, ref } from 'vue';

const scenarios = {
    moda: {
        label: 'Moda',
        averageTicket: 189,
        margin: 0.42,
        whatsappRate: 0.74,
        recurringRate: 0.28,
    },
    mercado: {
        label: 'Mercado',
        averageTicket: 84,
        margin: 0.19,
        whatsappRate: 0.61,
        recurringRate: 0.47,
    },
    autoeletrica: {
        label: 'Autoelétrica',
        averageTicket: 236,
        margin: 0.34,
        whatsappRate: 0.57,
        recurringRate: 0.22,
    },
    bazar: {
        label: 'Bazar',
        averageTicket: 122,
        margin: 0.31,
        whatsappRate: 0.69,
        recurringRate: 0.35,
    },
};

const selectedKey = ref('moda');
const monthlyOrders = ref(180);

const selectedScenario = computed(() => scenarios[selectedKey.value]);

const totalRevenue = computed(() => monthlyOrders.value * selectedScenario.value.averageTicket);
const estimatedMargin = computed(() => totalRevenue.value * selectedScenario.value.margin);
const whatsappOrders = computed(() => Math.round(monthlyOrders.value * selectedScenario.value.whatsappRate));
const recurringCustomers = computed(() => Math.round(monthlyOrders.value * selectedScenario.value.recurringRate));
const paidByPix = computed(() => Math.round(totalRevenue.value * 0.58));

const formatCurrency = (value) =>
    new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
        maximumFractionDigits: 0,
    }).format(value);
</script>

<template>
    <section id="simulador" class="mx-auto w-full max-w-6xl px-4 pb-6 pt-14 sm:px-6 lg:px-8 lg:pt-16">
        <div class="mb-8">
            <p class="veshop-kicker text-xs font-bold uppercase tracking-[0.2em]">Simulador inteligente</p>
            <h2 class="mt-3 font-display text-3xl font-extrabold text-slate-900 sm:text-4xl">
                Veja o impacto de gestão por nicho em tempo real.
            </h2>
        </div>

        <div class="grid gap-4 lg:grid-cols-[0.75fr_1.25fr]">
            <aside class="veshop-card p-5">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Escolha o nicho</p>
                <div class="mt-4 space-y-2">
                    <button
                        v-for="(scenario, key) in scenarios"
                        :key="key"
                        type="button"
                        class="veshop-sim-option w-full rounded-xl px-4 py-3 text-left text-sm font-semibold transition"
                        :class="{ 'veshop-sim-option-active': selectedKey === key }"
                        @click="selectedKey = key"
                    >
                        {{ scenario.label }}
                    </button>
                </div>
            </aside>

            <article class="veshop-card p-6 sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">
                        Cenário: {{ selectedScenario.label }}
                    </p>
                    <p class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                        Pedidos/mês: {{ monthlyOrders }}
                    </p>
                </div>

                <div class="mt-5">
                    <label for="orders" class="text-sm font-semibold text-slate-700">Volume mensal de pedidos</label>
                    <input
                        id="orders"
                        v-model.number="monthlyOrders"
                        type="range"
                        min="20"
                        max="800"
                        step="5"
                        class="veshop-range mt-3 w-full"
                    />
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="veshop-chip rounded-xl p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Faturamento estimado</p>
                        <p class="mt-2 font-display text-2xl font-extrabold text-slate-900">{{ formatCurrency(totalRevenue) }}</p>
                    </div>
                    <div class="veshop-chip rounded-xl p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Margem estimada</p>
                        <p class="mt-2 font-display text-2xl font-extrabold text-slate-900">{{ formatCurrency(estimatedMargin) }}</p>
                    </div>
                    <div class="veshop-chip rounded-xl p-4 sm:col-span-2 lg:col-span-1">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">Pedidos via WhatsApp</p>
                        <p class="mt-2 font-display text-2xl font-extrabold text-slate-900">{{ whatsappOrders }}</p>
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    <div>
                        <div class="mb-1 flex items-center justify-between text-xs font-semibold text-slate-600">
                            <span>Clientes recorrentes</span>
                            <span>{{ recurringCustomers }} / {{ monthlyOrders }}</span>
                        </div>
                        <div class="veshop-progress-track">
                            <div
                                class="veshop-progress-fill"
                                :style="{ width: `${Math.max(4, (recurringCustomers / monthlyOrders) * 100)}%` }"
                            ></div>
                        </div>
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between text-xs font-semibold text-slate-600">
                            <span>Pagamentos via PIX</span>
                            <span>{{ formatCurrency(paidByPix) }}</span>
                        </div>
                        <div class="veshop-progress-track">
                            <div
                                class="veshop-progress-fill is-alt"
                                :style="{ width: `${Math.max(4, (paidByPix / totalRevenue) * 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
