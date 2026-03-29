<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DeleteConfirmModal from '@/Components/App/DeleteConfirmModal.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, PlugZap, ShieldCheck, WalletCards, Pencil, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    gateways: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
});

const gatewayModalOpen = ref(false);
const editingGateway = ref(null);
const gatewayDeleteOpen = ref(false);
const gatewayToDelete = ref(null);

const gatewayForm = useForm({
    code: '',
    name: '',
    description: '',
    checkout_mode: 'automatic',
    is_active: true,
    sort_order: 100,
});

const gatewayDeleteForm = useForm({});
const isEditing = computed(() => Boolean(editingGateway.value?.id));
const isNativeEditing = computed(() => Boolean(editingGateway.value?.is_native));

const checkoutModeOptions = [
    { value: 'automatic', label: 'Automático' },
    { value: 'manual', label: 'Manual' },
];

const summaryCards = computed(() => [
    { key: 'total', label: 'Total de gateways', value: Number(props.stats?.total ?? 0), icon: PlugZap },
    { key: 'active', label: 'Gateways ativos', value: Number(props.stats?.active ?? 0), icon: ShieldCheck },
    { key: 'automatic_active', label: 'Automáticos ativos', value: Number(props.stats?.automatic_active ?? 0), icon: WalletCards },
    { key: 'implemented', label: 'Implementados', value: Number(props.stats?.implemented ?? 0), icon: ShieldCheck },
]);

const resetGatewayForm = () => {
    gatewayForm.reset();
    gatewayForm.clearErrors();
    gatewayForm.code = '';
    gatewayForm.name = '';
    gatewayForm.description = '';
    gatewayForm.checkout_mode = 'automatic';
    gatewayForm.is_active = true;
    gatewayForm.sort_order = 100;
};

const openCreateGateway = () => {
    editingGateway.value = null;
    resetGatewayForm();
    gatewayModalOpen.value = true;
};

const openEditGateway = (gateway) => {
    editingGateway.value = gateway;
    gatewayForm.code = String(gateway.code ?? '');
    gatewayForm.name = String(gateway.name ?? '');
    gatewayForm.description = String(gateway.description ?? '');
    gatewayForm.checkout_mode = String(gateway.checkout_mode ?? 'manual');
    gatewayForm.is_active = Boolean(gateway.is_active);
    gatewayForm.sort_order = Number(gateway.sort_order ?? 100);
    gatewayForm.clearErrors();
    gatewayModalOpen.value = true;
};

const closeGatewayModal = () => {
    gatewayModalOpen.value = false;
    editingGateway.value = null;
    resetGatewayForm();
};

const submitGateway = () => {
    const payload = {
        code: String(gatewayForm.code ?? '').trim().toLowerCase(),
        name: String(gatewayForm.name ?? '').trim(),
        description: String(gatewayForm.description ?? '').trim(),
        checkout_mode: String(gatewayForm.checkout_mode ?? 'manual').trim().toLowerCase(),
        is_active: Boolean(gatewayForm.is_active),
        sort_order: Number(gatewayForm.sort_order || 100),
    };

    if (isEditing.value) {
        gatewayForm.transform(() => payload).put(route('master.payment-gateways.update', editingGateway.value.id), {
            preserveScroll: true,
            onSuccess: closeGatewayModal,
        });
        return;
    }

    gatewayForm.transform(() => payload).post(route('master.payment-gateways.store'), {
        preserveScroll: true,
        onSuccess: closeGatewayModal,
    });
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

    gatewayDeleteForm.delete(route('master.payment-gateways.destroy', gatewayToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteGateway,
    });
};
</script>

<template>
    <AuthenticatedLayout area="master" header-variant="compact" header-title="Gateways de pagamento">
        <Head title="Gateways de pagamento" />

        <section class="space-y-4">
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="card in summaryCards" :key="card.key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-500">{{ card.label }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ card.value }}</p>
                        </div>
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                            <component :is="card.icon" class="h-5 w-5" />
                        </span>
                    </div>
                </article>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Catálogo de gateways suportados</h2>
                        <p class="text-xs text-slate-500">
                            Defina quais gateways ficam disponíveis no painel Admin. Gateways nativos podem ser desativados, mas não removidos.
                        </p>
                    </div>
                    <button type="button" class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white" @click="openCreateGateway">
                        <Plus class="h-3.5 w-3.5" />
                        Novo gateway
                    </button>
                </div>

                <div v-if="!gateways.length" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                    Nenhum gateway cadastrado.
                </div>

                <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="gateway in gateways" :key="gateway.id" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ gateway.name }}</p>
                                <p class="text-xs text-slate-500">{{ gateway.code }}</p>
                            </div>
                            <span class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="gateway.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                {{ gateway.is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </div>

                        <p v-if="gateway.description" class="mt-3 text-xs text-slate-600">{{ gateway.description }}</p>

                        <div class="mt-3 space-y-2 text-xs text-slate-700">
                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <strong>Operação:</strong>
                                {{ gateway.checkout_mode === 'automatic' ? 'Automático' : 'Manual' }}
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <strong>Status da implementação:</strong>
                                {{ gateway.implementation_status }}
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <strong>Ordem:</strong>
                                {{ gateway.sort_order }}
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span v-if="gateway.is_native" class="inline-flex items-center rounded-full bg-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                Nativo
                            </span>
                            <button type="button" class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:flex-none" @click="openEditGateway(gateway)">
                                <Pencil class="h-3.5 w-3.5" />
                                Editar
                            </button>
                            <button
                                v-if="!gateway.is_native"
                                type="button"
                                class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-rose-200 bg-white px-2 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 sm:flex-none"
                                @click="openDeleteGateway(gateway)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                Excluir
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </section>

        <Modal :show="gatewayModalOpen" max-width="3xl" @close="closeGatewayModal">
            <div class="space-y-4 rounded-3xl bg-white p-6">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">{{ isEditing ? 'Editar gateway' : 'Novo gateway' }}</h3>
                    <p class="text-sm text-slate-500">Configure a disponibilidade do gateway no painel Admin.</p>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Código</label>
                        <input
                            v-model="gatewayForm.code"
                            type="text"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:bg-slate-100"
                            :disabled="isNativeEditing"
                            placeholder="Ex.: mercado_pago"
                        >
                        <p v-if="gatewayForm.errors.code" class="mt-1 text-xs text-rose-600">{{ gatewayForm.errors.code }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                        <input v-model="gatewayForm.name" type="text" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Nome para exibição no Admin">
                        <p v-if="gatewayForm.errors.name" class="mt-1 text-xs text-rose-600">{{ gatewayForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tipo de operação</label>
                        <UiSelect
                            v-model="gatewayForm.checkout_mode"
                            :options="checkoutModeOptions"
                            button-class="mt-1"
                            :disabled="isNativeEditing"
                        />
                        <p v-if="gatewayForm.errors.checkout_mode" class="mt-1 text-xs text-rose-600">{{ gatewayForm.errors.checkout_mode }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ordem</label>
                        <input v-model="gatewayForm.sort_order" type="number" min="1" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700">
                        <p v-if="gatewayForm.errors.sort_order" class="mt-1 text-xs text-rose-600">{{ gatewayForm.errors.sort_order }}</p>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Descrição</label>
                    <textarea v-model="gatewayForm.description" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700" placeholder="Descrição para orientar o administrador"></textarea>
                    <p v-if="gatewayForm.errors.description" class="mt-1 text-xs text-rose-600">{{ gatewayForm.errors.description }}</p>
                </div>

                <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                    <input v-model="gatewayForm.is_active" type="checkbox" class="rounded border-slate-300">
                    Gateway ativo no painel Admin
                </label>

                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeGatewayModal">
                        Cancelar
                    </button>
                    <button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="gatewayForm.processing" @click="submitGateway">
                        {{ gatewayForm.processing ? 'Salvando...' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </Modal>

        <DeleteConfirmModal
            :show="gatewayDeleteOpen"
            title="Excluir gateway"
            message="Tem certeza que deseja excluir este gateway do catálogo?"
            :item-label="gatewayToDelete?.name ? `Gateway: ${gatewayToDelete.name}` : ''"
            :processing="gatewayDeleteForm.processing"
            @close="closeDeleteGateway"
            @confirm="removeGateway"
        />
    </AuthenticatedLayout>
</template>

