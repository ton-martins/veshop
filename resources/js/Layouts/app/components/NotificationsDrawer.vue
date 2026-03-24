<script setup>
defineProps({
    unreadNotifications: { type: Number, default: 0 },
    notificationItems: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
    canClear: { type: Boolean, default: false },
});

const emit = defineEmits([
    'close',
    'mark-all',
    'clear-all',
    'mark-one',
    'open-target',
]);
</script>

<template>
    <aside class="notifications-drawer absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
        <div class="border-b border-slate-200 px-4 py-4 sm:px-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-900">Notificações</p>
                    <p class="mt-1 text-xs text-slate-500">{{ unreadNotifications }} não lida(s)</p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-600 transition hover:bg-slate-200"
                    title="Fechar notificações"
                    aria-label="Fechar notificações"
                    @click="emit('close')"
                >
                    <span class="sr-only">Fechar</span>
                    ×
                </button>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="processing || unreadNotifications <= 0"
                    @click="emit('mark-all')"
                >
                    Marcar todas como lidas
                </button>
                <button
                    v-if="canClear"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="processing || notificationItems.length <= 0"
                    @click="emit('clear-all')"
                >
                    Limpar notificações
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-5">
            <div v-if="notificationItems.length" class="space-y-3">
                <article
                    v-for="item in notificationItems"
                    :key="item.id"
                    class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm"
                    :class="!item.read_at ? 'ring-1 ring-blue-100' : ''"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ item.title }}</p>
                            <p v-if="item.message" class="mt-1 text-sm text-slate-600">{{ item.message }}</p>
                            <p class="mt-2 text-xs text-slate-400">{{ item.created_at }}</p>
                        </div>
                        <span
                            v-if="!item.read_at"
                            class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700"
                        >
                            Nova
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            v-if="item.target_url"
                            type="button"
                            class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-50"
                            :disabled="processing"
                            @click="emit('open-target', item)"
                        >
                            Abrir
                        </button>
                        <button
                            v-if="!item.read_at"
                            type="button"
                            class="inline-flex items-center rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-[11px] font-semibold text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="processing"
                            @click="emit('mark-one', item.id)"
                        >
                            Marcar como lida
                        </button>
                        <span
                            v-else
                            class="inline-flex items-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-semibold text-emerald-700"
                        >
                            Lida
                        </span>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500"
            >
                Você ainda não possui notificações.
            </div>
        </div>
    </aside>
</template>
