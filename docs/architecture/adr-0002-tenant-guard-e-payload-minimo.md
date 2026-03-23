# ADR-0002 — Tenant guard e payload mínimo

- Data: 22/03/2026
- Status: Aceito

## Contexto

O Veshop é multi-tenant por definição. Durante a refatoração PR-01 a PR-18, os principais riscos técnicos observados foram:

- acesso cross-tenant por falha de escopo;
- autorização incompleta por módulo/role;
- payloads Inertia com dados além do necessário.

Esses pontos geram risco de segurança e acoplamento desnecessário entre frontend e backend.

## Decisão

Adotar como regra não negociável:

1. Toda leitura/escrita operacional deve validar escopo de contratante.
2. Toda rota administrativa deve respeitar `role + módulo habilitado + contratante ativo`.
3. Payload compartilhado de auth/context deve expor apenas campos estritamente necessários.
4. Violação de escopo/autorização deve ser auditada.

## Consequências

- Reduz risco de vazamento de dados entre contratantes.
- Mantém o frontend desacoplado de detalhes sensíveis do backend.
- Torna o comportamento de segurança testável e previsível.
- Exige disciplina contínua em requests, policies e shared props.

## Evidências de validação

- `Tests\Feature\Security\SecurityAuditHardeningTest`
- `Tests\Feature\Admin\ModuleAccessControlTest`
