Veshop - Requisitos do Produto e da Plataforma
==============================================

Versao: v1.0 (base para abertura do novo projeto Laravel)

1. Objetivo do Produto
----------------------
Construir um SaaS web multiempresa para gestao completa de varejos e comercios, com foco em:
- Operacao diaria (cadastros, estoque, pedidos, vendas, financeiro).
- Controle de caixa e faturamento.
- Catalogo online da loja.
- Conversao via WhatsApp.

2. Perfil de Cliente (ICP)
--------------------------
Segmentos alvo iniciais:
- Loja de roupas
- Bazar
- Mercado de bairro
- Auto-eletrica
- Outros pequenos e medios comercios

Faixa de porte:
- 1 a 10 lojas por empresa
- 2 a 50 usuarios internos

3. Proposta de Valor
--------------------
- Sistema unico para operacao e financeiro.
- Implantacao rapida por nicho (templates).
- Catalogo online pronto para vender.
- Integracao com WhatsApp para gerar pedidos e atendimento.

4. Escopo Funcional
-------------------
4.1 Fundacao (obrigatorio)
- Cadastro de empresa (tenant) e plano.
- Cadastro de nicho no onboarding.
- Usuarios, papeis e permissoes.
- Configuracoes da empresa (logo, endereco, moeda, fuso, impostos base).
- Autenticacao de dois fatores (app autenticador) obrigatoria para acesso ao sistema.

Perfis iniciais (v1):
- `master` (suporte): acesso total.
- `admin` do contratante: administrador da empresa cadastrada.

Politica de cadastro:
- Cadastro publico de novos usuarios/empresas desativado.
- Apenas usuario `master` cadastra novos clientes/contratantes no sistema.

4.2 Cadastros
- Clientes.
- Fornecedores.
- Produtos e servicos.
- Categorias, marcas e unidades.
- Variacoes (tamanho, cor, voltagem, etc).

4.3 Estoque
- Estoque por produto e por loja.
- Movimentacoes (entrada, saida, ajuste, transferencia).
- Custo medio.
- Alerta de estoque minimo.

4.4 Vendas e Pedidos
- Orcamento e pedido.
- Conversao de orcamento em venda.
- Status do pedido (aberto, pago parcial, pago, cancelado, entregue).
- Desconto, frete e observacoes.
- Impressao e envio de comprovante.

4.5 Financeiro
- Contas a receber (parcelas, vencimento, recebimento parcial, juros/multa).
- Contas a pagar.
- Fluxo de caixa diario.
- Centro de custo (fase 2, opcional no MVP).
- DRE simplificada (receita, custo, despesa, margem).

4.6 Faturamento
- Emissao e controle de faturamento da venda.
- Status fiscal por documento.
- Preparado para integracao fiscal futura (NFC-e/NF-e por adaptador).

4.7 Catalogo Online
- Vitrine publica por loja com URL amigavel.
- Exibicao de produtos, preco, estoque disponivel e destaque.
- Busca por nome e categoria.
- Carrinho leve (fase 2) ou pedido simplificado (MVP).

4.8 Integracao WhatsApp
- Botao "Comprar no WhatsApp" no catalogo.
- Link `wa.me` com mensagem pre-preenchida contendo itens e total.
- Registro da origem (catalogo, campanha, produto) para analise de conversao.
- Evolucao fase 2: API oficial para templates e mensagens automatizadas.

4.9 Relatorios e Dashboard
- Vendas por periodo.
- Ticket medio.
- Produtos mais vendidos.
- Inadimplencia.
- Fluxo de caixa previsto x realizado.

4.10 Administracao SaaS
- Gestao de planos e limites (usuarios, lojas, produtos, pedidos/mes).
- Assinatura e status de cobranca.
- Auditoria de operacoes criticas.

5. Regras de Negocio Principais
-------------------------------
- Todo dado de negocio pertence a um tenant (`tenant_id` obrigatorio).
- Cada usuario acessa apenas tenants autorizados.
- Venda aprovada baixa estoque automaticamente.
- Cancelamento de venda estorna estoque e ajusta financeiro.
- Pedido com pagamento parcial permanece em aberto ate quitacao.
- Registros financeiros nao podem ser excluidos fisicamente; usar cancelamento/estorno.
- Acoes sensiveis devem gerar evento de auditoria.

6. Requisitos Nao Funcionais
----------------------------
- Seguranca:
  - RBAC por papel.
  - Logs de auditoria.
  - Criptografia de segredos e dados sensiveis.
  - Boas praticas LGPD (base legal, minimizacao, exclusao sob solicitacao).
  - O desenvolvimento deve seguir o principio "secure by default" em backend, frontend e infraestrutura.
  - Toda nova feature deve passar por validacao de autenticacao, autorizacao, validacao de entrada e auditoria quando aplicavel.
- Performance:
  - Listagens paginadas.
  - Consultas indexadas por tenant e datas.
  - Filas para tarefas pesadas (notificacao, integracoes, relatorios).
- Confiabilidade:
  - Backups diarios.
  - Jobs com retry e dead-letter logico.
  - Monitoramento de filas e erros.
- Escalabilidade:
  - Arquitetura modular com monolito bem organizado.
  - Eventos de dominio para desacoplar modulos.
- Padrao de idioma e codificacao:
  - Textos da plataforma (UI, emails, mensagens de validacao e documentacao funcional) devem ser em portugues do Brasil (`pt-BR`).
  - Arquivos de codigo e conteudo textual devem usar codificacao UTF-8.

7. Stack Tecnologica Recomendada
--------------------------------
Decisao:
- Sim, seguir com Laravel + Vue + Inertia.

Stack base:
- Backend: Laravel 12 + PHP 8.4.
- Frontend: Vue 3 + Inertia v2 + TypeScript.
- UI: Tailwind CSS.
- Banco: PostgreSQL 16 (ou MySQL 8.4 se preferencia operacional atual for MySQL).
- Cache/fila: Redis + Laravel Horizon.
- Realtime (quando necessario): Laravel Reverb.
- Armazenamento de arquivos: S3 compativel (ou disco local no inicio).
- Testes: PHPUnit/Pest + testes de feature por modulo.

Por que essa stack:
- Alta produtividade para MVP com uma unica base de codigo.
- Menor custo de manutencao no inicio vs separar API + SPA.
- Escala gradual sem reescrever arquitetura.

8. Arquitetura de Dominio (modulos)
-----------------------------------
- Core SaaS: tenant, usuarios, papeis, planos, assinatura.
- Catalogo: produtos, categorias, variacoes, preco.
- Estoque: saldos, movimentos, inventario.
- Comercial: orcamentos, pedidos, vendas.
- Financeiro: contas a pagar/receber, caixa, conciliacao.
- Omnichannel: vitrine online, WhatsApp, campanhas.
- Analytics: dashboard e relatorios.

9. Modelagem Inicial (entidades)
--------------------------------
Entidades obrigatorias da v1:
- tenants
- tenant_users
- roles
- permissions
- customers
- suppliers
- stores
- products
- product_variants
- stock_items
- stock_movements
- quotes
- orders
- order_items
- receivables
- payable_accounts
- cash_entries
- invoices
- catalog_pages
- whatsapp_leads
- audit_logs

Indices minimos:
- (`tenant_id`, `created_at`) para tabelas transacionais.
- (`tenant_id`, `status`) para pedidos e financeiro.
- (`tenant_id`, `sku`) unico quando sku existir.

10. Roadmap de Entrega
----------------------
Fase 0 - Fundacao (2 semanas)
- Setup projeto Laravel novo (`veshop`), auth, tenant base, RBAC, layout base.

Fase 1 - MVP Comercial + Financeiro (6 a 8 semanas)
- Cadastros, estoque, pedidos, contas a receber/pagar, dashboard base.

Fase 2 - Catalogo + WhatsApp (3 a 4 semanas)
- Vitrine publica, `wa.me`, tracking de lead e origem.

Fase 3 - Escala e Diferenciais (4 semanas)
- Realtime, relatorios avancados, automacoes e inicio de integracao fiscal.

11. Criterios de Pronto para Lancamento (MVP)
---------------------------------------------
- Onboarding completo de tenant em menos de 10 minutos.
- Venda completa com impacto em estoque e financeiro sem inconsistencias.
- Fluxo de contas a pagar/receber com baixa parcial e total.
- Catalogo publico funcional com CTA para WhatsApp.
- Dashboard com KPIs minimos validos.
- Suite de testes para fluxos criticos e sem falhas.

12. Pendencias de Decisao (antes de codar)
------------------------------------------
- Banco padrao: PostgreSQL ou MySQL.
- Gateway de pagamento para assinatura SaaS.
- Provedor fiscal para fase 3.
- Politica de precificacao (por usuario, por loja, por volume, hibrido).
- Escolha final da identidade de marca:
  - Nome principal: Veshop.
  - Subtitulo recomendado: "Veshop Gestao" ou "Veshop ERP".
