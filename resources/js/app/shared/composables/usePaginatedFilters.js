import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

export function usePaginatedFilters(baseRouteName, initialFilters = {}) {
    const filters = computed(() => ({ ...initialFilters }));

    const applyFilters = (payload = {}) => {
        router.get(route(baseRouteName), payload, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        applyFilters({});
    };

    return {
        filters,
        applyFilters,
        resetFilters,
    };
}
