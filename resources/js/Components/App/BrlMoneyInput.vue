<script setup>
import { ref, watch } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    allowEmpty: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update:modelValue', 'blur', 'focus']);

const formatter = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const displayValue = ref('');

const parseModelToNumber = (value) => {
    if (value === null || value === undefined) return null;

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    const safe = String(value ?? '').trim();
    if (safe === '') return null;

    const withoutCurrency = safe
        .replace(/\s/g, '')
        .replace(/R\$/gi, '');
    const normalized = withoutCurrency.includes(',')
        ? withoutCurrency.replace(/\./g, '').replace(',', '.')
        : withoutCurrency;

    const parsed = Number(normalized);
    if (Number.isFinite(parsed)) return parsed;

    const digits = safe.replace(/\D+/g, '');
    if (digits === '') return null;

    return Number(digits) / 100;
};

const toModelString = (value) => {
    const parsed = Number(value ?? 0);
    if (!Number.isFinite(parsed)) return '';

    return parsed.toFixed(2);
};

const toCurrency = (value) => {
    const parsed = Number(value ?? 0);
    if (!Number.isFinite(parsed)) return formatter.format(0);

    return formatter.format(Math.max(0, parsed));
};

const syncDisplayFromModel = (value) => {
    const parsed = parseModelToNumber(value);
    if (parsed === null) {
        displayValue.value = '';
        return;
    }

    displayValue.value = toCurrency(parsed);
};

watch(
    () => props.modelValue,
    (nextValue) => {
        syncDisplayFromModel(nextValue);
    },
    { immediate: true },
);

const emitFromRawInput = (rawValue) => {
    const digits = String(rawValue ?? '').replace(/\D+/g, '');
    if (digits === '') {
        if (props.allowEmpty) {
            displayValue.value = '';
            emit('update:modelValue', '');
            return;
        }

        displayValue.value = toCurrency(0);
        emit('update:modelValue', '0.00');
        return;
    }

    const amount = Number(digits) / 100;
    const safeAmount = Number.isFinite(amount) ? amount : 0;
    displayValue.value = toCurrency(safeAmount);
    emit('update:modelValue', toModelString(safeAmount));
};

const onInput = (event) => {
    emitFromRawInput(event?.target?.value ?? '');
};

const onBlur = (event) => {
    if (!props.allowEmpty && String(props.modelValue ?? '').trim() === '') {
        displayValue.value = toCurrency(0);
        emit('update:modelValue', '0.00');
    }

    emit('blur', event);
};

const onFocus = (event) => {
    emit('focus', event);
};
</script>

<template>
    <input
        v-bind="$attrs"
        :value="displayValue"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        @input="onInput"
        @blur="onBlur"
        @focus="onFocus"
    >
</template>
