<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { useBranding } from '@/branding';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Plus, PlugZap, ShieldCheck, CreditCard, HandCoins, Pencil, Trash2, X } from 'lucide-vue-next';

const props = defineProps({
    paymentConfig: {
        type: Object,
        default: () => ({ gateways: [], methods: [], provider_options: [], stats: {} }),
    },
});

const gateways = computed(() => props.paymentConfig?.gateways ?? []);
const methods = computed(() => props.paymentConfig?.methods ?? []);
const stats = computed(() => props.paymentConfig?.stats ?? {});
const integratedGatewayOptions = computed(() => {
    const raw = Array.isArray(props.paymentConfig?.gateway_catalog?.automatic)
        ? props.paymentConfig.gateway_catalog.automatic
        : [];

    if (raw.length > 0) {
        return raw
            .map((item) => ({
                value: String(item.value ?? '').trim().toLowerCase(),
                label: String(item.label ?? '').trim(),
                description: String(item.description ?? '').trim(),
            }))
            .filter((item) => item.value !== '' && item.label !== '');
    }

    return [
        {
            value: 'mercado_pago',
            label: 'Mercado Pago',
            description: 'Checkout automático com Pix, cartão e boleto.',
        },
    ];
});
const manualMethods = computed(() => (
    methods.value.filter((method) => String(method.checkout_mode || '') !== 'integrated')
));
const integratedMethods = computed(() => (
    methods.value.filter((method) => String(method.checkout_mode || '') === 'integrated')
));
const integratedMethodOptions = computed(() => {
    const options = Array.isArray(props.paymentConfig?.integrated_method_options)
        ? props.paymentConfig.integrated_method_options
        : [];

    if (options.length > 0) {
        return options.map((option) => ({
            code: String(option.code ?? '').trim().toLowerCase(),
            label: String(option.label ?? ''),
            description: String(option.description ?? ''),
            supports_installments: Boolean(option.supports_installments),
        }));
    }

    return [
        { code: 'pix', label: 'Pix', description: 'Pagamento instantâneo com QR Code.', supports_installments: false },
        { code: 'credit_card', label: 'Cartão de crédito', description: 'Pagamento com parcelamento opcional.', supports_installments: true },
        { code: 'debit_card', label: 'Cartão de débito', description: 'Pagamento no débito à vista.', supports_installments: false },
        { code: 'boleto', label: 'Boleto', description: 'Pagamento por boleto.', supports_installments: false },
    ];
});

const page = usePage();
const { normalizeHex, withAlpha, secondaryColor } = useBranding();
const currentContractor = computed(() => page.props.contractorContext?.current ?? null);
const tabAccentColor = computed(() =>
    normalizeHex(currentContractor.value?.brand_primary_color || '', secondaryColor.value),
);
const paymentsUiStyles = computed(() => ({
    '--finance-tab-active': tabAccentColor.value,
    '--finance-tab-active-soft': withAlpha(tabAccentColor.value, 0.12),
    '--finance-tab-active-border': withAlpha(tabAccentColor.value, 0.28),
}));

const allowedPaymentTabs = new Set(['automatic', 'manual']);
const activePaymentTab = ref('automatic');
const paymentTabs = [
    { key: 'automatic', label: 'Automático', icon: PlugZap },
    { key: 'manual', label: 'Formas manuais', icon: HandCoins },
];

const setActivePaymentTab = (tab) => {
    if (!allowedPaymentTabs.has(tab)) return;
    activePaymentTab.value = tab;
};

const paymentStats = computed(() => [
    { key: 'gateways_total', label: 'Gateways', value: Number(stats.value.gateways_total ?? 0), icon: PlugZap },
    { key: 'gateways_active', label: 'Gateways ativos', value: Number(stats.value.gateways_active ?? 0), icon: ShieldCheck },
    { key: 'methods_total', label: 'Formas cadastradas', value: Number(stats.value.methods_total ?? 0), icon: HandCoins },
    { key: 'methods_active', label: 'Formas ativas', value: Number(stats.value.methods_active ?? 0), icon: CreditCard },
]);

const asCurrency = (value) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const manualMethodCodeOptions = [
    { value: 'pix', label: 'Pix manual' },
    { value: 'boleto', label: 'Boleto manual' },
    { value: 'cash', label: 'Dinheiro' },
    { value: 'debit_card', label: 'Cartão de débito' },
    { value: 'credit_card', label: 'Cartão de crédito' },
    { value: 'installment', label: 'A prazo' },
];

const methodCodeLabel = (code) => {
    const safe = String(code ?? '').trim().toLowerCase();
    const manual = manualMethodCodeOptions.find((item) => item.value === safe);
    if (manual) return manual.label;

    const integrated = integratedMethodOptions.value.find((item) => String(item.code ?? '').trim().toLowerCase() === safe);
    if (integrated?.label) return integrated.label;

    if (safe === 'pix') return 'Pix';
    return safe || '-';
};

const autoMethodLabel = (code) => {
    const safeCode = String(code ?? '').trim().toLowerCase();
    const found = integratedMethodOptions.value.find((item) => String(item.code || '').toLowerCase() === safeCode);
    return found?.label || methodCodeLabel(safeCode);
};

const resolveDefaultProviderCode = () => {
    const first = integratedGatewayOptions.value[0];
    return String(first?.value ?? 'mercado_pago');
};

const resolveDefaultGatewayForProvider = (providerCode) => {
    const safeCode = String(providerCode ?? '').trim().toLowerCase();
    if (safeCode === '') return null;

    const scoped = gateways.value.filter((gateway) => String(gateway.provider || '').toLowerCase() === safeCode);
    if (!scoped.length) return null;

    if (safeCode === 'mercado_pago') {
        const configDefaultId = Number(props.paymentConfig?.mercado_pago?.default_gateway_id ?? 0);
        if (configDefaultId > 0) {
            const byConfig = scoped.find((gateway) => Number(gateway.id) === configDefaultId);
            if (byConfig) return byConfig;
        }
    }

    return scoped.find((gateway) => Boolean(gateway.is_default))
        || scoped[0]
        || null;
};

const autoConfigForm = useForm({
    provider_code: 'mercado_pago',
    gateway_id: null,
    gateway_is_sandbox: true,
    mercado_pago_access_token: '',
    mercado_pago_webhook_secret: '',
    default_code: 'pix',
    methods: [],
});
const hasMultipleAutomaticGateways = computed(() => integratedGatewayOptions.value.length > 1);
const selectedIntegratedGatewayOption = computed(() => {
    const selectedCode = String(autoConfigForm.provider_code ?? '').trim().toLowerCase();
    return integratedGatewayOptions.value.find((item) => item.value === selectedCode) ?? integratedGatewayOptions.value[0] ?? null;
});

const autoGatewayConnectionLoading = ref(false);
const autoGatewayConnectionStatus = ref('idle');
const autoGatewayConnectionMessage = ref('');
const autoMethodFeedback = ref({});
const showMercadoPagoFeesNotice = ref(true);

const selectedAutoGateway = computed(() => {
    const providerCode = String(autoConfigForm.provider_code ?? '').trim().toLowerCase();
    return resolveDefaultGatewayForProvider(providerCode);
});

const isMercadoPagoSelected = computed(() => String(autoConfigForm.provider_code ?? '').trim().toLowerCase() === 'mercado_pago');
const mercadoPagoOauthReady = computed(() => Boolean(props.paymentConfig?.mercado_pago?.oauth_ready));
const mercadoPagoOauthSchemaReady = computed(() => Boolean(props.paymentConfig?.mercado_pago?.oauth_schema_ready));
const mercadoPagoOauthClientReady = computed(() => Boolean(props.paymentConfig?.mercado_pago?.oauth_client_ready));

const autoGatewayConnectionToneClass = computed(() => {
    if (autoGatewayConnectionStatus.value === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (autoGatewayConnectionStatus.value === 'error') return 'border-rose-200 bg-rose-50 text-rose-700';
    return 'border-slate-200 bg-slate-50 text-slate-700';
});

const clearAutoFeedback = () => {
    autoGatewayConnectionStatus.value = 'idle';
    autoGatewayConnectionMessage.value = '';
    autoMethodFeedback.value = {};
};

const buildAutomaticMethodRows = (gatewayId = null) => {
    const safeGatewayId = Number(gatewayId ?? 0);
    const providerCode = String(autoConfigForm.provider_code ?? '').trim().toLowerCase();
    const rowsByGateway = integratedMethods.value.filter((method) => {
        const methodGatewayId = Number(method.payment_gateway_id ?? 0);
        if (safeGatewayId > 0 && methodGatewayId === safeGatewayId) {
            return true;
        }

        if (safeGatewayId > 0) {
            return false;
        }

        const methodProvider = String(
            method.payment_gateway_provider
                ?? method.integration_profile?.provider
                ?? '',
        ).trim().toLowerCase();

        return methodProvider !== '' && methodProvider === providerCode;
    });

    return integratedMethodOptions.value.map((option) => {
        const code = String(option.code ?? '').trim().toLowerCase();
        const existing = rowsByGateway.find((method) => String(method.code ?? '').trim().toLowerCase() === code);

        return {
            code,
            label: option.label || autoMethodLabel(code),
            description: option.description || '',
            supports_installments: Boolean(option.supports_installments),
            enabled: Boolean(existing?.is_active),
            fee_fixed: existing?.fee_fixed ?? '',
            fee_percent: existing?.fee_percent ?? '',
            allows_installments: code === 'credit_card' ? Boolean(existing?.allows_installments) : false,
            max_installments: existing?.max_installments ?? '',
            is_default: Boolean(existing?.is_default),
        };
    });
};

const hydrateAutomaticConfig = (providerCode = null) => {
    const safeProviderCode = String(
        providerCode ?? autoConfigForm.provider_code ?? resolveDefaultProviderCode(),
    ).trim().toLowerCase();
    const normalizedProviderCode = safeProviderCode || resolveDefaultProviderCode();
    const gateway = resolveDefaultGatewayForProvider(normalizedProviderCode);

    autoConfigForm.provider_code = normalizedProviderCode;
    autoConfigForm.gateway_id = gateway ? Number(gateway.id) : null;
    autoConfigForm.gateway_is_sandbox = gateway?.is_sandbox !== undefined ? Boolean(gateway.is_sandbox) : true;
    autoConfigForm.mercado_pago_access_token = '';
    autoConfigForm.mercado_pago_webhook_secret = '';
    autoConfigForm.methods = buildAutomaticMethodRows(gateway?.id ?? null);

    const defaultRow = autoConfigForm.methods.find((row) => row.enabled && row.is_default)
        || autoConfigForm.methods.find((row) => row.enabled)
        || autoConfigForm.methods[0];
    autoConfigForm.default_code = defaultRow ? String(defaultRow.code || '') : '';

    autoConfigForm.clearErrors();
    clearAutoFeedback();
};

const saveAutomaticConfig = () => {
    if (!integratedGatewayOptions.value.length) {
        autoConfigForm.setError('provider_code', 'Nenhum gateway automático ativo está disponível.');
        return;
    }

    if (!isMercadoPagoSelected.value) {
        autoConfigForm.setError('provider_code', 'A integração automática deste gateway ainda não está disponível.');
        return;
    }

    autoConfigForm.clearErrors('provider_code');

    const payload = {
        gateway_id: autoConfigForm.gateway_id !== null && autoConfigForm.gateway_id !== ''
            ? Number(autoConfigForm.gateway_id)
            : null,
        gateway_is_sandbox: Boolean(autoConfigForm.gateway_is_sandbox),
        mercado_pago_access_token: String(autoConfigForm.mercado_pago_access_token ?? '').trim(),
        mercado_pago_webhook_secret: String(autoConfigForm.mercado_pago_webhook_secret ?? '').trim(),
        default_code: String(autoConfigForm.default_code ?? '').trim().toLowerCase(),
        methods: autoConfigForm.methods.map((row) => ({
            code: String(row.code ?? '').trim().toLowerCase(),
            enabled: Boolean(row.enabled),
            fee_fixed: row.fee_fixed === '' || row.fee_fixed === null ? null : Number(row.fee_fixed),
            fee_percent: row.fee_percent === '' || row.fee_percent === null ? null : Number(row.fee_percent),
            allows_installments: String(row.code ?? '').trim().toLowerCase() === 'credit_card'
                ? Boolean(row.allows_installments)
                : false,
            max_installments: String(row.code ?? '').trim().toLowerCase() === 'credit_card'
                && Boolean(row.allows_installments)
                && row.max_installments !== ''
                && row.max_installments !== null
                ? Number(row.max_installments)
                : null,
        })),
    };

    autoConfigForm.transform(() => payload).post(route('admin.finance.methods.sync-mercado-pago'), {
        preserveScroll: true,
        onSuccess: () => {
            autoConfigForm.mercado_pago_access_token = '';
            autoConfigForm.mercado_pago_webhook_secret = '';
            autoMethodFeedback.value = {};
        },
    });
};

const runAutomaticGatewayTest = async (methodCode = null) => {
    if (!isMercadoPagoSelected.value) {
        const message = 'Teste disponível apenas para gateways automáticos já implementados.';
        if (methodCode) {
            autoMethodFeedback.value = {
                ...autoMethodFeedback.value,
                [String(methodCode).trim().toLowerCase()]: { status: 'error', message },
            };
        } else {
            autoGatewayConnectionStatus.value = 'error';
            autoGatewayConnectionMessage.value = message;
        }
        return;
    }

    const gatewayId = Number(autoConfigForm.gateway_id ?? selectedAutoGateway.value?.id ?? 0);
    if (gatewayId <= 0) {
        const message = 'Salve ou selecione um gateway Mercado Pago antes de testar.';
        if (methodCode) {
            autoMethodFeedback.value = {
                ...autoMethodFeedback.value,
                [String(methodCode).trim().toLowerCase()]: { status: 'error', message },
            };
        } else {
            autoGatewayConnectionStatus.value = 'error';
            autoGatewayConnectionMessage.value = message;
        }
        return;
    }

    autoGatewayConnectionLoading.value = true;
    if (!methodCode) {
        autoGatewayConnectionStatus.value = 'idle';
        autoGatewayConnectionMessage.value = '';
    }

    const payload = {
        provider: 'mercado_pago',
        gateway_id: gatewayId,
        payment_method_code: methodCode ? String(methodCode).trim().toLowerCase() : null,
        validate_checkout_flow: methodCode ? String(methodCode).trim().toLowerCase() === 'pix' : false,
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
            const failureMessage = String(data?.message ?? 'Não foi possível validar a conexão.');
            if (methodCode) {
                autoMethodFeedback.value = {
                    ...autoMethodFeedback.value,
                    [String(methodCode).trim().toLowerCase()]: { status: 'error', message: failureMessage },
                };
            } else {
                autoGatewayConnectionStatus.value = 'error';
                autoGatewayConnectionMessage.value = failureMessage;
            }
            return;
        }

        const successMessage = String(data.message ?? 'Conexão validada com sucesso.');
        if (methodCode) {
            autoMethodFeedback.value = {
                ...autoMethodFeedback.value,
                [String(methodCode).trim().toLowerCase()]: { status: 'success', message: successMessage },
            };
        } else {
            autoGatewayConnectionStatus.value = 'success';
            autoGatewayConnectionMessage.value = successMessage;
        }
    } catch (error) {
        const failureMessage = String(error?.response?.data?.message ?? 'Não foi possível validar a conexão com o gateway.');
        if (methodCode) {
            autoMethodFeedback.value = {
                ...autoMethodFeedback.value,
                [String(methodCode).trim().toLowerCase()]: { status: 'error', message: failureMessage },
            };
        } else {
            autoGatewayConnectionStatus.value = 'error';
            autoGatewayConnectionMessage.value = failureMessage;
        }
    } finally {
        autoGatewayConnectionLoading.value = false;
    }
};

const methodModalOpen = ref(false);
const editingMethod = ref(null);
const methodDeleteOpen = ref(false);
const methodToDelete = ref(null);

const methodForm = useForm({
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

const resetMethodForm = (sortOrder = 0) => {
    methodForm.transform((data) => data);
    methodForm.reset();
    methodForm.clearErrors();
    methodForm.code = 'pix';
    methodForm.name = '';
    methodForm.is_active = true;
    methodForm.is_default = false;
    methodForm.allows_installments = false;
    methodForm.max_installments = '';
    methodForm.fee_fixed = '';
    methodForm.fee_percent = '';
    methodForm.sort_order = Number(sortOrder || 0);
};

watch(() => methodForm.code, (value) => {
    const code = String(value || '').trim().toLowerCase();
    if (code !== 'credit_card') {
        methodForm.allows_installments = false;
        methodForm.max_installments = '';
    }
});

const openCreateMethod = () => {
    editingMethod.value = null;
    resetMethodForm((manualMethods.value?.length ?? 0) + 10);
    methodModalOpen.value = true;
};

const openEditMethod = (method) => {
    editingMethod.value = method;

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
    methodModalOpen.value = true;
};

const closeMethodModal = () => {
    methodModalOpen.value = false;
    editingMethod.value = null;
    resetMethodForm();
};

const submitMethod = () => {
    const payload = {
        code: methodForm.code,
        name: methodForm.name,
        is_active: Boolean(methodForm.is_active),
        is_default: Boolean(methodForm.is_default),
        allows_installments: String(methodForm.code || '').trim().toLowerCase() === 'credit_card' && Boolean(methodForm.allows_installments),
        max_installments: String(methodForm.code || '').trim().toLowerCase() === 'credit_card' && methodForm.allows_installments && methodForm.max_installments !== ''
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

const disconnectGatewayForm = useForm({ gateway_id: null });
const connectMercadoPago = () => {
    if (!mercadoPagoOauthReady.value || !isMercadoPagoSelected.value) return;

    const returnTo = `${window.location.pathname}${window.location.search}`;
    window.location.href = route('admin.finance.mercadopago.redirect', { return_to: returnTo });
};

const disconnectMercadoPago = () => {
    if (!isMercadoPagoSelected.value) return;
    const gatewayId = Number(selectedAutoGateway.value?.id ?? 0);
    if (gatewayId <= 0) return;

    disconnectGatewayForm.transform(() => ({ gateway_id: gatewayId })).delete(route('admin.finance.mercadopago.disconnect'), {
        preserveScroll: true,
        onSuccess: () => {
            hydrateAutomaticConfig();
        },
    });
};

watch(
    () => autoConfigForm.methods,
    (rows) => {
        const enabledCodes = rows
            .filter((row) => Boolean(row.enabled))
            .map((row) => String(row.code || '').toLowerCase());

        if (!enabledCodes.includes(String(autoConfigForm.default_code || '').toLowerCase())) {
            autoConfigForm.default_code = enabledCodes[0] ?? '';
        }
    },
    { deep: true },
);

watch([integratedGatewayOptions, gateways], () => {
    const selectedProvider = String(autoConfigForm.provider_code ?? '').trim().toLowerCase();
    const hasSelectedOption = integratedGatewayOptions.value.some((item) => item.value === selectedProvider);
    if (!hasSelectedOption) {
        hydrateAutomaticConfig(resolveDefaultProviderCode());
        return;
    }

    hydrateAutomaticConfig(selectedProvider);
});

watch(() => autoConfigForm.provider_code, (value, oldValue) => {
    const current = String(value ?? '').trim().toLowerCase();
    const previous = String(oldValue ?? '').trim().toLowerCase();
    if (current === previous) return;

    hydrateAutomaticConfig(current);
});

hydrateAutomaticConfig();
</script>

<template>
    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Pagamentos">
        <Head title="Pagamentos" />

        <section class="space-y-4" :style="paymentsUiStyles">
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

            <div class="finance-tabs-shell">
                <div class="finance-tabs-track">
                    <button
                        v-for="tab in paymentTabs"
                        :key="tab.key"
                        type="button"
                        class="finance-tab"
                        :class="{ 'is-active': activePaymentTab === tab.key }"
                        @click="setActivePaymentTab(tab.key)"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        <span>{{ tab.label }}</span>
                    </button>
                </div>
            </div>

            <section v-show="activePaymentTab === 'automatic'" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Pagamento automático</h2>
                        <p class="text-xs text-slate-500">
                            Selecione um gateway ativo do catálogo master e configure as formas integradas.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="autoConfigForm.processing"
                        @click="saveAutomaticConfig"
                    >
                        {{ autoConfigForm.processing ? 'Salvando...' : 'Salvar configuração automática' }}
                    </button>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gateway automático</label>
                        <UiSelect
                            v-if="hasMultipleAutomaticGateways"
                            v-model="autoConfigForm.provider_code"
                            :options="integratedGatewayOptions"
                            button-class="mt-1"
                        />
                        <div
                            v-else
                            class="mt-1 inline-flex min-h-10 w-full items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700"
                        >
                            {{ selectedIntegratedGatewayOption?.label || 'Mercado Pago' }}
                        </div>
                        <p v-if="!integratedGatewayOptions.length" class="mt-1 text-xs text-amber-700">
                            Nenhum gateway automático ativo foi definido no painel master.
                        </p>
                        <p v-else-if="!isMercadoPagoSelected" class="mt-1 text-xs text-amber-700">
                            Integração automática para este gateway ainda não está disponível no backend.
                        </p>
                        <p v-if="autoConfigForm.errors.provider_code" class="mt-1 text-xs text-rose-600">{{ autoConfigForm.errors.provider_code }}</p>
                    </div>

                    <div v-if="isMercadoPagoSelected">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Access token (opcional)</label>
                        <input
                            v-model="autoConfigForm.mercado_pago_access_token"
                            type="password"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="APP_USR-..."
                            autocomplete="new-password"
                        >
                        <p class="mt-1 text-[11px] text-slate-500">
                            {{ selectedAutoGateway?.credentials_status?.access_token_configured ? 'Token já configurado neste gateway.' : 'Você também pode conectar por OAuth.' }}
                        </p>
                        <p v-if="autoConfigForm.errors.mercado_pago_access_token" class="mt-1 text-xs text-rose-600">{{ autoConfigForm.errors.mercado_pago_access_token }}</p>
                    </div>

                    <div v-if="isMercadoPagoSelected">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Webhook secret (opcional)</label>
                        <input
                            v-model="autoConfigForm.mercado_pago_webhook_secret"
                            type="password"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                            placeholder="Segredo do webhook"
                            autocomplete="new-password"
                        >
                        <p class="mt-1 text-[11px] text-slate-500">
                            {{ selectedAutoGateway?.credentials_status?.webhook_secret_configured ? 'Webhook secret já configurado.' : 'Informe somente se for usar validação por segredo.' }}
                        </p>
                        <p v-if="autoConfigForm.errors.mercado_pago_webhook_secret" class="mt-1 text-xs text-rose-600">{{ autoConfigForm.errors.mercado_pago_webhook_secret }}</p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="autoConfigForm.gateway_is_sandbox" type="checkbox" class="rounded border-slate-300">
                        Ambiente sandbox (teste)
                    </label>
                </div>

                <div v-if="isMercadoPagoSelected" class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                    <p class="font-semibold text-slate-900">Status da conexão</p>
                    <p v-if="!mercadoPagoOauthSchemaReady" class="mt-1 text-amber-700">
                        OAuth será habilitado após executar as migrations pendentes do Mercado Pago.
                    </p>
                    <p v-else-if="!mercadoPagoOauthClientReady" class="mt-1 text-amber-700">
                        Conexão OAuth indisponível no momento. Contate o suporte da plataforma.
                    </p>
                    <p v-else class="mt-1">
                        {{ selectedAutoGateway?.credentials_status?.oauth_connected ? 'Conta Mercado Pago conectada por OAuth.' : 'OAuth não conectado neste gateway.' }}
                    </p>
                    <p v-if="selectedAutoGateway?.oauth?.account_email" class="mt-1">
                        Conta: <span class="font-semibold">{{ selectedAutoGateway.oauth.account_email }}</span>
                    </p>
                    <p v-if="selectedAutoGateway?.oauth?.last_error" class="mt-1 text-rose-600">
                        Último erro: {{ selectedAutoGateway.oauth.last_error }}
                    </p>
                </div>

                <div v-if="isMercadoPagoSelected" class="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!mercadoPagoOauthReady"
                        @click="connectMercadoPago"
                    >
                        Conectar conta Mercado Pago
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="!mercadoPagoOauthReady || !selectedAutoGateway || disconnectGatewayForm.processing"
                        @click="disconnectMercadoPago"
                    >
                        {{ disconnectGatewayForm.processing ? 'Desconectando...' : 'Desconectar conta' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="autoGatewayConnectionLoading"
                        @click="runAutomaticGatewayTest()"
                    >
                        {{ autoGatewayConnectionLoading ? 'Testando...' : 'Testar conexão geral' }}
                    </button>
                </div>

                <div v-if="autoGatewayConnectionMessage" class="mt-3 rounded-xl border px-3 py-2 text-xs font-semibold" :class="autoGatewayConnectionToneClass">
                    {{ autoGatewayConnectionMessage }}
                </div>
                <div
                    v-if="showMercadoPagoFeesNotice"
                    class="mt-4 flex items-start justify-between gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2"
                >
                    <p class="text-xs font-semibold text-amber-800">
                        Consulte as taxas do Mercado Pago antes da integração.
                    </p>
                    <button
                        type="button"
                        class="inline-flex h-5 w-5 items-center justify-center rounded-md border border-amber-200 bg-white text-amber-700 hover:bg-amber-100"
                        aria-label="Fechar aviso de taxas"
                        @click="showMercadoPagoFeesNotice = false"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <article
                        v-for="method in autoConfigForm.methods"
                        :key="`auto-method-${method.code}`"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-3"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900">{{ method.label }}</p>
                                <p v-if="method.description" class="text-xs text-slate-500">{{ method.description }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700">
                                    <input v-model="method.enabled" type="checkbox" class="rounded border-slate-300">
                                    Habilitar
                                </label>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="autoGatewayConnectionLoading || !method.enabled || !isMercadoPagoSelected"
                                    @click="runAutomaticGatewayTest(method.code)"
                                >
                                    Testar forma
                                </button>
                            </div>
                        </div>

                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Taxa fixa (R$)</label>
                                <BrlMoneyInput
                                    v-model="method.fee_fixed"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:bg-slate-100"
                                    :disabled="!method.enabled"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Taxa percentual (%)</label>
                                <input
                                    v-model="method.fee_percent"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:bg-slate-100"
                                    :disabled="!method.enabled"
                                >
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700">
                                <input
                                    v-model="autoConfigForm.default_code"
                                    type="radio"
                                    :value="method.code"
                                    :disabled="!method.enabled"
                                    class="border-slate-300"
                                >
                                Forma padrão
                            </label>

                            <template v-if="String(method.code || '').toLowerCase() === 'credit_card'">
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700">
                                    <input
                                        v-model="method.allows_installments"
                                        type="checkbox"
                                        class="rounded border-slate-300"
                                        :disabled="!method.enabled"
                                    >
                                    Permite parcelamento
                                </label>
                                <input
                                    v-if="method.allows_installments"
                                    v-model="method.max_installments"
                                    type="number"
                                    min="2"
                                    max="24"
                                    class="w-28 rounded-lg border border-slate-200 px-2 py-1 text-xs text-slate-700 disabled:bg-slate-100"
                                    :disabled="!method.enabled"
                                    placeholder="Máx parcelas"
                                >
                            </template>
                        </div>

                        <p
                            v-if="autoMethodFeedback[String(method.code || '').toLowerCase()]?.message"
                            class="mt-2 rounded-xl border px-3 py-2 text-xs font-semibold"
                            :class="autoMethodFeedback[String(method.code || '').toLowerCase()]?.status === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'"
                        >
                            {{ autoMethodFeedback[String(method.code || '').toLowerCase()]?.message }}
                        </p>
                    </article>
                </div>
            </section>

            <section v-show="activePaymentTab === 'manual'" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Formas manuais</h2>
                        <p class="text-xs text-slate-500">Cadastre formas de pagamento sem captura automática (responsabilidade do contratante).</p>
                    </div>
                    <button type="button" class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white" @click="openCreateMethod">
                        <Plus class="h-3.5 w-3.5" />
                        Nova forma manual
                    </button>
                </div>

                <div v-if="!manualMethods.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                    Nenhuma forma manual cadastrada.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="method in manualMethods" :key="`method-${method.id}`" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 shadow-sm">
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
                                Manual
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <strong>Taxas:</strong>
                                {{ method.fee_percent !== null ? `${method.fee_percent}%` : '-' }} / {{ method.fee_fixed !== null ? asCurrency(method.fee_fixed) : '-' }}
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <strong>Parcelamento:</strong>
                                {{ method.allows_installments ? `Até ${method.max_installments || '-'}x` : 'Não' }}
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

        </section>

        <Modal :show="methodModalOpen" max-width="5xl" @close="closeMethodModal">
            <WizardModalFrame
                :title="isEditingMethod ? 'Editar forma manual' : 'Nova forma manual'"
                description="Cadastre uma forma manual de pagamento."
                :steps="['Dados da forma']"
                :current-step="1"
                @close="closeMethodModal"
            >
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Código</label>
                        <UiSelect v-model="methodForm.code" :options="manualMethodCodeOptions" button-class="mt-1" />
                        <p v-if="methodForm.errors.code" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.code }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome de exibição</label>
                        <input v-model="methodForm.name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Ex.: Pagamento principal">
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

                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="methodForm.is_active" type="checkbox" class="rounded border-slate-300">
                        Ativa
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="methodForm.is_default" type="checkbox" class="rounded border-slate-300">
                        Padrão
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input
                            v-model="methodForm.allows_installments"
                            type="checkbox"
                            class="rounded border-slate-300"
                            :disabled="String(methodForm.code || '').trim().toLowerCase() !== 'credit_card'"
                        >
                        Permite parcelamento
                    </label>
                </div>

                <div v-if="methodForm.allows_installments && String(methodForm.code || '').trim().toLowerCase() === 'credit_card'" class="mt-3">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Máximo de parcelas</label>
                    <input v-model="methodForm.max_installments" type="number" min="2" max="24" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                    <p v-if="methodForm.errors.max_installments" class="mt-1 text-xs text-rose-600">{{ methodForm.errors.max_installments }}</p>
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

<style scoped>
.finance-tabs-shell {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.finance-tabs-shell::-webkit-scrollbar {
    height: 6px;
}

.finance-tabs-shell::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background: rgba(148, 163, 184, 0.45);
}

.finance-tabs-track {
    display: inline-flex;
    min-width: max-content;
    gap: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #ffffff;
    padding: 0.3rem;
}

.finance-tab {
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

.finance-tab:hover {
    background: #f8fafc;
    color: #0f172a;
}

.finance-tab.is-active {
    border-color: var(--finance-tab-active-border);
    background: var(--finance-tab-active);
    color: #ffffff;
}
</style>

