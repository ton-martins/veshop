export const BRAZIL_STATES = [
    { code: 'AC', name: 'Acre' },
    { code: 'AL', name: 'Alagoas' },
    { code: 'AP', name: 'Amapá' },
    { code: 'AM', name: 'Amazonas' },
    { code: 'BA', name: 'Bahia' },
    { code: 'CE', name: 'Ceará' },
    { code: 'DF', name: 'Distrito Federal' },
    { code: 'ES', name: 'Espírito Santo' },
    { code: 'GO', name: 'Goiás' },
    { code: 'MA', name: 'Maranhão' },
    { code: 'MT', name: 'Mato Grosso' },
    { code: 'MS', name: 'Mato Grosso do Sul' },
    { code: 'MG', name: 'Minas Gerais' },
    { code: 'PA', name: 'Pará' },
    { code: 'PB', name: 'Paraíba' },
    { code: 'PR', name: 'Paraná' },
    { code: 'PE', name: 'Pernambuco' },
    { code: 'PI', name: 'Piauí' },
    { code: 'RJ', name: 'Rio de Janeiro' },
    { code: 'RN', name: 'Rio Grande do Norte' },
    { code: 'RS', name: 'Rio Grande do Sul' },
    { code: 'RO', name: 'Rondônia' },
    { code: 'RR', name: 'Roraima' },
    { code: 'SC', name: 'Santa Catarina' },
    { code: 'SP', name: 'São Paulo' },
    { code: 'SE', name: 'Sergipe' },
    { code: 'TO', name: 'Tocantins' },
];

export const BRAZIL_STATE_CODES = BRAZIL_STATES.map((state) => state.code);

export const onlyDigits = (value) => String(value ?? '').replace(/\D/g, '');

export const DOCUMENT_TYPE_CPF = 'cpf';
export const DOCUMENT_TYPE_CNPJ = 'cnpj';

export const formatCepBR = (value) => {
    const digits = onlyDigits(value).slice(0, 8);

    if (digits.length <= 5) return digits;
    return `${digits.slice(0, 5)}-${digits.slice(5)}`;
};

export const formatPhoneBR = (value) => {
    const digits = onlyDigits(value).slice(0, 11);

    if (digits.length === 0) return '';
    if (digits.length < 3) return `(${digits}`;

    const ddd = digits.slice(0, 2);
    const number = digits.slice(2);

    if (number.length <= 5) return `(${ddd}) ${number}`;
    return `(${ddd}) ${number.slice(0, 5)}-${number.slice(5)}`;
};

export const formatCpfCnpjBR = (value) => {
    const digits = onlyDigits(value).slice(0, 14);

    if (digits.length <= 11) {
        if (digits.length <= 3) return digits;
        if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`;
        if (digits.length <= 9) return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
        return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
    }

    if (digits.length <= 2) return digits;
    if (digits.length <= 5) return `${digits.slice(0, 2)}.${digits.slice(2)}`;
    if (digits.length <= 8) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
    if (digits.length <= 12) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;
    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
};

export const formatDocumentByTypeBR = (value, type = DOCUMENT_TYPE_CPF) => {
    const digits = onlyDigits(value);

    if (type === DOCUMENT_TYPE_CNPJ) {
        const cnpj = digits.slice(0, 14);

        if (cnpj.length <= 2) return cnpj;
        if (cnpj.length <= 5) return `${cnpj.slice(0, 2)}.${cnpj.slice(2)}`;
        if (cnpj.length <= 8) return `${cnpj.slice(0, 2)}.${cnpj.slice(2, 5)}.${cnpj.slice(5)}`;
        if (cnpj.length <= 12) return `${cnpj.slice(0, 2)}.${cnpj.slice(2, 5)}.${cnpj.slice(5, 8)}/${cnpj.slice(8)}`;
        return `${cnpj.slice(0, 2)}.${cnpj.slice(2, 5)}.${cnpj.slice(5, 8)}/${cnpj.slice(8, 12)}-${cnpj.slice(12)}`;
    }

    const cpf = digits.slice(0, 11);

    if (cpf.length <= 3) return cpf;
    if (cpf.length <= 6) return `${cpf.slice(0, 3)}.${cpf.slice(3)}`;
    if (cpf.length <= 9) return `${cpf.slice(0, 3)}.${cpf.slice(3, 6)}.${cpf.slice(6)}`;
    return `${cpf.slice(0, 3)}.${cpf.slice(3, 6)}.${cpf.slice(6, 9)}.${cpf.slice(9)}`;
};

export const detectDocumentTypeBR = (value) => (onlyDigits(value).length > 11 ? DOCUMENT_TYPE_CNPJ : DOCUMENT_TYPE_CPF);

export const isValidPhoneMaskBR = (value) => /^\(\d{2}\)\s\d{5}-\d{4}$/.test(String(value ?? '').trim());
export const isValidCepMaskBR = (value) => /^\d{5}-\d{3}$/.test(String(value ?? '').trim());
export const isValidDocumentByTypeBR = (value, type = DOCUMENT_TYPE_CPF) => {
    const safe = String(value ?? '').trim();

    if (type === DOCUMENT_TYPE_CNPJ) {
        return /^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/.test(safe);
    }

    return /^\d{3}\.\d{3}\.\d{3}([.-])\d{2}$/.test(safe);
};

export const normalizeStateCode = (value) => {
    const state = String(value ?? '').trim().toUpperCase();
    return BRAZIL_STATE_CODES.includes(state) ? state : '';
};

export const viaCepToAddress = (payload) => ({
    cep: formatCepBR(payload?.cep ?? ''),
    street: String(payload?.logradouro ?? '').trim(),
    neighborhood: String(payload?.bairro ?? '').trim(),
    city: String(payload?.localidade ?? '').trim(),
    state: normalizeStateCode(payload?.uf ?? ''),
    complement: String(payload?.complemento ?? '').trim(),
});

