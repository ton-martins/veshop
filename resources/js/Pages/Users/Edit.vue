<script setup>
import InputError from '@/Components/InputError.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    userData: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        default: () => [],
    },
    contractors: {
        type: Array,
        default: () => [],
    },
});

const addressJson = ref(props.userData.address ? JSON.stringify(props.userData.address, null, 2) : '');
const preferencesJson = ref(props.userData.preferences ? JSON.stringify(props.userData.preferences, null, 2) : '');
const jsonError = ref('');

const form = useForm({
    contractor_ids: props.userData.contractor_ids ?? [],
    name: props.userData.name ?? '',
    email: props.userData.email ?? '',
    cpf: props.userData.cpf ?? '',
    phone: props.userData.phone ?? '',
    password: '',
    password_confirmation: '',
    role: props.userData.role ?? 'admin',
    job_title: props.userData.job_title ?? '',
    avatar_url: props.userData.avatar_url ?? '',
    is_active: Boolean(props.userData.is_active),
    address: props.userData.address ?? null,
    preferences: props.userData.preferences ?? null,
});

const parseJsonOrNull = (rawValue) => {
    const safeValue = String(rawValue ?? '').trim();
    if (!safeValue) return null;
    return JSON.parse(safeValue);
};

const submit = () => {
    jsonError.value = '';

    try {
        form.address = parseJsonOrNull(addressJson.value);
        form.preferences = parseJsonOrNull(preferencesJson.value);
    } catch {
        jsonError.value = 'Address e Preferences precisam estar em JSON válido.';
        return;
    }

    form.put(route('master.users.update', props.userData.id));
};
</script>

<template>
    <Head title="Editar usuário" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Editar usuário">

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nome</label>
                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">E-mail</label>
                    <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">CPF</label>
                    <input v-model="form.cpf" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <InputError :message="form.errors.cpf" />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telefone</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <InputError :message="form.errors.phone" />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nova senha (opcional)</label>
                    <input v-model="form.password" type="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Confirmar nova senha</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Perfil</label>
                    <select v-model="form.role" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                    <InputError :message="form.errors.role" />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo</label>
                    <input v-model="form.job_title" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <InputError :message="form.errors.job_title" />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contratante</label>
                    <select
                        v-model="form.contractor_ids"
                        multiple
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    >
                        <option v-for="contractor in contractors" :key="contractor.id" :value="contractor.id">
                            {{ contractor.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.contractor_ids" />
                    <p class="text-[11px] text-slate-500">Segure Ctrl (ou Cmd) para selecionar mais de um contratante.</p>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avatar URL</label>
                    <input v-model="form.avatar_url" type="url" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <InputError :message="form.errors.avatar_url" />
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address (JSON)</label>
                    <textarea
                        v-model="addressJson"
                        rows="3"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        placeholder='{"street":"Rua Exemplo","city":"Fortaleza","state":"CE"}'
                    ></textarea>
                    <InputError :message="form.errors.address" />
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Preferences (JSON)</label>
                    <textarea
                        v-model="preferencesJson"
                        rows="3"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                        placeholder='{"theme":"light","notifications":true}'
                    ></textarea>
                    <InputError :message="form.errors.preferences" />
                </div>

                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                        Usuário ativo
                    </label>
                    <InputError :message="form.errors.is_active" />
                </div>

                <div v-if="jsonError" class="md:col-span-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                    {{ jsonError }}
                </div>

                <div class="md:col-span-2 flex flex-wrap items-center gap-2">
                    <button
                        type="submit"
                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Salvando...' : 'Atualizar usuário' }}
                    </button>
                    <Link
                        :href="route('master.users.index')"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                    >
                        Cancelar
                    </Link>
                </div>
            </form>
        </section>
    </AuthenticatedLayout>
</template>
