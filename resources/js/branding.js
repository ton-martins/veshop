import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const BRANDING = Object.freeze({
    appName: 'Veshop',
    locale: 'pt-BR',
    charset: 'UTF-8',
    colors: {
        primary: '#112240',
        primaryHover: '#0B1A34',
        accent: '#0284C7',
        accentSoft: '#E0F2FE',
        accentBorder: '#7DD3FC',
        accentInk: '#075985',
        highlight: '#F59E0B',
        highlightSoft: '#FEF3C7',
        highlightInk: '#92400E',
        textStrong: '#0F172A',
        textMuted: '#475569',
        line: '#D6E2F1',
        card: 'rgba(255, 255, 255, 0.92)',
        gridLine: 'rgba(30, 41, 59, 0.16)',
    },
    gradients: {
        background:
            'radial-gradient(circle at 12% 10%, rgba(2, 132, 199, 0.24), transparent 44%), radial-gradient(circle at 88% 4%, rgba(245, 158, 11, 0.2), transparent 34%), linear-gradient(180deg, #F8FBFF 0%, #F8FBFF 40%, #EEF4FF 100%)',
        cta: 'linear-gradient(135deg, #112240 0%, #0E2A5B 55%, #0284C7 140%)',
    },
});

export const BRAND_CSS_VARS = Object.freeze({
    '--veshop-primary': BRANDING.colors.primary,
    '--veshop-primary-hover': BRANDING.colors.primaryHover,
    '--veshop-accent': BRANDING.colors.accent,
    '--veshop-accent-soft': BRANDING.colors.accentSoft,
    '--veshop-accent-border': BRANDING.colors.accentBorder,
    '--veshop-accent-ink': BRANDING.colors.accentInk,
    '--veshop-highlight': BRANDING.colors.highlight,
    '--veshop-highlight-soft': BRANDING.colors.highlightSoft,
    '--veshop-highlight-ink': BRANDING.colors.highlightInk,
    '--veshop-text-strong': BRANDING.colors.textStrong,
    '--veshop-text-muted': BRANDING.colors.textMuted,
    '--veshop-line': BRANDING.colors.line,
    '--veshop-card-bg': BRANDING.colors.card,
    '--veshop-grid-line': BRANDING.colors.gridLine,
    '--veshop-bg-gradient': BRANDING.gradients.background,
    '--veshop-cta-gradient': BRANDING.gradients.cta,
});

export const normalizeHex = (hex, fallback = '#073341') => {
    const safeFallback = String(fallback || '#073341');
    const value = String(hex || '').trim();

    if (!value) return safeFallback;

    if (/^#[0-9a-fA-F]{6}$/.test(value)) return value;
    if (/^#[0-9a-fA-F]{3}$/.test(value)) {
        const chars = value.slice(1).split('');
        return `#${chars.map((char) => char + char).join('')}`;
    }

    return safeFallback;
};

export const withAlpha = (hex, alpha = 1) => {
    const normalized = normalizeHex(hex, '#073341').slice(1);
    const int = parseInt(normalized, 16);
    const r = (int >> 16) & 255;
    const g = (int >> 8) & 255;
    const b = int & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

const lightenHex = (hex, amount = 0.35) => {
    const normalized = normalizeHex(hex, '#073341').slice(1);
    const int = parseInt(normalized, 16);
    const r = (int >> 16) & 255;
    const g = (int >> 8) & 255;
    const b = int & 255;
    const lighten = (channel) => Math.min(255, Math.round(channel + (255 - channel) * amount));
    return `#${[lighten(r), lighten(g), lighten(b)]
        .map((component) => component.toString(16).padStart(2, '0'))
        .join('')}`;
};

export const useBranding = () => {
    const page = usePage();

    const branding = computed(() => page.props.systemBranding ?? {});
    const contractorContext = computed(() => page.props.contractorContext ?? {});
    const currentContractor = computed(() => contractorContext.value.current ?? null);

    const brandName = computed(() => branding.value.name || BRANDING.appName);
    const logoUrl = computed(() => branding.value.logo_url || '');
    const avatarUrl = computed(() => branding.value.avatar_url || '');
    const primaryColor = computed(() => normalizeHex(branding.value.primary_color, '#073341'));
    const secondaryColor = computed(() => normalizeHex(branding.value.accent_color, '#81D86F'));
    const contractorPrimary = computed(() =>
        normalizeHex(currentContractor.value?.brand_primary_color, primaryColor.value),
    );

    const contractorActiveGradient = computed(
        () =>
            `linear-gradient(135deg, ${contractorPrimary.value} 0%, ${secondaryColor.value} 100%)`,
    );
    const glassSecondary = computed(() => lightenHex(contractorPrimary.value, 0.35));
    const glassGradient = computed(
        () =>
            `linear-gradient(135deg, ${withAlpha(contractorPrimary.value, 0.45)}, rgba(255,255,255,0.38), ${withAlpha(glassSecondary.value, 0.5)})`,
    );

    const publicFaviconHref = computed(() => {
        return branding.value.favicon_url || avatarUrl.value || logoUrl.value || '/favicon.ico';
    });

    const publicFaviconType = computed(() => {
        const href = String(publicFaviconHref.value || '');
        if (href.endsWith('.svg')) return 'image/svg+xml';
        if (href.endsWith('.ico')) return 'image/x-icon';
        return 'image/png';
    });

    const userAvatarUrl = computed(() => page.props.auth?.user?.avatar_url || '');
    const themeStyles = computed(() => ({
        ...BRAND_CSS_VARS,
        '--contractor-primary': contractorPrimary.value,
        '--brand-primary': primaryColor.value,
        '--brand-accent': secondaryColor.value,
    }));

    const defaultBrandIconUrl = computed(() => logoUrl.value || '/favicon.ico');

    return {
        branding,
        brandName,
        logoUrl,
        avatarUrl,
        publicFaviconHref,
        publicFaviconType,
        primaryColor,
        secondaryColor,
        contractorActiveGradient,
        glassGradient,
        userAvatarUrl,
        themeStyles,
        normalizeHex,
        withAlpha,
        defaultBrandIconUrl,
    };
};

export default BRANDING;
