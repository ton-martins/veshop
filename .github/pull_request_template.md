## Resumo
- [ ] Escopo objetivo da alteração descrito em até 5 linhas
- [ ] Módulo/domínio impactado identificado

## Checklist técnico obrigatório
- [ ] Respeita arquitetura por domínio (`Domain/Application/Infrastructure/Http`)
- [ ] Mantém escopo multi-tenant por `contractor_id`
- [ ] Controller está fino (sem regra pesada)
- [ ] Escrita com efeito colateral em `Action`
- [ ] Leitura complexa em `Query`
- [ ] Payload Inertia via `Resource/Presenter/DTO`
- [ ] Não expõe dados sensíveis
- [ ] Textos funcionais em pt-BR e UTF-8
- [ ] Testes críticos incluídos/atualizados

## Testes executados
- [ ] `composer lint`
- [ ] `composer test`
- [ ] `npm run lint`
- [ ] `npm run build`

## Riscos e plano de rollback
- [ ] Riscos mapeados
- [ ] Plano de rollback descrito

## Evidências
- [ ] Prints/GIFs (quando há impacto visual)
- [ ] Logs/saídas relevantes (quando há impacto técnico)
