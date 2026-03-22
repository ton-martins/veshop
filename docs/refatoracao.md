Documento oficial — Padrão de Arquitetura do Veshop
Veshop — Padrão Oficial de Arquitetura e Desenvolvimento
1. Objetivo
Este documento define o padrão obrigatório para desenvolvimento do Veshop.

O Veshop é um ERP multi-tenant que atende múltiplos nichos e tipos de negócio, com arquitetura orientada a:

domínio;

escopo por contratante;

módulos por plano/nicho/tipo de negócio;

escalabilidade;

fácil manutenção;

segurança;

padronização entre backend, frontend e banco.

Este padrão substitui implementações ad hoc e passa a ser a referência oficial para qualquer nova funcionalidade, refatoração ou reimplementação.

2. Princípios obrigatórios
2.1 Multi-tenant primeiro
Toda funcionalidade deve respeitar escopo de contratante.

Regras obrigatórias:

nenhum dado operacional pode ser acessado sem escopo correto;

toda modelagem deve decidir explicitamente se a entidade é global ou tenant-scoped;

toda consulta operacional deve respeitar contractor_id, quando aplicável;

autorização deve considerar usuário, role, contratante ativo e módulos habilitados.

2.2 Arquitetura por domínio
O sistema deve ser organizado por domínios de negócio, e não apenas por tipo técnico.

2.3 Controllers finos
Controllers não devem conter regra de negócio complexa, queries extensas, montagem pesada de payload ou persistência sofisticada.

2.4 Frontend modular
Páginas, layouts e componentes devem ser organizados por módulo e responsabilidade. Layouts gigantes e páginas excessivamente longas devem ser quebrados.

2.5 Banco limpo e explícito
As migrations devem ser reescritas do zero, agrupadas por domínio, com nomes consistentes, FKs explícitas, índices corretos e padronização de tipos.

2.6 Teste como contrato
Fluxos críticos, escopo multi-tenant, autorização, payload mínimo e segurança devem ser protegidos por testes.

3. Arquitetura alvo
3.1 Modelo arquitetural
Adotar monólito modular.

3.2 Stack oficial
Laravel

PHP 8.2+

Inertia

Vue 3

Vite

Tailwind

3.3 Estrutura de diretórios backend
app/
  Domain/
    Shared/
    Identity/
    Tenant/
    Catalog/
    CRM/
    Sales/
    Pdv/
    Inventory/
    Finance/
    Storefront/
    Services/
    Accounting/
    Notifications/
    Reports/
    Branding/
    Files/
    Audit/

  Application/
    Shared/
    Identity/
    Tenant/
    Catalog/
    CRM/
    Sales/
    Pdv/
    Inventory/
    Finance/
    Storefront/
    Services/
    Accounting/
    Notifications/
    Reports/
    Branding/
    Files/
    Audit/

  Infrastructure/
    Persistence/
    Payments/
    Notifications/
    Storage/
    Security/

  Http/
    Controllers/
    Requests/
    Resources/
    Middleware/
3.4 Estrutura de diretórios frontend
resources/js/
  app/
    shared/
      ui/
      composables/
      utils/
      constants/
      types/
      services/
    modules/
      auth/
      tenant/
      catalog/
      crm/
      orders/
      sales/
      pdv/
      inventory/
      finance/
      storefront/
      services/
      accounting/
      notifications/
      reports/
      branding/
  layouts/
    app/
    public/
    master/
  pages/
    admin/
    public/
    master/
4. Padrão por camada
4.1 Domain
Responsável por:

entidades;

enums;

value objects;

contratos;

regras centrais do domínio.

4.2 Application
Responsável por:

actions/use cases;

queries;

DTOs;

orquestração de fluxo.

4.3 Infrastructure
Responsável por:

Eloquent/repositories;

gateways;

storage;

notificações;

integrações externas.

4.4 Http
Responsável por:

controllers finos;

requests;

middleware;

resources/presenters.

5. Padrões obrigatórios de backend
5.1 Controllers
Controllers devem:

receber request;

validar;

chamar action/query;

retornar response.

Controllers não devem:

executar regra de negócio pesada;

montar payload grande inline;

conter transações complexas;

misturar upload, quota, cálculo, persistência e transformação.

5.2 Actions
Todo caso de uso com efeito colateral deve ser uma Action.

Exemplos:

CreateProductAction

UpdateProductAction

CreatePdvSaleAction

CheckoutAction

BookServiceAction

5.3 Queries
Toda leitura complexa deve ser Query.

Exemplos:

ListProductsQuery

BuildPdvPageQuery

BuildPublicShopPageQuery

5.4 Requests
Requests devem:

validar input;

normalizar input.

Requests não devem:

executar lógica de negócio;

repetir grandes blocos de normalização se isso puder ser compartilhado.

5.5 Resources / Presenters
Saída para Inertia deve ser construída por resources/presenters/DTOs de saída.

6. Padrões obrigatórios de frontend
6.1 Pages
Pages devem ser compostas por blocos menores.

6.2 Layouts
Layouts não devem concentrar:

contractor context;

notificações;

branding;

menu;

drawer;

responsividade;

atalhos de ação;

regras de módulo

em um único arquivo gigante.

6.3 Shared UI
Criar biblioteca interna de UI:

AppPageShell

AppSectionCard

AppDataTable

AppFilterBar

AppModal

AppConfirmDialog

AppEmptyState

AppStatCard

AppStatusBadge

AppFormField

6.4 Composables
Estado e lógica reutilizável devem ir para composables.

Exemplos:

useContractorContext

useNotifications

useCurrency

usePaginatedFilters

useModuleAccess

7. Banco e migrations
7.1 Estratégia
Recriar migrations do zero.

7.2 Regras
agrupar migrations por domínio;

usar nomes consistentes;

explicitar FKs e índices;

separar tabelas globais e tenant-scoped;

revisar enums e colunas monetárias.

7.3 Organização sugerida
database/migrations/
  0000_00_00_000001_create_identity_tables.php
  0000_00_00_000010_create_tenant_tables.php
  0000_00_00_000020_create_plan_and_module_tables.php
  0000_00_00_000100_create_catalog_tables.php
  0000_00_00_000200_create_crm_tables.php
  0000_00_00_000300_create_inventory_tables.php
  0000_00_00_000400_create_sales_tables.php
  0000_00_00_000500_create_pdv_tables.php
  0000_00_00_000600_create_finance_tables.php
  0000_00_00_000700_create_storefront_tables.php
  0000_00_00_000800_create_services_tables.php
  0000_00_00_000900_create_accounting_tables.php
  0000_00_00_001000_create_notifications_and_audit_tables.php
8. Módulos oficiais
8.1 Core
Identity

Tenant

Users

Plans

Modules

Branding

Notifications

Files

Audit

Reports

8.2 Comércio
Catalog

Categories

Clients

Suppliers

Inventory

Orders

Sales

PDV

Finance

Storefront

Payments

8.3 Serviços
Service Categories

Service Catalog

Service Orders

Schedule

Attendance

Service Storefront

8.4 Contábil
Client Profiles

Obligations

Fees

Templates

Documents

Reminder Automation

9. Testes obrigatórios
Cada módulo deve ter:

testes de fluxo principal;

testes de autorização;

testes de escopo por contratante;

testes de payload mínimo;

testes de regressão crítica.

10. Definition of Done
Uma entrega só é considerada pronta quando:

segue a arquitetura por domínio;

respeita contractor scope;

usa action/query/resource adequadamente;

não cria controller/layout/página monolítica;

inclui testes do fluxo crítico;

mantém textos funcionais em pt-BR;

não expõe dados sensíveis em payload.

11. Regras de bloqueio
São proibidos:

controllers gigantes com regras misturadas;

requests duplicadas sem abstração compartilhada;

páginas Vue gigantes sem extração;

rota nova em arquivo incorreto;

regra multi-tenant sem proteção;

payload Inertia montado manualmente de forma extensa em controller.

12. Estratégia de migração
A reimplementação será feita por fases:

fundação;

migrations novas;

core;

comércio;

serviços;

contábil;

hardening.

Opção 2 — Backlog técnico por PRs
Aqui está a versão prática para o Codex executar em sequência.

A lógica deste backlog respeita o que a base atual já mostra como prioridade: módulo por nicho, contractor/module access, loja/PDV/financeiro/serviços e segurança como contratos obrigatórios. 

PR-01 — Fundação arquitetural
Objetivo
Criar o esqueleto novo do projeto.

Entregas
criar nova árvore Domain/Application/Infrastructure/Http;

criar nova organização frontend por módulos;

criar pasta de docs de arquitetura;

adicionar convenções oficiais;

criar README técnico novo;

criar scripts de qualidade.

Critério de aceite
estrutura nova aprovada;

README explica setup, arquitetura e convenções;

scripts padronizados definidos.

PR-02 — Tooling e governança
Objetivo
Padronizar o fluxo do time.

Entregas
PHP CS com Pint;

ESLint;

Prettier;

scripts:

lint

lint:php

lint:js

format

test

test:php

build

template de PR;

checklist de DoD.

Critério de aceite
todos os scripts rodando;

PR template e checklists criados.

PR-03 — Reset de migrations e schema base
Objetivo
Reescrever banco do zero.

Entregas
remover/arquivar migrations antigas;

criar baseline nova por domínio;

revisar tipos monetários;

revisar índices multi-tenant;

revisar constraints e FKs;

criar seeders base.

Critério de aceite
banco sobe limpo do zero;

seed básico funcional;

schema revisado.

PR-04 — Core: Identity + Tenant + Plans + Modules
Objetivo
Reimplementar núcleo do sistema.

Entregas
usuários;

contractor;

contractor-user;

plans;

modules;

contract/module capabilities;

tenant context resolver;

role + module enforcement.

Critério de aceite
login/2FA/contexto por contratante funcionam;

módulos por contratante/plano controlam acesso.

PR-05 — Core: Audit + Notifications + Branding + Files
Objetivo
Padronizar serviços transversais.

Entregas
audit log;

notification center;

branding settings;

storage base;

policies/guards de arquivos;

payload mínimo de auth/context.

Critério de aceite
trilha de auditoria funcional;

notificações padronizadas;

branding isolado;

arquivos escopados.

PR-06 — Frontend shell novo
Objetivo
Substituir o layout autenticado monolítico.

Entregas
novo AppLayoutShell;

SidebarNavigation;

TopbarBranding;

ContractorSwitcher;

NotificationsDrawer;

UserMenu;

design tokens básicos;

shared UI primitives.

Critério de aceite
layout antigo deixa de ser centro de tudo;

navegação e contractor context ficam desacoplados.

PR-07 — Módulo Catalog
Objetivo
Reimplementar categorias, produtos, imagens e variações.

Entregas
migrations novas;

actions:

create/update/delete product

sync variations

sync gallery

queries de listagem;

requests padronizadas;

resources/presenters;

páginas Vue modulares.

Critério de aceite
CRUD completo;

contractor scope garantido;

upload/variações desacoplados do controller.

PR-08 — Módulo CRM
Objetivo
Reimplementar clientes e fornecedores.

Entregas
clientes;

fornecedores;

actions/queries;

components padrão de listagem/form;

cobertura de testes.

Critério de aceite
CRUD padronizado;

filtros e paginação consistentes.

PR-09 — Inventory
Objetivo
Reorganizar estoque e movimentações.

Entregas
inventory movements;

estoque por produto/variação;

regras de ajuste;

tela de estoque padronizada.

Critério de aceite
movimentação consistente com vendas e catálogo.

PR-10 — Orders / Sales
Objetivo
Reimplementar pedidos e vendas.

Entregas
aggregates claros;

actions de confirmação/cancelamento/pagamento;

recálculo isolado;

payload de detalhes por presenter;

UI de detalhes e edição desacoplada.

Critério de aceite
fluxo transacional íntegro;

auditoria preservada;

testes críticos cobrindo alterações.

PR-11 — PDV
Objetivo
Reimplementar caixa e venda balcão.

Entregas
cash session;

cash movement;

create PDV sale;

create walk-in client;

query de página do PDV;

page Vue modular.

Critério de aceite
abertura/fechamento de caixa;

venda PDV consistente;

sem controller monolítico.

PR-12 — Finance
Objetivo
Reimplementar financeiro base.

Entregas
lançamentos;

métodos de pagamento;

gateways;

tabs/painéis padronizados;

testes de conexão/gateway desacoplados.

Critério de aceite
financeiro padronizado com CRUD, filtros e regras claras.

PR-13 — Storefront comercial
Objetivo
Reimplementar loja pública comercial.

Entregas
query de vitrine;

query de produto;

checkout action;

shipping/payment presenter;

componentes públicos da loja modularizados.

Critério de aceite
vitrine, produto, carrinho e checkout funcionando;

PublicShopController deixa de concentrar tudo.

PR-14 — Serviços
Objetivo
Reimplementar nicho serviços.

Entregas
service categories;

service catalog;

service orders;

scheduling;

booking público;

UI administrativa por módulo.

Critério de aceite
agenda, catálogo e ordens de serviço padronizados.

PR-15 — Contábil
Objetivo
Reimplementar módulo contábil gerencial.

Entregas
client profiles;

fees;

obligations;

templates;

documents;

automation actions.

Critério de aceite
estrutura pronta para expansão futura.

PR-16 — Master area
Objetivo
Padronizar área master.

Entregas
users;

contractors;

plans;

branding master;

pages menores e organizadas;

resources/presenters.

Critério de aceite
área master alinhada ao novo padrão.

PR-17 — Segurança e payload hardening
Objetivo
Fechar bordas de segurança.

Entregas
revisar payloads Inertia;

revisar role/module/tenant guards;

revisar logs;

revisar headers;

manter testes de segurança.

Critério de aceite
nenhum payload sensível indevido;

cross-tenant acessos bloqueados.

PR-18 — Hardening final + documentação
Objetivo
Fechar release interno.

Entregas
atualizar docs;

adicionar mapa de módulos;

definir ADRs;

revisar textos pt-BR/UTF-8;

checklist final de release.

Critério de aceite
arquitetura documentada;

onboarding real;

base pronta para evolução.

Opção 3 — Prompt mestre para o Codex executar
Abaixo está um prompt pronto, no tom de instrução operacional, para você colar no Codex.

Prompt mestre para o Codex
Quero que você reimplemente a aplicação Veshop de forma organizada, estruturada, escalável e fácil de manter, sem mudar a stack principal e preservando as regras de negócio atuais.

Contexto do produto
O Veshop é um ERP multi-tenant que atende diferentes nichos e tipos de negócio. A arquitetura precisa refletir:

módulos globais;

módulos por nicho;

módulos por tipo de negócio;

escopo por contratante;

módulos habilitados por plano/contractor;

segurança multi-tenant;

manutenção simples e previsível.

Decisão arquitetural
Adote monólito modular com separação clara entre:

Domain

Application

Infrastructure

Http

Stack
Manter:

Laravel

PHP 8.2+

Inertia

Vue 3

Vite

Tailwind

Objetivos obrigatórios
Refazer a base com organização por domínio.

Reescrever migrations do zero.

Reestruturar backend e frontend por módulos.

Eliminar controllers grandes com regra de negócio misturada.

Eliminar páginas/layouts Vue gigantes e quebrar em componentes menores.

Padronizar requests, actions, queries, resources e testes.

Garantir escopo multi-tenant em todo fluxo.

Garantir que plano/módulos do contratante governem acesso.

Preservar e ampliar os testes críticos de segurança e escopo.

Restrições obrigatórias
Backend
Controller deve ser fino.

Controller não pode conter regra de negócio complexa.

Toda leitura complexa deve ser Query.

Todo caso de uso com efeito colateral deve ser Action.

Requests apenas validam e normalizam input.

Saída para Inertia deve ser via Resources/Presenters/DTOs.

Frontend
Organizar por módulos.

Criar design system básico.

Extrair layouts, drawers, menus, notificações, contractor switcher e branding em componentes próprios.

Nenhum layout gigante centralizando tudo.

Nenhuma página com responsabilidade excessiva.

Banco
Refazer migrations do zero.

Agrupar migrations por domínio.

Revisar constraints, FKs, índices e tipos monetários.

Decidir explicitamente o que é global e o que é tenant-scoped.

Segurança
Toda regra operacional deve respeitar contractor scope.

Toda autorização deve considerar role + contractor + módulos.

Não expor payload sensível no Inertia.

Manter trilha de auditoria.

Garantir testes de cross-tenant denial.

Estrutura alvo
Backend
app/
  Domain/
  Application/
  Infrastructure/
  Http/
Frontend
resources/js/
  app/
    shared/
    modules/
  layouts/
  pages/
Rotas
Separar arquivos de rotas por área e domínio:

routes/
  admin/
  public/
  master/
Módulos obrigatórios
Core
Identity

Tenant

Users

Plans

Modules

Notifications

Audit

Branding

Files

Reports

Comércio
Catalog

Categories

Clients

Suppliers

Inventory

Orders

Sales

PDV

Finance

Storefront

Payments

Serviços
Service Categories

Service Catalog

Service Orders

Schedule

Attendance

Service Storefront

Contábil
Client Profiles

Obligations

Fees

Templates

Documents

Reminder Automation

Ordem de implementação
Fundação arquitetural e docs.

Tooling e scripts de qualidade.

Reset de migrations e schema novo.

Core: identity, tenant, plans, modules.

Core: audit, notifications, branding, files.

Frontend shell novo.

Catalog.

CRM.

Inventory.

Orders/Sales.

PDV.

Finance.

Storefront comercial.

Serviços.

Contábil.

Área master.

Hardening de segurança e payload.

Documentação final.

Padrões de nomenclatura
Actions
Use nomes explícitos:

CreateProductAction

UpdateProductAction

CheckoutAction

CreatePdvSaleAction

Queries
Use nomes de leitura:

ListProductsQuery

BuildPdvPageQuery

BuildPublicShopPageQuery

Resources / Presenters
ProductResource

SaleResource

ContractorContextResource

Vue
Separar:

Page

Section

Form

Table

Card

Modal

Drawer

Widget

Critérios de aceite por PR
Cada PR deve:

seguir a nova arquitetura;

incluir testes mínimos;

respeitar escopo multi-tenant;

não introduzir controllers/layouts/pages monolíticas;

atualizar documentação quando necessário.

Entregáveis esperados
Quero que você:

proponha a nova estrutura;

implemente os arquivos-base;

migre módulo por módulo;

mantenha commits organizados;

produza PRs com escopo claro;

documente cada fase.

Instrução final
Se identificar lógica crítica existente na base antiga, preserve o comportamento funcional, mas reimplemente com a nova arquitetura.
Não replique a bagunça estrutural anterior.
Priorize clareza, separação de responsabilidades, testabilidade, consistência visual e segurança multi-tenant.