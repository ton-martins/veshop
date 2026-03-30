<script setup>
import { Check, ChevronDown, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Selecione',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    buttonClass: {
        type: String,
        default: '',
    },
    panelClass: {
        type: String,
        default: '',
    },
    optionClass: {
        type: String,
        default: '',
    },
    useModal: {
        type: Boolean,
        default: false,
    },
    modalTitle: {
        type: String,
        default: 'Selecione uma opção',
    },
    menuPlacement: {
        type: String,
        default: 'bottom',
        validator: (value) => ['top', 'bottom'].includes(String(value ?? '').toLowerCase()),
    },
    teleportPanel: {
        type: Boolean,
        default: false,
    },
    searchable: {
        type: Boolean,
        default: false,
    },
    searchPlaceholder: {
        type: String,
        default: 'Digite para buscar...',
    },
});

const emit = defineEmits(['update:modelValue', 'change', 'blur', 'search-change']);

const root = ref(null);
const panelRef = ref(null);
const open = ref(false);
const floatingPanelStyle = ref({});
const searchQuery = ref('');

const normalizeOption = (option, index) => {
    if (option && typeof option === 'object' && !Array.isArray(option)) {
        return {
            value: 'value' in option ? option.value : option.id ?? option.key ?? option.label ?? index,
            label: String(option.label ?? option.name ?? option.text ?? option.value ?? ''),
            disabled: Boolean(option.disabled),
        };
    }

    return {
        value: option,
        label: String(option ?? ''),
        disabled: false,
    };
};

const normalizedOptions = computed(() => (props.options ?? []).map(normalizeOption));
const isSearchable = computed(() => props.searchable === true);

const normalizeValueKey = (value) => String(value ?? '');
const normalizeSearchText = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();

const isSelected = (value) => normalizeValueKey(value) === normalizeValueKey(props.modelValue);

const selectedOption = computed(
    () => normalizedOptions.value.find((option) => isSelected(option.value)) ?? null,
);
const selectedLabel = computed(() => selectedOption.value?.label ?? '');
const visibleOptions = computed(() => {
    if (!isSearchable.value) return normalizedOptions.value;

    const query = normalizeSearchText(searchQuery.value);
    if (query === '') return normalizedOptions.value;

    return normalizedOptions.value.filter((option) => normalizeSearchText(option.label).includes(query));
});

const triggerLabel = computed(() => selectedOption.value?.label ?? props.placeholder);
const openAsModal = computed(() => props.useModal === true);
const shouldTeleportPanel = computed(() => props.teleportPanel === true && !openAsModal.value);
const panelPositionClass = computed(() =>
    String(props.menuPlacement ?? 'bottom').toLowerCase() === 'top'
        ? 'bottom-[calc(100%+0.35rem)]'
        : 'top-[calc(100%+0.35rem)]',
);
const panelEnterFromClass = computed(() =>
    String(props.menuPlacement ?? 'bottom').toLowerCase() === 'top'
        ? 'opacity-0 translate-y-1'
        : 'opacity-0 -translate-y-1',
);
const panelLeaveToClass = computed(() =>
    String(props.menuPlacement ?? 'bottom').toLowerCase() === 'top'
        ? 'opacity-0 translate-y-1'
        : 'opacity-0 -translate-y-1',
);

const syncSearchQueryWithSelection = () => {
    if (!isSearchable.value) return;
    searchQuery.value = selectedLabel.value;
};

const close = () => {
    open.value = false;
    syncSearchQueryWithSelection();
};
const closeWithBlur = () => {
    close();
    emit('blur');
};

const toggleOpen = () => {
    if (props.disabled) return;

    if (!open.value && isSearchable.value && searchQuery.value === selectedLabel.value) {
        searchQuery.value = '';
    }

    open.value = !open.value;
    if (open.value && isSearchable.value) {
        emit('search-change', searchQuery.value);
    }
};

const selectOption = (option) => {
    if (option.disabled) return;

    emit('update:modelValue', option.value);
    emit('change', option.value);
    if (isSearchable.value) {
        searchQuery.value = option.label;
    }
    close();
};

const onSearchInput = () => {
    if (props.disabled || !isSearchable.value) return;
    if (!open.value) {
        open.value = true;
    }

    emit('search-change', searchQuery.value);
    nextTick(() => {
        if (shouldTeleportPanel.value) {
            updateFloatingPanelPosition();
        }
    });
};

const openFromSearchInput = () => {
    if (props.disabled || !isSearchable.value) return;
    if (!open.value) {
        if (searchQuery.value === selectedLabel.value) {
            searchQuery.value = '';
        }
        open.value = true;
        emit('search-change', searchQuery.value);
    }
};

const onClickOutside = (event) => {
    if (openAsModal.value) return;
    if (!open.value || !root.value) return;
    if (root.value.contains(event.target)) return;
    if (panelRef.value?.contains?.(event.target)) return;
    closeWithBlur();
};

const onEscape = (event) => {
    if (event.key !== 'Escape') return;
    close();
};

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) close();
    },
);

watch(
    () => props.modelValue,
    () => {
        if (!open.value) {
            syncSearchQueryWithSelection();
        }
    },
);

watch(
    () => props.options,
    () => {
        if (!open.value) {
            syncSearchQueryWithSelection();
        }
    },
    { deep: true },
);

const updateFloatingPanelPosition = () => {
    if (!root.value || !shouldTeleportPanel.value || !open.value) return;

    const rect = root.value.getBoundingClientRect();
    const viewportPadding = 8;
    const gap = 6;
    const preferredPlacement = String(props.menuPlacement ?? 'bottom').toLowerCase();

    const targetWidth = Math.max(Math.ceil(rect.width), 192);
    const maxAllowedWidth = Math.max(192, window.innerWidth - (viewportPadding * 2));
    const width = Math.min(targetWidth, maxAllowedWidth);

    let left = rect.left;
    if (left + width > window.innerWidth - viewportPadding) {
        left = window.innerWidth - viewportPadding - width;
    }
    left = Math.max(viewportPadding, left);

    const availableBelow = window.innerHeight - rect.bottom - gap - viewportPadding;
    const availableAbove = rect.top - gap - viewportPadding;
    const autoTop = availableBelow < 180 && availableAbove > availableBelow;
    const placeTop = preferredPlacement === 'top' || autoTop;

    const maxHeight = Math.max(120, Math.min(256, placeTop ? availableAbove : availableBelow));
    const top = placeTop
        ? Math.max(viewportPadding, rect.top - gap - maxHeight)
        : rect.bottom + gap;

    floatingPanelStyle.value = {
        left: `${Math.round(left)}px`,
        top: `${Math.round(top)}px`,
        width: `${Math.round(width)}px`,
        maxHeight: `${Math.round(maxHeight)}px`,
    };
};

const bindFloatingPanelListeners = () => {
    window.addEventListener('resize', updateFloatingPanelPosition);
    window.addEventListener('scroll', updateFloatingPanelPosition, true);
};

const unbindFloatingPanelListeners = () => {
    window.removeEventListener('resize', updateFloatingPanelPosition);
    window.removeEventListener('scroll', updateFloatingPanelPosition, true);
};

watch(
    [open, shouldTeleportPanel],
    ([isOpen, useTeleport]) => {
        if (isOpen && useTeleport) {
            nextTick(() => {
                updateFloatingPanelPosition();
                bindFloatingPanelListeners();
            });
            return;
        }

        unbindFloatingPanelListeners();
    },
    { immediate: true },
);

onMounted(() => {
    syncSearchQueryWithSelection();
    document.addEventListener('mousedown', onClickOutside);
    document.addEventListener('touchstart', onClickOutside, { passive: true });
    document.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', onClickOutside);
    document.removeEventListener('touchstart', onClickOutside);
    document.removeEventListener('keydown', onEscape);
    unbindFloatingPanelListeners();
});
</script>

<template>
    <div ref="root" class="relative min-w-0">
        <div
            v-if="isSearchable"
            class="inline-flex min-h-[2.25rem] w-full items-center gap-1 rounded-xl bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
            :class="[buttonClass, disabled ? 'cursor-not-allowed opacity-60' : '']"
        >
            <input
                v-model="searchQuery"
                type="text"
                class="h-full w-full border-0 bg-transparent text-left text-xs font-semibold text-slate-700 outline-none ring-0 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                :placeholder="searchPlaceholder || placeholder"
                :disabled="disabled"
                @focus="openFromSearchInput"
                @input="onSearchInput"
                @click.stop
            >
            <button
                type="button"
                class="inline-flex h-5 w-5 shrink-0 items-center justify-center text-slate-500 transition disabled:cursor-not-allowed"
                :disabled="disabled"
                @click="toggleOpen"
            >
                <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="open ? 'rotate-180' : ''" />
            </button>
        </div>

        <button
            v-else
            type="button"
            class="inline-flex w-full items-center justify-between gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-left text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
            :class="buttonClass"
            :disabled="disabled"
            @click="toggleOpen"
        >
            <span class="truncate">{{ triggerLabel }}</span>
            <ChevronDown class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-transform" :class="open ? 'rotate-180' : ''" />
        </button>

        <Transition
            enter-active-class="transition ease-out duration-150"
            :enter-from-class="panelEnterFromClass"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0"
            :leave-to-class="panelLeaveToClass"
        >
            <div
                v-if="open && !openAsModal && !shouldTeleportPanel"
                ref="panelRef"
                class="absolute left-0 z-[220] max-h-64 w-full min-w-[12rem] overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-[0_22px_50px_-32px_rgba(15,23,42,0.95)]"
                :class="[panelPositionClass, panelClass]"
                role="listbox"
            >
                <button
                    v-for="(option, index) in visibleOptions"
                    :key="`${option.label}-${index}`"
                    type="button"
                    class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-sm transition"
                    :class="[
                        isSelected(option.value)
                            ? 'bg-slate-100 font-semibold text-slate-900'
                            : 'font-medium text-slate-700 hover:bg-slate-50',
                        option.disabled ? 'cursor-not-allowed opacity-50' : '',
                        optionClass,
                    ]"
                    :disabled="option.disabled"
                    @click="selectOption(option)"
                >
                    <span class="truncate">{{ option.label }}</span>
                    <Check v-if="isSelected(option.value)" class="h-4 w-4 shrink-0 text-slate-600" />
                </button>
                <p v-if="visibleOptions.length === 0" class="px-2.5 py-2 text-xs font-medium text-slate-500">
                    Nenhuma opção encontrada.
                </p>
            </div>
        </Transition>

        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-150"
                :enter-from-class="panelEnterFromClass"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 translate-y-0"
                :leave-to-class="panelLeaveToClass"
            >
                <div
                    v-if="open && !openAsModal && shouldTeleportPanel"
                    ref="panelRef"
                    class="fixed z-[260] overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-[0_22px_50px_-32px_rgba(15,23,42,0.95)]"
                    :class="panelClass"
                    :style="floatingPanelStyle"
                    role="listbox"
                >
                    <button
                        v-for="(option, index) in visibleOptions"
                        :key="`floating-${option.label}-${index}`"
                        type="button"
                        class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-sm transition"
                        :class="[
                            isSelected(option.value)
                                ? 'bg-slate-100 font-semibold text-slate-900'
                                : 'font-medium text-slate-700 hover:bg-slate-50',
                            option.disabled ? 'cursor-not-allowed opacity-50' : '',
                            optionClass,
                        ]"
                        :disabled="option.disabled"
                        @click="selectOption(option)"
                    >
                        <span class="truncate">{{ option.label }}</span>
                        <Check v-if="isSelected(option.value)" class="h-4 w-4 shrink-0 text-slate-600" />
                    </button>
                    <p v-if="visibleOptions.length === 0" class="px-2.5 py-2 text-xs font-medium text-slate-500">
                        Nenhuma opção encontrada.
                    </p>
                </div>
            </Transition>
        </Teleport>

        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="open && openAsModal"
                    class="fixed inset-0 z-[140] flex items-end justify-center bg-slate-900/45 p-3 sm:items-center sm:p-6"
                    @click.self="closeWithBlur"
                >
                    <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-[0_30px_60px_-24px_rgba(15,23,42,0.5)]">
                        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                            <h3 class="text-sm font-semibold text-slate-900">{{ modalTitle }}</h3>
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                                @click="closeWithBlur"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="max-h-[70vh] overflow-y-auto p-2" role="listbox">
                            <button
                                v-for="(option, index) in visibleOptions"
                                :key="`modal-${option.label}-${index}`"
                                type="button"
                                class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-left text-sm transition"
                                :class="[
                                    isSelected(option.value)
                                        ? 'bg-slate-100 font-semibold text-slate-900'
                                        : 'font-medium text-slate-700 hover:bg-slate-50',
                                    option.disabled ? 'cursor-not-allowed opacity-50' : '',
                                    optionClass,
                                ]"
                                :disabled="option.disabled"
                                @click="selectOption(option)"
                            >
                                <span class="truncate">{{ option.label }}</span>
                                <Check v-if="isSelected(option.value)" class="h-4 w-4 shrink-0 text-slate-600" />
                            </button>
                            <p v-if="visibleOptions.length === 0" class="px-3 py-2.5 text-sm font-medium text-slate-500">
                                Nenhuma opção encontrada.
                            </p>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
