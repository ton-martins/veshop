<script setup>
import { X } from 'lucide-vue-next';
import { computed, useSlots } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    steps: {
        type: Array,
        default: () => ['Formulário'],
    },
    currentStep: {
        type: Number,
        default: 1,
    },
    stepsClickable: {
        type: Boolean,
        default: false,
    },
    maxClickableStep: {
        type: Number,
        default: null,
    },
    showClose: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close', 'step-change']);
const slots = useSlots();

const stepsSafe = computed(() => {
    if (!Array.isArray(props.steps) || props.steps.length === 0) return ['Formulário'];
    return props.steps;
});

const currentStepSafe = computed(() => {
    const maxStep = stepsSafe.value.length;
    return Math.min(Math.max(Number(props.currentStep) || 1, 1), maxStep);
});

const stepsGridClass = computed(() => {
    const total = stepsSafe.value.length;
    if (total <= 1) return 'grid-cols-1';
    if (total === 2) return 'sm:grid-cols-2';
    if (total === 3) return 'sm:grid-cols-3';
    return 'sm:grid-cols-2 lg:grid-cols-4';
});

const hasFooter = computed(() => Boolean(slots.footer));

const isStepClickable = (stepNumber) => {
    if (!props.stepsClickable) return false;

    if (props.maxClickableStep === null || props.maxClickableStep === undefined) return true;

    const parsedMax = Number(props.maxClickableStep);
    if (!Number.isFinite(parsedMax)) return true;

    const safeMax = Math.max(1, Math.floor(parsedMax));
    return stepNumber <= safeMax;
};

const onStepClick = (stepNumber) => {
    if (!isStepClickable(stepNumber)) return;
    emit('step-change', stepNumber);
};
</script>

<template>
    <div class="space-y-4 bg-white p-5 sm:p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
                <p v-if="description" class="text-sm text-slate-500">{{ description }}</p>
            </div>

            <div class="flex items-center gap-2 self-start">
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                    Etapa {{ currentStepSafe }} de {{ stepsSafe.length }}
                </span>
                <button
                    v-if="showClose"
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                    aria-label="Fechar modal"
                    @click="emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="grid gap-2" :class="stepsGridClass">
            <button
                v-for="(step, index) in stepsSafe"
                :key="`wizard-step-${index}`"
                type="button"
                :disabled="!isStepClickable(index + 1)"
                class="flex items-center gap-2 rounded-xl border px-3 py-2 text-left text-xs font-semibold transition disabled:cursor-default"
                :class="[
                    index + 1 === currentStepSafe ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600',
                    isStepClickable(index + 1) ? 'hover:border-slate-300 hover:bg-slate-50' : '',
                ]"
                @click="onStepClick(index + 1)"
            >
                {{ step }}
            </button>
        </div>

        <slot />

        <div v-if="hasFooter" class="border-t border-slate-200 pt-3">
            <slot name="footer" />
        </div>
    </div>
</template>
