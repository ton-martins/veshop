# Veshop - Requisitos do Produto e da Plataforma

VersÃ£o: v2.5  
Ãšltima atualizaÃ§Ã£o: 18/03/2026

## 1. Objetivo

Consolidar, em um Ãºnico lugar:

1. o que jÃ¡ estÃ¡ validado no sistema;
2. o que estÃ¡ parcialmente validado e precisa fechamento;
3. o que ainda estÃ¡ pendente de desenvolvimento.

Este documento Ã© a referÃªncia oficial para priorizaÃ§Ã£o do backlog.

## 2. Escopo jÃ¡ validado no sistema

### 2.1 Base de plataforma

- Stack: Laravel + Inertia + Vue.
- Multi-tenant por `contractor_id`.
- Perfis `master` e `admin`.
- RelaÃ§Ã£o N:N usuÃ¡rio/contratante.
- Login com 2FA.
- Filas separadas (`emails`, `exports`, `notifications`).

### 2.2 MÃ³dulo master e governanÃ§a

- CRUD de usuÃ¡rios, contratantes e planos.
- CatÃ¡logo de mÃ³dulos com habilitaÃ§Ã£o por contratante.
- Arquitetura modular por `business_niche`, `business_type` e `modules`.

### 2.3 Admin comercial

- Dashboard.
- CRUD de produtos, categorias, clientes e fornecedores.
- Pedidos com aÃ§Ãµes: confirmar, rejeitar, marcar pago e cancelar.
- PDV funcional com caixa e movimentaÃ§Ã£o de estoque.
- Financeiro com abas e gestÃ£o de gateways/mÃ©todos.
- PÃ¡gina de Manuais com guia de integraÃ§Ã£o do Mercado Pago.

### 2.4 Loja pÃºblica do contratante

- Loja por slug (`/shop/{slug}`) e pÃ¡gina pÃºblica de produto.
- Busca, filtros, ordenaÃ§Ã£o, carrinho e favoritos.
- Cadastro/login de cliente por loja.
- Conta do cliente com perfil, pedidos, favoritos e notificaÃ§Ãµes.
- VerificaÃ§Ã£o de e-mail no fluxo de cliente da loja.
- Fallback de identidade visual da loja: quando nao houver logo valido, exibir iniciais com a cor primaria do contratante em login, cadastro e demais telas publicas da loja.

### 2.5 Fluxo Pix (validado)

- Checkout com `idempotency_key` para evitar duplicidade.
- CriaÃ§Ã£o de `payment_intent` Pix (Mercado Pago) com persistÃªncia de QR/ticket.
- ExibiÃ§Ã£o de cobranÃ§a Pix no pÃ³s-checkout da loja (QR + copia e cola + link).
- Endpoint seguro de status de cobranÃ§a por pedido:
  - `GET /shop/{slug}/checkout/pagamento/{sale}`
- Acompanhamento de cobranÃ§a Pix em "Minha conta > Meus pedidos" com atualizaÃ§Ã£o de status.
- Webhook com deduplicaÃ§Ã£o por recibo (`payment_webhook_receipts`).

### 2.6 Gateways (validado)

- Provedores suportados no MVP: `manual` e `mercado_pago`.
- Bloqueio de provedores nÃ£o suportados na validaÃ§Ã£o backend.
- Teste de conexÃ£o do gateway Mercado Pago no Financeiro:
  - valida token com chamada real (`/users/me`);
  - feedback na UI;
  - atualizaÃ§Ã£o de `last_health_check_at`.

### 2.7 Qualidade jÃ¡ coberta

- Testes de feature para checkout Pix, webhook, conta da loja e conexÃ£o de gateway.
- Build frontend validado apÃ³s as mudanÃ§as recentes.

## 3. Matriz executiva de status (14 frentes)

| ID | Frente | Status atual | Validado | Pendente principal | Prioridade |
|---|---|---|---|---|---|
| 1 | Login/cadastro da loja + UX de navegacao + segmentacao de layouts | Parcial | Fluxo funcional de autenticacao, conta da loja e fallback visual de logo (iniciais + cor do contratante) | Padronizacao completa UX/UI por nicho e jornadas com modais estrategicos | Alta |
| 2 | Pedidos online completos + QR-code + central de pedidos | Implementado (MVP) | Checkout Pix com QR/copia e cola + status no pÃ³s-checkout e na conta | Evoluir para central Ãºnica de pedidos e automaÃ§Ãµes operacionais | CrÃ­tica |
| 3 | E-mail de verificaÃ§Ã£o em produÃ§Ã£o + endereÃ§o obrigatÃ³rio | Implementado (com monitoramento) | Envio estabilizado + endereÃ§o obrigatÃ³rio no cadastro da loja | Monitoramento/alerta operacional contÃ­nuo de fila e e-mail | Alta |
| 4 | EstratÃ©gia de gateways (Mercado Pago e outros) | Parcial avanÃ§ado | MVP manual + Mercado Pago + webhook + teste de conexÃ£o | Expandir alÃ©m de Pix (cartÃ£o/parcelado) e polÃ­tica de novos gateways | Alta |
| 5 | NotificaÃ§Ãµes admin (in-app + e-mail + WhatsApp) | Parcial | In-app ativo para pedidos/relatÃ³rios | Canais e-mail/WhatsApp com preferÃªncias e auditoria | Alta |
| 6 | Vendas e estoque (mÃ³dulo completo) | Parcial | PDV e baixa de estoque no fluxo de venda | Entradas, ajustes, inventÃ¡rio e reconciliaÃ§Ã£o operacional completa | Alta |
| 7 | Unificar configuraÃ§Ãµes da loja virtual | Pendente | Base existente em pÃ¡ginas separadas | Consolidar configuraÃ§Ãµes em uma experiÃªncia Ãºnica | MÃ©dia |
| 8 | Contas a pagar/receber/pagamentos com documentos e alertas | Parcial | GestÃ£o de gateways/mÃ©todos pronta | LanÃ§amentos financeiros, anexos, lembretes e conciliaÃ§Ã£o | Alta |
| 9 | RelatÃ³rios assÃ­ncronos em PDF e Excel | Parcial | Export assÃ­ncrona de vendas em CSV | PDF/Excel, catÃ¡logo completo e envio automatizado | MÃ©dia |
| 10 | AdaptaÃ§Ã£o do nicho serviÃ§os por cenÃ¡rio real | Parcial | Base modular por nicho/tipo pronta | Menus e pÃ¡ginas dinÃ¢micas por tipo de serviÃ§o | CrÃ­tica |
| 11 | Centralizar mÃ³dulos na pÃ¡gina de planos | Parcial | Planos e mÃ³dulos jÃ¡ existem | Tornar plano a fonte Ãºnica de permissÃµes com regra de override | Alta |
| 12 | CorreÃ§Ãµes de login/2FA em produÃ§Ã£o | Implementado (estÃ¡vel) | Fluxo estabilizado nos cenÃ¡rios cobertos | Monitoramento preventivo de regressÃ£o | Alta |
| 13 | Termos e polÃ­tica + consentimento no login | Pendente | Escopo definido | Implementar pÃ¡ginas legais e consentimento no login | MÃ©dia |
| 14 | SeguranÃ§a e isolamento multi-tenant (hardening) | Parcial (alto impacto) | Auditoria inicial e reforÃ§o de escopo em rotas crÃ­ticas | Hardening completo, revisÃ£o de payloads e trilha contÃ­nua | CrÃ­tica |

## 4. PendÃªncias por frente (resumo objetivo)

### 4.1 Frentes crÃ­ticas (prioridade imediata)

- Frente 2: evoluÃ§Ã£o pÃ³s-MVP da central de pedidos unificada e automaÃ§Ãµes operacionais.
- Frente 10: especializaÃ§Ã£o real do nicho serviÃ§os por tipo de negÃ³cio.
- Frente 14: hardening de seguranÃ§a multi-tenant ponta a ponta.

### 4.2 Frentes altas (fase seguinte)

- Frente 4: ampliar Mercado Pago para cartÃ£o/parcelado.
- Frente 8: contas a pagar/receber completas com documentos e alertas.
- Frente 5: canais de notificaÃ§Ã£o admin por e-mail/WhatsApp.
- Frente 6: ciclo completo de estoque (entradas, ajustes, inventÃ¡rio).
- Frente 11: centralizaÃ§Ã£o final de mÃ³dulos por plano.

### 4.3 Frentes mÃ©dias (organizaÃ§Ã£o e governanÃ§a)

- Frente 7: unificaÃ§Ã£o da configuraÃ§Ã£o da loja virtual.
- Frente 9: evoluÃ§Ã£o de relatÃ³rios para PDF/Excel.
- Frente 13: termos de uso/polÃ­tica e consentimento de login.

## 5. SequÃªncia recomendada de desenvolvimento

1. Iniciar hardening multi-tenant (Frente 14) com checklist de seguranÃ§a por release.
2. Evoluir gateway para meios alÃ©m de Pix no provider homologado (Frente 4).
3. Concluir financeiro funcional (Frente 8) e evoluir estoque completo (Frente 6).
4. Fechar especializaÃ§Ã£o do nicho serviÃ§os (Frente 10) e centralizaÃ§Ã£o de mÃ³dulos por plano (Frente 11).
5. Evoluir pÃ³s-MVP a central unificada de pedidos com automaÃ§Ãµes (Frente 2).


## 6. CritÃ©rios de aceite (DoD)

Todo item concluÃ­do deve cumprir:

1. testes de unidade/feature cobrindo regra crÃ­tica;
2. validaÃ§Ã£o de autorizaÃ§Ã£o e escopo por contratante;
3. rastreabilidade mÃ­nima por logs/auditoria;
4. UX consistente em desktop e mobile;
5. textos em portuguÃªs brasileiro e codificaÃ§Ã£o UTF-8.

## 7. Resumo executivo

O Veshop jÃ¡ tem base sÃ³lida e funcionalidades crÃ­ticas validadas em checkout Pix, conta do cliente, webhook e onboarding tÃ©cnico do gateway Mercado Pago. O backlog pendente estÃ¡ concentrado em trÃªs eixos: central de pedidos, hardening de seguranÃ§a multi-tenant e evoluÃ§Ã£o funcional dos mÃ³dulos financeiro/serviÃ§os para escala.
