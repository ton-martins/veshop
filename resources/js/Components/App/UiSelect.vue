<script setup>
import { Check, ChevronDown, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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
});

const emit = defineEmits(['update:modelValue', 'change', 'blur']);

const root = ref(null);
const open = ref(false);

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

const normalizeValueKey = (value) => String(value ?? '');

const isSelected = (value) => normalizeValueKey(value) === normalizeValueKey(props.modelValue);

const selectedOption = computed(
    () => normalizedOptions.value.find((option) => isSelected(option.value)) ?? null,
);

const triggerLabel = computed(() => selectedOption.value?.label ?? props.placeholder);
const openAsModal = computed(() => props.useModal === true);
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

const close = () => {
    open.value = false;
};
const closeWithBlur = () => {
    close();
    emit('blur');
};

const toggleOpen = () => {
    if (props.disabled) return;
    open.value = !open.value;
};

const selectOption = (option) => {
    if (option.disabled) return;

    emit('update:modelValue', option.value);
    emit('change', option.value);
    close();
};

const onClickOutside = (event) => {
    if (openAsModal.value) return;
    if (!open.value || !root.value) return;
    if (root.value.contains(event.target)) return;
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

onMounted(() => {
    document.addEventListener('mousedown', onClickOutside);
    document.addEventListener('touchstart', onClickOutside, { passive: true });
    document.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', onClickOutside);
    document.removeEventListener('touchstart', onClickOutside);
    document.removeEventListener('keydown', onEscape);
});
</script>

<template>
    <div ref="root" class="relative min-w-0">
        <button
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
                v-if="open && !openAsModal"
                class="absolute left-0 z-[80] max-h-64 w-full min-w-[12rem] overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-[0_22px_50px_-32px_rgba(15,23,42,0.95)]"
                :class="[panelPositionClass, panelClass]"
                role="listbox"
            >
                <button
                    v-for="(option, index) in normalizedOptions"
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
            </div>
        </Transition>

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
                                v-for="(option, index) in normalizedOptions"
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
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
