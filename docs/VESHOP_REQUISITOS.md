# Veshop - Requisitos do Produto e da Plataforma

Versão: v2.4  
Última atualização: 18/03/2026

## 1. Objetivo

Consolidar, em um único lugar:

1. o que já está validado no sistema;
2. o que está parcialmente validado e precisa fechamento;
3. o que ainda está pendente de desenvolvimento.

Este documento é a referência oficial para priorização do backlog.

## 2. Escopo já validado no sistema

### 2.1 Base de plataforma

- Stack: Laravel + Inertia + Vue.
- Multi-tenant por `contractor_id`.
- Perfis `master` e `admin`.
- Relação N:N usuário/contratante.
- Login com 2FA.
- Filas separadas (`emails`, `exports`, `notifications`).

### 2.2 Módulo master e governança

- CRUD de usuários, contratantes e planos.
- Catálogo de módulos com habilitação por contratante.
- Arquitetura modular por `business_niche`, `business_type` e `modules`.

### 2.3 Admin comercial

- Dashboard.
- CRUD de produtos, categorias, clientes e fornecedores.
- Pedidos com ações: confirmar, rejeitar, marcar pago e cancelar.
- PDV funcional com caixa e movimentação de estoque.
- Financeiro com abas e gestão de gateways/métodos.
- Página de Manuais com guia de integração do Mercado Pago.

### 2.4 Loja pública do contratante

- Loja por slug (`/shop/{slug}`) e página pública de produto.
- Busca, filtros, ordenação, carrinho e favoritos.
- Cadastro/login de cliente por loja.
- Conta do cliente com perfil, pedidos, favoritos e notificações.
- Verificação de e-mail no fluxo de cliente da loja.

### 2.5 Fluxo Pix (validado)

- Checkout com `idempotency_key` para evitar duplicidade.
- Criação de `payment_intent` Pix (Mercado Pago) com persistência de QR/ticket.
- Exibição de cobrança Pix no pós-checkout da loja (QR + copia e cola + link).
- Endpoint seguro de status de cobrança por pedido:
  - `GET /shop/{slug}/checkout/pagamento/{sale}`
- Acompanhamento de cobrança Pix em "Minha conta > Meus pedidos" com atualização de status.
- Webhook com deduplicação por recibo (`payment_webhook_receipts`).

### 2.6 Gateways (validado)

- Provedores suportados no MVP: `manual` e `mercado_pago`.
- Bloqueio de provedores não suportados na validação backend.
- Teste de conexão do gateway Mercado Pago no Financeiro:
  - valida token com chamada real (`/users/me`);
  - feedback na UI;
  - atualização de `last_health_check_at`.

### 2.7 Qualidade já coberta

- Testes de feature para checkout Pix, webhook, conta da loja e conexão de gateway.
- Build frontend validado após as mudanças recentes.

## 3. Matriz executiva de status (14 frentes)

| ID | Frente | Status atual | Validado | Pendente principal | Prioridade |
|---|---|---|---|---|---|
| 1 | Login/cadastro da loja + UX de navegação + segmentação de layouts | Parcial | Fluxo funcional de autenticação e conta da loja | Padronização completa UX/UI por nicho e jornadas com modais estratégicos | Alta |
| 2 | Pedidos online completos + QR-code + central de pedidos | Implementado (MVP) | Checkout Pix com QR/copia e cola + status no pós-checkout e na conta | Evoluir para central única de pedidos e automações operacionais | Crítica |
| 3 | E-mail de verificação em produção + endereço obrigatório | Implementado (com monitoramento) | Envio estabilizado + endereço obrigatório no cadastro da loja | Monitoramento/alerta operacional contínuo de fila e e-mail | Alta |
| 4 | Estratégia de gateways (Mercado Pago e outros) | Parcial avançado | MVP manual + Mercado Pago + webhook + teste de conexão | Expandir além de Pix (cartão/parcelado) e política de novos gateways | Alta |
| 5 | Notificações admin (in-app + e-mail + WhatsApp) | Parcial | In-app ativo para pedidos/relatórios | Canais e-mail/WhatsApp com preferências e auditoria | Alta |
| 6 | Vendas e estoque (módulo completo) | Parcial | PDV e baixa de estoque no fluxo de venda | Entradas, ajustes, inventário e reconciliação operacional completa | Alta |
| 7 | Unificar configurações da loja virtual | Pendente | Base existente em páginas separadas | Consolidar configurações em uma experiência única | Média |
| 8 | Contas a pagar/receber/pagamentos com documentos e alertas | Parcial | Gestão de gateways/métodos pronta | Lançamentos financeiros, anexos, lembretes e conciliação | Alta |
| 9 | Relatórios assíncronos em PDF e Excel | Parcial | Export assíncrona de vendas em CSV | PDF/Excel, catálogo completo e envio automatizado | Média |
| 10 | Adaptação do nicho serviços por cenário real | Parcial | Base modular por nicho/tipo pronta | Menus e páginas dinâmicas por tipo de serviço | Crítica |
| 11 | Centralizar módulos na página de planos | Parcial | Planos e módulos já existem | Tornar plano a fonte única de permissões com regra de override | Alta |
| 12 | Correções de login/2FA em produção | Implementado (estável) | Fluxo estabilizado nos cenários cobertos | Monitoramento preventivo de regressão | Alta |
| 13 | Termos e política + consentimento no login | Pendente | Escopo definido | Implementar páginas legais e consentimento no login | Média |
| 14 | Segurança e isolamento multi-tenant (hardening) | Parcial (alto impacto) | Auditoria inicial e reforço de escopo em rotas críticas | Hardening completo, revisão de payloads e trilha contínua | Crítica |

## 4. Pendências por frente (resumo objetivo)

### 4.1 Frentes críticas (prioridade imediata)

- Frente 2: evolução pós-MVP da central de pedidos unificada e automações operacionais.
- Frente 10: especialização real do nicho serviços por tipo de negócio.
- Frente 14: hardening de segurança multi-tenant ponta a ponta.

### 4.2 Frentes altas (fase seguinte)

- Frente 4: ampliar Mercado Pago para cartão/parcelado.
- Frente 8: contas a pagar/receber completas com documentos e alertas.
- Frente 5: canais de notificação admin por e-mail/WhatsApp.
- Frente 6: ciclo completo de estoque (entradas, ajustes, inventário).
- Frente 11: centralização final de módulos por plano.

### 4.3 Frentes médias (organização e governança)

- Frente 7: unificação da configuração da loja virtual.
- Frente 9: evolução de relatórios para PDF/Excel.
- Frente 13: termos de uso/política e consentimento de login.

## 5. Sequência recomendada de desenvolvimento

1. Iniciar hardening multi-tenant (Frente 14) com checklist de segurança por release.
2. Evoluir gateway para meios além de Pix no provider homologado (Frente 4).
3. Concluir financeiro funcional (Frente 8) e evoluir estoque completo (Frente 6).
4. Fechar especialização do nicho serviços (Frente 10) e centralização de módulos por plano (Frente 11).
5. Evoluir pós-MVP a central unificada de pedidos com automações (Frente 2).


## 6. Critérios de aceite (DoD)

Todo item concluído deve cumprir:

1. testes de unidade/feature cobrindo regra crítica;
2. validação de autorização e escopo por contratante;
3. rastreabilidade mínima por logs/auditoria;
4. UX consistente em desktop e mobile;
5. textos em português brasileiro e codificação UTF-8.

## 7. Resumo executivo

O Veshop já tem base sólida e funcionalidades críticas validadas em checkout Pix, conta do cliente, webhook e onboarding técnico do gateway Mercado Pago. O backlog pendente está concentrado em três eixos: central de pedidos, hardening de segurança multi-tenant e evolução funcional dos módulos financeiro/serviços para escala.