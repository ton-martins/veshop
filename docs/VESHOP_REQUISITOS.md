# Veshop - Requisitos do Produto e da Plataforma

Versão: v1.5  
Última atualização: 15/03/2026

## 1. Visão do produto

O Veshop é um SaaS multiempresa para gestão operacional de contratantes, com foco em uso diário e execução rápida.

Objetivos:
- Centralizar operação comercial e de serviços em uma única plataforma.
- Reduzir retrabalho entre vendas, estoque, financeiro e atendimento.
- Permitir identidade visual por contratante.
- Entregar experiência consistente em desktop, tablet e celular.

## 2. Estado atual do sistema (implementado)

### 2.1 Base da plataforma

- Arquitetura monolítica em Laravel + Inertia + Vue.
- Estratégia multiempresa por `contractor_id` (contractor-first).
- Papéis ativos:
- `master`
- `admin`
- Relação N:N entre usuários e contratantes (`contractor_user`).
- Autenticação com 2FA obrigatório no fluxo autenticado.
- Testes bloqueados para uso exclusivo de SQLite em memória:
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`
- Trava aplicada no `AppServiceProvider`, `tests/TestCase.php`, `.env.testing` e `phpunit.xml`.

### 2.2 Módulo Master

- CRUD de usuários (modal padrão do sistema).
- Upload de avatar de usuário (`png`, `jpg`, `jpeg`) com substituição do arquivo anterior.
- CRUD de contratantes.
- CRUD de planos.
- Planos segmentados por nicho:
- `commercial` (Comércio)
- `services` (Serviços)
- Página de branding da plataforma (cores, logo, ícone e imagens da landing).

### 2.3 Módulo Admin (Comércio)

- Dashboard com visão operacional e PDV.
- CRUD de Produtos.
- CRUD de Categorias.
- CRUD de Clientes.
- CRUD de Fornecedores.
- Página única de Contas com abas:
- Contas a pagar
- Contas a receber
- Pagamentos
- Aba Pagamentos com CRUD funcional de:
- Gateways de pagamento (`payment_gateways`)
- Formas de pagamento (`payment_methods`)
- Página de pedidos (camada de interface disponível, sem fluxo completo de e-commerce).
- Página de estoque (camada de interface disponível, sem ciclo completo de inventário geral).
- Página de relatórios (camada de interface disponível).

### 2.4 PDV (MVP transacional já funcional)

- Abertura e fechamento de caixa.
- Controle de sessão de caixa (`cash_sessions`) e movimentos (`cash_movements`).
- Carrinho no PDV com busca e categorias.
- Cadastro rápido de cliente dentro do PDV.
- Definição dos Top 12 produtos prioritários do PDV.
- Fechamento de venda com:
- Itens
- Desconto
- Acréscimo
- Forma de pagamento
- Valor pago em dinheiro e troco
- Persistência transacional de:
- `sales`
- `sale_items`
- `sale_payments`
- Baixa automática de estoque na venda.
- Geração de movimentação de estoque (`inventory_movements`) vinculada à venda.
- Registro de entrada de caixa para pagamento em dinheiro.
- Testes de fluxo PDV cobrindo abertura de caixa, venda e baixa de estoque.

### 2.5 Experiência pública (Landing + Loja)

- Landing page componentizada e responsiva.
- Sessão de planos segmentada por nicho (Comércio e Serviços).
- Loja pública por contratante via rota:
- `/shop/{slug}`
- Página de detalhes de produto:
- `/shop/{slug}/produto/{product}`
- Redirecionamento legado:
- `/catalogo/{slug}`
- `/catalogo/{slug}/produto/{product}`
- Loja pública com:
- Busca de produtos
- Filtro por categoria
- Ordenação
- Paginação
- Favoritos em `localStorage`
- Carrinho em `localStorage`
- Checkout atual via WhatsApp (MVP).

### 2.6 Padrões de UX/UI adotados

- Paginação padronizada em pt-BR.
- Tabelas com scroll horizontal interno (evita quebrar o layout da página em mobile).
- Modo de exibição lista/cards nas telas principais.
- Inputs de busca com botão de limpar (`x`) e fluxo consistente.
- Selects customizados do sistema (`UiSelect`) nas páginas migradas.
- Modais padronizados para criação/edição.
- Confirmação de exclusão no padrão anterior do sistema.
- Navegação mobile inferior no app autenticado e na loja pública.

## 3. Regras já definidas de catálogo público e domínio

- Estratégia principal de publicação: `slug.veshop.com.br`.
- Contratantes sem domínio próprio usam o padrão de subdomínio.
- Contratantes com domínio próprio poderão vincular domínio externo.
- Contratantes com loja virtual existente poderão usar Veshop como backoffice.
- Em ambiente local, a navegação atual está operando por rota (`/shop/{slug}`).

## 4. Pontos ainda pendentes (por prioridade)

### 4.1 P0 - Fechamento do fluxo de pedido online (prioridade máxima)

- Persistir pedido do catálogo público no backend (não apenas checkout por WhatsApp).
- Criar lifecycle de pedido:
- `novo`
- `aguardando_confirmação`
- `confirmado`
- `rejeitado`
- `aguardando_pagamento`
- `pago`
- `cancelado`
- Notificar admin no painel e por canal externo (ex.: WhatsApp).
- Permitir decisão do admin (aceitar/rejeitar) com motivo.
- Gerar cobrança digital para o cliente (PIX/link) após confirmação.

### 4.2 P1 - Integração real de pagamentos e conciliação

- Integrar gateways (ex.: Mercado Pago) para cobrança real.
- Gerar QR Code PIX dinâmico por transação.
- Processar webhook para confirmação de pagamento.
- Atualizar status do pedido/venda automaticamente por evento do gateway.
- Tratar estorno/cancelamento com reversões financeiras e de estoque.

### 4.3 P2 - Fechar ciclo comercial completo

- Evoluir módulo de Pedidos para operação completa no painel.
- Evoluir módulo de Estoque para entradas, saídas, ajustes e inventário geral.
- Evoluir Contas a pagar/receber para fluxo transacional completo.
- Conciliação financeira ponta a ponta (caixa, venda, contas e pagamento).
- Relatórios gerenciais e financeiros com dados consolidados reais.

### 4.4 P3 - Catálogo público multi-tenant por host

- Resolver contratante por host (subdomínio/domínio customizado) em produção.
- Fluxo de onboarding de domínio:
- Validação DNS
- SSL
- Redirecionamentos canônicos

### 4.5 P4 - Módulo de integrações

- Área dedicada de integrações por contratante.
- Gestão segura de credenciais de gateway.
- Health-check de integração.
- Integrações com e-commerce externo (sincronização de pedidos, estoque e financeiro).

### 4.6 P5 - Módulo de serviços (fluxo transacional)

- Concluir catálogo de serviços com backend final.
- Concluir ordens de serviço com status e histórico.
- Concluir agenda operacional integrada a ordens.

### 4.7 P6 - Auditoria e governança

- Implementar trilha de auditoria de ações críticas.
- Padronizar eventos auditáveis por entidade.
- Endurecer validações tenant-first em toda a aplicação.

### 4.8 P7 - Escalabilidade e operação

- Redis para cache, sessão e filas.
- Workers e jobs assíncronos de produção.
- Observabilidade (logs estruturados, métricas, alertas).
- Estratégia de backup/restore validada.
- Evolução de armazenamento de mídia para object storage quando necessário.

## 5. Decisão atual para identidade do comprador no catálogo

- No modelo atual, o cliente comprador é tratado por contratante (isolamento por loja).
- Conta global única de comprador entre múltiplas lojas ainda não foi implementada.
- Se necessário, será evoluído para modelo híbrido:
- conta global do comprador
- perfil e histórico por loja/contratante

## 6. Próxima entrega recomendada

Ordem sugerida para a próxima etapa:
1. Checkout transacional do catálogo público criando pedido no backend.
2. Fluxo de confirmação/rejeição de pedido no painel do admin.
3. Integração de pagamento com geração de PIX QR Code/link.
4. Webhook de confirmação de pagamento com atualização automática de status.
5. Conciliação entre pedido, venda, estoque e financeiro.
