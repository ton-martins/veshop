# Arquitetura Oficial do Veshop

Este documento consolida a arquitetura alvo da refatoração definida em `docs/refatoracao.md`.

## Modelo

- Monólito modular.
- Organização por domínio de negócio.
- Multi-tenant como regra central da plataforma.

## Stack

- Laravel + PHP 8.2+
- Inertia + Vue 3 + Vite + Tailwind

## Camadas

- `Domain`: entidades, enums, value objects e regras centrais.
- `Application`: actions, queries, DTOs e orquestração de casos de uso.
- `Infrastructure`: persistência, gateways, storage, integrações externas.
- `Http`: requests, controllers finos, middleware e presenters/resources.

## Regras não negociáveis

- Toda operação deve respeitar escopo do contratante.
- Toda autorização deve considerar `role + contractor + módulos`.
- Controller não concentra regra de negócio.
- Query complexa não fica em controller.
- Payload Inertia deve ser mínimo e seguro.

## ADRs ativos

- `adr-0001-monolito-modular.md`
- `adr-0002-tenant-guard-e-payload-minimo.md`

## Estrutura de pastas

```txt
app/
  Domain/
  Application/
  Infrastructure/
  Http/

resources/js/
  app/
    shared/
    modules/
  layouts/
  pages/

routes/
  admin/
  public/
  master/
```
