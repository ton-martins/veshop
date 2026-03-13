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

export default BRANDING;
