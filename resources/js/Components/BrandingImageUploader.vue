<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    label: {
        type: String,
        default: 'Imagem',
    },
    helpText: {
        type: String,
        default: '',
    },
    initialPreview: {
        type: String,
        default: '',
    },
    aspectRatio: {
        type: Number,
        default: 1,
    },
    desktopAspectRatio: {
        type: Number,
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    accept: {
        type: String,
        default: 'image/*',
    },
});

const emit = defineEmits(['change']);
const localPreview = ref(props.initialPreview || '');
let objectUrl = null;

const previewStyle = computed(() => {
    const mobileRatio = Number(props.aspectRatio);
    const desktopRatio = Number(props.desktopAspectRatio);

    return {
        '--uploader-aspect-mobile': String(Number.isFinite(mobileRatio) && mobileRatio > 0 ? mobileRatio : 1),
        ...(Number.isFinite(desktopRatio) && desktopRatio > 0
            ? { '--uploader-aspect-desktop': String(desktopRatio) }
            : {}),
    };
});

watch(
    () => props.initialPreview,
    (value) => {
        if (!objectUrl) {
            localPreview.value = value || '';
        }
    },
);

const clearObjectUrl = () => {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
};

const handleFileChange = (event) => {
    const file = event.target?.files?.[0] ?? null;

    clearObjectUrl();

    if (!file) {
        localPreview.value = props.initialPreview || '';
        emit('change', { file: null, preview: localPreview.value });
        return;
    }

    objectUrl = URL.createObjectURL(file);
    localPreview.value = objectUrl;
    emit('change', { file, preview: objectUrl });
};

onBeforeUnmount(() => {
    clearObjectUrl();
});
</script>

<template>
    <div class="space-y-2">
        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ label }}</label>
        <div
            class="branding-image-uploader-preview overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
            :style="previewStyle"
        >
            <img
                v-if="localPreview"
                :src="localPreview"
                :alt="label"
                class="h-full w-full object-contain"
            />
            <div
                v-else
                class="flex h-full w-full items-center justify-center text-xs font-semibold text-slate-400"
            >
                Sem imagem
            </div>
        </div>
        <input
            type="file"
            :accept="accept"
            class="block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="disabled"
            @change="handleFileChange"
        />
        <p v-if="helpText" class="text-[11px] text-slate-500">{{ helpText }}</p>
    </div>
</template>

<style scoped>
.branding-image-uploader-preview {
    aspect-ratio: var(--uploader-aspect-mobile, 1);
}

@media (min-width: 768px) {
    .branding-image-uploader-preview {
        aspect-ratio: var(--uploader-aspect-desktop, var(--uploader-aspect-mobile, 1));
    }
}
</style>
