export const masterMenuGroups = [
    {
        key: 'dashboard',
        label: 'Início',
        icon: 'LayoutDashboard',
        links: [
            {
                key: 'master-dashboard',
                label: 'Visão geral',
                route: 'master.home',
                match: ['master.home'],
                icon: 'BarChart3',
            },
        ],
    },
    {
        key: 'saas',
        label: 'Gestão',
        icon: 'Briefcase',
        links: [
            {
                key: 'master-contractors',
                label: 'Contratantes',
                route: 'master.contractors.index',
                match: ['master.contractors.*'],
                icon: 'Building2',
            },
            {
                key: 'master-plans',
                label: 'Planos',
                route: 'master.plans.index',
                match: ['master.plans.*'],
                icon: 'ServerCog',
            },
            {
                key: 'master-billing',
                label: 'Faturamento',
                route: 'master.billing.index',
                match: ['master.billing.*'],
                icon: 'ReceiptText',
            },
            {
                key: 'master-support',
                label: 'Suporte',
                route: 'master.support.index',
                match: ['master.support.*'],
                icon: 'LifeBuoy',
            },
        ],
    },
    {
        key: 'access',
        label: 'Acessos',
        icon: 'UsersRound',
        links: [
            {
                key: 'master-users',
                label: 'Usuários',
                route: 'master.users.index',
                match: ['master.users.*'],
                icon: 'UserRound',
            },
        ],
    },
    {
        key: 'settings',
        label: 'Configurações',
        icon: 'Cog',
        links: [
            {
                key: 'master-branding',
                label: 'Branding',
                route: 'master.branding.index',
                match: ['master.branding.*'],
                icon: 'Palette',
            },
        ],
    },
];
