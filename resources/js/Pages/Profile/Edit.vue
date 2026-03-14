<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? null);
</script>

<template>
    <Head title="Perfil" />

    <AuthenticatedLayout header-variant="compact" header-title="Perfil">
        <section class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-1">
                    <h2 class="text-sm font-semibold text-slate-900">Minha conta</h2>
                    <p class="text-xs text-slate-500">
                        Gerencie dados de acesso, senha e segurança da conta.
                    </p>
                    <p v-if="currentUser?.email" class="text-xs text-slate-500">
                        {{ currentUser.email }}
                    </p>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="w-full"
                    />
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                    <UpdatePasswordForm class="w-full" />
                </section>
            </div>

            <section class="rounded-2xl border border-rose-200 bg-white p-4 shadow-sm md:p-5">
                <DeleteUserForm class="w-full" />
            </section>
        </section>
    </AuthenticatedLayout>
</template>
