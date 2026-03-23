# Checklist final de release (PR-18)

Data: 22/03/2026

## Arquitetura e organização

- [x] Estrutura por domínio consolidada (`Domain/Application/Infrastructure/Http`).
- [x] Rotas separadas por área (`routes/admin`, `routes/master`, `routes/public`).
- [x] Frontend modular ativo com shell extraído em componentes.

## Banco e modelagem

- [x] Migrations legadas removidas.
- [x] Baseline única no padrão `0000_...` por domínio.
- [x] Tabelas multi-tenant com FKs e índices explícitos.

## Segurança e escopo

- [x] Guardas de módulo/role/tenant aplicados.
- [x] Payload Inertia minimizado para auth/context.
- [x] Auditoria de eventos críticos ativa.

## Qualidade

- [x] `composer lint:php` sem falhas.
- [x] `composer test:php` com suíte verde.
- [x] Regressões críticas de comércio, PDV, financeiro, storefront e segurança cobertas.

## Documentação

- [x] README técnico atualizado.
- [x] Visão de arquitetura atualizada.
- [x] Convenções e mapa de módulos publicados.
- [x] ADRs consolidados (`adr-0001`, `adr-0002`).
