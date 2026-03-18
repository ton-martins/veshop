<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaginationLinks from '@/Components/App/PaginationLinks.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Bell, CheckCheck, Check } from 'lucide-vue-next';

const props = defineProps({
    notifications: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },
    unread_count: { type: Number, default: 0 },
});

const page = usePage();
const area = computed(() => (page.props.auth?.user?.role === 'master' ? 'master' : 'admin'));
const notificationItems = computed(() => (
    Array.isArray(props.notifications?.data) ? props.notifications.data : []
));
const paginationLinks = computed(() => (
    Array.isArray(props.notifications?.links) ? props.notifications.links : []
));

const markReadForm = useForm({ id: '' });

const markAllAsRead = () => {
    markReadForm.transform(() => ({ id: '' })).post(route('notifications.read'), {
        preserveScroll: true,
    });
};

const markOneAsRead = (id) => {
    if (!id) return;
    markReadForm.transform(() => ({ id })).post(route('notifications.read'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Notificações" />

    <AuthenticatedLayout :area="area" header-variant="compact" header-title="Notificações">
        <section class="space-y-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                            <Bell class="h-4 w-4" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Central de notificações</p>
                            <p class="text-xs text-slate-500">{{ unread_count }} não lida(s)</p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                        :disabled="markReadForm.processing || unread_count <= 0"
                        @click="markAllAsRead"
                    >
                        <CheckCheck class="h-3.5 w-3.5" />
                        Marcar todas como lidas
                    </button>
                </div>
            </article>

            <div v-if="notificationItems.length" class="space-y-3">
                <article
                    v-for="item in notificationItems"
                    :key="item.id"
                    class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    :class="!item.read_at ? 'ring-1 ring-blue-100' : ''"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ item.message }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ item.created_at }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link
                                v-if="item.target_url"
                                :href="item.target_url"
                                class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Abrir
                            </Link>
                            <button
                                v-if="!item.read_at"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-[11px] font-semibold text-blue-700 hover:bg-blue-100"
                                :disabled="markReadForm.processing"
                                @click="markOneAsRead(item.id)"
                            >
                                <Check class="h-3 w-3" />
                                Lida
                            </button>
                            <span v-else class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-semibold text-emerald-700">
                                Lida
                            </span>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
                Você ainda não possui notificações.
            </div>
            <div v-if="paginationLinks.length" class="pt-1">
                <PaginationLinks :links="paginationLinks" :min-links="4" />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
