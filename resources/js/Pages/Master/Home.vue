<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    totals: {
        type: Object,
        default: () => ({}),
    },
});

</script>

<template>
    <Head title="Visão Geral" />

    <AuthenticatedLayout area="master" header-variant="compact" header-title="Visão Geral">

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contratantes</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ totals.contractors ?? 0 }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Admins</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ totals.admins ?? 0 }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Masters</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ totals.masters ?? 0 }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Usuários ativos</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ totals.active_users ?? 0 }}</p>
            </article>
        </section>

        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Atalhos de gestão SaaS</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <Link class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700" :href="route('master.contractors.index')">
                    Contratantes
                </Link>
                <Link class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700" :href="route('master.plans.index')">
                    Planos
                </Link>
                <Link class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700" :href="route('master.users.index')">
                    Usuários
                </Link>
                <Link class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700" :href="route('master.branding.index')">
                    Branding
                </Link>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
