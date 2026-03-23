export function useCurrency(locale = 'pt-BR', currency = 'BRL') {
    const formatter = new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    });

    const formatCurrency = (value) => {
        const numericValue = Number(value ?? 0);

        if (Number.isNaN(numericValue)) {
            return formatter.format(0);
        }

        return formatter.format(numericValue);
    };

    return {
        formatCurrency,
    };
}
