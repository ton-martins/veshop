# Veshop - Guia Mestre

Versão: v3.3  
Última atualização: 18/03/2026

## 1. Objetivo

Este é o documento único de referência do Veshop para:

1. visão atual do produto;
2. padrão de arquitetura, segurança e qualidade;
3. backlog priorizado com status real;
4. sequência de implementação para reduzir risco e retrabalho.

## 2. Regras obrigatórias da plataforma

### 2.1 Idioma e codificação

- Todo texto funcional da aplicação deve estar em português (pt-BR).
- Arquivos e conteúdo devem ser mantidos em UTF-8.
- Não publicar telas com texto quebrado, sem acentuação correta ou mistura de idioma sem necessidade.

### 2.2 Multi-tenant e autorização

- Toda regra de negócio deve respeitar `contractor_id`.
- Nenhum usuário pode acessar dados de outro contratante sem permissão explícita.
- Rotas, policies e queries devem validar escopo de contratante.
- Módulos devem respeitar plano e permissões habilitadas.

### 2.3 Segurança e dados no frontend

- Evitar exposição desnecessária de dados no payload inicial do Inertia.
- Não incluir segredo, token, senha ou dado sensível em logs.
- Eventos críticos devem gerar trilha de auditoria.

## 3. O que já está validado no sistema

### 3.1 Base de produto e operação

- Painel admin multi-tenant com contexto de contratante.
- Loja virtual por contratante (`/shop/{slug}`) com catálogo, carrinho e checkout.
- Fluxo de pedidos com pipeline operacional no admin.
- Integração inicial de pagamentos com Mercado Pago (MVP Pix) e webhook.
- Fluxo de checkout manual previsto para cenários sem gateway ativo.

### 3.2 UX/UI já implementada e padronizada

- Menu lateral em padrão profissional, mantendo identidade do Veshop.
- Header/footer do menu harmonizados com o mesmo padrão visual.
- Uso de cor do contratante no menu com fallback para cor secundária do sistema.
- Página de Contas com abas no topo e padrão replicado para outras telas principais.
- Ajustes de layout para manter consistência em desktop e mobile.

### 3.3 Loja virtual

- Login e cadastro do cliente alinhados ao padrão visual do Veshop, com identidade do contratante.
- Recuperação de senha do cliente criada no mesmo padrão visual.
- Card de verificação de e-mail ajustado para o mesmo layout.
- Fallback de logo por iniciais do contratante aplicado quando não houver logo.
- Modal de favoritos ajustado para padrão de modal lateral, similar ao carrinho.
- Cards de produto com cursor de ação (`pointer`) no hover.

### 3.4 Pedidos e visão geral

- Página de pedidos com abas de pipeline em padrão visual do sistema.
- Ações de pedido no card/lista com melhor organização para mobile.
- Modal de detalhes de pedido compartilhado entre tela de pedidos e visão geral.
- Visão Geral com abas no topo (`Loja virtual` e `PDV`) e ícones.
- Sessões de pedidos recentes e vendas recentes usando o mesmo modal de detalhes.
- Edição de pedido (MVP) no admin com modal dedicado.
- Auditoria de segurança para edição de pedido (`order.updated.admin`).
- Página `Vendas` criada para o PDV no mesmo padrão visual da página de pedidos (filtros, abas, cards, tabela e modal de detalhes).

### 3.5 Paginação já implementada

Paginação backend/frontend confirmada em:

- Pedidos: `20` por página.
- Notificações: `20` por página.
- Produtos: `10` por página.
- Categorias: `10` por página.
- Clientes: `10` por página.
- Fornecedores: `10` por página.
- Usuários: `10` por página.
- Catálogo de serviços: `10` por página.
- Planos (master): `10` por página.
- Contratantes (master): `10` por página.

### 3.6 E-mail e autenticação

- Envio de e-mail de verificação da loja corrigido em produção (SMTP/porta/esquema).
- Template do e-mail de verificação traduzido para pt-BR.
- Correções no fluxo de erro de login para evitar modal branco/iframe indevido.
- Endereço obrigatório no cadastro público da loja (cliente final), conforme regra de negócio.

### 3.7 Segurança técnica já aplicada

- Middleware de escopo de contratante em rotas críticas.
- Redução de exposição de dados no payload compartilhado do Inertia.
- Base de auditoria de segurança consolidada no guia mestre.

### 3.8 Financeiro (Fase 1)

- Estrutura de dados de lançamentos financeiros criada (`financial_entries`) com soft delete e índices.
- CRUD de contas a pagar/receber implementado no admin com upload de documento.
- Listagem com abas, filtros por busca/status e paginação (`20` por página).
- Regras de segurança aplicadas: escopo por `contractor_id`, validação de forma de pagamento do contratante e bloqueio cross-tenant.
- Testes de feature do fluxo financeiro adicionados e validados.

## 4. Backlog priorizado (status real)

| ID | Item | Status | Prioridade |
| --- | --- | --- | --- |
| 1 | UX/UI da loja virtual (feedback visual, carrinho mobile, consistência dos modais) | Concluído (MVP) | Crítica |
| 2 | Edição de pedido/venda no admin com auditoria | Parcial (pedido MVP) | Alta |
| 3 | Módulo financeiro (contas a pagar/receber + anexos + status + observações) | Parcial (Fase 1 concluída) | Crítica |
| 4 | Finalizar páginas de estoque e pedidos + botão "Novo pedido" | Parcial (página Vendas concluída) | Alta |
| 5 | Produto com até 5 fotos + variações + promoção (%) | Pendente | Alta |
| 6 | Categorias com subcategorias | Pendente | Alta |
| 7 | Mover "Manuais" para item independente no menu | Pendente | Média |
| 8 | Checkout dual (Mercado Pago integrado + fluxo manual) com padrão único | Parcial | Crítica |
| 9 | Notificações in-app + e-mail + estudo e viabilidade WhatsApp | Parcial | Alta |
| 10 | Taxa por forma de pagamento (PDV e loja virtual) | Pendente | Alta |
| 11 | Evitar renderização inicial quebrada nas páginas de login/cadastro da loja | Parcial | Alta |
| 12 | Páginas legais (Termos de Uso e Política de Privacidade LGPD) | Pendente | Alta |

## 5. Diretrizes de arquitetura para os pendentes críticos

### 5.1 ID 2 - Edição de pedido/venda

- MVP de pedido já implementado.
- Próximos passos:
  - expandir para edição de venda (PDV);
  - definir campos editáveis por status;
  - manter trilha auditável por campo alterado.

### 5.2 ID 3 - Financeiro

Implementar núcleo com:

- contas a pagar;
- contas a receber;
- campos mínimos: valor, vencimento, status, observações;
- upload de documento por lançamento;
- histórico de alterações.

OCR (estudo):

- fase inicial assistida (pré-preenchimento com confirmação do usuário);
- sem automação cega na primeira versão.

### 5.3 ID 8 - Checkout dual

Padronizar dois modos por contratante:

- `integrado`: pagamento via gateway (Mercado Pago);
- `manual`: pedido sem pagamento imediato, contratante conclui cobrança fora da plataforma.

Regras obrigatórias:

- status de pedido e pagamento claros e auditáveis;
- mesma experiência operacional para suporte e para o contratante;
- sem ambiguidade no painel sobre "pedido criado" x "pedido pago".

## 6. Padrão de qualidade para toda entrega

Toda implementação só é considerada concluída se tiver:

1. validação de escopo multi-tenant e autorização;
2. textos em pt-BR (UTF-8);
3. tratamento de erro amigável para usuário;
4. logs sem dados sensíveis;
5. atualização deste arquivo (`docs/veshop.md`) com status real.

## 7. Próximo item recomendado de desenvolvimento

Próximo passo: **ID 3 - Módulo financeiro (Fase 2: OCR assistido + alertas)**.

Motivo:

1. Fase 1 já entregou o núcleo operacional (CRUD + anexos + filtros + paginação);
2. OCR assistido aumenta produtividade sem comprometer segurança de dados;
3. alertas financeiros conectam o módulo a notificações e rotina de cobrança.

Escopo objetivo da próxima entrega:

1. upload de documento com pré-extração OCR assistida (sugestão de campos, confirmação manual obrigatória);
2. trilha de auditoria por campo ajustado após OCR;
3. lembretes financeiros in-app (vencimento próximo e vencido), sem WhatsApp nesta fase.
