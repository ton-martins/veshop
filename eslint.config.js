import js from '@eslint/js';
import globals from 'globals';
import vue from 'eslint-plugin-vue';
import eslintConfigPrettier from 'eslint-config-prettier';

export default [
    {
        ignores: ['node_modules/**', 'vendor/**', 'public/**', 'bootstrap/cache/**', 'storage/**'],
    },
    js.configs.recommended,
    ...vue.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
                route: 'readonly',
            },
        },
        rules: {
            'no-console': ['warn', { allow: ['warn', 'error'] }],
            'no-undef': 'off',
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            'vue/multi-word-component-names': 'off',
            'vue/require-default-prop': 'off',
        },
    },
    eslintConfigPrettier,
];
