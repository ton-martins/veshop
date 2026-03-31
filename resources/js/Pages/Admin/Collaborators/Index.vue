<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Briefcase, Mail, Pencil, Phone, Plus, Tags, Trash2, UserRound } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    niche: { type: String, default: 'commercial' },
    collaborators: { type: Array, default: () => [] },
    serviceCategories: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const page = usePage();
const statusMessage = computed(() => String(page.props.flash?.status ?? '').trim());
const isServicesNiche = computed(() => String(props.niche ?? '').toLowerCase() === 'services');

const modalOpen = ref(false);
const deleteModalOpen = ref(false);
const editingCollaborator = ref(null);
const collaboratorToDelete = ref(null);
const photoPreview = ref('');
const photoInput = ref(null);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    job_title: '',
    notes: '',
    is_active: true,
    service_category_ids: [],
    photo: null,
    remove_photo: false,
});

const resetPhotoPreview = () => {
    if (photoPreview.value && photoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(photoPreview.value);
    }

    photoPreview.value = '';
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.is_active = true;
    form.service_category_ids = [];
    form.photo = null;
    form.remove_photo = false;
    editingCollaborator.value = null;
    resetPhotoPreview();

    if (photoInput.value) {
        photoInput.value.value = '';
    }
};

const openCreateModal = () => {
    resetForm();
    modalOpen.value = true;
};

const openEditModal = (collaborator) => {
    resetForm();
    editingCollaborator.value = collaborator;
    form.name = String(collaborator?.name ?? '');
    form.email = String(collaborator?.email ?? '');
    form.phone = String(collaborator?.phone ?? '');
    form.job_title = String(collaborator?.job_title ?? '');
    form.notes = String(collaborator?.notes ?? '');
    form.is_active = Boolean(collaborator?.is_active);
    form.service_category_ids = Array.isArray(collaborator?.service_category_ids)
        ? collaborator.service_category_ids.map((id) => Number(id))
        : [];
    photoPreview.value = String(collaborator?.photo_url ?? '').trim();
    modalOpen.value = true;
};

const closeModal = () => {
    modalOpen.value = false;
    resetForm();
};

const triggerPhotoPicker = () => {
    photoInput.value?.click();
};

const handlePhotoChange = (event) => {
    const [file] = event?.target?.files ?? [];

    resetPhotoPreview();
    form.remove_photo = false;

    if (!(file instanceof File)) {
        form.photo = null;
        if (editingCollaborator.value?.photo_url) {
            photoPreview.value = String(editingCollaborator.value.photo_url);
        }
        return;
    }

    form.photo = file;
    photoPreview.value = URL.createObjectURL(file);
};

const removePhoto = () => {
    form.photo = null;
    form.remove_photo = true;
    resetPhotoPreview();

    if (photoInput.value) {
        photoInput.value.value = '';
    }
};

const toggleCategory = (categoryId) => {
    const safeId = Number(categoryId);
    const current = new Set((form.service_category_ids ?? []).map((id) => Number(id)));

    if (current.has(safeId)) {
        current.delete(safeId);
    } else {
        current.add(safeId);
    }

    form.service_category_ids = Array.from(current.values());
};

const submit = () => {
    form.transform((data) => {
        const payload = {
            ...data,
            ...(editingCollaborator.value?.id ? { _method: 'put' } : {}),
        };

        if (!payload.photo) {
            delete payload.photo;
        }

        return payload;
    });

    if (editingCollaborator.value?.id) {
        form.post(route('admin.collaborators.update', editingCollaborator.value.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closeModal,
            onFinish: () => form.transform((data) => data),
        });
        return;
    }

    form.post(route('admin.collaborators.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeModal,
        onFinish: () => form.transform((data) => data),
    });
};

const openDeleteModal = (collaborator) => {
    collaboratorToDelete.value = collaborator;
    deleteModalOpen.value = true;
};

const closeDeleteModal = () => {
    collaboratorToDelete.value = null;
    deleteModalOpen.value = false;
};

const destroyCollaborator = () => {
    if (!collaboratorToDelete.value?.id) return;

    form.delete(route('admin.collaborators.destroy', collaboratorToDelete.value.id), {
        preserveScroll: true,
        onSuccess: closeDeleteModal,
    });
};

const avatarFallback = (name) => {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'CL';
    const first = parts[0]?.charAt(0) ?? '';
    const last = parts.length > 1 ? parts[parts.length - 1]?.charAt(0) ?? '' : '';
    return `${first}${last}`.toUpperCase();
};

onBeforeUnmount(() => {
    resetPhotoPreview();
});
</script>

<template>
    <AuthenticatedLayout area="admin" header-variant="compact" header-title="Colaboradores">
        <Head title="Colaboradores" />

        <div class="space-y-6">
            <section class="rounded-3xl border border-emerald-100 bg-white/95 p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="space-y-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Equipe do contratante</p>
                        <h1 class="text-xl font-semibold text-slate-900">Cadastro de colaboradores</h1>
                        <p class="text-sm text-slate-500">
                            Centralize quem atua na operacao e, no nicho de servicos, vincule categorias para disponibilizar a agenda na loja virtual.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                        @click="openCreateModal"
                    >
                        <Plus class="h-4 w-4" />
                        Novo colaborador
                    </button>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-4">
                    <article class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ stats.total ?? 0 }}</p>
                    </article>
                    <article class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Ativos</p>
                        <p class="mt-2 text-2xl font-semibold text-emerald-900">{{ stats.active ?? 0 }}</p>
                    </article>
                    <article class="rounded-2xl border border-sky-100 bg-sky-50/70 px-4 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-sky-700">Com foto</p>
                        <p class="mt-2 text-2xl font-semibold text-sky-900">{{ stats.with_photo ?? 0 }}</p>
                    </article>
                    <article class="rounded-2xl border border-amber-100 bg-amber-50/70 px-4 py-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Categorias vinculadas</p>
                        <p class="mt-2 text-2xl font-semibold text-amber-900">{{ stats.with_categories ?? 0 }}</p>
                    </article>
                </div>
            </section>

            <div v-if="statusMessage" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ statusMessage }}
            </div>

            <section v-if="collaborators.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="collaborator in collaborators"
                    :key="`collaborator-${collaborator.id}`"
                    class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 text-sm font-semibold text-slate-700">
                                <img
                                    v-if="collaborator.photo_url"
                                    :src="collaborator.photo_url"
                                    :alt="collaborator.name"
                                    class="h-full w-full object-cover"
                                >
                                <span v-else>{{ avatarFallback(collaborator.name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-base font-semibold text-slate-900">{{ collaborator.name }}</p>
                                <p class="truncate text-sm text-slate-500">{{ collaborator.job_title || 'Sem funcao definida' }}</p>
                            </div>
                        </div>

                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold"
                            :class="collaborator.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ collaborator.is_active ? 'Ativo' : 'Inativo' }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-2 text-sm text-slate-600">
                        <p class="flex items-center gap-2">
                            <Mail class="h-4 w-4 text-slate-400" />
                            <span class="truncate">{{ collaborator.email || 'Sem e-mail' }}</span>
                        </p>
                        <p class="flex items-center gap-2">
                            <Phone class="h-4 w-4 text-slate-400" />
                            <span>{{ collaborator.phone || 'Sem telefone' }}</span>
                        </p>
                    </div>

                    <div v-if="isServicesNiche" class="mt-4 space-y-2">
                        <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <Tags class="h-4 w-4" />
                            Categorias
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="category in collaborator.service_categories"
                                :key="`collaborator-category-${collaborator.id}-${category.id}`"
                                class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
                            >
                                {{ category.name }}
                            </span>
                            <span
                                v-if="!collaborator.service_categories.length"
                                class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500"
                            >
                                Sem categorias vinculadas
                            </span>
                        </div>
                    </div>

                    <div v-if="isServicesNiche && collaborator.recent_appointments.length" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Agenda recente</p>
                        <ul class="mt-2 space-y-2">
                            <li
                                v-for="appointment in collaborator.recent_appointments"
                                :key="`appointment-${appointment.id}`"
                                class="rounded-xl bg-white px-3 py-2 text-xs text-slate-600"
                            >
                                <p class="font-semibold text-slate-800">{{ appointment.title }}</p>
                                <p>{{ appointment.starts_at }} - {{ appointment.status }}</p>
                            </li>
                        </ul>
                    </div>

                    <p v-if="collaborator.notes" class="mt-4 text-sm text-slate-500">{{ collaborator.notes }}</p>

                    <div class="mt-5 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="openEditModal(collaborator)"
                        >
                            <Pencil class="h-3.5 w-3.5" />
                            Editar
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                            @click="openDeleteModal(collaborator)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Remover
                        </button>
                    </div>
                </article>
            </section>

            <section v-else class="rounded-3xl border border-dashed border-slate-300 bg-white/80 px-6 py-12 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                    <UserRound class="h-6 w-6" />
                </div>
                <h2 class="mt-4 text-lg font-semibold text-slate-900">Nenhum colaborador cadastrado</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Monte a equipe que aparece na operacao interna e, em servicos, disponibilize profissionais para o agendamento online.
                </p>
                <button
                    type="button"
                    class="mt-5 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                    @click="openCreateModal"
                >
                    <Plus class="h-4 w-4" />
                    Criar primeiro colaborador
                </button>
            </section>
        </div>

        <Modal :show="modalOpen" max-width="4xl" @close="closeModal">
            <div class="rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Equipe</p>
                        <h3 class="text-lg font-semibold text-slate-900">
                            {{ editingCollaborator?.id ? 'Editar colaborador' : 'Novo colaborador' }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Fechar
                    </button>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-[220px_minmax(0,1fr)]">
                    <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                        <div class="flex h-44 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <img
                                v-if="photoPreview"
                                :src="photoPreview"
                                :alt="form.name || 'Colaborador'"
                                class="h-full w-full object-cover"
                            >
                            <div v-else class="flex h-full w-full flex-col items-center justify-center gap-2 text-slate-500">
                                <UserRound class="h-8 w-8" />
                                <span class="text-xs font-semibold uppercase">Sem foto</span>
                            </div>
                        </div>

                        <input
                            ref="photoInput"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="hidden"
                            @change="handlePhotoChange"
                        >

                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="triggerPhotoPicker"
                        >
                            <UserRound class="h-4 w-4" />
                            {{ photoPreview ? 'Trocar foto' : 'Enviar foto' }}
                        </button>
                        <button
                            v-if="photoPreview"
                            type="button"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"
                            @click="removePhoto"
                        >
                            <Trash2 class="h-4 w-4" />
                            Remover foto
                        </button>
                        <InputError :message="form.errors.photo" />
                    </div>

                    <div class="space-y-5">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-1.5 md:col-span-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                                <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Funcao</label>
                                <div class="relative">
                                    <Briefcase class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <input v-model="form.job_title" type="text" class="w-full rounded-xl border border-slate-200 py-2.5 pl-10 pr-3 text-sm text-slate-700">
                                </div>
                                <InputError :message="form.errors.job_title" />
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                                <select v-model="form.is_active" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                    <option :value="true">Ativo</option>
                                    <option :value="false">Inativo</option>
                                </select>
                                <InputError :message="form.errors.is_active" />
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                                <input v-model="form.email" type="email" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700">
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                                <input v-model="form.phone" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-700" placeholder="(00) 00000-0000">
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>

                        <div v-if="isServicesNiche" class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4">
                            <div class="flex items-center gap-2">
                                <Tags class="h-4 w-4 text-emerald-700" />
                                <h4 class="text-sm font-semibold text-slate-900">Categorias atendidas</h4>
                            </div>
                            <p class="mt-1 text-xs text-slate-600">
                                Vincule uma ou mais categorias para disponibilizar este colaborador no agendamento online.
                            </p>
                            <div class="mt-3 grid gap-2 md:grid-cols-2">
                                <label
                                    v-for="category in serviceCategories"
                                    :key="`service-category-${category.id}`"
                                    class="flex items-center gap-3 rounded-xl border border-emerald-100 bg-white px-3 py-2 text-sm text-slate-700"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="form.service_category_ids.includes(category.id)"
                                        @change="toggleCategory(category.id)"
                                    >
                                    <span>{{ category.name }}</span>
                                </label>
                            </div>
                            <InputError :message="form.errors.service_category_ids" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Observacoes</label>
                            <textarea v-model="form.notes" rows="4" class="w-full rounded-2xl border border-slate-200 px-3 py-3 text-sm text-slate-700"></textarea>
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        {{ form.processing ? 'Salvando...' : (editingCollaborator?.id ? 'Salvar alteracoes' : 'Criar colaborador') }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="deleteModalOpen" max-width="md" @close="closeDeleteModal">
            <div class="rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-semibold text-slate-900">Remover colaborador</h3>
                <p class="mt-2 text-sm text-slate-500">
                    Esta acao remove o cadastro de <span class="font-semibold text-slate-700">{{ collaboratorToDelete?.name }}</span>.
                </p>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="closeDeleteModal"
                    >
                        Voltar
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:opacity-60"
                        :disabled="form.processing"
                        @click="destroyCollaborator"
                    >
                        {{ form.processing ? 'Removendo...' : 'Confirmar remocao' }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
