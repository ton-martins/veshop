<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import BrlMoneyInput from '@/Components/App/BrlMoneyInput.vue';
import WizardModalFrame from '@/Components/App/WizardModalFrame.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, PlugZap, ShieldCheck, CreditCard, HandCoins, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    paymentConfig: {
        type: Object,
        default: () => ({ gateways: [], methods: [], provider_options: [], stats: {} }),
    },
});

const page = usePage();
const statusMessage = computed(() => page.props.flash?.status ?? null);
const gateways = computed(() => props.paymentConfig?.gateways ?? []);
const methods = computed(() => props.paymentConfig?.methods ?? []);
const providerOptions = computed(() => props.paymentConfig?.provider_options ?? []);
const stats = computed(() => props.paymentConfig?.stats ?? {});

const paymentStats = computed(() => [
    { key: 'gateways_total', label: 'Gateways', value: Number(stats.value.gateways_total ?? 0), icon: PlugZap },
    { key: 'gateways_active', label: 'Gateways ativos', value: Number(stats.value.gateways_active ?? 0), icon: ShieldCheck },
    { key: 'methods_total', label: 'Formas cadastradas', value: Number(stats.value.methods_total ?? 0), icon: HandCoins },
    { key: 'methods_active', label: 'Formas ativas', value: Number(stats.value.methods_active ?? 0), icon: CreditCard },
]);

const methodCodeOptions = [
    { value: 'pix', label: 'Pix' },
    { value: 'cash', label: 'Dinheiro' },
    { value: 'debit_card', label: 'Cartão de débito' },
    { value: 'credit_card', label: 'Cartão de crédito' },
    { value: 'installment', label: 'A prazo' },
];

const methodCodeLabel = (code) => {
    const safe = String(code ?? '').trim().toLowerCase();
    const found = methodCodeOptions.find((item) => item.value === safe);
    return found?.label || safe || '-';
};

const asCurrency = (value) => Number(value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const gatewayModalOpen = ref(false);
const editingGateway = ref(null);
const gatewayDeleteOpen = ref(false);
const gatewayToDelete = ref(null);
const gatewayForm = useForm({
    provider: 'manual',
    name: '',
    is_active: true,
    is_default: false,
    is_sandbox: true,
    mercado_pago_access_token: '',
    mercado_pago_webhook_secret: '',
});
const gatewayDeleteForm = useForm({});
const isEditingGateway = computed(() => Boolean(editingGateway.value?.id));

const resetGatewayForm = () => {
    gatewayForm.transform((data) => data);
    gatewayForm.reset();
    gatewayForm.clearErrors();
    gatewayForm.provider = 'manual';
    gatewayForm.name = '';
    gatewayForm.is_active = true;
    gatewayForm.is_default = false;
    gatewayForm.is_sandbox = true;
    gatewayForm.mercado_pago_access_token = '';
    gatewayForm.mercado_pago_webhook_secret = '';
};

const openCreateGateway = () => {
    editingGateway.value = null;
    resetGatewayForm();
    gatewayModalOpen.value = true;
};
const openEditGateway = (gateway) => {
    editingGateway.value = gateway;
    gatewayForm.provider = String(gateway.provider ?? 'manual');
    gatewayForm.name = String(gateway.name ?? '');
    gatewayForm.is_active = Boolean(gateway.is_active);
    gatewayForm.is_default = Boolean(gateway.is_default);
    gatewayForm.is_sandbox = Boolean(gateway.is_sandbox);
    gatewayForm.mercado_pago_access_token = '';
    gatewayForm.mercado_pago_webhook_secret = '';
    gatewayForm.clearErrors();
    gatewayModalOpen.value = true;
};
const closeGatewayModal = () => {
    gatewayModalOpen.value = false;
    editingGateway.value = null;
    resetGatewayForm();
};
const submitGateway = () => {
    if (isEditingGateway.value) {
        gatewayForm.put(route('admin.finance.gateways.update', editingGateway.value.id), { preserveScroll: true, onSuccess: closeGatewayModal });
        return;
    }
    gatewayForm.post(route('admin.finance.gateways.store'), { preserveScroll: true, onSuccess: closeGatewayModal });
};
const openDeleteGateway = (gateway) => {
    gatewayToDelete.value = gateway;
    gatewayDeleteOpen.value = true;
};
const closeDeleteGateway = () => {
    gatewayToDelete.value = null;
    gatewayDeleteOpen.value = false;
};
const removeGateway = () => {
    if (!gatewayToDelete.value?.id) return;
    gatewayDeleteForm.delete(route('admin.finance.gateways.destroy', gatewayToDelete.value.id), { preserveScroll: true, onSuccess: closeDeleteGateway });
};

const methodModalOpen = ref(false);
const editingMethod = ref(null);
const methodDeleteOpen = ref(false);
const methodToDelete = ref(null);
const methodForm = useForm({
    payment_gateway_id: '',
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
const gatewaySelectOptions = computed(() => [{ value: '', label: 'Sem gateway' }, ...gateways.value.map((gateway) => ({ value: gateway.id, label: gateway.name }))]);

const resetMethodForm = (sortOrder = 0) => {
    methodForm.transform((data) => data);
    methodForm.reset();
    methodForm.clearErrors();
    methodForm.payment_gateway_id = '';
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

const openCreateMethod = () => {
    editingMethod.value = null;
    resetMethodForm((methods.value?.length ?? 0) + 10);
    methodModalOpen.value = true;
};
const openEditMethod = (method) => {
    editingMethod.value = method;
    methodForm.payment_gateway_id = method.payment_gateway_id ?? '';
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
        payment_gateway_id: methodForm.payment_gateway_id === '' ? null : Number(methodForm.payment_gateway_id),
        code: methodForm.code,
        name: methodForm.name,
        is_active: Boolean(methodForm.is_active),
        is_default: Boolean(methodForm.is_default),
        allows_installments: Boolean(methodForm.allows_installments),
        max_installments: methodForm.allows_installments && methodForm.max_installments !== '' ? Number(methodForm.max_installments) : null,
        fee_fixed: methodForm.fee_fixed === '' ? null : Number(methodForm.fee_fixed),
        fee_percent: methodForm.fee_percent === '' ? null : Number(methodForm.fee_percent),
        sort_order: Number(methodForm.sort_order || 0),
    };
    if (isEditingMethod.value) {
        methodForm.transform(() => payload).put(route('admin.finance.methods.update', editingMethod.value.id), { preserveScroll: true, onSuccess: closeMethodModal });
        return;
    }
    methodForm.transform(() => payload).post(route('admin.finance.methods.store'), { preserveScroll: true, onSuccess: closeMethodModal });
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
    methodDeleteForm.delete(route('admin.finance.methods.destroy', methodToDelete.value.id), { preserveScroll: true, onSuccess: closeDeleteMethod });
};
</script>

<template>
    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Pagamentos">
        <Head title="Pagamentos" />
        <div v-if="statusMessage" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ statusMessage }}</div>

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
                <div class="flex flex-wrap justify-end gap-2"><button type="button" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700" @click="openCreateGateway"><Plus class="h-3.5 w-3.5" />Novo gateway</button><button type="button" class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white" @click="openCreateMethod"><Plus class="h-3.5 w-3.5" />Nova forma</button></div>
                <div class="mt-4 grid gap-4 xl:grid-cols-2">
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <h3 class="text-sm font-semibold text-slate-900">Gateways</h3>
                        <div v-if="!gateways.length" class="mt-2 rounded-xl border border-dashed border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">Nenhum gateway cadastrado.</div>
                        <div v-for="gateway in gateways" :key="`gateway-${gateway.id}`" class="mt-2 rounded-xl border border-slate-200 bg-white p-3">
                            <p class="text-sm font-semibold text-slate-900">{{ gateway.name }}</p>
                            <p class="text-xs text-slate-500">{{ gateway.provider }} • {{ gateway.is_sandbox ? 'Sandbox' : 'Produção' }}</p>
                            <div class="mt-2 flex justify-end gap-1"><button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700" @click="openEditGateway(gateway)"><Pencil class="h-3.5 w-3.5" />Editar</button><button type="button" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700" @click="openDeleteGateway(gateway)"><Trash2 class="h-3.5 w-3.5" />Excluir</button></div>
                        </div>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <h3 class="text-sm font-semibold text-slate-900">Formas de pagamento</h3>
                        <div v-if="!methods.length" class="mt-2 rounded-xl border border-dashed border-slate-200 bg-white px-3 py-6 text-center text-sm text-slate-500">Nenhuma forma cadastrada.</div>
                        <div v-for="method in methods" :key="`method-${method.id}`" class="mt-2 rounded-xl border border-slate-200 bg-white p-3">
                            <p class="text-sm font-semibold text-slate-900">{{ method.name }}</p>
                            <p class="text-xs text-slate-500">{{ methodCodeLabel(method.code) }} • {{ method.fee_fixed !== null ? asCurrency(method.fee_fixed) : '-' }}</p>
                            <div class="mt-2 flex justify-end gap-1"><button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700" @click="openEditMethod(method)"><Pencil class="h-3.5 w-3.5" />Editar</button><button type="button" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-xs font-semibold text-rose-700" @click="openDeleteMethod(method)"><Trash2 class="h-3.5 w-3.5" />Excluir</button></div>
                        </div>
                    </article>
                </div>
            </section>
        </section>

        <Modal :show="gatewayModalOpen" max-width="4xl" @close="closeGatewayModal"><WizardModalFrame :title="isEditingGateway ? 'Editar gateway' : 'Novo gateway'" :steps="['Dados']" :current-step="1" @close="closeGatewayModal"><div class="grid gap-3 md:grid-cols-2"><UiSelect v-model="gatewayForm.provider" :options="providerOptions" /><input v-model="gatewayForm.name" type="text" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Nome do gateway"><input v-model="gatewayForm.mercado_pago_access_token" type="password" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Access token (se aplicável)"><input v-model="gatewayForm.mercado_pago_webhook_secret" type="password" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Webhook secret (se aplicável)"></div><div class="mt-3 grid gap-2 sm:grid-cols-3"><label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm"><input v-model="gatewayForm.is_active" type="checkbox" class="rounded border-slate-300">Ativo</label><label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm"><input v-model="gatewayForm.is_default" type="checkbox" class="rounded border-slate-300">Padrão</label><label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm"><input v-model="gatewayForm.is_sandbox" type="checkbox" class="rounded border-slate-300">Sandbox</label></div><template #footer><div class="flex justify-end gap-2"><button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700" @click="closeGatewayModal">Cancelar</button><button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white" :disabled="gatewayForm.processing" @click="submitGateway">{{ gatewayForm.processing ? 'Salvando...' : 'Salvar' }}</button></div></template></WizardModalFrame></Modal>
        <Modal :show="methodModalOpen" max-width="4xl" @close="closeMethodModal"><WizardModalFrame :title="isEditingMethod ? 'Editar forma' : 'Nova forma'" :steps="['Dados']" :current-step="1" @close="closeMethodModal"><div class="grid gap-3 md:grid-cols-2"><UiSelect v-model="methodForm.payment_gateway_id" :options="gatewaySelectOptions" /><UiSelect v-model="methodForm.code" :options="methodCodeOptions" /><input v-model="methodForm.name" type="text" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Nome da forma"><input v-model="methodForm.sort_order" type="number" min="0" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Ordem"><BrlMoneyInput v-model="methodForm.fee_fixed" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Taxa fixa" /><input v-model="methodForm.fee_percent" type="number" step="0.01" min="0" max="100" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Taxa percentual"></div><div class="mt-3 grid gap-2 sm:grid-cols-3"><label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm"><input v-model="methodForm.is_active" type="checkbox" class="rounded border-slate-300">Ativa</label><label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm"><input v-model="methodForm.is_default" type="checkbox" class="rounded border-slate-300">Padrão</label><label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm"><input v-model="methodForm.allows_installments" type="checkbox" class="rounded border-slate-300">Parcela</label></div><div v-if="methodForm.allows_installments" class="mt-3"><input v-model="methodForm.max_installments" type="number" min="2" max="24" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Máximo de parcelas"></div><template #footer><div class="flex justify-end gap-2"><button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700" @click="closeMethodModal">Cancelar</button><button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white" :disabled="methodForm.processing" @click="submitMethod">{{ methodForm.processing ? 'Salvando...' : 'Salvar' }}</button></div></template></WizardModalFrame></Modal>
        <DeleteConfirmModal :show="gatewayDeleteOpen" title="Excluir gateway" message="Tem certeza que deseja excluir este gateway?" :item-label="gatewayToDelete?.name ? `Gateway: ${gatewayToDelete.name}` : ''" :processing="gatewayDeleteForm.processing" @close="closeDeleteGateway" @confirm="removeGateway" />
        <DeleteConfirmModal :show="methodDeleteOpen" title="Excluir forma de pagamento" message="Tem certeza que deseja excluir esta forma de pagamento?" :item-label="methodToDelete?.name ? `Forma: ${methodToDelete.name}` : ''" :processing="methodDeleteForm.processing" @close="closeDeleteMethod" @confirm="removeMethod" />
    </AuthenticatedLayout>
</template>
