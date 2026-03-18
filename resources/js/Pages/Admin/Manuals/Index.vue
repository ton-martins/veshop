<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useBranding } from '@/branding';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Settings2,
    CreditCard,
    Package,
    ShoppingBag,
    ShieldCheck,
    CheckCircle2,
    CircleAlert,
    ExternalLink,
} from 'lucide-vue-next';

const props = defineProps({
    initialTab: {
        type: String,
        default: 'settings',
    },
});

const page = usePage();
const { normalizeHex, withAlpha, secondaryColor } = useBranding();
const currentContractor = computed(() => page.props.contractorContext?.current ?? null);
const tabAccentColor = computed(() =>
    normalizeHex(currentContractor.value?.brand_primary_color || '', secondaryColor.value),
);
const manualStyles = computed(() => ({
    '--manual-tab-active': tabAccentColor.value,
    '--manual-tab-active-soft': withAlpha(tabAccentColor.value, 0.12),
    '--manual-tab-active-border': withAlpha(tabAccentColor.value, 0.28),
}));

const allowedTabs = new Set(['settings', 'finance', 'products', 'orders', 'best_practices']);
const activeTab = ref(allowedTabs.has(props.initialTab) ? props.initialTab : 'settings');

watch(
    () => props.initialTab,
    (tab) => {
        activeTab.value = allowedTabs.has(tab) ? tab : 'settings';
    },
);

const setActiveTab = (tab) => {
    if (!allowedTabs.has(tab)) return;
    if (activeTab.value === tab) return;

    activeTab.value = tab;

    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState(window.history.state, '', url.toString());
    }
};

const tabs = [
    { key: 'settings', label: 'Configurações', icon: Settings2 },
    { key: 'finance', label: 'Financeiro', icon: CreditCard },
    { key: 'products', label: 'Produtos', icon: Package },
    { key: 'orders', label: 'Pedidos', icon: ShoppingBag },
    { key: 'best_practices', label: 'Boas práticas', icon: ShieldCheck },
];

const mercadoPagoSteps = [
    'Acesse o painel do Mercado Pago com a sua conta em mercadopago.com.br/developers.',
    'Crie uma aplicação (se ainda não existir) e copie o Access Token da credencial de teste (TEST-...) ou produção (APP_USR-...).',
    'No Veshop, vá em Contas > Pagamentos > Novo gateway.',
    'Selecione o provedor Mercado Pago, informe um nome, marque Ativo e defina Sandbox conforme o token usado.',
    'Cole o Access Token no campo "Access token Mercado Pago".',
    'Crie um token forte para o webhook (32+ caracteres) e informe no campo "Token do webhook".',
    'Salve o gateway e confira se o card mostra Token/Webhook como "configurado".',
    'Crie uma forma de pagamento Pix vinculada ao gateway Mercado Pago e marque como ativa.',
    'Na loja pública, finalize um pedido com Pix para validar a geração do QR Code e do código copia e cola.',
];

const mercadoPagoChecklist = [
    'Gateway Mercado Pago ativo',
    'Forma Pix vinculada ao gateway',
    'Access token válido para o ambiente (sandbox/produção)',
    'Token de webhook configurado no Veshop',
    'Pedido de teste com status aguardando pagamento',
];
</script>

<template>
    <Head title="Manuais" />

    <AuthenticatedLayout
        area="admin"
        header-variant="compact"
        header-title="Manuais"
        :show-table-view-toggle="false"
    >
        <section class="space-y-4" :style="manualStyles">
            <div class="manual-tabs-shell">
                <div class="manual-tabs-track">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="manual-tab"
                        :class="activeTab === tab.key ? 'is-active' : ''"
                        @click="setActiveTab(tab.key)"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        <span class="truncate">{{ tab.label }}</span>
                    </button>
                </div>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <template v-if="activeTab === 'settings'">
                    <h2 class="text-sm font-semibold text-slate-900">Configurações essenciais</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Use esta aba para validar os dados iniciais da sua operação antes de publicar a loja.
                    </p>

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Loja virtual</p>
                            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                <li>• Nome da marca e cor principal definidos.</li>
                                <li>• Endereço e canais de contato atualizados.</li>
                                <li>• Métodos de entrega configurados.</li>
                            </ul>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Acesso e segurança</p>
                            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                <li>• E-mail de administrador validado.</li>
                                <li>• 2FA habilitado para acesso ao painel.</li>
                                <li>• Senha forte e sem compartilhamento.</li>
                            </ul>
                        </article>
                    </div>
                </template>

                <template v-else-if="activeTab === 'finance'">
                    <h2 class="text-sm font-semibold text-slate-900">Guia de integração Mercado Pago (PIX)</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Siga este passo a passo para ativar a cobrança PIX com QR Code e copia e cola.
                    </p>

                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-sm text-emerald-800">
                        <p class="font-semibold">Resultado esperado</p>
                        <p class="mt-1">
                            Ao finalizar um pedido com Pix, o sistema cria a cobrança no Mercado Pago e disponibiliza os dados de pagamento ao cliente.
                        </p>
                    </div>

                    <ol class="mt-4 space-y-3 text-sm text-slate-700">
                        <li
                            v-for="(step, index) in mercadoPagoSteps"
                            :key="`mp-step-${index}`"
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                        >
                            <span class="font-semibold text-slate-900">{{ index + 1 }}.</span>
                            <span class="ml-1">{{ step }}</span>
                        </li>
                    </ol>

                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                        <article class="rounded-xl border border-slate-200 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Checklist de validação</p>
                            <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                <li
                                    v-for="item in mercadoPagoChecklist"
                                    :key="item"
                                    class="flex items-start gap-2"
                                >
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 text-emerald-600" />
                                    <span>{{ item }}</span>
                                </li>
                            </ul>
                        </article>

                        <article class="rounded-xl border border-slate-200 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dúvidas comuns</p>
                            <ul class="mt-2 space-y-2 text-sm text-slate-700">
                                <li class="flex items-start gap-2">
                                    <CircleAlert class="mt-0.5 h-4 w-4 text-amber-600" />
                                    <span>O token TEST-... funciona apenas em sandbox.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <CircleAlert class="mt-0.5 h-4 w-4 text-amber-600" />
                                    <span>Se o token for inválido, o pedido não cria cobrança PIX.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <CircleAlert class="mt-0.5 h-4 w-4 text-amber-600" />
                                    <span>Troque imediatamente o token se houver suspeita de exposição.</span>
                                </li>
                            </ul>
                            <a
                                href="https://www.mercadopago.com.br/developers/panel/app"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-slate-700 hover:text-slate-900"
                            >
                                Abrir documentação oficial
                                <ExternalLink class="h-3.5 w-3.5" />
                            </a>
                        </article>
                    </div>
                </template>

                <template v-else-if="activeTab === 'products'">
                    <h2 class="text-sm font-semibold text-slate-900">Cadastro de produtos</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Mantenha o catálogo organizado por categoria, SKU e estoque para evitar rupturas e erros no checkout.
                    </p>

                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li>• Use nome objetivo e descrição curta com benefícios.</li>
                        <li>• Cadastre SKU único para cada item.</li>
                        <li>• Atualize o estoque sempre que houver compra de fornecedor.</li>
                        <li>• Revise preço de venda e margens periodicamente.</li>
                    </ul>
                </template>

                <template v-else-if="activeTab === 'orders'">
                    <h2 class="text-sm font-semibold text-slate-900">Fluxo de pedidos</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Acompanhe os pedidos em ordem de prioridade para reduzir atrasos e melhorar a experiência do cliente.
                    </p>

                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sequência recomendada</p>
                            <ol class="mt-2 space-y-1 text-sm text-slate-700">
                                <li>1. Confirmar pedido.</li>
                                <li>2. Validar pagamento.</li>
                                <li>3. Separar ou produzir.</li>
                                <li>4. Entregar ou concluir.</li>
                            </ol>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alertas</p>
                            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                <li>• Pedidos sem confirmação por muito tempo.</li>
                                <li>• Divergência entre valor do pedido e pagamento.</li>
                                <li>• Itens sem saldo no estoque.</li>
                            </ul>
                        </article>
                    </div>
                </template>

                <template v-else>
                    <h2 class="text-sm font-semibold text-slate-900">Boas práticas operacionais</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Estas recomendações ajudam a manter segurança, consistência de dados e previsibilidade operacional.
                    </p>

                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        <li>• Trabalhe com rotina diária de conferência de pedidos e caixa.</li>
                        <li>• Use padrão único para nomes de produtos e categorias.</li>
                        <li>• Revise permissões e acessos do painel periodicamente.</li>
                        <li>• Gere relatórios semanais para acompanhar performance.</li>
                        <li>• Faça backup antes de mudanças críticas de configuração.</li>
                    </ul>
                </template>
            </section>
        </section>
    </AuthenticatedLayout>
</template>

<style scoped>
.manual-tabs-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.manual-tabs-shell::-webkit-scrollbar {
    height: 6px;
}

.manual-tabs-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

.manual-tabs-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #ffffff;
    padding: 0.3rem;
}

.manual-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid transparent;
    border-radius: 0.72rem;
    min-height: 38px;
    padding: 0.6rem 0.95rem;
    color: #334155;
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.manual-tab:hover {
    background: #f8fafc;
    color: #0f172a;
}

.manual-tab.is-active {
    border-color: var(--manual-tab-active-border);
    background: var(--manual-tab-active);
    color: #ffffff;
}
</style>
