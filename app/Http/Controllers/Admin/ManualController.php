<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ManualController extends Controller
{
    use ResolvesCurrentContractor;

    /**
     * @var array<int, string>
     */
    private const ALLOWED_TABS = [
        'overview',
        'global_modules',
        'niche_modules',
        'business_modules',
        'playbook',
    ];

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        $tab = trim((string) $request->string('tab')->toString());
        $initialTab = in_array($tab, self::ALLOWED_TABS, true) ? $tab : 'overview';

        $enabledModules = $this->resolveEnabledModuleCodes($contractor);
        $manualGroups = $this->resolveManualGroups($enabledModules);

        return Inertia::render('Admin/Manuals/Index', [
            'initialTab' => $initialTab,
            'manualContext' => $this->resolveManualContext($contractor, $enabledModules, $manualGroups),
            'manualGroups' => $manualGroups,
            'operationChecklist' => $this->resolveOperationChecklist($contractor, $enabledModules),
            'businessPlaybook' => $this->resolveBusinessPlaybook($contractor),
            'quickLinks' => $this->resolveQuickLinks($enabledModules, $contractor),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function resolveEnabledModuleCodes(?Contractor $contractor): array
    {
        if (! $contractor) {
            return [];
        }

        return collect($contractor->enabledModules())
            ->map(static fn (mixed $code): string => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $enabledModules
     * @return array{global: array<int, array<string, mixed>>, niche: array<int, array<string, mixed>>, business: array<int, array<string, mixed>>}
     */
    private function resolveManualGroups(array $enabledModules): array
    {
        if ($enabledModules === []) {
            return [
                'global' => [],
                'niche' => [],
                'business' => [],
            ];
        }

        $modules = Module::query()
            ->where('is_active', true)
            ->whereIn('code', $enabledModules)
            ->orderByRaw("CASE scope WHEN 'global' THEN 0 WHEN 'niche' THEN 1 ELSE 2 END")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Module $module): array => $this->formatModuleManual($module))
            ->values();

        return [
            'global' => $modules->where('scope', Module::SCOPE_GLOBAL)->values()->all(),
            'niche' => $modules->where('scope', Module::SCOPE_NICHE)->values()->all(),
            'business' => $modules->where('scope', Module::SCOPE_SPECIFIC)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatModuleManual(Module $module): array
    {
        $guide = $this->resolveGuideForModule($module);
        $businessTypes = collect(is_array($module->business_types) ? $module->business_types : [])
            ->map(static fn (mixed $type): string => Contractor::labelForBusinessType((string) $type))
            ->filter()
            ->values()
            ->all();

        return [
            'code' => (string) $module->code,
            'name' => (string) $module->name,
            'description' => trim((string) ($module->description ?? '')) ?: 'Sem descrição cadastrada.',
            'scope' => (string) $module->scope,
            'scope_label' => $this->scopeLabel((string) $module->scope),
            'is_default' => (bool) $module->is_default,
            'goal' => $guide['goal'],
            'steps' => $guide['steps'],
            'checklist' => $guide['checklist'],
            'business_types' => $businessTypes,
            'actions' => $this->resolveModuleActions((string) $module->code),
        ];
    }

    /**
     * @return array{goal: string, steps: array<int, string>, checklist: array<int, string>}
     */
    private function resolveGuideForModule(Module $module): array
    {
        $code = strtolower(trim((string) $module->code));
        $name = (string) $module->name;

        return match ($code) {
            'finance' => [
                'goal' => 'Consolidar entradas e saídas com conciliação diária.',
                'steps' => [
                    'Classifique todos os lançamentos por categoria.',
                    'Concilie caixa e bancos no fechamento diário.',
                    'Projete fluxo de caixa da semana.',
                ],
                'checklist' => [
                    'Lançamentos do dia finalizados.',
                    'Divergências tratadas no mesmo dia.',
                    'Previsão de caixa atualizada.',
                ],
            ],
            'catalog', 'services_catalog' => [
                'goal' => 'Manter catálogo claro, vendável e fácil de operar.',
                'steps' => [
                    'Padronize nome, preço e descrição dos itens.',
                    'Organize categorias sem duplicidade.',
                    'Revise itens com baixa saída semanalmente.',
                ],
                'checklist' => [
                    'Itens com dados mínimos completos.',
                    'Categorias organizadas.',
                    'Preços revisados.',
                ],
            ],
            'orders', 'service_orders' => [
                'goal' => 'Padronizar fila operacional para reduzir atraso.',
                'steps' => [
                    'Priorize ordens por prazo e criticidade.',
                    'Atualize status em tempo real.',
                    'Registre pendências antes de encerrar o turno.',
                ],
                'checklist' => [
                    'Fila do dia priorizada.',
                    'Status atualizado nas ordens críticas.',
                    'Pendências com responsável definido.',
                ],
            ],
            'checkout', 'services_storefront' => [
                'goal' => 'Transformar demanda online em pedidos confirmados.',
                'steps' => [
                    'Valide jornada completa da loja pública.',
                    'Teste meios de pagamento e contato.',
                    'Acompanhe taxa de confirmação de pedidos.',
                ],
                'checklist' => [
                    'Loja pública acessível.',
                    'Pedido de teste concluído.',
                    'Canal de contato validado.',
                ],
            ],
            'schedule' => [
                'goal' => 'Organizar agenda para equilibrar capacidade e demanda.',
                'steps' => [
                    'Defina blocos por profissional.',
                    'Adicione folga entre atendimentos críticos.',
                    'Revise o dia seguinte no fechamento.',
                ],
                'checklist' => [
                    'Agenda sem conflito de horário.',
                    'Capacidade da equipe distribuída.',
                    'Confirmações do dia seguinte enviadas.',
                ],
            ],
            'pdv' => [
                'goal' => 'Padronizar abertura e fechamento para caixa seguro.',
                'steps' => [
                    'Siga checklist de abertura por turno.',
                    'Registre vendas sem atalhos fora do fluxo.',
                    'Concilie vendas e caixa no fechamento.',
                ],
                'checklist' => [
                    'Abertura registrada.',
                    'Fechamento validado.',
                    'Diferenças de caixa auditadas.',
                ],
            ],
            default => [
                'goal' => sprintf('Estruturar o módulo %s com rotina previsível.', $name),
                'steps' => [
                    sprintf('Acesse %s e valide as configurações principais.', $name),
                    'Defina responsável pela rotina diária.',
                    'Monitore indicadores desse módulo no fechamento.',
                ],
                'checklist' => [
                    sprintf('%s configurado para operação.', $name),
                    'Equipe orientada para o fluxo padrão.',
                    'Conferência diária em execução.',
                ],
            ],
        };
    }

    /**
     * @return array<int, array{label: string, href: string}>
     */
    private function resolveModuleActions(string $moduleCode): array
    {
        $actionsByModule = [
            'catalog' => [['label' => 'Produtos', 'route' => 'admin.products.index']],
            'services_catalog' => [['label' => 'Catálogo de serviços', 'route' => 'admin.services.catalog']],
            'orders' => [['label' => 'Pedidos', 'route' => 'admin.orders.index']],
            'service_orders' => [['label' => 'Ordens de serviço', 'route' => 'admin.services.orders']],
            'schedule' => [['label' => 'Agenda', 'route' => 'admin.services.schedule']],
            'pdv' => [['label' => 'PDV', 'route' => 'admin.pdv.index']],
            'finance' => [['label' => 'Financeiro', 'route' => 'admin.finance.index']],
            'reports' => [['label' => 'Relatórios', 'route' => 'admin.reports.index']],
            'files' => [['label' => 'Branding', 'route' => 'admin.branding.index']],
            'checkout' => [['label' => 'Loja virtual', 'route' => 'admin.storefront.index']],
            'services_storefront' => [['label' => 'Loja virtual', 'route' => 'admin.storefront.index']],
            'crm' => [['label' => 'Clientes', 'route' => 'admin.clients.index']],
            'inventory' => [['label' => 'Estoque', 'route' => 'admin.inventory.index']],
        ];

        return collect($actionsByModule[strtolower(trim($moduleCode))] ?? [])
            ->map(function (array $action): ?array {
                $routeName = (string) ($action['route'] ?? '');
                $label = (string) ($action['label'] ?? '');
                if ($routeName === '' || $label === '' || ! Route::has($routeName)) {
                    return null;
                }

                try {
                    return [
                        'label' => $label,
                        'href' => route($routeName),
                    ];
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $enabledModules
     * @return array<string, mixed>
     */
    private function resolveManualContext(?Contractor $contractor, array $enabledModules, array $manualGroups): array
    {
        $niche = $contractor?->niche() ?? Contractor::defaultNiche();
        $businessType = $contractor?->businessType() ?? Contractor::defaultBusinessType($niche);

        return [
            'contractor_name' => trim((string) ($contractor?->brand_name ?? $contractor?->name ?? 'Operação atual')) ?: 'Operação atual',
            'niche' => $niche,
            'niche_label' => $niche === Contractor::NICHE_SERVICES ? 'Serviços' : 'Comércio',
            'business_type' => $businessType,
            'business_type_label' => Contractor::labelForBusinessType($businessType),
            'plan_name' => $contractor?->activePlanName() ?? 'Sem plano',
            'enabled_modules_count' => count($enabledModules),
            'group_counts' => [
                'global' => count($manualGroups['global'] ?? []),
                'niche' => count($manualGroups['niche'] ?? []),
                'business' => count($manualGroups['business'] ?? []),
            ],
        ];
    }

    /**
     * @param array<int, string> $enabledModules
     * @return array<string, array<int, string>>
     */
    private function resolveOperationChecklist(?Contractor $contractor, array $enabledModules): array
    {
        $niche = $contractor?->niche() ?? Contractor::defaultNiche();

        $essentials = [
            'Validar dados do negócio e canais de atendimento.',
            'Revisar permissões de usuários e acessos críticos.',
            'Definir responsável pela rotina de fechamento diário.',
        ];

        if (in_array('finance', $enabledModules, true)) {
            $essentials[] = 'Executar conciliação financeira no fim do expediente.';
        }

        if (in_array('checkout', $enabledModules, true) || in_array('services_storefront', $enabledModules, true)) {
            $essentials[] = 'Finalizar pedido de teste na loja pública.';
        }

        return [
            'essentials' => $essentials,
            'niche' => $niche === Contractor::NICHE_SERVICES
                ? [
                    'Revisar agenda e capacidade técnica no início do dia.',
                    'Priorizar ordens com prazo crítico.',
                    'Atualizar cliente sobre andamento dos atendimentos.',
                ]
                : [
                    'Conferir pedidos pendentes de separação/entrega.',
                    'Revisar rupturas de estoque crítico.',
                    'Validar fechamento de caixa e pedidos do dia.',
                ],
            'security' => [
                'Aplicar política de senha forte e troca periódica.',
                'Registrar incidentes operacionais com responsável e plano de ação.',
                'Evitar compartilhamento de contas administrativas.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveBusinessPlaybook(?Contractor $contractor): array
    {
        $niche = $contractor?->niche() ?? Contractor::defaultNiche();
        $businessType = $contractor?->businessType() ?? Contractor::defaultBusinessType($niche);
        $businessTypeLabel = Contractor::labelForBusinessType($businessType);

        $summaryByType = [
            Contractor::BUSINESS_TYPE_STORE => 'Foco em conversão, giro de estoque e checkout.',
            Contractor::BUSINESS_TYPE_CONFECTIONERY => 'Foco em produção sob demanda e prazo de entrega.',
            Contractor::BUSINESS_TYPE_BARBERSHOP => 'Foco em agenda e recorrência de clientes.',
            Contractor::BUSINESS_TYPE_AUTO_ELECTRIC => 'Foco em diagnóstico técnico e prazo de execução.',
            Contractor::BUSINESS_TYPE_MECHANIC => 'Foco em ordens, peças e previsibilidade da oficina.',
            Contractor::BUSINESS_TYPE_ACCOUNTING => 'Foco em tarefas recorrentes, documentos e SLA.',
            Contractor::BUSINESS_TYPE_GENERAL_SERVICES => 'Foco em agenda, ordens e padronização operacional.',
        ];

        return [
            'business_type' => $businessType,
            'business_type_label' => $businessTypeLabel,
            'title' => sprintf('Playbook para %s', $businessTypeLabel),
            'summary' => $summaryByType[$businessType] ?? 'Foco em eficiência e previsibilidade operacional.',
            'daily' => [
                'Priorizar fila crítica no início do dia.',
                'Atualizar status dos atendimentos/pedidos em tempo real.',
                'Fechar o dia com pendências e responsáveis definidos.',
            ],
            'weekly' => [
                'Revisar indicadores principais e gargalos.',
                'Ajustar capacidade da equipe conforme demanda.',
                'Executar ação de retenção/reativação de clientes.',
            ],
            'monthly' => [
                'Revisar margem e produtividade por frente.',
                'Atualizar plano de ação para principais desvios.',
            ],
            'alerts' => [
                'Aumento de atraso em fila operacional.',
                'Queda de conversão ou produtividade semanal.',
                'Acúmulo de pendências sem responsável.',
            ],
        ];
    }

    /**
     * @param array<int, string> $enabledModules
     * @return array<int, array<string, mixed>>
     */
    private function resolveQuickLinks(array $enabledModules, ?Contractor $contractor): array
    {
        $links = [];
        $map = [
            'catalog' => ['label' => 'Produtos', 'route' => 'admin.products.index'],
            'services_catalog' => ['label' => 'Catálogo de serviços', 'route' => 'admin.services.catalog'],
            'orders' => ['label' => 'Pedidos', 'route' => 'admin.orders.index'],
            'service_orders' => ['label' => 'Ordens de serviço', 'route' => 'admin.services.orders'],
            'schedule' => ['label' => 'Agenda', 'route' => 'admin.services.schedule'],
            'finance' => ['label' => 'Financeiro', 'route' => 'admin.finance.index'],
            'reports' => ['label' => 'Relatórios', 'route' => 'admin.reports.index'],
        ];

        foreach ($map as $moduleCode => $item) {
            if (! in_array($moduleCode, $enabledModules, true)) {
                continue;
            }

            $routeName = (string) ($item['route'] ?? '');
            if (! Route::has($routeName)) {
                continue;
            }

            try {
                $links[] = [
                    'label' => (string) ($item['label'] ?? 'Abrir'),
                    'href' => route($routeName),
                    'external' => false,
                ];
            } catch (Throwable) {
                // ignore invalid route
            }
        }

        $hasPublicStore = in_array('checkout', $enabledModules, true) || in_array('services_storefront', $enabledModules, true);
        if ($hasPublicStore && Route::has('shop.show')) {
            $slug = trim((string) ($contractor?->slug ?? ''));
            if ($slug !== '') {
                try {
                    array_unshift($links, [
                        'label' => 'Loja pública',
                        'href' => route('shop.show', ['slug' => $slug]),
                        'external' => true,
                    ]);
                } catch (Throwable) {
                    // ignore invalid route
                }
            }
        }

        return array_slice($links, 0, 6);
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            Module::SCOPE_GLOBAL => 'Global',
            Module::SCOPE_NICHE => 'Nicho',
            default => 'Tipo de negócio',
        };
    }
}

