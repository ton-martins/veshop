<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AppModal from './AppModal.vue';

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Confirmar ação',
    },
    description: {
        type: String,
        default: 'Tem certeza que deseja continuar?',
    },
    confirmLabel: {
        type: String,
        default: 'Confirmar',
    },
    cancelLabel: {
        type: String,
        default: 'Cancelar',
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['close', 'confirm']);
</script>

<template>
    <AppModal :show="show" @close="$emit('close')">
        <div class="space-y-5">
            <div class="space-y-1">
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ title }}
                </h3>
                <p class="text-sm text-slate-600">
                    {{ description }}
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <SecondaryButton @click="$emit('close')">
                    {{ cancelLabel }}
                </SecondaryButton>
                <PrimaryButton :disabled="processing" @click="$emit('confirm')">
                    {{ confirmLabel }}
                </PrimaryButton>
            </div>
        </div>
    </AppModal>
</template>
