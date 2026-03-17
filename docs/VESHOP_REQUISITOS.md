# Veshop - Requisitos do Produto e da Plataforma

Versão: v2.0  
Última atualização: 17/03/2026

## 1. Objetivo deste documento

Consolidar:

1. o que já está implementado no Veshop;
2. o que está parcialmente implementado e precisa fechamento técnico;
3. o que está pendente e exige decisão conjunta (produto + arquitetura + operação).

Este documento passa a ser o guia oficial para priorização e execução das próximas entregas.

## 2. Visão do produto

O Veshop é um SaaS multiempresa para gestão operacional de contratantes, com dois eixos de nicho:

- comércio (`commercial`): lojas, bazares, confeitarias, mercados e similares;
- serviços (`services`): barbearias, autoelétricas, contabilidades, oficinas e similares.

Objetivos centrais:

- centralizar operação comercial e de serviços em uma única plataforma;
- reduzir retrabalho entre pedidos, vendas, estoque, financeiro e atendimento;
- permitir identidade visual por contratante;
- manter UX consistente em desktop e mobile.

## 3. Inventário atual do sistema (implementado)

### 3.1 Base de plataforma

- Stack principal: Laravel + Inertia + Vue.
- Estratégia multiempresa por `contractor_id`.
- Perfis: `master` e `admin`.
- Relação N:N entre usuários e contratantes (`contractor_user`).
- Fluxo de autenticação com 2FA.
- Estrutura de filas com workloads separados (`emails`, `exports`, `notifications`).

### 3.2 Master e governança

- CRUD de usuários e contratantes.
- CRUD de planos com segmentação por nicho.
- Catálogo de módulos e habilitação por contratante.
- Arquitetura modular com `business_niche`, `business_type` e `modules`.
- Branding da plataforma.

### 3.3 Admin comercial

- Dashboard.
- CRUD de produtos, categorias, clientes e fornecedores.
- Pedidos com ações operacionais: confirmar, rejeitar, marcar pago e cancelar.
- Financeiro com página de Contas (abas) e gestão de gateways/métodos.
- PDV funcional (caixa, venda, movimentação de estoque e pagamentos da venda).

### 3.4 Loja pública do contratante

- Loja por slug (`/shop/{slug}`) e produto público.
- Busca, filtro, ordenação, paginação, carrinho e favoritos.
- Cadastro/login de cliente isolado por loja.
- Conta do cliente com favoritos, pedidos e notificações.
- Verificação de e-mail no fluxo de cliente da loja.

### 3.5 Relatórios e notificações

- Exportação assíncrona de vendas em CSV via job (`GenerateSalesExportJob`).
- Notificações in-app (database) para pedidos e relatórios.

### 3.6 UX/UI já evoluída

- Menu lateral principal já elevado para padrão mais profissional.
- Padrão visual da página de Contas evoluído com abas e seção de alternância lista/cards.

## 4. Matriz executiva de status (14 frentes)

| ID | Frente | Status | Prioridade |
|---|---|---|---|
| 1 | Login/cadastro da loja + UX de navegação + segmentação de layouts | Parcial | Alta |
| 2 | Fluxo de pedidos online completo + QR-code + central de pedidos | Parcial | Crítica |
| 3 | E-mail de verificação em produção + endereço obrigatório no cadastro | Parcial (com risco operacional) | Crítica |
| 4 | Estratégia de gateways de pagamento (Mercado Pago e outros) | Parcial | Alta |
| 5 | Notificações in-app + e-mail + WhatsApp para admin | Parcial | Alta |
| 6 | Módulo de vendas e estoque (evolução funcional completa) | Parcial | Alta |
| 7 | Unificar configurações da loja virtual (storefront vs branding) | Pendente | Média |
| 8 | Contas a pagar/receber/pagamentos com documentos e alertas | Parcial | Alta |
| 9 | Relatórios assíncronos em PDF e Excel | Parcial | Média |
| 10 | Adaptação por nicho de serviços e menus dinâmicos por cenário | Parcial | Crítica |
| 11 | Centralizar módulos na página de planos (fonte única de permissões) | Parcial | Alta |
| 12 | Correção de problemas de login/2FA em produção | Pendente (bug) | Crítica |
| 13 | Termos e política na landing e consentimento no login | Pendente | Média |
| 14 | Segurança e isolamento de dados multi-tenant (hardening completo) | Parcial (alto impacto) | Crítica |

Legenda de status:

- `Implementado`: pronto para produção sem lacuna funcional principal.
- `Parcial`: existe base funcional, mas faltam fechamentos técnicos de produto/operação.
- `Pendente`: não funcional ou não iniciado.

## 5. Detalhamento por frente (status, lacuna e melhor prática)

### 5.1 Frente 1 - Loja virtual (login/cadastro + UX + segmentação comercial/serviços)

Status atual:

- Parcial.
- Login/cadastro da loja já existem com identidade do contratante.
- Ainda falta padronização completa com o mesmo nível de UX do login interno do Veshop.

Lacunas:

- revisar navegação interna e trocar páginas específicas por modais quando reduzir fricção;
- fechar variação de layout por nicho de serviços com foco em agendamentos;
- consolidar biblioteca de componentes da loja por cenário.

Melhor prática recomendada:

- design system com tokens por contratante;
- arquitetura de páginas por `template` + `business_type`;
- feature flags por nicho para liberar experiências específicas sem forkar código.

### 5.2 Frente 2 - Pedidos online completos + QR-code + central de pedidos

Status atual:

- Parcial.
- Checkout já cria `sale`, `sale_items` e `sale_payments` com status inicial.
- Administração de pedidos já tem fluxo de decisão (aprovar/rejeitar/pago/cancelar).

Lacunas:

- geração real de cobrança transacional com QR dinâmico no checkout;
- idempotência de criação de cobrança e de processamento de webhook;
- centralização operacional completa de pedidos em uma visão única.

Melhor prática recomendada:

- máquina de estados de pedido/pagamento;
- `payment_intent` por transação com `idempotency_key`;
- webhook com assinatura, deduplicação e trilha de auditoria.

### 5.3 Frente 3 - E-mail de verificação em produção + endereço obrigatório

Status atual:

- Parcial com risco operacional.
- `ShopCustomer` usa verificação de e-mail e notification em fila.
- Cadastro atual da loja ainda aceita endereço opcional.

Lacunas:

- garantir worker ativo para fila de e-mails em produção;
- validar configuração SMTP real de homolog/produção;
- tornar endereço obrigatório no cadastro da loja (CEP, logradouro, número, bairro, cidade, UF).

Melhor prática recomendada:

- monitoramento de filas (`emails`, `failed_jobs`) com alertas;
- health-check de envio SMTP e logs de rejeição;
- validações de endereço obrigatórias no backend e frontend.

### 5.4 Frente 4 - Estratégia de gateway de pagamento do contratante

Status atual:

- Parcial.
- Existe estrutura para gateways e métodos de pagamento por contratante.
- Existe endpoint de webhook genérico por provider.

Lacunas:

- padronizar conectores oficiais e política de homologação;
- fechar suporte prioritário a provedores brasileiros;
- fechar requisitos de segurança operacional por gateway.

Melhor prática recomendada:

- interface única de provedor (`PaymentProviderContract`);
- primeiro conector oficial: Mercado Pago (PIX + cartão);
- fallback manual controlado por feature flag.

### 5.5 Frente 5 - Notificações admin (in-app + e-mail + WhatsApp)

Status atual:

- Parcial.
- In-app já existe para pedidos e relatórios.

Lacunas:

- falta canal de e-mail para alertas administrativos de negócio;
- falta integração WhatsApp com consentimento, template e auditoria;
- falta central de preferências de notificação por usuário.

Melhor prática recomendada:

- arquitetura de eventos + filas por canal;
- provedores desacoplados por adaptadores;
- controle de frequência e regras anti-spam.

### 5.6 Frente 6 - Vendas e estoque (módulo funcional completo)

Status atual:

- Parcial.
- PDV e baixa de estoque já funcionam no fluxo de venda.

Lacunas:

- fechar ciclo completo de estoque (entradas, ajustes, perdas, inventário periódico, alertas mínimos);
- conciliar plenamente pedidos online, vendas e estoque;
- ampliar visão analítica operacional para gestão.

Melhor prática recomendada:

- ledger de inventário (movimentações imutáveis);
- regras transacionais para reserva/baixa/liberação de estoque;
- reconciliação diária assistida.

### 5.7 Frente 7 - Padronizar configuração da loja virtual

Status atual:

- Pendente de organização de UX.
- Configurações ainda distribuídas entre páginas diferentes (ex.: loja virtual e branding).

Lacunas:

- unificar “Configurações da Loja” em uma única experiência modular.

Melhor prática recomendada:

- separar seções: identidade, checkout, entrega, comunicação, domínios e integrações;
- manter branding global apenas para itens realmente globais.

### 5.8 Frente 8 - Contas a pagar / receber / pagamentos

Status atual:

- Parcial.
- A aba de pagamentos (gateways/métodos) está funcional.
- Contas a pagar e receber ainda não possuem operação financeira completa.

Lacunas:

- lançar títulos a pagar/receber;
- upload de documentos;
- recorrência, lembretes e alertas in-app/WhatsApp;
- conciliação com recebimentos de pedidos e vendas.

Melhor prática recomendada:

- modelo de títulos financeiros com histórico de status;
- notificações por vencimento e atraso;
- trilha de auditoria para alterações críticas.

### 5.9 Frente 9 - Relatórios assíncronos PDF/Excel

Status atual:

- Parcial.
- Já existe export assíncrona de vendas em CSV.

Lacunas:

- geração em PDF e Excel;
- catálogo de relatórios operacionais/financeiros;
- distribuição automática por e-mail.

Melhor prática recomendada:

- pipeline de relatórios por job com armazenamento versionado;
- templates padronizados;
- controle de acesso por contratante e por módulo.

### 5.10 Frente 10 - Nicho serviços com cenários diferentes

Status atual:

- Parcial.
- Arquitetura modular por nicho/tipo já existe, com base para evolução.

Lacunas:

- menus e páginas ainda não refletem totalmente cada cenário de serviços;
- necessidade de mapear variações reais (barbearia, oficina, contabilidade, etc.).

Melhor prática recomendada:

- matriz `business_type x módulos x jornadas`;
- presets por tipo de negócio;
- evitar condicionais espalhadas em controllers/views.

### 5.11 Frente 11 - Centralizar módulos em planos

Status atual:

- Parcial.
- Planos existem, mas o ajuste de módulos ainda ocorre no fluxo de contratante.

Lacunas:

- tornar plano a fonte única de permissões base;
- definir política de override por contratante (quando permitido).

Melhor prática recomendada:

- pivot `plan_module` com versionamento de plano;
- sincronização controlada para contratantes ativos;
- trilha de impacto em mudanças de plano.

### 5.12 Frente 12 - Bug de login/2FA em produção

Status atual:

- Pendente com impacto de UX e confiabilidade.

Sintomas informados:

- necessidade de recarregar página após 2FA para entrar no sistema;
- em erro de senha aparece falha de conexão em vez de mensagem amigável.

Melhor prática recomendada:

- investigação orientada a evidência: logs de aplicação, sessão, cookies e reverse proxy;
- validar `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SameSite`, headers de proxy e comportamento Inertia;
- testes E2E específicos para fluxo de autenticação e falha de credenciais.

### 5.13 Frente 13 - Termos e políticas + consentimento no login

Status atual:

- Pendente.

Lacunas:

- páginas públicas de Termos de Uso e Política de Privacidade;
- aviso de consentimento no login.

Melhor prática recomendada:

- versionar termos/políticas;
- registrar data/versão de aceite quando necessário por compliance;
- manter links visíveis na landing e no login.

### 5.14 Frente 14 - Segurança e isolamento de dados

Status atual:

- Parcial, com prioridade crítica.
- Já existe base multi-tenant, porém falta hardening formal para escala.

Lacunas:

- auditoria de exposição de dados em payloads/HTML;
- revisão de autorização por rota/ação (prevenção de IDOR);
- política de segurança operacional contínua.

Melhor prática recomendada:

- programa de hardening em camadas com validação tenant-first em query/policy;
- minimização de dados enviados ao frontend;
- pentest e checklists periódicos;
- trilha de auditoria para ações sensíveis.

## 6. Itens que exigem análise conjunta (produto + técnico)

Os itens abaixo dependem de decisão de produto e priorização com você para evitar retrabalho:

1. Frente 1: quais telas da loja devem virar modal e quais permanecem página.
2. Frente 4: quais gateways entram no MVP e em qual ordem.
3. Frente 5: política de notificações WhatsApp (eventos, frequência, consentimento).
4. Frente 10: matriz oficial de tipos de negócio do nicho serviços.
5. Frente 11: regra de override de módulos por contratante quando houver plano.
6. Frente 14: definição de baseline de segurança e critérios de aprovação para produção.

## 7. Backlog faseado recomendado

### Fase 0 - Correções críticas de produção

1. Frente 3: estabilizar envio de e-mail de verificação e tornar endereço obrigatório no cadastro da loja.
2. Frente 12: corrigir fluxo de login/2FA e mensagens de erro.
3. Frente 14: iniciar hardening de segurança e isolamento.

### Fase 1 - Conversão de receita (pedido + pagamento)

1. Frente 2: fluxo de pedido online completo.
2. Frente 4: integração de gateway prioritário com QR-code transacional.
3. Frente 5: alertas críticos para admin (in-app + e-mail).

### Fase 2 - Gestão financeira e operacional

1. Frente 8: contas a pagar/receber com documentos e lembretes.
2. Frente 6: evolução de vendas e estoque para ciclo completo.
3. Frente 9: relatórios PDF/Excel assíncronos.

### Fase 3 - Escalabilidade por nicho e governança de planos

1. Frente 10: serviço por cenário (menus e jornadas por tipo de negócio).
2. Frente 11: centralização de módulos na página de planos.
3. Frente 7: unificação final de configurações da loja virtual.
4. Frente 13: termos/políticas e consentimento no login.

## 8. Critérios de qualidade e aceite (DoD)

Todo item concluído deve cumprir:

1. testes de unidade e feature para regras críticas;
2. validação de autorização por contratante;
3. observabilidade mínima (logs e rastreabilidade);
4. UX em desktop e mobile;
5. textos em português brasileiro com codificação UTF-8.

## 9. Resumo executivo

O Veshop já possui base sólida para operação comercial, PDV, pedidos, módulos e multi-tenant, mas ainda precisa fechar blocos críticos de produção, pagamento online, financeiro completo, segurança em escala e especialização real do nicho serviços.  
Com este plano, a execução passa a ser orientada por prioridade de impacto: primeiro estabilidade e segurança, depois receita e operação, e por fim escalabilidade por nicho e governança de planos.
