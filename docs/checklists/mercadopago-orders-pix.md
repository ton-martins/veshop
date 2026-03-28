# Checklist técnico de auditoria

## Mercado Pago (Checkout API via Orders + Pix)

Data: 28/03/2026  
Objetivo: garantir geração automática de QR Code Pix na loja virtual e consistência de status entre storefront, ERP e Mercado Pago.

## 1) Escopo e pré-requisitos

- [ ] Fluxo auditado por **contratante** (`contractor_id`).
- [ ] Aplicação Mercado Pago criada para Checkout API via Orders.
- [ ] Credenciais corretas por ambiente:
  - [ ] Homologação usa token `TEST-...`.
  - [ ] Produção usa token `APP_USR-...`.
- [ ] Forma de pagamento Pix vinculada ao gateway correto do contratante.
- [ ] Loja com HTTPS público para webhook (em produção/homologação pública).

## 2) Configuração da aplicação no Mercado Pago

- [ ] Aplicação criada em “Suas integrações”.
- [ ] Produto correto habilitado para Checkout API via Orders.
- [ ] Notificações configuradas para tópico/evento de **order**.
- [ ] URL de notificação salva com sucesso no painel.
- [ ] Secret de webhook definido e armazenado no ERP.

## 3) Criação da cobrança (POST /v1/orders)

- [ ] Chamada usa `POST /v1/orders`.
- [ ] Header `Authorization: Bearer <token>` presente.
- [ ] Header `X-Idempotency-Key` único por tentativa de checkout.
- [ ] Payload mínimo válido:
  - [ ] `type = online`
  - [ ] `processing_mode = automatic` (ou estratégia definida pelo negócio)
  - [ ] `transactions.payments[0].payment_method.id = pix`
  - [ ] `transactions.payments[0].payment_method.type = bank_transfer`
  - [ ] `external_reference` com código do pedido/agendamento
  - [ ] `payer.email` válido
- [ ] Resposta salva no `gateway_payload` sem perda de campos.

## 4) Extração do QR (payload por payload)

Validar leitura em **todas** as variações possíveis:

- [ ] `transactions.payments[].payment_method.qr_code`
- [ ] `transactions.payments[].payment_method.data.qr_code`
- [ ] `transactions.payments[].qr_code`
- [ ] `point_of_interaction.transaction_data.qr_code` (fallback)
- [ ] `qr_code_base64` em variações equivalentes
- [ ] `ticket_url` em variações equivalentes

Resultado esperado:

- [ ] `checkout_payment.qr_code` preenchido quando disponível.
- [ ] `checkout_payment.qr_code_base64` preenchido quando disponível.
- [ ] `checkout_payment.ticket_url` preenchido quando disponível.

## 5) Geração automática no storefront

- [ ] Após finalizar pedido/agendamento Pix, tela exibe bloco de cobrança Pix.
- [ ] Se QR não vier na primeira resposta, frontend faz atualização automática de status.
- [ ] Polling para quando QR aparece ou quando expira número máximo de tentativas.
- [ ] Botão manual “Atualizar cobrança” permanece como fallback.
- [ ] QR renderiza por `qr_code_base64`; se ausente, gera imagem via `qr_code`.
- [ ] Mensagem clara em pt-BR quando ainda está aguardando retorno da cobrança.

## 6) Consulta de status (GET /v1/orders/{id})

- [ ] Endpoint de status interno consulta Mercado Pago quando ainda sem QR/status final.
- [ ] `transaction_reference` do pedido interno corresponde ao `order.id` do MP.
- [ ] Dados reconciliados persistem em `sale_payments` e `sales.metadata`.
- [ ] Requisições de consulta respeitam segurança do cliente dono do pedido.

## 7) Webhook e segurança

- [ ] Webhook recebe eventos de order do Mercado Pago.
- [ ] Validação de assinatura (`x-signature`) implementada e ativa.
- [ ] Validação de secret/token adicional (quando aplicável) ativa.
- [ ] Processamento idempotente por `event_id`/chave de evento.
- [ ] Ao receber webhook, sistema consulta o recurso atualizado no MP antes de persistir.

## 8) Mapeamento de status (negócio)

- [ ] `action_required` / `waiting_transfer` => pagamento pendente.
- [ ] `in_process` / `processing` => pagamento em processamento.
- [ ] `processed` / `approved` / `accredited` => pagamento confirmado.
- [ ] `cancelled` / `rejected` / `refunded` => pagamento não concluído.
- [ ] Status de venda/agendamento refletido corretamente após cada transição.

## 9) Cenários obrigatórios de homologação

- [ ] Cenário A: QR retornado imediatamente no `POST /v1/orders`.
- [ ] Cenário B: QR ausente no create e retornado no `GET /v1/orders/{id}`.
- [ ] Cenário C: evento webhook recebido e status atualizado automaticamente.
- [ ] Cenário D: token inválido => erro claro e ação corretiva orientada.
- [ ] Cenário E: ambiente misto (`TEST` vs `APP_USR`) bloqueado com mensagem explícita.
- [ ] Cenário F: repetição de checkout com mesma idempotency key não duplica cobrança.

## 10) Observabilidade e diagnóstico

- [ ] Log com `contractor_id`, `sale_id`, `payment_id`, `transaction_reference`.
- [ ] Log de chamada MP com método, endpoint e status HTTP (sem expor segredo).
- [ ] Log de erro com mensagem normalizada do provedor.
- [ ] Métrica de tempo médio para disponibilidade de QR.
- [ ] Métrica de taxa de sucesso na geração de QR por ambiente.

## 11) Critérios de aceite final

- [ ] Cliente final sempre recebe QR automaticamente (imediato ou por atualização automática).
- [ ] Não há criação de cobrança duplicada.
- [ ] Status de pagamento e pedido/agendamento ficam consistentes entre frontend e admin.
- [ ] Fluxo funciona para nicho comercial e nicho serviços.
- [ ] Fluxo validado em ambiente local (com fallback sem webhook público) e homologação pública (com webhook).

## 12) Evidências (preencher)

- [ ] Prints/vídeos do fluxo completo.
- [ ] Payload real mascarado de `POST /v1/orders`.
- [ ] Payload real mascarado de `GET /v1/orders/{id}`.
- [ ] Evento real de webhook mascarado.
- [ ] Evidência de status final no admin e na conta do cliente.

