import { computed } from 'vue';
import { useContractorContext } from './useContractorContext';

export function useModuleAccess() {
    const { currentContractor } = useContractorContext();

    const enabledModules = computed(() => currentContractor.value?.enabled_modules ?? []);

    const hasModule = (moduleKey) => enabledModules.value.includes(moduleKey);

    return {
        enabledModules,
        hasModule,
    };
}
