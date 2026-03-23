<script setup>
defineProps({
    label: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" class="block text-sm font-medium text-slate-700">
            {{ label }}
            <span v-if="required" class="text-rose-600">*</span>
        </label>
        <slot />
        <p v-if="error" class="text-xs text-rose-600">
            {{ error }}
        </p>
    </div>
</template>
