# Auditoria de Segurança Multi-tenant

Versão: v1.0  
Última atualização: 17/03/2026

## 1. Objetivo

Registrar evidências técnicas de isolamento entre contratantes e trilha de segurança para investigação de tentativas de acesso indevido.

## 2. Entregas implementadas

1. Tabela `security_audit_logs` para trilha de eventos de segurança.
2. Serviço `SecurityAuditLogger` com sanitização de contexto (sem dados sensíveis em texto puro).
3. Middleware `tenant.scope` para bloquear acesso cruzado por `contractor_id` em route-model binding.
4. Auditoria de negação por perfil (`auth.role_denied`) e módulo não habilitado (`module.access_denied`).
5. Redução do payload compartilhado de `auth.user` no Inertia para campos mínimos seguros.

## 3. Eventos auditados (fase atual)

- `tenant.resource_scope_violation` (crítico)
- `auth.role_denied` (warning)
- `module.access_denied` (warning)

## 4. Próxima fase

1. Criar página de auditoria para `master` (visão global) e `admin` (somente seu contratante).
2. Aplicar retenção por plano usando `audit_log_retention_days`.
3. Incluir filtros por severidade, período, rota e tipo de evento.

## 5. Diretriz de produto

Mesmo com 1 admin por contratante, a página de auditoria faz sentido para:

1. rastreabilidade de incidentes;
2. transparência operacional para contratante;
3. governança quando houver expansão de usuários/perfis.

A recomendação é manter primeiro a trilha técnica e publicar a página na próxima iteração.

