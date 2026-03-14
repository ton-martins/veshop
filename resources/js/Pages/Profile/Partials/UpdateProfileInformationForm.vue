<script setup>
import InputError from '@/Components/InputError.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});

const form = useForm({
    name: user.value.name ?? '',
    email: user.value.email ?? '',
    avatar: null,
    remove_avatar: false,
});

const avatarInput = ref(null);
const avatarPreviewUrl = ref('');
const avatarImageError = ref(false);

const normalizeStorageAvatarUrl = (value) => {
    const raw = String(value ?? '').trim();
    if (!raw) return '';

    try {
        const parsed = new URL(raw, 'http://local');
        const path = String(parsed.pathname || '');
        if (path.startsWith('/storage/')) {
            return `${path}${parsed.search || ''}`;
        }
    } catch {
        // ignore
    }

    if (raw.startsWith('/storage/')) return raw;
    if (raw.startsWith('storage/')) return `/${raw}`;

    return raw;
};

const revokeAvatarPreview = () => {
    if (avatarPreviewUrl.value && avatarPreviewUrl.value.startsWith('blob:')) {
        URL.revokeObjectURL(avatarPreviewUrl.value);
    }

    avatarPreviewUrl.value = '';
};

const currentAvatarUrl = computed(() => {
    if (avatarPreviewUrl.value) return avatarPreviewUrl.value;
    if (form.remove_avatar) return '';

    const value = normalizeStorageAvatarUrl(user.value.avatar_url ?? '');
    if (!value || avatarImageError.value) return '';

    return value;
});

const avatarInitials = computed(() => {
    const source = String(form.name || user.value.name || 'U').trim();
    const parts = source.split(/\s+/).filter(Boolean).slice(0, 2);
    if (!parts.length) return 'U';
    return parts.map((part) => part.charAt(0)).join('').toUpperCase();
});

const avatarFileLabel = computed(() => {
    if (!(form.avatar instanceof File)) return '';
    return form.avatar.name;
});

const chooseAvatarFile = () => {
    avatarInput.value?.click();
};

const onAvatarFileChange = (event) => {
    const input = event?.target;
    const file = input?.files?.[0] ?? null;

    revokeAvatarPreview();
    avatarImageError.value = false;
    form.remove_avatar = false;

    if (!file) {
        form.avatar = null;
        return;
    }

    form.avatar = file;
    avatarPreviewUrl.value = URL.createObjectURL(file);
};

const clearAvatar = () => {
    form.avatar = null;
    form.remove_avatar = true;
    avatarImageError.value = false;
    revokeAvatarPreview();

    if (avatarInput.value) {
        avatarInput.value.value = '';
    }
};

const submit = () => {
    form.transform((data) => {
        const payload = {
            ...data,
            _method: 'patch',
        };

        if (!(payload.avatar instanceof File)) {
            delete payload.avatar;
        }

        return payload;
    }).post(route('profile.update'), {
        preserveScroll: true,
        preserveState: false,
        forceFormData: true,
        onSuccess: () => {
            form.avatar = null;
            avatarImageError.value = false;
            revokeAvatarPreview();
            router.reload({
                preserveScroll: true,
                only: ['auth'],
            });
        },
    });
};

onBeforeUnmount(() => {
    revokeAvatarPreview();
});
</script>

<template>
    <section class="space-y-4">
        <header>
            <h2 class="text-sm font-semibold text-slate-900">
                Dados do perfil
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Atualize nome e e-mail utilizados para acesso.
            </p>
        </header>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 text-sm font-semibold text-slate-700">
                        <img
                            v-if="currentAvatarUrl"
                            :src="currentAvatarUrl"
                            alt="Avatar do usuário"
                            class="h-full w-full object-cover"
                            @error="avatarImageError = true"
                        >
                        <span v-else>{{ avatarInitials }}</span>
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                @click="chooseAvatarFile"
                            >
                                Upload de avatar
                            </button>
                            <button
                                v-if="currentAvatarUrl || avatarFileLabel"
                                type="button"
                                class="inline-flex items-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                                @click="clearAvatar"
                            >
                                Remover avatar
                            </button>
                        </div>
                        <input
                            ref="avatarInput"
                            type="file"
                            class="hidden"
                            accept="image/png,image/jpeg,image/jpg"
                            @change="onAvatarFileChange"
                        >
                        <p class="text-[11px] text-slate-500">
                            Formatos: PNG, JPG ou JPEG (até 2MB).
                        </p>
                        <p v-if="avatarFileLabel" class="text-[11px] text-slate-600">
                            Arquivo: {{ avatarFileLabel }}
                        </p>
                        <InputError class="mt-1" :message="form.errors.avatar" />
                    </div>
                </div>
            </div>

            <div class="space-y-1">
                <label for="name" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Nome
                </label>

                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:ring-slate-300"
                />

                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div class="space-y-1">
                <label for="email" class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    E-mail
                </label>

                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:ring-slate-300"
                />

                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-xs text-amber-800">
                <p>
                    Seu e-mail ainda não foi verificado.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-semibold underline underline-offset-2 hover:text-amber-900"
                    >
                        Reenviar e-mail de verificação
                    </Link>
                </p>

                <p
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 font-semibold text-emerald-700"
                >
                    Novo link de verificação enviado com sucesso.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="submit"
                    class="inline-flex items-center rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Salvando...' : 'Salvar alterações' }}
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-xs font-semibold text-emerald-700"
                    >
                        Dados atualizados.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>

