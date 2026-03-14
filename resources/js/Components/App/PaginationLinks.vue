<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

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

const displayLabel = (rawLabel) => {
    const plain = toPlainText(rawLabel);
    const plainWithoutArrows = plain.replace(/[«»]/g, '').trim();
    const normalized = plainWithoutArrows.toLowerCase();

    if (normalized === 'previous') {
        return 'Anterior';
    }

    if (normalized === 'next') {
        return 'Proximo';
    }

    return plainWithoutArrows || plain;
};
</script>

<template>
    <div v-if="shouldRender" class="mt-4 flex flex-wrap items-center gap-2" :class="justifyClass">
        <component
            :is="link.url ? Link : 'span'"
            v-for="(link, index) in safeLinks"
            :key="`pagination-link-${index}-${displayLabel(link.label)}`"
            :href="link.url || undefined"
            class="rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
            :class="[
                link.active
                    ? 'border-slate-900 bg-slate-900 text-white'
                    : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                !link.url ? 'cursor-not-allowed opacity-50' : '',
            ]"
        >
            {{ displayLabel(link.label) }}
        </component>
    </div>
</template>
