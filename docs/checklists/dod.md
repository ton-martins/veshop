# Checklist de Definition of Done (DoD)

## Arquitetura
- [ ] Implementação segue `Domain/Application/Infrastructure/Http`.
- [ ] Código organizado por domínio de negócio.
- [ ] Não cria controller, layout ou página monolítica.

## Multi-tenant e segurança
- [ ] Fluxo respeita `contractor_id` em leitura e escrita.
- [ ] Autorização considera usuário, role, contratante ativo e módulos habilitados.
- [ ] Payload Inertia não expõe dados sensíveis.
- [ ] Ação crítica gera trilha de auditoria.

## Backend
- [ ] Controller apenas valida/chama action-query/retorna response.
- [ ] Caso de uso com efeito colateral implementado em `Action`.
- [ ] Leitura complexa implementada em `Query`.
- [ ] Validação e normalização concentradas em `Request`.
- [ ] Saída padronizada via `Resource/Presenter/DTO`.

## Frontend
- [ ] Página quebrada em blocos menores.
- [ ] Layout sem concentração excessiva de responsabilidades.
- [ ] Reuso de `shared/ui` e `composables`.
- [ ] Textos funcionais em pt-BR.

## Banco e dados
- [ ] Migrations seguem agrupamento por domínio.
- [ ] FKs, índices e constraints explícitos.
- [ ] Entidades globais vs tenant-scoped documentadas.

## Qualidade
- [ ] Testes de fluxo principal.
- [ ] Testes de autorização.
- [ ] Testes de escopo por contratante.
- [ ] Testes de payload mínimo.
- [ ] Testes de regressão crítica.
