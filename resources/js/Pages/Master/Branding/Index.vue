<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BrandingImageUploader from '@/Components/BrandingImageUploader.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    branding: { type: Object, default: () => ({}) },
    defaults: { type: Object, default: () => ({}) },
    storageConfigured: { type: Boolean, default: true },
});

const form = useForm({
    name: '',
    tagline: '',
    primary_color: '#073341',
    accent_color: '#81D86F',
    logo: null,
    icon: null,
    favicon: null,
    landing_about_image: null,
    landing_why_choose_image: null,
    landing_work_image: null,
    remove_logo: false,
    remove_icon: false,
    remove_favicon: false,
    remove_landing_about_image: false,
    remove_landing_why_choose_image: false,
    remove_landing_work_image: false,
});

const logoPreview = ref('');
const iconPreview = ref('');
const faviconPreview = ref('');
const aboutPreview = ref('');
const whyChoosePreview = ref('');
const workPreview = ref('');

const normalizedPrimaryColor = computed(() => {
    const safe = String(form.primary_color || '#073341').trim();
    return safe.startsWith('#') ? safe : `#${safe}`;
});

const normalizedAccentColor = computed(() => {
    const safe = String(form.accent_color || '#81D86F').trim();
    return safe.startsWith('#') ? safe : `#${safe}`;
});

const resolveLandingImages = (branding) => ({
    about: branding?.landing_images?.about
        ?? props.defaults?.landing_images?.about
        ?? '/landing/images/about.png',
    why_choose: branding?.landing_images?.why_choose
        ?? props.defaults?.landing_images?.why_choose
        ?? '/landing/images/working.png',
    work: branding?.landing_images?.work
        ?? props.defaults?.landing_images?.work
        ?? '/landing/images/group-working.png',
});

const hydrate = () => {
    const branding = props.branding ?? {};
    const landingImages = resolveLandingImages(branding);

    form.name = branding.name ?? props.defaults?.name ?? 'Veshop';
    form.tagline = branding.tagline ?? props.defaults?.tagline ?? 'ERP para comércio e serviços';
    form.primary_color = branding.primary_color ?? props.defaults?.primary_color ?? '#073341';
    form.accent_color = branding.accent_color ?? props.defaults?.accent_color ?? '#81D86F';

    form.logo = null;
    form.icon = null;
    form.favicon = null;
    form.landing_about_image = null;
    form.landing_why_choose_image = null;
    form.landing_work_image = null;

    form.remove_logo = false;
    form.remove_icon = false;
    form.remove_favicon = false;
    form.remove_landing_about_image = false;
    form.remove_landing_why_choose_image = false;
    form.remove_landing_work_image = false;

    logoPreview.value = branding.logo_url ?? props.defaults?.logo_url ?? '';
    iconPreview.value = branding.icon_url ?? props.defaults?.icon_url ?? '/brand/icone-veshop.png';
    faviconPreview.value = branding.favicon_url ?? props.defaults?.favicon_url ?? '/brand/favicon-veshop.ico';
    aboutPreview.value = landingImages.about;
    whyChoosePreview.value = landingImages.why_choose;
    workPreview.value = landingImages.work;
};

watch(() => props.branding, hydrate, { deep: true, immediate: true });

const setImageChange = (fileField, removeField, previewRef) => ({ file, preview }) => {
    form[fileField] = file;
    form[removeField] = false;
    previewRef.value = preview;
};

const clearImage = (fileField, removeField, previewRef) => {
    form[fileField] = null;
    form[removeField] = true;
    previewRef.value = '';
};

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            primary_color: normalizedPrimaryColor.value,
            accent_color: normalizedAccentColor.value,
            _method: 'put',
        }))
        .post(route('master.branding.update'), {
            preserveScroll: true,
            forceFormData: true,
        });
};
</script>

<template>
    <AuthenticatedLayout area="master" header-variant="compact" header-title="Identidade visual do sistema">
        <Head title="Identidade visual do sistema" />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,360px)]">
            <form class="space-y-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:p-6" @submit.prevent="submit">
                <section class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Identidade principal</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">Dados da marca</h2>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Nome do sistema</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm"
                                placeholder="Veshop"
                            >
                            <p v-if="form.errors.name" class="text-[11px] text-rose-600">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Slogan</label>
                            <input
                                v-model="form.tagline"
                                type="text"
                                class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm"
                                placeholder="ERP para comércio e serviços"
                            >
                            <p v-if="form.errors.tagline" class="text-[11px] text-rose-600">{{ form.errors.tagline }}</p>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cores</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">Paleta do sistema</h2>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Cor primária</label>
                            <div class="flex items-center gap-2">
                                <input v-model="form.primary_color" type="color" class="h-11 w-14 rounded-md border border-slate-200">
                                <input
                                    v-model="form.primary_color"
                                    type="text"
                                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm"
                                    placeholder="#073341"
                                >
                            </div>
                            <p v-if="form.errors.primary_color" class="text-[11px] text-rose-600">{{ form.errors.primary_color }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500">Cor de destaque</label>
                            <div class="flex items-center gap-2">
                                <input v-model="form.accent_color" type="color" class="h-11 w-14 rounded-md border border-slate-200">
                                <input
                                    v-model="form.accent_color"
                                    type="text"
                                    class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm"
                                    placeholder="#81D86F"
                                >
                            </div>
                            <p v-if="form.errors.accent_color" class="text-[11px] text-rose-600">{{ form.errors.accent_color }}</p>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Arquivos da marca</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">Logo, ícone e favicon</h2>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-2">
                            <BrandingImageUploader
                                label="Logo do sistema"
                                :help-text="storageConfigured ? 'Sugestão: 320x80px.' : 'Configure o armazenamento para upload.'"
                                :initial-preview="logoPreview"
                                :aspect-ratio="3.5"
                                :disabled="!storageConfigured"
                                @change="setImageChange('logo', 'remove_logo', logoPreview)"
                            />
                            <button
                                v-if="logoPreview"
                                type="button"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                @click="clearImage('logo', 'remove_logo', logoPreview)"
                            >
                                Remover logo
                            </button>
                            <p v-if="form.errors.logo" class="text-[11px] text-rose-600">{{ form.errors.logo }}</p>
                        </div>

                        <div class="space-y-2">
                            <BrandingImageUploader
                                label="Ícone do sistema"
                                :help-text="storageConfigured ? 'Formato quadrado.' : 'Configure o armazenamento para upload.'"
                                :initial-preview="iconPreview"
                                :aspect-ratio="3.5"
                                :disabled="!storageConfigured"
                                @change="setImageChange('icon', 'remove_icon', iconPreview)"
                            />
                            <button
                                v-if="iconPreview"
                                type="button"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                @click="clearImage('icon', 'remove_icon', iconPreview)"
                            >
                                Remover ícone
                            </button>
                            <p v-if="form.errors.icon" class="text-[11px] text-rose-600">{{ form.errors.icon }}</p>
                        </div>

                        <div class="space-y-2">
                            <BrandingImageUploader
                                label="Favicon"
                                :help-text="storageConfigured ? 'Sugestão: 48x48px ou .ico.' : 'Configure o armazenamento para upload.'"
                                :initial-preview="faviconPreview"
                                :aspect-ratio="3.5"
                                accept=".ico,image/png,image/webp,image/svg+xml"
                                :disabled="!storageConfigured"
                                @change="setImageChange('favicon', 'remove_favicon', faviconPreview)"
                            />
                            <button
                                v-if="faviconPreview"
                                type="button"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                @click="clearImage('favicon', 'remove_favicon', faviconPreview)"
                            >
                                Remover favicon
                            </button>
                            <p v-if="form.errors.favicon" class="text-[11px] text-rose-600">{{ form.errors.favicon }}</p>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Imagens da landing</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">Personalização visual</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            O fundo do Hero é gerado por elemento HTML/CSS, sem necessidade de imagem.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <BrandingImageUploader
                                label="Sobre"
                                :help-text="storageConfigured ? 'Recomendado: 900x700.' : 'Configure o armazenamento para upload.'"
                                :initial-preview="aboutPreview"
                                :aspect-ratio="1.35"
                                :disabled="!storageConfigured"
                                @change="setImageChange('landing_about_image', 'remove_landing_about_image', aboutPreview)"
                            />
                            <button
                                v-if="aboutPreview"
                                type="button"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                @click="clearImage('landing_about_image', 'remove_landing_about_image', aboutPreview)"
                            >
                                Remover imagem
                            </button>
                            <p v-if="form.errors.landing_about_image" class="text-[11px] text-rose-600">{{ form.errors.landing_about_image }}</p>
                        </div>

                        <div class="space-y-2">
                            <BrandingImageUploader
                                label="Por que escolher"
                                :help-text="storageConfigured ? 'Recomendado: 900x700.' : 'Configure o armazenamento para upload.'"
                                :initial-preview="whyChoosePreview"
                                :aspect-ratio="1.35"
                                :disabled="!storageConfigured"
                                @change="setImageChange('landing_why_choose_image', 'remove_landing_why_choose_image', whyChoosePreview)"
                            />
                            <button
                                v-if="whyChoosePreview"
                                type="button"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                @click="clearImage('landing_why_choose_image', 'remove_landing_why_choose_image', whyChoosePreview)"
                            >
                                Remover imagem
                            </button>
                            <p v-if="form.errors.landing_why_choose_image" class="text-[11px] text-rose-600">{{ form.errors.landing_why_choose_image }}</p>
                        </div>

                        <div class="space-y-2">
                            <BrandingImageUploader
                                label="Como funciona"
                                :help-text="storageConfigured ? 'Recomendado: 900x700.' : 'Configure o armazenamento para upload.'"
                                :initial-preview="workPreview"
                                :aspect-ratio="1.35"
                                :disabled="!storageConfigured"
                                @change="setImageChange('landing_work_image', 'remove_landing_work_image', workPreview)"
                            />
                            <button
                                v-if="workPreview"
                                type="button"
                                class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                @click="clearImage('landing_work_image', 'remove_landing_work_image', workPreview)"
                            >
                                Remover imagem
                            </button>
                            <p v-if="form.errors.landing_work_image" class="text-[11px] text-rose-600">{{ form.errors.landing_work_image }}</p>
                        </div>
                    </div>
                </section>

                <footer class="border-t border-slate-200 pt-5">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Salvando...' : 'Salvar identidade visual' }}
                    </button>
                </footer>
            </form>

            <aside class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Pré-visualização</h3>

                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <div class="flex items-center gap-3 px-4 py-3" :style="{ backgroundColor: normalizedPrimaryColor }">
                        <img
                            v-if="iconPreview"
                            :src="iconPreview"
                            :alt="form.name"
                            class="h-9 w-9 rounded-md bg-white/10 p-1 object-contain"
                        >
                        <div v-else class="flex h-9 w-9 items-center justify-center rounded-md bg-white/20 text-xs font-bold text-white">
                            {{ (form.name?.slice(0, 2) || 'VS').toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">{{ form.name }}</p>
                            <p class="truncate text-xs text-white/80">{{ form.tagline }}</p>
                        </div>
                    </div>

                    <div class="space-y-3 bg-slate-50 px-4 py-4">
                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Cores ativas</p>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="h-6 w-6 rounded-md border border-slate-200" :style="{ backgroundColor: normalizedPrimaryColor }"></span>
                                <span class="h-6 w-6 rounded-md border border-slate-200" :style="{ backgroundColor: normalizedAccentColor }"></span>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Landing</p>
                            <p class="mt-1 text-xs text-slate-600">
                                O Hero usa fundo em HTML/CSS. As seções Sobre, Por que escolher e Como funciona usam as imagens configuradas.
                            </p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
