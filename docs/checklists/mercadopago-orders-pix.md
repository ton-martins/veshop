# Checklist de Implementação - Mercado Pago Orders + Pix

Data: 28/03/2026  
Status: Planejamento técnico  
Decisão: substituir a integração legada de Mercado Pago por uma integração nova, completa e multi-tenant.

## 1) Decisão de arquitetura (fechamento)

- [x] Descontinuar fluxo legado atual (token manual + endpoints legados).
- [x] Adotar integração oficial via Orders + Pix com webhook assinado.
- [x] Manter segregação por `contractor_id` em toda leitura/escrita.
- [x] Tratar `shop/{slug_contratante}` como contexto de loja do contratante.
- [x] Confirmar que o valor do pagamento deve cair na conta do contratante (OAuth por contratante), não na conta da Veshop.

## 2) Escopo funcional (v1 obrigatória)

- [ ] Conectar conta Mercado Pago no painel admin da loja (OAuth).
- [ ] Criar cobrança Pix no checkout da loja virtual (produto e serviço).
- [ ] Exibir QR Code automaticamente na finalização.
- [ ] Atualizar status por webhook e reconciliação ativa.
- [ ] Refletir status consistente em storefront, admin e histórico do cliente.
- [ ] Suportar ambientes de teste e produção sem mistura de credenciais.

## 3) Modelagem e dados

- [ ] Criar estrutura para conexão OAuth por contratante:
  - [ ] `contractor_id`
  - [ ] `provider = mercado_pago`
  - [ ] `mp_user_id`
  - [ ] `access_token` (criptografado)
  - [ ] `refresh_token` (criptografado)
  - [ ] `expires_at`
  - [ ] `scopes`
  - [ ] `status` (`connected`, `expired`, `revoked`, `error`)
- [ ] Criar trilha de sincronização:
  - [ ] `last_sync_at`
  - [ ] `last_error`
  - [ ] `webhook_fail_count`
- [ ] Revisar `sale_payments` para garantir persistência de:
  - [ ] `transaction_reference` (id da order/pagamento no MP)
  - [ ] `gateway_payload` (sanitizado)
  - [ ] `metadata` (estado interno e auditoria)
- [ ] Criar migration de transição para desligar campos legados sem quebrar leitura histórica.

## 4) Backend - serviços de integração

- [ ] Implementar `MercadoPagoOAuthService`:
  - [ ] gerar URL de autorização
  - [ ] trocar `code` por tokens
  - [ ] renovar token expirado
  - [ ] revogar/desconectar conta
- [ ] Implementar `MercadoPagoOrdersService`:
  - [ ] criar order Pix com `X-Idempotency-Key`
  - [ ] consultar order/pagamento por id
  - [ ] normalizar payload de QR Code e status
- [ ] Padronizar contrato de provider:
  - [ ] `createPixOrder`
  - [ ] `fetchOrder`
  - [ ] `normalizeWebhook`
  - [ ] `testConnection`
- [ ] Garantir idempotência por pedido/agendamento no domínio interno.

## 5) Webhook e segurança

- [ ] Receber webhook de order/payment do Mercado Pago.
- [ ] Validar assinatura oficial (`x-signature`) e timestamp.
- [ ] Implementar processamento idempotente por `event_id`.
- [ ] Sempre buscar estado atualizado no MP antes de persistir.
- [ ] Mapear status do MP para estados internos:
  - [ ] pendente
  - [ ] processando
  - [ ] pago
  - [ ] cancelado/expirado
- [ ] Registrar logs técnicos sem expor segredo/token.

## 6) Frontend admin (configuração simplificada)

- [ ] Unificar fluxo no cadastro de forma de pagamento:
  - [ ] opção manual
  - [ ] opção Mercado Pago Pix (conectar conta)
- [ ] Remover necessidade de colar token manual para MP.
- [ ] Exibir status da conexão:
  - [ ] conectado
  - [ ] token expirado
  - [ ] desconectado
  - [ ] erro de permissão/escopo
- [ ] Exibir ação de reconectar e desconectar.

## 7) Frontend loja virtual (checkout)

- [ ] Após finalizar pedido, abrir bloco Pix com:
  - [ ] QR Code (imagem base64 ou fallback por payload textual)
  - [ ] código Pix copia-e-cola
  - [ ] valor, expiração e status
- [ ] Atualizar status automático (polling controlado) até confirmação/expiração.
- [ ] Tratar falhas com mensagem clara em pt-BR e botão de tentativa.
- [ ] Garantir fluxo em desktop e mobile.

## 8) Remoção do legado (obrigatório nesta entrega)

- [ ] Remover provider/serviço legado de criação Pix atual.
- [ ] Remover telas/campos legados de credencial manual do MP.
- [ ] Remover rotas/handlers legados não utilizados.
- [ ] Manter apenas adaptador temporário de leitura para dados antigos (se existir).
- [ ] Após 1 ciclo estável, remover código de compatibilidade temporária.

## 9) Testes e homologação

- [ ] Testes unitários:
  - [ ] OAuth callback/refresh
  - [ ] normalização de payload
  - [ ] mapeamento de status
- [ ] Testes de integração:
  - [ ] create order Pix
  - [ ] fetch order
  - [ ] webhook válido/inválido
- [ ] Testes E2E:
  - [ ] nicho comercial (produto)
  - [ ] nicho serviços (agendamento)
  - [ ] QR imediato
  - [ ] QR tardio via atualização
- [ ] Teste de não regressão multi-tenant:
  - [ ] contratante A não acessa dados do contratante B.

## 10) Impacto e riscos

- [ ] Impacto técnico: médio/alto (financeiro, checkout, webhook, admin).
- [ ] Impacto em dados: médio (nova modelagem OAuth + transição).
- [ ] Impacto operacional: alto positivo (menos suporte manual).
- [ ] Risco principal: quebra de checkout Pix durante transição.
- [ ] Mitigação:
  - [ ] rollout por feature flag
  - [ ] logs estruturados por `contractor_id`/`sale_id`
  - [ ] monitoramento de taxa de geração de QR e aprovação.

## 11) Plano de rollout

- [ ] Fase 1: modelagem + OAuth + provider novo (sem ativar para clientes).
- [ ] Fase 2: ativar em homologação e validar cenários completos.
- [ ] Fase 3: ativar por lote de contratantes em produção.
- [ ] Fase 4: remover compatibilidade temporária e limpar legado.

## 12) Critérios de aceite (DoD desta frente)

- [ ] Checkout Pix gera QR automaticamente.
- [ ] Status sincroniza por webhook + reconciliação.
- [ ] Integração funciona para produto e serviço.
- [ ] Configuração do contratante é simples (conectar conta, sem colar token manual).
- [ ] Não há mistura de dados entre contratantes.
- [ ] Logs permitem diagnóstico rápido de falha.

