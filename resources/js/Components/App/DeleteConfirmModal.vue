<script setup>
import Modal from '@/Components/Modal.vue';
import { AlertTriangle } from 'lucide-vue-next';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Confirmar exclusão',
    },
    message: {
        type: String,
        default: 'Esta ação não pode ser desfeita.',
    },
    itemLabel: {
        type: String,
        default: '',
    },
    confirmLabel: {
        type: String,
        default: 'Excluir',
    },
    cancelLabel: {
        type: String,
        default: 'Cancelar',
    },
    processing: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: 'lg',
    },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Modal :show="show" :max-width="maxWidth" @close="emit('close')">
        <div class="space-y-4 rounded-3xl p-6">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
                    <AlertTriangle class="h-5 w-5" />
                </span>
                <div class="space-y-1">
                    <h3 class="text-base font-semibold text-slate-900">{{ title }}</h3>
                    <p class="text-sm text-slate-600">{{ message }}</p>
                    <p v-if="itemLabel" class="text-xs font-semibold text-slate-500">{{ itemLabel }}</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    :disabled="processing"
                    @click="emit('close')"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ processing ? 'Excluindo...' : confirmLabel }}
                </button>
            </div>
        </div>
    </Modal>
</template>
