# Veshop - Guia Mestre de Produto, Arquitetura e Execução

Versão: v3.0  
Última atualização: 18/03/2026

## 1. Finalidade deste documento

Este arquivo é a referência única do Veshop para:

1. visão do produto e escopo atual;
2. padrão de arquitetura, segurança e qualidade;
3. backlog priorizado com critérios claros de entrega;
4. direcionamento de implementação para reduzir risco técnico e de suporte.

## 2. Visão consolidada do Veshop

O Veshop é uma plataforma multi-tenant para contratantes de comércio e serviços, com:

- painel admin para operação diária (cadastros, vendas, pedidos, financeiro, configurações);
- loja virtual pública por contratante (catálogo, carrinho, checkout, conta do cliente);
- governança master (planos, módulos, contratantes, usuários);
- trilhas de segurança e isolamento por contratante.

## 3. Responsabilidades técnicas obrigatórias

## 3.1 Arquitetura e isolamento multi-tenant

- Toda entidade de negócio deve respeitar `contractor_id`.
- Route model binding e consultas devem bloquear acesso cruzado entre contratantes.
- Autorização deve validar perfil (`master`/`admin`) e módulo habilitado por plano/contratante.
- Não expor dados sensíveis ou desnecessários no payload Inertia/HTML inicial.

## 3.2 Segurança e auditoria

- Manter trilha de auditoria para eventos críticos de segurança.
- Sanitizar contexto de logs (sem segredo, token, senha, dados sensíveis em texto puro).
- Registrar e monitorar:
  - `tenant.resource_scope_violation`;
  - `auth.role_denied`;
  - `module.access_denied`.
- Evoluir retenção, filtros e visualização da auditoria por perfil.

## 3.3 Padrão de desenvolvimento e qualidade

- Backend: testes de feature para fluxos críticos e autorização.
- Frontend: UX consistente desktop/mobile.
- Filas e jobs: robustez para e-mail, exportações e notificações.
- Regras de negócio versionadas no banco e no código (migrations + validações).
- Deploy com build atualizado e limpeza de cache/config quando necessário.

## 3.4 Requisitos de conteúdo e idioma

- Textos da plataforma em português brasileiro.
- Codificação UTF-8 em arquivos e conteúdo persistido.
- Labels, mensagens de erro e e-mails padronizados em PT-BR.

## 4. Estado atual validado no sistema

## 4.1 Base de plataforma

- Stack Laravel + Inertia + Vue.
- Multi-tenant por `contractor_id`.
- Perfis `master` e `admin`.
- Relação N:N usuário/contratante.
- Login com 2FA.
- Filas separadas (`emails`, `exports`, `notifications`).

## 4.2 Governança master

- CRUD de usuários, contratantes e planos.
- Catálogo de módulos com habilitação por contratante.
- Arquitetura modular por `business_niche`, `business_type` e módulos.

## 4.3 Operação comercial (admin)

- Dashboard e visão operacional.
- CRUD de produtos, categorias, clientes e fornecedores.
- Pedidos com ações operacionais.
- PDV funcional com movimento de estoque.
- Financeiro com base para gateways/métodos.
- Página de manuais de uso.

## 4.4 Loja virtual pública

- Loja por slug (`/shop/{slug}`) e página pública de produto.
- Busca, filtros, ordenação, carrinho e favoritos.
- Login/cadastro/conta do cliente por loja.
- Verificação de e-mail no fluxo do cliente.
- Fluxo de recuperação de senha do cliente.
- Fallback visual de identidade do contratante (logo ou iniciais).

## 4.5 Pagamentos e checkout

- Checkout Pix com `idempotency_key`.
- Integração Mercado Pago no MVP de Pix.
- QR Code, código copia e cola e acompanhamento de status.
- Webhook com deduplicação de recebimentos.
- Fluxo manual (`manual`) e integrado (`mercado_pago`) já previstos no modelo.

## 4.6 Segurança implementada

- Tabela de auditoria de segurança e serviço de logging sanitizado.
- Middleware de escopo por contratante em rotas críticas.
- Redução do payload de autenticação compartilhado no frontend.

## 5. Backlog priorizado (itens pendentes)

| ID | Frente | Status | Prioridade | Resultado esperado |
|---|---|---|---|---|
| 1 | UX/UI da loja virtual (feedback de ação + carrinho mobile) | Parcial | Crítica | Jornada de compra clara, sem sobreposição e com confirmação visual |
| 2 | Edição de pedido/venda no admin | Pendente | Alta | Permitir correção operacional com rastreabilidade |
| 3 | Financeiro (contas a pagar/receber + anexos + OCR) | Pendente | Crítica | Gestão financeira completa com lançamentos, status e documentos |
| 4 | Finalizar páginas de estoque e pedidos (+ botão novo pedido) | Parcial | Alta | Fluxo operacional completo de pedidos/estoque |
| 5 | Produto com até 5 fotos + variações + promoção (%) | Pendente | Alta | Cadastro robusto e refletido na loja virtual |
| 6 | Categorias com subcategorias | Pendente | Alta | Navegação e catálogo mais precisos na loja pública |
| 7 | Mover “Manuais” para item independente no menu | Pendente | Média | Acesso direto e organizado para treinamento |
| 8 | Checkout dual: integrado (Pix) e padrão manual | Parcial | Crítica | Um único padrão operacional cobrindo os 2 cenários |
| 9 | Notificações in-app, e-mail e WhatsApp | Parcial | Alta | Comunicação transacional completa para admin |
| 10 | Taxas por forma de pagamento (PDV + loja) | Pendente | Alta | Cálculo automático de taxa no pedido/venda |
| 11 | Ajuste de carregamento inicial do login da loja | Pendente | Alta | Evitar erro visual no HTML inicial |
| 12 | Páginas legais (Termos e Privacidade LGPD) | Pendente | Alta | Base legal completa com consentimento e transparência |

## 5.1 Detalhamento técnico dos itens pendentes

### ID 1 - UX/UI da loja virtual

- Adicionar feedback visual imediato em ações:
  - adicionar ao carrinho;
  - finalizar compra;
  - atualizar quantidade;
  - aplicar método de entrega/pagamento.
- Refatorar modal de carrinho em mobile:
  - separar conteúdo por etapas (wizard) ou sessões colapsáveis;
  - evitar sobreposição de bloco de entrega sobre lista de itens;
  - manter resumo de totais sempre legível.
- Critério de aceite:
  - sem sobreposição em viewport mobile;
  - CTA principal sempre visível;
  - usuário entende em qual etapa está.

### ID 2 - Editar pedido/venda

- Permitir edição controlada para admin em cenários de erro operacional.
- Campos editáveis por status (ex.: antes de concluir/faturar).
- Registrar trilha:
  - quem alterou;
  - quando alterou;
  - valores antes/depois.
- Critério de aceite:
  - alteração auditável;
  - integridade de estoque e financeiro preservada.

### ID 3 - Financeiro (AP/AR + anexos + OCR)

- Entidades mínimas:
  - contas a pagar;
  - contas a receber;
  - anexos por lançamento;
  - histórico de status.
- Campos obrigatórios:
  - valor;
  - vencimento;
  - status padronizado;
  - observações;
  - documento opcional.
- OCR (estudo e faseamento):
  - Fase 1: upload + extração assistida (pré-preencher, usuário confirma);
  - Fase 2: regras por tipo de documento e melhoria de acurácia;
  - Fase 3: automação com confiança mínima e fila assíncrona.
- Observação:
  - OCR deve ser “assistivo”, não automático cego.

### ID 4 - Estoque e pedidos

- Concluir páginas com consistência de estado e filtros.
- Incluir ação “Novo pedido” na página de pedidos.
- Garantir integração com estoque e fluxo de status.

### ID 5 - Produtos (fotos, variações e promoção)

- Suportar até 5 imagens por produto.
- Modelo de variações (ex.: cor, tamanho).
- Promoção em percentual com cálculo de:
  - valor original;
  - valor de venda com desconto.
- Refletir corretamente no catálogo e no checkout.

### ID 6 - Categorias e subcategorias

- Estrutura hierárquica (`parent_id`) para categorias.
- Ajustar admin + loja pública para navegação por nível.
- Garantir filtros e SEO interno coerentes.

### ID 7 - Página de manuais no menu

- Tirar do grupo de configurações.
- Publicar item de menu independente.
- Manter controle de permissão por módulo/plano.

### ID 8 - Checkout dual (integrado + padrão manual)

- Definir configuração por contratante:
  - `checkout_mode = integrated_gateway`;
  - `checkout_mode = manual_after_order`.
- Fluxo integrado:
  - pedido + intenção de pagamento + webhook/status.
- Fluxo manual:
  - cliente envia pedido sem pagar;
  - contratante recebe no sistema/WhatsApp;
  - contratante cobra externamente e confirma manualmente no sistema.
- Padronização recomendada de status:
  - `pending_payment`;
  - `payment_confirmed`;
  - `in_preparation`;
  - `shipped`;
  - `completed`;
  - `canceled`.
- Resultado:
  - um único modelo operacional, mudando apenas o modo de confirmação de pagamento.

### ID 9 - Notificações (in-app + e-mail + WhatsApp)

- In-app e e-mail: consolidar gatilhos por evento de negócio.
- WhatsApp: avaliar viabilidade por provedor oficial e custos.
- Recomendação:
  - iniciar com arquitetura de canais plugáveis;
  - implementar preferências por usuário/contratante;
  - registrar entregas e falhas por canal.

### ID 10 - Taxa por forma de pagamento

- Permitir configuração de taxa (fixa ou percentual) por método.
- Aplicar em:
  - PDV;
  - loja virtual.
- Mostrar transparência de cálculo no resumo do pedido/venda.

### ID 11 - Carregamento inicial do login da loja

- Revisar hydration e payload inicial para evitar erro de render.
- Garantir carregamento com estado pronto antes de exibir formulário.
- Critério de aceite:
  - sem quebra visual no primeiro paint.

### ID 12 - Termos e políticas (LGPD)

- Criar páginas:
  - termos de uso;
  - política de privacidade.
- Incluir consentimento no login/cadastro quando aplicável.
- Definir versionamento de termos e evidência de aceite.

## 6. Roadmap sugerido de execução

## Fase A (crítica)

1. ID 1 (UX/UI loja e carrinho mobile).
2. ID 8 (checkout dual padronizado).
3. ID 3 (financeiro base AP/AR com anexos, sem OCR automático).

## Fase B (operação)

1. ID 4 (pedidos/estoque + novo pedido).
2. ID 2 (edição de pedido/venda com auditoria).
3. ID 10 (taxas por forma de pagamento).

## Fase C (expansão e governança)

1. ID 5 e ID 6 (catálogo avançado).
2. ID 9 (multicanal de notificações, com estudo WhatsApp).
3. ID 12 e ID 7 (compliance e organização de navegação).
4. ID 11 (hardening visual de carregamento inicial).

## 7. Critérios globais de aceite (DoD)

Toda entrega deve cumprir:

1. testes cobrindo regra crítica;
2. autorização e escopo por contratante;
3. logs e auditoria sem exposição de dados sensíveis;
4. UX consistente em desktop e mobile;
5. textos em português brasileiro e codificação UTF-8;
6. documentação atualizada neste arquivo ao final da entrega.

## 8. Decisões de produto já assumidas

1. O sistema deve suportar checkout integrado e manual sem duplicar arquitetura.
2. Segurança multi-tenant é requisito de plataforma, não item opcional.
3. OCR no financeiro deve começar como apoio ao usuário, não automação sem revisão.
4. Backlog deve priorizar impacto operacional real para contratante.

## 9. Próximo passo recomendado

Iniciar pelo combo **ID 1 + ID 8**, porque entrega valor direto ao contratante e reduz fricção de compra:

1. fechar UX/UI do carrinho mobile com etapas claras;
2. formalizar o modo de checkout por contratante (integrado x manual) com status unificado.
