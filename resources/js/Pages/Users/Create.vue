<script setup>
import InputError from '@/Components/InputError.vue';
import UiSelect from '@/Components/App/UiSelect.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    roles: {
        type: Array,
        default: () => [],
    },
    contractors: {
        type: Array,
        default: () => [],
    },
});

const addressJson = ref('');
const preferencesJson = ref('');
const jsonError = ref('');

const roleOptions = props.roles.map((role) => ({
    value: role,
    label: role,
}));

const form = useForm({
    contractor_ids: [],
    name: '',
    email: '',
    cpf: '',
    phone: '',
    password: '',
    password_confirmation: '',
    role: 'admin',
    job_title: '',
    avatar_url: '',
    is_active: true,
    address: null,
    preferences: null,
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

    form.post(route('master.users.store'));
};
</script>

<template>
    <Head title="Novo usuário" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Novo usuário">

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
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Senha</label>
                    <input v-model="form.password" type="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Confirmar senha</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Perfil</label>
                    <UiSelect v-model="form.role" :options="roleOptions" button-class="w-full text-sm" />
                    <InputError :message="form.errors.role" />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cargo</label>
                    <input v-model="form.job_title" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
                    <InputError :message="form.errors.job_title" />
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contratante</label>
                    <div class="max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-white">
                        <label
                            v-for="contractor in contractors"
                            :key="contractor.id"
                            class="flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-2 text-sm text-slate-700 last:border-b-0 hover:bg-slate-50"
                        >
                            <input v-model="form.contractor_ids" type="checkbox" class="rounded border-slate-300" :value="contractor.id">
                            <span>{{ contractor.name }}</span>
                        </label>
                    </div>
                    <InputError :message="form.errors.contractor_ids" />
                    <p class="text-[11px] text-slate-500">Selecione um ou mais contratantes.</p>
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
                        {{ form.processing ? 'Salvando...' : 'Salvar usuário' }}
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
