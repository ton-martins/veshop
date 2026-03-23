<script setup>
import AppLayoutShell from './app/AppLayoutShell.vue';

defineProps({
    hideNav: { type: Boolean, default: false },
    area: { type: String, default: null },
    headerVariant: { type: String, default: 'compact' },
    headerTitle: { type: String, default: '' },
    headerIcon: { type: String, default: '' },
    showTableViewToggle: { type: Boolean, default: true },
});
</script>

<template>
    <AppLayoutShell
        :hide-nav="hideNav"
        :area="area"
        :header-variant="headerVariant"
        :header-title="headerTitle"
        :header-icon="headerIcon"
        :show-table-view-toggle="showTableViewToggle"
    >
        <template
            v-if="$slots.header"
            #header
        >
            <slot name="header" />
        </template>

        <slot />
    </AppLayoutShell>
</template>
