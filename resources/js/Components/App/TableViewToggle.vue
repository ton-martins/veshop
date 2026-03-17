<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { LayoutGrid, List } from 'lucide-vue-next';

const TABLE_VIEW_STORAGE_KEY = 'veshop:table-view-mode';
const allowedModes = new Set(['list', 'cards']);
const tableViewMode = ref('list');
let modeObserver = null;

const normalizeMode = (value) => {
    const normalized = String(value ?? '').trim().toLowerCase();
    return allowedModes.has(normalized) ? normalized : 'list';
};

const syncModeFromDom = () => {
    if (typeof document === 'undefined') return;
    tableViewMode.value = normalizeMode(document.documentElement.getAttribute('data-table-view-mode'));
};

const setMode = (mode) => {
    const normalized = normalizeMode(mode);
    tableViewMode.value = normalized;

    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('data-table-view-mode', normalized);
    }

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(TABLE_VIEW_STORAGE_KEY, normalized);
    }
};

onMounted(() => {
    const fromDom =
        typeof document !== 'undefined'
            ? document.documentElement.getAttribute('data-table-view-mode')
            : '';
    const fromStorage =
        typeof window !== 'undefined'
            ? window.localStorage.getItem(TABLE_VIEW_STORAGE_KEY)
            : '';

    setMode(fromDom || fromStorage || 'list');

    if (typeof MutationObserver !== 'undefined' && typeof document !== 'undefined') {
        modeObserver = new MutationObserver(syncModeFromDom);
        modeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-table-view-mode'],
        });
    }
});

onBeforeUnmount(() => {
    if (modeObserver) {
        modeObserver.disconnect();
        modeObserver = null;
    }
});
</script>

<template>
    <div class="veshop-table-view-toggle">
        <button
            type="button"
            class="veshop-table-view-btn inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
            :class="tableViewMode === 'list' ? 'is-active' : ''"
            @click="setMode('list')"
        >
            <List class="h-3.5 w-3.5" />
            Lista
        </button>
        <button
            type="button"
            class="veshop-table-view-btn inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
            :class="tableViewMode === 'cards' ? 'is-active' : ''"
            @click="setMode('cards')"
        >
            <LayoutGrid class="h-3.5 w-3.5" />
            Cards
        </button>
    </div>
</template>
