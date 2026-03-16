# Veshop - Requisitos do Produto e da Plataforma

Versão: v1.6
Última atualização: 16/03/2026

## 1. Visão do produto

O Veshop é um SaaS multiempresa para gestão operacional de contratantes, com foco em execução diária rápida para:
- Comércio: lojas, bazares, mercados, padarias e similares.
- Serviços: contabilidade, barbearia, autoelétrica e similares.

Objetivos de produto:
- Centralizar operação comercial e de serviços em uma única plataforma.
- Reduzir retrabalho entre vendas, estoque, financeiro e atendimento.
- Permitir identidade visual por contratante.
- Entregar experiência consistente em desktop, tablet e celular.

## 2. Status atual (implementado)

### 2.1 Base da plataforma

- Stack: Laravel + Inertia + Vue.
- Estratégia multiempresa por `contractor_id`.
- Perfis ativos: `master` e `admin`.
- Relação N:N entre usuários e contratantes (`contractor_user`).
- Fluxo autenticado com 2FA.
- Testes configurados para SQLite em memória.

### 2.2 Módulo Master

- CRUD de usuários.
- Upload de avatar de usuário com substituição do arquivo anterior.
- CRUD de contratantes.
- CRUD de planos.
- Planos segmentados por nicho:
- `commercial` (Comércio)
- `services` (Serviços)
- Branding da plataforma (cores, logo, ícone e imagens da landing).

### 2.3 Módulo Admin (Comércio)

- Dashboard operacional.
- CRUD de produtos.
- CRUD de categorias.
- CRUD de clientes.
- CRUD de fornecedores.
- Página de Contas com abas de pagar/receber/pagamentos.
- CRUD de gateways e formas de pagamento na aba Pagamentos.

### 2.4 PDV (MVP funcional)

- Abertura e fechamento de caixa.
- Controle de sessão de caixa e movimentos.
- Carrinho PDV com busca e categorias.
- Cadastro rápido de cliente no PDV.
- Top 12 produtos prioritários no PDV.
- Fechamento de venda com desconto/acréscimo, forma de pagamento e troco.
- Persistência transacional de `sales`, `sale_items` e `sale_payments`.
- Baixa automática de estoque e movimentação de inventário.
- Registro automático de entrada de caixa para venda em dinheiro.
- Cálculo do dashboard PDV ajustado para dia de negócio no fuso do contratante (vendas do dia e ticket médio).

### 2.5 Loja virtual pública do contratante

- Loja por rota pública `/shop/{slug}`.
- Página de produto por `/shop/{slug}/produto/{product}`.
- Busca, filtro por categoria, ordenação e paginação.
- Carrinho e favoritos.
- Favoritos persistidos por cliente da loja (`shop_customer_favorites`) e exibidos na conta.
- Checkout MVP (ainda com limitações do fluxo transacional completo).

### 2.6 Identidade visual e experiência da loja

- Logo da loja pública prioriza o logo do contratante.
- Sem logo/ícone, exibe iniciais do contratante com cor de fundo da marca.
- Separação de identidade:
- Portal do sistema mantém nome do sistema.
- Loja pública usa identidade do contratante.

### 2.7 Conta do cliente da loja pública

- Cadastro e login isolados por contratante/loja.
- Cliente da loja não acessa o portal administrativo do sistema.
- Verificação de e-mail no fluxo de cliente da loja (com envio e confirmação).
- Página “Minha conta” com:
- Atualização de telefone e endereço.
- Consulta de CEP com ViaCEP.
- Lista de favoritos.
- Histórico de pedidos.
- Notificações da conta.

### 2.8 Cadastro de clientes e fornecedores (nova entrega)

- Máscara/regex para documento (CPF/CNPJ).
- Máscara/regex para telefone no padrão `(11) 99999-9999`.
- Wizard de endereço nos modais de cliente e fornecedor.
- Campo CEP com máscara e integração ViaCEP.
- Preenchimento manual como fallback quando ViaCEP falha.
- Campo UF com seleção de estados brasileiros.
- Mesma padronização aplicada no cadastro público da loja e no perfil do cliente da loja.

## 3. Pendências (o que ainda falta)

### 3.1 P0 - Fechamento do pedido online transacional

- Finalizar criação de pedido no backend no checkout público (não só fluxo parcial).
- Fechar lifecycle de pedido ponta a ponta:
- `novo`
- `aguardando_confirmação`
- `confirmado`
- `rejeitado`
- `aguardando_pagamento`
- `pago`
- `cancelado`
- Ação do admin para aceitar/rejeitar pedido com motivo.

### 3.2 P1 - Pagamento online real

- Integração com gateway (ex.: Mercado Pago).
- PIX dinâmico por transação.
- Webhook para confirmação automática.
- Tratamento de estorno/cancelamento.

### 3.3 P2 - Conciliação operacional completa

- Conectar pedido, venda, estoque e financeiro ponta a ponta.
- Evoluir módulos de pedidos, estoque e relatórios para operação completa.

### 3.4 P3 - Publicação por domínio/subdomínio

- Resolver contratante por host (subdomínio/domínio customizado).
- Onboarding de domínio com validação DNS, SSL e redirecionamentos.

### 3.5 P4 - Módulo de serviços (transacional)

- Concluir catálogo de serviços com backend final.
- Concluir ordens de serviço com status e histórico.
- Concluir agenda operacional integrada.

### 3.6 P5 - Auditoria e governança

- Trilhas de auditoria para ações críticas.
- Padronização de eventos auditáveis por entidade.
- Reforço de validações tenant-first em todos os fluxos.

## 4. Próxima entrega recomendada

Ordem sugerida:
1. Fechar checkout transacional da loja pública com pedido persistido.
2. Implementar aprovação/rejeição de pedido pelo admin com notificação.
3. Integrar pagamento online (PIX/link) com webhook de confirmação.
4. Concluir conciliação automática entre pedido, venda, estoque e financeiro.
