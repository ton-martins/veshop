# Backlog técnico por PR (status final)

Data de fechamento interno: 22/03/2026.

## PR-01 Fundação arquitetural

- [x] Estrutura `Domain/Application/Infrastructure/Http`.
- [x] Organização frontend por módulos em `resources/js/app`.
- [x] Documentação de arquitetura e convenções.
- [x] README técnico atualizado.

## PR-02 Tooling e governança

- [x] Scripts oficiais: `lint`, `lint:php`, `lint:js`, `format`, `test`, `test:php`, `build`.
- [x] Pint + ESLint + Prettier ativos.
- [x] Template de PR criado em `.github/pull_request_template.md`.
- [x] Checklist de DoD criado em `docs/checklists/dod.md`.

## PR-03 Reset de migrations e schema base

- [x] Migrations legadas removidas e baseline `0000_...` consolidado por domínio.
- [x] Modelagem robusta multi-tenant com FKs e índices explícitos.
- [x] Fluxo de migração do zero validado na base atual.

## PR-04 Core: Identity + Tenant + Plans + Modules

- [x] Contexto de contratante ativo.
- [x] Vínculo contractor-user e módulos por plano/contratante.
- [x] Enforcement por role + módulo + contexto.

## PR-05 Core: Audit + Notifications + Branding + Files

- [x] Auditoria padronizada e trilha crítica ativa.
- [x] Notification center integrado.
- [x] Branding e armazenamento com escopo por contratante.

## PR-06 Frontend shell novo

- [x] `AppLayoutShell` reestruturado com extrações:
    - `TopbarBranding`
    - `ContractorSwitcher`
    - `SidebarNavigation`
    - `UserMenu`
    - `NotificationsDrawer`
- [x] Navegação/contexto desacoplados do shell principal.

## PR-07 Módulo Catalog

- [x] Catálogo, categorias, imagens e variações no novo padrão.
- [x] Controllers finos delegando para `Application/Catalog`.
- [x] Cobertura crítica validada em testes de comércio.

## PR-08 Módulo CRM

- [x] Clientes e fornecedores no padrão de serviços de aplicação.
- [x] Escopo multi-tenant e autorização preservados.

## PR-09 Inventory

- [x] Estoque e movimentações compatíveis com produto/variação.
- [x] Integração com vendas e PDV validada por testes.

## PR-10 Orders / Sales

- [x] Fluxos de confirmação/pagamento/cancelamento/refugo padronizados.
- [x] Regras transacionais isoladas em camada de aplicação.
- [x] Auditoria e testes de regressão críticos preservados.

## PR-11 PDV

- [x] Sessão de caixa, movimentação e venda balcão no padrão novo.
- [x] Query/payload de PDV desacoplados de controller.

## PR-12 Finance

- [x] Lançamentos, métodos e gateways extraídos para `Application/Finance`.
- [x] Testes de validação/conexão de gateway preservados.

## PR-13 Storefront comercial

- [x] Vitrine, produto, carrinho e checkout no padrão modular.
- [x] `PublicShopController` e `StorefrontController` sem concentração de regra.

## PR-14 Serviços

- [x] Categorias, catálogo, ordens e agenda de serviços padronizados.
- [x] Controllers administrativos delegando para `Application/Services`.

## PR-15 Contábil

- [x] Estrutura contábil gerencial implementada com tabelas e fluxo base.
- [x] Controller contábil fino delegando para `Application/Accounting`.

## PR-16 Master area

- [x] Área master alinhada com padrão de serviços de aplicação.
- [x] Gestão de contractors/usuários/planos/branding padronizada.

## PR-17 Segurança e payload hardening

- [x] Guardas de role/módulo/tenant reforçados.
- [x] Payloads Inertia com dados mínimos de auth/context.
- [x] Regressões de segurança cobertas (`SecurityAuditHardeningTest`).

## PR-18 Hardening final + documentação

- [x] Atualização da documentação de arquitetura, convenções e mapa de módulos.
- [x] Registro de ADRs da refatoração.
- [x] Checklist final de release criado em `docs/checklists/release-pr18.md`.
- [x] Validação final: `composer lint:php` e `composer test:php` com suíte verde.
