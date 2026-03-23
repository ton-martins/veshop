# ADR-0001: Monólito Modular com Multi-tenant First

## Status
Aprovada

## Contexto
O Veshop precisa suportar múltiplos nichos e tipos de negócio mantendo isolamento de dados por contratante, evolução rápida e baixa complexidade operacional.

## Decisão
Adotar monólito modular com separação obrigatória entre `Domain`, `Application`, `Infrastructure` e `Http`, mantendo stack Laravel + Inertia + Vue.

## Consequências
- Evolução previsível por domínio.
- Menor acoplamento entre interface e regra de negócio.
- Facilidade para aplicar autorização e escopo multi-tenant de forma transversal.
- Exige disciplina com controllers finos, actions/queries e testes de contrato.
