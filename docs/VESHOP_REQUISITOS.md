# Veshop - Requisitos do Produto e da Plataforma

Versão: v1.2  
Última atualização: 14/03/2026

## 1. Visão do produto

O Veshop é um SaaS multiempresa para operação de contratantes, com foco em gestão prática e execução diária.

Objetivos:
- Centralizar operação comercial e de serviços em uma única plataforma.
- Reduzir retrabalho entre vendas, estoque, financeiro e atendimento.
- Permitir branding por contratante.
- Entregar experiência simples para uso diário em desktop, tablet e celular.

## 2. Escopo funcional atual

### 2.1 Papéis e acesso

Papéis ativos:
- `master`: gestão da plataforma.
- `admin`: gestão operacional do contratante.

Regras:
- Não há cadastro público de usuários/contratantes.
- O `master` gerencia contratantes, planos e usuários.

### 2.2 Modelo organizacional (contractor-first)

- Relação N:N entre usuários e contratantes (`contractor_user`).
- Cada contratante possui branding próprio, plano ativo e nicho único.
- Nicho não é editável pelo `admin`.

Nichos ativos:
- `commercial` (Comércio)
- `services` (Serviços)

## 3. Módulos por nicho

### 3.1 Comércio

- Início (Visão geral)
- Produtos
- Categorias
- Clientes
- Fornecedores
- Pedidos
- Estoque
- Contas (abas: pagar / receber)
- Relatórios

### 3.2 Serviços

- Início (Visão geral)
- Catálogo de serviços
- Ordens de serviço
- Agenda

## 4. O que já temos (status consolidado)

### 4.1 Backend

Implementado:
- CRUDs com paginação (10 por página): Produtos, Categorias, Clientes, Fornecedores.
- CRUD de usuários (master), contratantes (master) e planos (master).
- Segmentação por nicho e bloqueio de módulos por contratante ativo.
- Relacionamento de usuários com múltiplos contratantes.
- Soft deletes nas entidades principais do domínio.
- Upload de avatar de usuário com validação (`png`, `jpg`, `jpeg`) e substituição do avatar anterior.

Parcial/visual (sem fluxo completo de negócio):
- Pedidos
- Estoque
- Contas (pagar/receber)
- Relatórios
- Ordens de serviço
- Agenda

### 4.2 Frontend e UX/UI

Implementado:
- Layout autenticado com sidebar, header simplificado e menu mobile inferior.
- Modo de visualização em lista/cards para tabelas adaptativas.
- Tabelas com scroll horizontal interno para preservar layout mobile.
- Paginação padronizada com rótulos em pt-BR.
- Modais padronizados para criação/edição e confirmação de exclusão.
- Página de perfil com upload de avatar.
- Página de branding (master/admin) com identidade visual e ativos da landing.
- Landing page segmentada por nicho nos planos (Comércio/Serviços) e responsiva.

### 4.3 Padrões de busca e filtros

Implementado hoje:
- Campos de busca com:
  - botão `Buscar`,
  - acionamento por `Enter`,
  - botão `x` para limpar rapidamente o texto.
- Mantido botão `Limpar` para reset completo dos filtros.

## 5. Funcionalidades desenvolvidas hoje

- Ajuste de exibição de avatar no perfil e no rodapé/menu do layout autenticado.
- Reforço de normalização de URL de avatar para evitar inconsistências de host.
- Confirmação de troca de avatar com remoção do arquivo anterior para não acumular storage.
- Troca de ícones no menu admin:
  - grupo `Financeiro` para ícone de cifrão,
  - link `Relatórios` para ícone de documento.
- Padronização da busca nas páginas com botão explícito (`Buscar`) + `x` para limpar.
- Atualização deste documento com status objetivo de “temos” e “falta”.

## 6. Arquitetura e banco (diagnóstico atual)

### 6.1 Arquitetura

Estado atual:
- Monólito Laravel com separação por contexto de domínio e papel.
- Estratégia multiempresa por `contractor_id` + contexto de contratante em sessão.
- Estrutura adequada para fase atual e crescimento inicial.

### 6.2 MySQL para o cenário

Resposta objetiva:
- Sim, MySQL atende bem o cenário atual e a próxima fase do Veshop.
- É uma escolha correta para SaaS multiempresa com o volume esperado de início.

## 7. O que falta (prioridade)

### 7.1 Negócio e domínio

- Concluir backend real de:
  - Pedidos (itens, status, regras),
  - Estoque (movimentações e consistência),
  - Contas a pagar/receber (ciclo financeiro),
  - Relatórios analíticos.
- Concluir backend operacional de serviços (ordens e agenda).

### 7.2 Segurança e governança

- Endurecer isolamento tenant-first em escopo/policies globais para reduzir risco de vazamento por erro de controller.
- Consolidar auditoria de ações críticas (criação, edição, exclusão, troca de status).

### 7.3 Escalabilidade operacional

Não será feito agora, mas está mapeado:
- Migrar `session`, `cache` e `queue` para Redis (hoje estão em `database` no ambiente local).
- Estruturar workers de fila e rotinas assíncronas.
- Definir observabilidade (APM, logs estruturados e alertas).
- Definir backup/restore com testes periódicos.
- Planejar armazenamento de mídia em object storage (S3 compatível).

## 8. Próxima fase sugerida

Ordem recomendada:
1. Pedidos e itens de pedido.
2. Estoque e movimentações.
3. Contas (pagar/receber) com regras de vencimento e baixa.
4. Relatórios consolidados.
5. Ordens de serviço e agenda (fluxo completo).
6. Auditoria e observabilidade.

## 9. Checklist de retomada

Ao retomar o desenvolvimento, considerar como verdade:
- Sistema é multiempresa com `contractor-first`.
- Nicho define módulos disponíveis.
- `master` e `admin` são os únicos papéis ativos.
- CRUDs principais de cadastro base já estão operacionais.
- Fluxos transacionais centrais (pedidos, estoque, financeiro completo) ainda precisam de backend final.
- MySQL é adequado para o estágio atual; escalabilidade futura depende de camadas de infraestrutura (Redis, filas, observabilidade e backup).
