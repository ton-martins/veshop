<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import Dropdown from '@/Components/Dropdown.vue';

const props = defineProps({
    mode: { type: String, default: 'desktop' },
    collapsed: { type: Boolean, default: false },
    showContractorContext: { type: Boolean, default: false },
    hasBrandingLink: { type: Boolean, default: false },
    brandingHref: { type: String, default: '#' },
    contractorLogoUrl: { type: String, default: '' },
    contractorName: { type: String, default: '' },
    contractorInitials: { type: String, default: 'CT' },
    contractorPlanName: { type: String, default: 'Sem plano' },
    canSwitchContractor: { type: Boolean, default: false },
    availableContractors: { type: Array, default: () => [] },
    isCurrentContractorOption: { type: Function, default: () => false },
    resolveContractorColor: { type: Function, default: () => '#22c55e' },
    resolveContractorInitials: { type: Function, default: () => 'CT' },
    switchContractorTo: { type: Function, default: () => {} },
});

const emit = defineEmits(['action']);

const isMobileMode = () => props.mode === 'mobile';

const handleSwitchContractor = (contractor) => {
    props.switchContractorTo(contractor);
    if (isMobileMode()) {
        emit('action');
    }
};

const handleBrandingClick = () => {
    if (isMobileMode()) {
        emit('action');
    }
};
</script>

<template>
    <template v-if="showContractorContext">
        <div
            v-if="mode === 'desktop'"
            class="veshop-head-context"
            :class="collapsed ? 'is-collapsed' : ''"
        >
            <div class="flex items-center gap-3" :class="collapsed ? 'justify-center' : ''">
                <Link
                    v-if="hasBrandingLink"
                    :href="brandingHref"
                    class="flex min-w-0 items-center gap-3 rounded-xl border border-transparent px-1.5 py-1 transition hover:border-slate-200 hover:bg-slate-100/80"
                    :class="collapsed ? 'justify-center' : 'flex-1'"
                    title="Abrir branding"
                >
                    <div
                        class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg ring-1 ring-emerald-200/70"
                        :style="
                            contractorLogoUrl ? null : { background: 'var(--contractor-primary)' }
                        "
                    >
                        <img
                            v-if="contractorLogoUrl"
                            :src="contractorLogoUrl"
                            :alt="contractorName"
                            class="h-full w-full rounded-lg object-cover"
                        />
                        <span v-else class="text-xs font-semibold text-white">{{
                            contractorInitials
                        }}</span>
                    </div>
                    <div v-if="!collapsed" class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-slate-900">
                            {{ contractorName }}
                        </p>
                        <span class="veshop-head-context-chip mt-1">
                            {{ contractorPlanName }}
                        </span>
                    </div>
                </Link>
                <div
                    v-else
                    class="flex min-w-0 items-center gap-3"
                    :class="collapsed ? 'justify-center' : 'flex-1'"
                >
                    <div
                        class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg ring-1 ring-emerald-200/70"
                        :style="
                            contractorLogoUrl ? null : { background: 'var(--contractor-primary)' }
                        "
                    >
                        <img
                            v-if="contractorLogoUrl"
                            :src="contractorLogoUrl"
                            :alt="contractorName"
                            class="h-full w-full rounded-lg object-cover"
                        />
                        <span v-else class="text-xs font-semibold text-white">{{
                            contractorInitials
                        }}</span>
                    </div>
                    <div v-if="!collapsed" class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-slate-900">
                            {{ contractorName }}
                        </p>
                        <span class="veshop-head-context-chip mt-1">
                            {{ contractorPlanName }}
                        </span>
                    </div>
                </div>

                <Dropdown
                    v-if="canSwitchContractor && !collapsed"
                    align="right"
                    width="48"
                    content-classes="py-2 bg-white"
                    class="ml-auto"
                >
                    <template #trigger>
                        <button
                            type="button"
                            class="veshop-head-action"
                            title="Trocar contratante"
                            aria-label="Trocar contratante"
                        >
                            <ChevronDown class="h-4 w-4" />
                        </button>
                    </template>
                    <template #content>
                        <p
                            class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400"
                        >
                            Trocar contratante
                        </p>
                        <div class="space-y-1">
                            <button
                                v-for="contractor in availableContractors"
                                :key="contractor.uuid ?? contractor.id"
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-xs font-semibold transition"
                                :class="
                                    isCurrentContractorOption(contractor)
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                "
                                @click="handleSwitchContractor(contractor)"
                            >
                                <span
                                    class="flex h-6 w-6 items-center justify-center overflow-hidden rounded-md text-[10px] font-semibold text-white"
                                    :style="
                                        contractor.brand_avatar_url
                                            ? null
                                            : { background: resolveContractorColor(contractor) }
                                    "
                                >
                                    <img
                                        v-if="contractor.brand_avatar_url"
                                        :src="contractor.brand_avatar_url"
                                        :alt="contractor.brand_name ?? contractor.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>{{
                                        resolveContractorInitials(
                                            contractor.brand_name ?? contractor.name,
                                        )
                                    }}</span>
                                </span>
                                <span class="min-w-0 flex-1 truncate">{{
                                    contractor.brand_name ?? contractor.name
                                }}</span>
                                <span
                                    v-if="isCurrentContractorOption(contractor)"
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                                    >Atual</span
                                >
                            </button>
                        </div>
                    </template>
                </Dropdown>
            </div>
        </div>

        <div v-else class="mb-4 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2">
            <div class="flex items-center gap-3">
                <Link
                    v-if="hasBrandingLink"
                    :href="brandingHref"
                    class="flex min-w-0 flex-1 items-center gap-3 rounded-lg border border-transparent px-1.5 py-1 transition hover:border-slate-200 hover:bg-white"
                    title="Abrir branding"
                    @click="handleBrandingClick"
                >
                    <div
                        class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg ring-1 ring-emerald-200/70"
                        :style="
                            contractorLogoUrl ? null : { background: 'var(--contractor-primary)' }
                        "
                    >
                        <img
                            v-if="contractorLogoUrl"
                            :src="contractorLogoUrl"
                            :alt="contractorName"
                            class="h-full w-full rounded-lg object-cover"
                        />
                        <span v-else class="text-xs font-semibold text-white">{{
                            contractorInitials
                        }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-slate-900">
                            {{ contractorName }}
                        </p>
                        <span
                            class="mt-1 inline-flex w-fit items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                        >
                            {{ contractorPlanName }}
                        </span>
                    </div>
                </Link>
                <div v-else class="flex min-w-0 flex-1 items-center gap-3">
                    <div
                        class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg ring-1 ring-emerald-200/70"
                        :style="
                            contractorLogoUrl ? null : { background: 'var(--contractor-primary)' }
                        "
                    >
                        <img
                            v-if="contractorLogoUrl"
                            :src="contractorLogoUrl"
                            :alt="contractorName"
                            class="h-full w-full rounded-lg object-cover"
                        />
                        <span v-else class="text-xs font-semibold text-white">{{
                            contractorInitials
                        }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-slate-900">
                            {{ contractorName }}
                        </p>
                        <span
                            class="mt-1 inline-flex w-fit items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                        >
                            {{ contractorPlanName }}
                        </span>
                    </div>
                </div>

                <Dropdown
                    v-if="canSwitchContractor"
                    align="right"
                    width="48"
                    content-classes="py-2 bg-white"
                    class="ml-auto"
                >
                    <template #trigger>
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50"
                            title="Trocar contratante"
                            aria-label="Trocar contratante"
                        >
                            <ChevronDown class="h-4 w-4" />
                        </button>
                    </template>
                    <template #content>
                        <p
                            class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400"
                        >
                            Trocar contratante
                        </p>
                        <div class="space-y-1">
                            <button
                                v-for="contractor in availableContractors"
                                :key="contractor.uuid ?? contractor.id"
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-xs font-semibold transition"
                                :class="
                                    isCurrentContractorOption(contractor)
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                "
                                @click="handleSwitchContractor(contractor)"
                            >
                                <span
                                    class="flex h-6 w-6 items-center justify-center overflow-hidden rounded-md text-[10px] font-semibold text-white"
                                    :style="
                                        contractor.brand_avatar_url
                                            ? null
                                            : { background: resolveContractorColor(contractor) }
                                    "
                                >
                                    <img
                                        v-if="contractor.brand_avatar_url"
                                        :src="contractor.brand_avatar_url"
                                        :alt="contractor.brand_name ?? contractor.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>{{
                                        resolveContractorInitials(
                                            contractor.brand_name ?? contractor.name,
                                        )
                                    }}</span>
                                </span>
                                <span class="min-w-0 flex-1 truncate">{{
                                    contractor.brand_name ?? contractor.name
                                }}</span>
                                <span
                                    v-if="isCurrentContractorOption(contractor)"
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                                >
                                    Atual
                                </span>
                            </button>
                        </div>
                    </template>
                </Dropdown>
            </div>
        </div>
    </template>
</template>
