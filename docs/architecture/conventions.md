# Convenções Oficiais de Código

## Convenções de backend
- Nomear actions por intenção de escrita: `CreateProductAction`, `CheckoutAction`.
- Nomear queries por intenção de leitura: `ListProductsQuery`, `BuildPdvPageQuery`.
- Nomear presenters/resources com sufixo explícito: `ProductResource`, `SalePresenter`.
- Evitar dependência direta de infraestrutura no controller.

## Convenções de frontend
- Separar `Page`, `Section`, `Form`, `Table`, `Card`, `Modal`, `Drawer`, `Widget`.
- Reuso obrigatório de `resources/js/app/shared/ui`.
- Lógica compartilhável em composables de `resources/js/app/shared/composables`.

## Texto e codificação
- Conteúdo funcional em português (pt-BR).
- Arquivos de texto em UTF-8.

## Rotas
- Separar rotas por área e domínio em:
  - `routes/admin/*.php`
  - `routes/public/*.php`
  - `routes/master/*.php`

## Testes obrigatórios por módulo
- Fluxo principal.
- Autorização.
- Escopo por contratante.
- Payload mínimo.
- Regressão crítica.
