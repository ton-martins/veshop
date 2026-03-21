<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BrandingImageUploader from '@/Components/BrandingImageUploader.vue';
import ActionButton from '@/Components/ActionButton.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { LayoutDashboard, BarChart3, UserX, UserCheck } from 'lucide-vue-next';

const props = defineProps({
    profileContractor: { type: Object, default: () => ({}) },
    security: { type: Object, default: () => ({}) },
    niches: { type: Object, default: () => ({ current: 'commercial', options: [] }) },
    defaults: { type: Object, default: () => ({}) },
    timezones: { type: Array, default: () => [] },
    storageConfigured: { type: Boolean, default: true },
    storageUsage: { type: Object, default: () => ({}) },
    supportAccess: { type: Object, default: () => ({}) },
});

const page = usePage();
const statusMessage = computed(() => page.props.flash?.status ?? null);

const form = useForm({
    brand_name: '',
    brand_primary_color: '#557D97',
    email: '',
    timezone: 'America/Sao_Paulo',
    brand_logo: null,
    brand_avatar: null,
    remove_brand_logo: false,
    remove_brand_avatar: false,
    require_email_verification: true,
    email_notifications_enabled: true,
});

const logoPreview = ref('');
const avatarPreview = ref('');

const normalizedColor = computed(() => {
    const value = String(form.brand_primary_color || '#557D97').trim();
    return value.startsWith('#') ? value : `#${value}`;
});

const currentNiche = computed(() => props.profileContractor?.business_niche ?? props.niches?.current ?? 'commercial');
const currentNicheLabel = computed(() => {
    const found = (props.niches?.options ?? []).find((option) => option?.value === currentNiche.value);
    if (found?.label) return found.label;
    return currentNiche.value === 'services' ? 'Serviços' : 'Comércio';
});

const timezoneOptions = computed(() =>
    (props.timezones ?? []).map((timezone) => ({
        value: timezone.value,
        label: timezone.label ?? timezone.value,
    })),
);

const hydrate = () => {
    const contractor = props.profileContractor ?? {};
    form.brand_name = contractor.brand_name ?? contractor.name ?? props.defaults?.name ?? '';
    form.brand_primary_color = contractor.brand_primary_color ?? props.defaults?.primary_color ?? '#557D97';
    form.email = contractor.email ?? '';
    form.timezone = contractor.timezone ?? props.timezones?.[0]?.value ?? 'America/Sao_Paulo';
    form.require_email_verification = props.security?.require_email_verification ?? true;
    form.email_notifications_enabled = props.security?.email_notifications_enabled ?? true;
    form.brand_logo = null;
    form.brand_avatar = null;
    form.remove_brand_logo = false;
    form.remove_brand_avatar = false;
    logoPreview.value = contractor.brand_logo_url ?? props.defaults?.logo_url ?? '';
    avatarPreview.value = contractor.brand_avatar_url ?? props.defaults?.avatar_url ?? '';
};

watch(() => props.profileContractor, hydrate, { deep: true, immediate: true });

const previewBranding = computed(() => ({
    name: form.brand_name?.trim() || props.defaults?.name || '',
    email: form.email?.trim() || '',
    timezone: form.timezone,
    avatar_url: avatarPreview.value,
}));

const formatBytes = (value) => {
    const bytes = Number(value ?? 0);
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 MB';
    const mb = bytes / (1024 * 1024);
    return mb < 1024 ? `${mb.toFixed(1)} MB` : `${(mb / 1024).toFixed(1)} GB`;
};

const storageLimitLabel = computed(() => {
    const limit = Number(props.storageUsage?.limit_gb ?? 0);
    return limit > 0 ? `${limit} GB` : 'Ilimitado';
});

const handleLogoChange = ({ file, preview }) => {
    form.brand_logo = file;
    form.remove_brand_logo = false;
    logoPreview.value = preview;
};

const handleAvatarChange = ({ file, preview }) => {
    form.brand_avatar = file;
    form.remove_brand_avatar = false;
    avatarPreview.value = preview;
};

const removeLogo = () => {
    form.brand_logo = null;
    form.remove_brand_logo = true;
    logoPreview.value = '';
};

const removeAvatar = () => {
    form.brand_avatar = null;
    form.remove_brand_avatar = true;
    avatarPreview.value = '';
};

const submit = () => {
    form.transform((data) => {
        const payload = {
            ...data,
            brand_primary_color: normalizedColor.value,
            _method: 'put',
        };

        if (!payload.brand_logo) delete payload.brand_logo;
        if (!payload.brand_avatar) delete payload.brand_avatar;

        return payload;
    }).post(route('admin.branding.update'), { preserveScroll: true, forceFormData: true });
};

const pendingAccess = computed(() => props.supportAccess?.pending ?? []);
const activeAccess = computed(() => props.supportAccess?.active ?? []);
const canApproveSupport = computed(() => Boolean(props.supportAccess?.canApprove));

const supportActionForm = useForm({});
const confirmSupportOpen = ref(false);
const confirmSupportAction = ref('approve');
const confirmSupportGrant = ref(null);

const openSupportConfirm = (action, grant) => {
    confirmSupportAction.value = action;
    confirmSupportGrant.value = grant;
    confirmSupportOpen.value = true;
};

const closeSupportConfirm = () => {
    confirmSupportOpen.value = false;
    confirmSupportGrant.value = null;
};

const confirmSupportTitle = computed(() => {
    if (confirmSupportAction.value === 'approve') return 'Aprovar acesso?';
    if (confirmSupportAction.value === 'revoke') return 'Revogar acesso?';
    return 'Rejeitar acesso?';
});

const confirmSupportIcon = computed(() => (confirmSupportAction.value === 'approve' ? UserCheck : UserX));
const confirmSupportIconWrapClass = computed(() => (confirmSupportAction.value === 'approve' ? 'bg-emerald-600' : 'bg-rose-600'));

const confirmSupportActionLabel = computed(() => {
    if (confirmSupportAction.value === 'approve') return 'Aprovar';
    if (confirmSupportAction.value === 'revoke') return 'Revogar';
    return 'Rejeitar';
});

const confirmSupportDescription = computed(() => {
    const userName = confirmSupportGrant.value?.user?.name ?? 'usuário';

    if (confirmSupportAction.value === 'approve') {
        return `Aprovar solicitação de ${userName}?`;
    }

    if (confirmSupportAction.value === 'revoke') {
        return `Revogar acesso ativo de ${userName}?`;
    }

    return `Rejeitar solicitação de ${userName}?`;
});

const submitSupportConfirmation = () => {
    const grantId = confirmSupportGrant.value?.id;
    if (!grantId) return;

    const routeName = confirmSupportAction.value === 'approve'
        ? 'support-access.approve'
        : confirmSupportAction.value === 'revoke'
            ? 'support-access.revoke'
            : 'support-access.reject';

    supportActionForm.post(route(routeName, grantId), {
        preserveScroll: true,
        onSuccess: closeSupportConfirm,
    });
};
</script>

<template>
    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Branding">
        <Head title="Branding" />

        <div
            v-if="statusMessage"
            class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            {{ statusMessage }}
        </div>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,360px)]">
            <form
                class="overflow-hidden rounded-3xl border border-emerald-100 bg-white/95 shadow-sm"
                @submit.prevent="submit"
            >
                <header class="flex items-center justify-between border-b border-emerald-100/70 px-6 py-5 md:px-8">
                    <h2 class="text-sm font-semibold text-emerald-900">Ajustes de identidade</h2>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-semibold text-emerald-700">
                        Visível imediatamente
                    </span>
                </header>

                <div class="space-y-6 px-6 py-6 md:px-8">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nome exibido</label>
                            <input
                                v-model="form.brand_name"
                                type="text"
                                class="w-full rounded-md border border-emerald-100 px-3 py-2 text-sm"
                                placeholder="Ex.: Filial Salvador"
                            >
                            <p v-if="form.errors.brand_name" class="text-[11px] text-rose-600">{{ form.errors.brand_name }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Fuso horário</label>
                            <UiSelect v-model="form.timezone" :options="timezoneOptions" button-class="w-full text-sm" />
                            <p v-if="form.errors.timezone" class="text-[11px] text-rose-600">{{ form.errors.timezone }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                        <div class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Cor primária</label>
                            <div class="flex items-center gap-3">
                                <input v-model="form.brand_primary_color" type="color" class="h-12 w-16 rounded-md border border-emerald-200">
                                <input
                                    v-model="form.brand_primary_color"
                                    type="text"
                                    class="flex-1 rounded-md border border-emerald-100 px-3 py-2 text-sm"
                                    placeholder="#557D97"
                                >
                            </div>
                            <p v-if="form.errors.brand_primary_color" class="text-[11px] text-rose-600">{{ form.errors.brand_primary_color }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Contato principal</label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full rounded-md border border-emerald-100 px-3 py-2 text-sm"
                                placeholder="contato@empresa.com"
                            >
                            <p v-if="form.errors.email" class="text-[11px] text-rose-600">{{ form.errors.email }}</p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <BrandingImageUploader
                                label="Logo do contratante"
                                :help-text="props.storageConfigured ? 'Sugestão: 320x80px.' : 'Configure o storage para upload.'"
                                :initial-preview="logoPreview"
                                :aspect-ratio="3.6"
                                :disabled="!props.storageConfigured"
                                @change="handleLogoChange"
                            />
                            <button
                                v-if="logoPreview"
                                type="button"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                @click="removeLogo"
                            >
                                Remover logo
                            </button>
                        </div>

                        <div class="space-y-2">
                            <BrandingImageUploader
                                label="Avatar/Ícone"
                                :help-text="props.storageConfigured ? 'Formato quadrado.' : 'Configure o storage para upload.'"
                                :initial-preview="avatarPreview"
                                :aspect-ratio="1"
                                :disabled="!props.storageConfigured"
                                @change="handleAvatarChange"
                            />
                            <button
                                v-if="avatarPreview"
                                type="button"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                @click="removeAvatar"
                            >
                                Remover avatar
                            </button>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Uso de armazenamento</p>
                        <div class="mt-2 text-xs text-slate-600">
                            <p><strong class="text-slate-800">Usado:</strong> {{ formatBytes(props.storageUsage?.used_bytes) }}</p>
                            <p><strong class="text-slate-800">Limite do plano:</strong> {{ storageLimitLabel }}</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Configurações</p>
                        <div class="mt-3 space-y-3 text-sm text-slate-700">
                            <label class="flex items-center justify-between gap-4">
                                <span>Verificação de email obrigatória</span>
                                <input v-model="form.require_email_verification" type="checkbox" class="rounded border-slate-300">
                            </label>
                            <label class="flex items-center justify-between gap-4">
                                <span>Ativar notificações via email</span>
                                <input v-model="form.email_notifications_enabled" type="checkbox" class="rounded border-slate-300">
                            </label>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nicho do contratante</p>
                        <div class="mt-3 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                            <p class="text-sm font-semibold text-slate-800">{{ currentNicheLabel }}</p>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                Somente leitura
                            </span>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">
                            O nicho é definido na configuração do contratante e não pode ser alterado por aqui.
                        </p>
                        <p class="mt-2 text-xs text-slate-500">
                            Plano ativo:
                            <span class="font-semibold text-slate-700">{{ props.profileContractor?.active_plan_name ?? 'Sem plano' }}</span>
                        </p>
                    </div>
                </div>

                <footer class="border-t border-emerald-100/70 bg-white/80 px-6 py-5 md:px-8">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        Salvar alterações
                    </button>
                </footer>
            </form>

            <aside class="space-y-4 rounded-3xl border border-emerald-100 bg-white/95 p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-emerald-900">Pré-visualização</h3>
                <div class="overflow-hidden rounded-2xl border border-slate-200/70">
                    <div class="flex items-center gap-3 px-4 py-3" :style="{ backgroundColor: normalizedColor }">
                        <img
                            v-if="previewBranding.avatar_url"
                            :src="previewBranding.avatar_url"
                            class="h-8 w-auto rounded-md bg-white/10 p-1"
                            :alt="previewBranding.name"
                        >
                        <div
                            v-else
                            class="flex h-9 w-9 items-center justify-center rounded-md bg-white/10 text-sm font-semibold text-white"
                        >
                            {{ (previewBranding.name?.substring(0, 2) || 'CT').toUpperCase() }}
                        </div>
                        <span class="text-sm font-semibold text-white">{{ previewBranding.name }}</span>
                    </div>
                    <div class="bg-slate-50/60 px-4 py-3 text-xs text-slate-600">
                        <div class="ml-2 flex items-center gap-2">
                            <LayoutDashboard class="h-4 w-4 text-slate-600" />
                            <p class="font-semibold text-slate-800">Início</p>
                        </div>
                        <div class="mt-3 flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-slate-700">
                            <BarChart3 class="h-4 w-4" />
                            <span class="text-sm font-medium">Visão geral</span>
                        </div>
                    </div>
                </div>

                <section
                    v-if="canApproveSupport || pendingAccess.length || activeAccess.length"
                    class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Acesso de suporte</p>
                    <div class="mt-3 space-y-2 text-xs text-slate-600">
                        <p>Solicitações pendentes: {{ pendingAccess.length }}</p>
                        <p>Acessos ativos: {{ activeAccess.length }}</p>
                        <div v-if="canApproveSupport && (pendingAccess[0] || activeAccess[0])" class="pt-2">
                            <ActionButton
                                type="button"
                                class="border-emerald-200 bg-emerald-50 text-emerald-700"
                                @click="openSupportConfirm('approve', pendingAccess[0] ?? activeAccess[0])"
                            >
                                Gerenciar
                            </ActionButton>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        <Modal :show="confirmSupportOpen" max-width="md" @close="closeSupportConfirm">
            <div class="flex w-full flex-col bg-white">
                <div class="flex items-center gap-2 border-b border-slate-200 px-6 py-4">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full" :class="confirmSupportIconWrapClass">
                        <component :is="confirmSupportIcon" class="h-5 w-5 text-white" />
                    </span>
                    <h3 class="text-sm font-semibold text-slate-900">{{ confirmSupportTitle }}</h3>
                </div>
                <div class="px-6 py-5 text-sm text-slate-600">{{ confirmSupportDescription }}</div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-6 py-4">
                    <SecondaryButton type="button" @click="closeSupportConfirm">Cancelar</SecondaryButton>
                    <PrimaryButton
                        type="button"
                        :disabled="supportActionForm.processing"
                        @click="submitSupportConfirmation"
                    >
                        {{ confirmSupportActionLabel }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
