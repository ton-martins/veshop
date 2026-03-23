import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useContractorContext() {
    const page = usePage();

    const context = computed(() => page.props.contractorContext ?? { current: null, available: [] });
    const currentContractor = computed(() => context.value.current ?? null);
    const availableContractors = computed(() => context.value.available ?? []);

    return {
        context,
        currentContractor,
        availableContractors,
    };
}
