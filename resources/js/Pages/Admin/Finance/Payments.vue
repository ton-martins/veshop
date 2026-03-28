<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Plus, PlugZap, ShieldCheck, CreditCard, HandCoins, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    paymentConfig: {
        type: Object,
        default: () => ({ gateways: [], methods: [], provider_options: [], stats: {} }),
    },
});

const gateways = computed(() => props.paymentConfig?.gateways ?? []);
const methods = computed(() => props.paymentConfig?.methods ?? []);
const stats = computed(() => props.paymentConfig?.stats ?? {});

const paymentStats = computed(() => [
    { key: 'gateways_total', label: 'Gateways', value: Number(stats.value.gateways_total ?? 0), icon: PlugZap },
    { key: 'gateways_active', label: 'Gateways ativos', value: Number(stats.value.gateways_active ?? 0), icon: ShieldCheck },
    { key: 'methods_total', label: 'Formas cadastradas', value: Number(stats.value.methods_total ?? 0), icon: HandCoins },
    { key: 'methods_active', label: 'Formas ativas', value: Number(stats.value.methods_active ?? 0), icon: CreditCard },
]);

const asCurrency = (value) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const manualMethodCodeOptions = [
    { value: 'pix', label: 'Pix manual' },
    { value: 'cash', label: 'Dinheiro' },
    { value: 'debit_card', label: 'Cartão de débito' },
    { value: 'credit_card', label: 'Cartão de crédito' },
    { value: 'installment', label: 'A prazo' },
];

const checkoutModeOptions = [
    { value: 'manual', label: 'Operação manual' },
    { value: 'integrated', label: 'Gateway Mercado Pago Pix' },
];

const methodCodeLabel = (code) => {
    const safe = String(code ?? '').trim().toLowerCase();
    const found = manualMethodCodeOptions.find((item) => item.value === safe)
        || (safe === 'pix' ? { value: 'pix', label: 'Pix' } : null);
    return found?.label || safe || '-';
};

const methodModalOpen = ref(false);
const editingMethod = ref(null);
const methodDeleteOpen = ref(false);
const methodToDelete = ref(null);

const methodForm = useForm({
    payment_gateway_id: null,
    checkout_mode: 'manual',
    gateway_provider: 'manual',
    gateway_name: '',
    gateway_is_active: true,
    gateway_is_default: false,
    gateway_is_sandbox: true,
    mercado_pago_access_token: '',
    mercado_pago_webhook_secret: '',
    code: 'pix',
    name: '',
    is_active: true,
    is_default: false,
    allows_installments: false,
    max_installments: '',
    fee_fixed: '',
    fee_percent: '',
    sort_order: 0,
});

const methodDeleteForm = useForm({});
const isEditingMethod = computed(() => Boolean(editingMethod.value?.id));
const isIntegratedMode = computed(() => String(methodForm.checkout_mode ?? 'manual') === 'integrated');
const methodCodeOptions = computed(() => (
    isIntegratedMode.value
        ? [{ value: 'pix', label: 'Pix (integrado)' }]
        : manualMethodCodeOptions
));

const gatewayConnectionLoading = ref(false);
const gatewayConnectionStatus = ref('idle');
const gatewayConnectionMessage = ref('');

const gatewayConnectionToneClass = computed(() => {
    if (gatewayConnectionStatus.value === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (gatewayConnectionStatus.value === 'error') return 'border-rose-200 bg-rose-50 text-rose-700';
    return 'border-slate-200 bg-slate-50 text-slate-700';
});

const resetGatewayConnectionFeedback = () => {
    gatewayConnectionStatus.value = 'idle';
    gatewayConnectionMessage.value = '';
};

const resetMethodForm = (sortOrder = 0) => {
    methodForm.transform((data) => data);
    methodForm.reset();
    methodForm.clearErrors();
    methodForm.payment_gateway_id = null;
    methodForm.checkout_mode = 'manual';
    methodForm.gateway_provider = 'manual';
    methodForm.gateway_name = '';
    methodForm.gateway_is_active = true;
    methodForm.gateway_is_default = false;
    methodForm.gateway_is_sandbox = true;
    methodForm.mercado_pago_access_token = '';
    methodForm.mercado_pago_webhook_secret = '';
    methodForm.code = 'pix';
    methodForm.name = '';
    methodForm.is_active = true;
    methodForm.is_default = false;
    methodForm.allows_installments = false;
    methodForm.max_installments = '';
    methodForm.fee_fixed = '';
    methodForm.fee_percent = '';
    methodForm.sort_order = Number(sortOrder || 0);
    resetGatewayConnectionFeedback();
};

watch(isIntegratedMode, (enabled) => {
    if (enabled) {
        methodForm.code = 'pix';
        methodForm.gateway_provider = 'mercado_pago';
        methodForm.allows_installments = false;
        methodForm.max_installments = '';
        return;
    }

    methodForm.gateway_provider = 'manual';
    methodForm.payment_gateway_id = null;
    methodForm.mercado_pago_access_token = '';
    methodForm.mercado_pago_webhook_secret = '';
});

const openCreateMethod = () => {
    editingMethod.value = null;
    resetMethodForm((methods.value?.length ?? 0) + 10);
    methodModalOpen.value = true;
};

const openEditMethod = (method) => {
    editingMethod.value = method;

    const linkedGateway = gateways.value.find((gateway) => Number(gateway.id) === Number(method.payment_gateway_id ?? 0));
    const mode = String(method.checkout_mode ?? (method.payment_gateway_id ? 'integrated' : 'manual'));

    methodForm.payment_gateway_id = method.payment_gateway_id ?? null;
    methodForm.checkout_mode = mode === 'integrated' ? 'integrated' : 'manual';
    methodForm.gateway_provider = methodForm.checkout_mode === 'integrated'
        ? String(method.payment_gateway_provider ?? linkedGateway?.provider ?? 'mercado_pago')
        : 'manual';
    methodForm.gateway_name = String(method.payment_gateway_name ?? linkedGateway?.name ?? '');
    methodForm.gateway_is_active = method.payment_gateway_is_active !== null
        ? Boolean(method.payment_gateway_is_active)
        : Boolean(linkedGateway?.is_active ?? true);
    methodForm.gateway_is_default = method.payment_gateway_is_default !== null
        ? Boolean(method.payment_gateway_is_default)
        : Boolean(linkedGateway?.is_default ?? false);
    methodForm.gateway_is_sandbox = method.payment_gateway_is_sandbox !== null
        ? Boolean(method.payment_gateway_is_sandbox)
        : Boolean(linkedGateway?.is_sandbox ?? true);
    methodForm.mercado_pago_access_token = '';
    methodForm.mercado_pago_webhook_secret = '';

    methodForm.code = String(method.code ?? 'pix');
    methodForm.name = String(method.name ?? '');
    methodForm.is_active = Boolean(method.is_active);
    methodForm.is_default = Boolean(method.is_default);
    methodForm.allows_installments = Boolean(method.allows_installments);
    methodForm.max_installments = method.max_installments ?? '';
    methodForm.fee_fixed = method.fee_fixed ?? '';
    methodForm.fee_percent = method.fee_percent ?? '';
    methodForm.sort_order = Number(method.sort_order ?? 0);
    methodForm.clearErrors();
    resetGatewayConnectionFeedback();
    methodModalOpen.value = true;
};

const closeMethodModal = () => {
    methodModalOpen.value = false;
    editingMethod.value = null;
    resetMethodForm();
};

const submitMethod = () => {
    const isIntegrated = isIntegratedMode.value;

    const payload = {
        payment_gateway_id: isIntegrated
            ? (methodForm.payment_gateway_id === null || methodForm.payment_gateway_id === ''
                ? null
                : Number(methodForm.payment_gateway_id))
            : null,
        checkout_mode: isIntegrated ? 'integrated' : 'manual',
        gateway_provider: isIntegrated ? 'mercado_pago' : 'manual',
        gateway_name: isIntegrated ? String(methodForm.gateway_name || '').trim() : '',
        gateway_is_active: isIntegrated ? Boolean(methodForm.gateway_is_active) : false,
        gateway_is_default: isIntegrated ? Boolean(methodForm.gateway_is_default) : false,
        gateway_is_sandbox: isIntegrated ? Boolean(methodForm.gateway_is_sandbox) : true,
        mercado_pago_access_token: isIntegrated ? String(methodForm.mercado_pago_access_token || '').trim() : '',
        mercado_pago_webhook_secret: isIntegrated ? String(methodForm.mercado_pago_webhook_secret || '').trim() : '',
        code: isIntegrated ? 'pix' : methodForm.code,
        name: methodForm.name,
        is_active: Boolean(methodForm.is_active),
        is_default: Boolean(methodForm.is_default),
        allows_installments: isIntegrated ? false : Boolean(methodForm.allows_installments),
        max_installments: !isIntegrated && methodForm.allows_installments && methodForm.max_installments !== ''
            ? Number(methodForm.max_installments)
            : null,
        fee_fixed: methodForm.fee_fixed === '' ? null : Number(methodForm.fee_fixed),
        fee_percent: methodForm.fee_percent === '' ? null : Number(methodForm.fee_percent),
        sort_order: Number(methodForm.sort_order || 0),
    };

    if (isEditingMethod.value) {
        methodForm.transform(() => payload).put(route('admin.finance.methods.update', editingMethod.value.id), {
            preserveScroll: true,
            onSuccess: closeMethodModal,
        });
        return;
    }

    methodForm.transform(() => payload).post(route('admin.finance.methods.store'), {
        preserveScroll: true,
        onSuccess: closeMethodModal,
    });
};

const openDeleteMethod = (method) => {
    methodToDelete.value = method;
    methodDeleteOpen.value = true;
};

const closeDeleteMethod = () => {
    methodToDelete.value = null;
    methodDeleteOpen.value = false;
};

const removeMethod = () => {
    if (!methodToDelete.value?.id) return;

    methodDeleteForm.delete(route('admin.finance.methods.destroy', methodToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteMethod,
    });
};

const runGatewayConnectionTest = async () => {
    if (!isIntegratedMode.value) return;

    gatewayConnectionLoading.value = true;
    gatewayConnectionStatus.value = 'idle';
    gatewayConnectionMessage.value = '';

    const payload = {
        provider: 'mercado_pago',
        is_sandbox: Boolean(methodForm.gateway_is_sandbox),
        gateway_id: methodForm.payment_gateway_id ?? (editingMethod.value?.payment_gateway_id ?? null),
        mercado_pago_access_token: methodForm.mercado_pago_access_token,
        mercado_pago_webhook_secret: methodForm.mercado_pago_webhook_secret,
    };

    try {
        let response;

        if (window?.axios?.post) {
            response = await window.axios.post(route('admin.finance.gateways.test'), payload, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
        } else {
            response = await fetch(route('admin.finance.gateways.test'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload),
            });

            response = { data: await response.json() };
        }

        const data = response.data ?? {};
        if (data.ok === false) {
            gatewayConnectionStatus.value = 'error';
            gatewayConnectionMessage.value = String(data?.message ?? 'Não foi possível validar a conexão.');
            return;
        }

        gatewayConnectionStatus.value = 'success';
        gatewayConnectionMessage.value = String(data.message ?? 'Conexão validada com sucesso.');
    } catch (error) {
        gatewayConnectionStatus.value = 'error';
        gatewayConnectionMessage.value = String(error?.response?.data?.message ?? 'Não foi possível validar a conexão com o gateway.');
    } finally {
        gatewayConnectionLoading.value = false;
    }
};
</script>

<template>
    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Pagamentos">
        <Head title="Pagamentos" />

        <section class="space-y-4">
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="stat in paymentStats" :key="stat.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">{{ stat.label }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ stat.value }}</p>
                        </div>
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700"><component :is="stat.icon" class="h-5 w-5" /></span>
                    </div>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Configurações de checkout</h2>
                        <p class="text-xs text-slate-500">Cadastre a forma de pagamento com operação manual ou Mercado Pago Pix integrado no mesmo modal.</p>
                    </div>
                    <button type="button" class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white" @click="openCreateMethod">
                        <Plus class="h-3.5 w-3.5" />
                        Nova forma
                    </button>
                </div>

                <div v-if="!methods.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                    Nenhuma forma de pagamento cadastrada.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="method in methods" :key="`method-${method.id}`" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ method.name }}</p>
                                <p class="text-xs text-slate-500">{{ methodCodeLabel(method.code) }}</p>
                            </div>
                            <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="method.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                {{ method.is_active ? 'Ativa' : 'Inativa' }}
                            </span>
                        </div>

                        <div class="mt-3 space-y-2 text-xs text-slate-700">
                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <strong>Operação:</strong>
                                {{ method.checkout_mode === 'integrated' ? 'Gateway Mercado Pago Pix' : 'Manual' }}
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <strong>Taxas:</strong>
                                {{ method.fee_percent !== null ? `${method.fee_percent}%` : '-' }} / {{ method.fee_fixed !== null ? asCurrency(method.fee_fixed) : '-' }}
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <strong>Gateway:</strong>
                                {{ method.payment_gateway_name || 'Sem gateway' }}
                                <template v-if="method.payment_gateway_id">
                                    · {{ method.payment_gateway_is_sandbox ? 'Sandbox' : 'Produção' }}
                                </template>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <button type="button" class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:flex-none" @click="openEditMethod(method)">
                                <Pencil class="h-3.5 w-3.5" />
                                Editar
                            </button>
                            <button type="button" class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-rose-200 bg-white px-2 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 sm:flex-none" @click="openDeleteMethod(method)">
                                <Trash2 class="h-3.5 w-3.5" />
                                Excluir
                            </button>
                        </div>
                    </article>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-slate-900">Gateways configurados</h3>
                <div v-if="!gateways.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                    Nenhum gateway configurado.
                </div>
                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="gateway in gateways" :key="`gateway-${gateway.id}`" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                        <p class="text-sm font-semibold text-slate-900">{{ gateway.name }}</p>
                        <p>{{ gateway.provider }} · {{ gateway.is_sandbox ? 'Sandbox' : 'Produção' }}</p>
                        <p>Token: {{ gateway.credentials_status?.access_token_configured ? 'configurado' : 'pendente' }}</p>
                    </article>
                </div>
            </section>
        </section>

        <Modal :show="methodModalOpen" max-width="5xl" @close="closeMethodModal">
            <WizardModalFrame
                :title="isEditingMethod ? 'Editar forma de pagamento' : 'Nova forma de pagamento'"
                description="Configure a operação manual ou integração Mercado Pago Pix em um único fluxo."
                :steps="['Dados da forma']"
                :current-step="1"
                @close="closeMethodModal"
            >
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo de operação</label>
                        <UiSelect v-model="methodForm.checkout_mode" :options="checkoutModeOptions" button-class="mt-1" />
                        <p v-if="methodForm.errors.checkout_mode" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.checkout_mode }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Código</label>
                        <UiSelect v-model="methodForm.code" :options="methodCodeOptions" button-class="mt-1" :disabled="isIntegratedMode" />
                        <p v-if="methodForm.errors.code" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.code }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome de exibição</label>
                        <input v-model="methodForm.name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Ex.: Pix principal">
                        <p v-if="methodForm.errors.name" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ordem</label>
                        <input v-model="methodForm.sort_order" type="number" min="0" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        <p v-if="methodForm.errors.sort_order" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.sort_order }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Taxa fixa (R$)</label>
                        <BrlMoneyInput v-model="methodForm.fee_fixed" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="R$ 0,00" />
                        <p v-if="methodForm.errors.fee_fixed" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.fee_fixed }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Taxa percentual (%)</label>
                        <input v-model="methodForm.fee_percent" type="number" step="0.01" min="0" max="100" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="0,00">
                        <p v-if="methodForm.errors.fee_percent" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.fee_percent }}</p>
                    </div>
                </div>

                <div v-if="!isIntegratedMode" class="mt-3 grid gap-2 sm:grid-cols-3">
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="methodForm.is_active" type="checkbox" class="rounded border-slate-300">
                        Ativa
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="methodForm.is_default" type="checkbox" class="rounded border-slate-300">
                        Padrão
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="methodForm.allows_installments" type="checkbox" class="rounded border-slate-300">
                        Permite parcelamento
                    </label>
                </div>

                <div v-else class="mt-3 space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome do gateway</label>
                            <input v-model="methodForm.gateway_name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Ex.: Gateway Pix principal">
                            <p v-if="methodForm.errors.gateway_name" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.gateway_name }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ambiente</label>
                            <UiSelect
                                v-model="methodForm.gateway_is_sandbox"
                                :options="[{ value: true, label: 'Sandbox' }, { value: false, label: 'Produção' }]"
                                button-class="mt-1"
                            />
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Access token Mercado Pago</label>
                            <input
                                v-model="methodForm.mercado_pago_access_token"
                                type="password"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                :placeholder="isEditingMethod ? 'Deixe em branco para manter o token atual' : 'APP_USR-...'"
                                autocomplete="off"
                            >
                            <p v-if="methodForm.errors.mercado_pago_access_token" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.mercado_pago_access_token }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Token do webhook</label>
                            <input
                                v-model="methodForm.mercado_pago_webhook_secret"
                                type="password"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                :placeholder="isEditingMethod ? 'Deixe em branco para manter o token atual' : 'Opcional'"
                                autocomplete="off"
                            >
                            <p v-if="methodForm.errors.mercado_pago_webhook_secret" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.mercado_pago_webhook_secret }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700">
                            <input v-model="methodForm.gateway_is_active" type="checkbox" class="rounded border-slate-300">
                            Gateway ativo
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700">
                            <input v-model="methodForm.gateway_is_default" type="checkbox" class="rounded border-slate-300">
                            Gateway padrão
                        </label>
                    </div>

                    <div class="space-y-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="gatewayConnectionLoading"
                            @click="runGatewayConnectionTest"
                        >
                            {{ gatewayConnectionLoading ? 'Testando conexão...' : 'Testar conexão Mercado Pago' }}
                        </button>
                        <div v-if="gatewayConnectionMessage" class="rounded-xl border px-3 py-2 text-xs font-semibold" :class="gatewayConnectionToneClass">
                            {{ gatewayConnectionMessage }}
                        </div>
                    </div>
                </div>

                <div v-if="!isIntegratedMode && methodForm.allows_installments" class="mt-3">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Máximo de parcelas</label>
                    <input v-model="methodForm.max_installments" type="number" min="2" max="24" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    <p v-if="methodForm.errors.max_installments" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.max_installments }}</p>
                </div>

                <div v-if="isIntegratedMode" class="mt-3 grid gap-2 sm:grid-cols-2">
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="methodForm.is_active" type="checkbox" class="rounded border-slate-300">
                        Forma ativa
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="methodForm.is_default" type="checkbox" class="rounded border-slate-300">
                        Forma padrão
                    </label>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeMethodModal">
                            Cancelar
                        </button>
                        <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="methodForm.processing" @click="submitMethod">
                            {{ methodForm.processing ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </template>
            </WizardModalFrame>
        </Modal>

        <DeleteConfirmModal
            :show="methodDeleteOpen"
            title="Excluir forma de pagamento"
            message="Tem certeza que deseja excluir esta forma de pagamento?"
            :item-label="methodToDelete?.name ? `Forma: ${methodToDelete.name}` : ''"
            :processing="methodDeleteForm.processing"
            @close="closeDeleteMethod"
            @confirm="removeMethod"
        />
    </AuthenticatedLayout>
</template>
