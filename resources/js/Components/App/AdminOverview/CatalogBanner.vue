<script setup>
import { computed } from 'vue';
import { Store, ChevronRight } from 'lucide-vue-next';
import { useBranding } from '@/branding';

defineProps({
    contractorName: {
        type: String,
        default: 'Sua empresa',
    },
    catalogUrl: {
        type: String,
        default: '',
    },
});

const { primaryColor, contractorActiveGradient, withAlpha } = useBranding();

const panelStyle = computed(() => ({
    borderColor: withAlpha(primaryColor.value, 0.18),
    backgroundImage: [
        `radial-gradient(120% 140% at 100% 0%, ${withAlpha(primaryColor.value, 0.12)} 0%, transparent 55%)`,
        `radial-gradient(120% 140% at 0% 100%, ${withAlpha(primaryColor.value, 0.08)} 0%, transparent 52%)`,
        'linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(248,250,252,0.98) 55%, rgba(241,245,249,0.96) 100%)',
    ].join(','),
    boxShadow: `0 18px 40px -28px ${withAlpha(primaryColor.value, 0.35)}`,
}));

const iconStyle = computed(() => ({
    backgroundColor: withAlpha(primaryColor.value, 0.14),
    color: primaryColor.value,
    border: `1px solid ${withAlpha(primaryColor.value, 0.24)}`,
}));

const catalogLinkStyle = computed(() => ({
    color: primaryColor.value,
}));

const catalogButtonStyle = computed(() => ({
    backgroundImage: contractorActiveGradient.value,
    boxShadow: `0 12px 28px -18px ${withAlpha(primaryColor.value, 0.55)}`,
}));
</script>

<template>
    <div class="rounded-2xl border px-4 py-4 shadow-sm md:px-5" :style="panelStyle">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl" :style="iconStyle">
                    <Store class="h-4 w-4" />
                </span>
                <div>
                    <p class="text-sm font-semibold text-slate-900">Seu Catálogo Público</p>
                    <p class="text-[11px] text-slate-500">Loja ativa: {{ contractorName }}</p>
                    <a :href="catalogUrl" target="_blank" rel="noopener noreferrer" class="text-xs hover:underline" :style="catalogLinkStyle">
                        {{ catalogUrl }}
                    </a>
                </div>
            </div>

            <a
                :href="catalogUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 self-start rounded-xl px-3 py-2 text-xs font-semibold text-white transition hover:opacity-90 md:self-auto"
                :style="catalogButtonStyle"
            >
                Ver Catálogo
                <ChevronRight class="h-3.5 w-3.5" />
            </a>
        </div>
    </div>
</template>
