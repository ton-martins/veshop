<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    links: {
        type: Array,
        default: () => [],
    },
    minLinks: {
        type: Number,
        default: 4,
    },
    align: {
        type: String,
        default: 'end',
    },
});

const safeLinks = computed(() => (Array.isArray(props.links) ? props.links : []));
const shouldRender = computed(() => safeLinks.value.length >= props.minLinks);

const justifyClass = computed(() => {
    if (props.align === 'start') return 'justify-start';
    if (props.align === 'center') return 'justify-center';
    return 'justify-end';
});

const decodeEntities = (value) =>
    String(value ?? '')
        .replace(/&laquo;/gi, '«')
        .replace(/&raquo;/gi, '»')
        .replace(/&nbsp;/gi, ' ')
        .replace(/&amp;/gi, '&');

const toPlainText = (value) =>
    decodeEntities(value)
        .replace(/<[^>]*>/g, '')
        .trim();

const fold = (value) =>
    toPlainText(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

const isPreviousLabel = (rawLabel) => {
    const normalized = fold(rawLabel);
    return normalized.includes('previous') || normalized.includes('anterior');
};

const isNextLabel = (rawLabel) => {
    const normalized = fold(rawLabel);
    return normalized.includes('next') || normalized.includes('proximo');
};

const previousLink = computed(() => safeLinks.value.find((link) => isPreviousLabel(link?.label)) ?? null);
const nextLink = computed(() => safeLinks.value.find((link) => isNextLabel(link?.label)) ?? null);
const pageLinks = computed(() =>
    safeLinks.value.filter((link) => !isPreviousLabel(link?.label) && !isNextLabel(link?.label)),
);

const displayLabel = (rawLabel) => {
    const plain = toPlainText(rawLabel);
    const plainWithoutArrows = plain.replace(/[«»]/g, '').trim();
    const normalized = fold(plainWithoutArrows);

    if (normalized.includes('previous')) return 'Anterior';
    if (normalized.includes('next')) return 'Próximo';

    return plainWithoutArrows || plain;
};
</script>

<template>
    <div v-if="shouldRender" class="mt-4 flex flex-wrap items-center gap-2" :class="justifyClass">
        <component
            :is="previousLink?.url ? Link : 'span'"
            :href="previousLink?.url || undefined"
            class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
            :class="[
                previousLink?.active
                    ? 'border-slate-900 bg-slate-900 text-white'
                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                !previousLink?.url ? 'cursor-not-allowed opacity-50' : '',
            ]"
        >
            <ChevronLeft class="h-3.5 w-3.5" />
            Anterior
        </component>

        <component
            :is="link.url ? Link : 'span'"
            v-for="(link, index) in pageLinks"
            :key="`pagination-link-${index}-${displayLabel(link.label)}`"
            :href="link.url || undefined"
            class="inline-flex items-center rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
            :class="[
                link.active
                    ? 'border-slate-900 bg-slate-900 text-white'
                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                !link.url ? 'cursor-not-allowed opacity-50' : '',
            ]"
        >
            {{ displayLabel(link.label) }}
        </component>

        <component
            :is="nextLink?.url ? Link : 'span'"
            :href="nextLink?.url || undefined"
            class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
            :class="[
                nextLink?.active
                    ? 'border-slate-900 bg-slate-900 text-white'
                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                !nextLink?.url ? 'cursor-not-allowed opacity-50' : '',
            ]"
        >
            Próximo
            <ChevronRight class="h-3.5 w-3.5" />
        </component>
    </div>
</template>
