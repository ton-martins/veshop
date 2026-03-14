# Veshop - Requisitos do Produto e da Plataforma

Versão: v1.4  
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

### 2.3 Catálogo público por contratante

Diretriz definida:
- Cada contratante terá catálogo público em subdomínio no padrão `slug.veshop.com.br`.
- O `slug` será customizável e único por contratante.

Suporte adicional:
- Contratantes sem domínio próprio usam o padrão `slug.veshop.com.br`.
- Contratantes que desejarem podem:
  - adquirir domínio com suporte da Veshop, ou
  - vincular domínio próprio/existente.
- Contratantes que já possuem loja virtual podem usar o Veshop como plataforma de gestão (backoffice), com integrações.

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
- CRUD admin de configuração de pagamentos por contratante:
  - gateways (`payment_gateways`),
  - formas de pagamento (`payment_methods`).
- Segmentação por nicho e bloqueio de módulos por contratante ativo.
- Relacionamento de usuários com múltiplos contratantes.
- Soft deletes nas entidades principais do domínio.
- Upload de avatar de usuário com validação (`png`, `jpg`, `jpeg`) e substituição do avatar anterior.

Parcial/visual (sem fluxo completo de negócio):
- PDV (aba na visão geral comercial com métricas reais e ações ainda em evolução)
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
- Visão geral comercial com abas Operação e PDV (camada visual pronta para integração).

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

### 7.1 Prioridade P0 (bloqueante): base transacional comercial

- Criar modelagem e regras para:
  - sessões de caixa (`cash_sessions`) e movimentos de caixa (`cash_movements`),
  - vendas (`sales`), itens de venda (`sale_items`) e pagamentos (`sale_payments`),
  - movimentações de estoque por origem (`inventory_movements`).
- Garantir consistência transacional:
  - baixa de estoque na venda,
  - lançamento financeiro vinculado ao pagamento,
  - reversão em cancelamento/estorno.
- Definir numeração e identificação operacional (cupom/venda/caixa).
- Aplicar escopo tenant-first e políticas de autorização em toda a cadeia.

### 7.2 Prioridade P1: PDV MVP na página de visão geral

- Transformar a aba PDV em fluxo operacional real:
  - abrir/fechar caixa com conferência,
  - nova venda com carrinho e leitura por código,
  - finalização por forma de pagamento (dinheiro, PIX, cartão),
  - orçamento pendente e conversão em venda.
- Integrar atalhos e cards do painel PDV com endpoints reais.
- Registrar histórico de vendas recentes com dados reais do contratante.
- Evoluir a configuração de pagamentos já criada:
  - incluir credenciais seguras por gateway,
  - validar conexão e health-check do provedor,
  - concluir fluxo online (autorização/captura/estorno) por integração.

### 7.3 Prioridade P2: fechar ciclo comercial (após PDV MVP)

- Concluir backend real de Pedidos (itens, status e regras).
- Concluir backend real de Estoque (entradas, saídas, ajustes e inventário).
- Concluir backend real de Contas (pagar/receber) com baixa e conciliação.
- Concluir Relatórios operacionais e financeiros com indicadores consolidados.

### 7.4 Prioridade P3: módulo de serviços (fluxo completo)

- Concluir backend operacional de Ordens de serviço.
- Concluir backend operacional de Agenda de serviços.
- Conectar indicadores da visão geral de serviços com dados transacionais reais.

### 7.5 Prioridade P4: catálogo público e domínio por contratante

- Implementar catálogo público com resolução por host:
  - padrão `slug.veshop.com.br`,
  - suporte a domínio customizado por contratante.
- Criar onboarding de domínio:
  - validação de DNS,
  - emissão/renovação de SSL,
  - domínio canônico e redirecionamentos.
- Estruturar integrações para cenário em que a loja virtual externa permanece como vitrine e o Veshop atua na gestão.

### 7.6 Prioridade P7: módulo de integrações

- Implementar módulo de integrações para conectores externos.
- Incluir integração de pagamentos (gateways) como trilha formal do produto.
- Incluir integração com loja virtual existente do contratante para sincronização operacional (pedidos, estoque e financeiro).

### 7.7 Prioridade P5: segurança e governança

- Endurecer isolamento tenant-first em escopo/policies globais para reduzir risco de vazamento por erro de controller.
- Consolidar auditoria de ações críticas (criação, edição, exclusão, troca de status).

### 7.8 Prioridade P6: escalabilidade operacional

Não será feito agora, mas está mapeado:
- Migrar `session`, `cache` e `queue` para Redis (hoje estão em `database` no ambiente local).
- Estruturar workers de fila e rotinas assíncronas.
- Definir observabilidade (APM, logs estruturados e alertas).
- Definir backup/restore com testes periódicos.
- Planejar armazenamento de mídia em object storage (S3 compatível).

## 8. Próxima fase sugerida

Ordem recomendada:
1. Base transacional comercial (caixa, venda, pagamento, estoque).
2. PDV MVP na visão geral (fluxo completo de frente de caixa).
3. Pedidos e itens de pedido.
4. Estoque e movimentações completas.
5. Contas (pagar/receber) com baixa e conciliação.
6. Relatórios consolidados.
7. Ordens de serviço e agenda (fluxo completo).
8. Catálogo público por host + domínio customizado + onboarding DNS/SSL.
9. Auditoria e observabilidade.

## 9. Checklist de retomada

Ao retomar o desenvolvimento, considerar como verdade:
- Sistema é multiempresa com `contractor-first`.
- Nicho define módulos disponíveis.
- `master` e `admin` são os únicos papéis ativos.
- Catálogo público seguirá padrão de subdomínio `slug.veshop.com.br`, com suporte a domínio próprio.
- CRUDs principais de cadastro base já estão operacionais.
- Aba PDV da visão geral está em camada visual; fluxo transacional ainda precisa ser implementado.
- Fluxos transacionais centrais (PDV, pedidos, estoque, financeiro completo) ainda precisam de backend final.
- MySQL é adequado para o estágio atual; escalabilidade futura depende de camadas de infraestrutura (Redis, filas, observabilidade e backup).
