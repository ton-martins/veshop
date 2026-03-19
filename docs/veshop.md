# Veshop - Guia Mestre de Produto e Arquitetura

Versão: v4.0  
Última atualização: 19/03/2026

## 1. Objetivo

Este é o documento único de referência do Veshop para:

1. estado real do sistema por módulo;
2. planejamento por nicho e por tipo de negócio;
3. padrão obrigatório de arquitetura, segurança e qualidade;
4. roadmap de implementação com prioridade e critérios de entrega.

## 2. Regras obrigatórias da plataforma

### 2.1 Idioma, conteúdo e codificação

- Todo texto funcional deve estar em português (pt-BR).
- Arquivos e conteúdo devem ser mantidos em UTF-8.
- Não publicar telas com texto quebrado, sem acentuação ou com mistura indevida de idioma.

### 2.2 Multi-tenant e autorização

- Toda regra de negócio deve respeitar `contractor_id`.
- Nenhum usuário pode acessar dados de outro contratante sem permissão explícita.
- Rotas, policies e consultas devem validar escopo de contratante.
- Módulos devem respeitar plano e permissões habilitadas.

### 2.3 Segurança e privacidade

- Não expor dados sensíveis no payload inicial (Inertia/Vue).
- Não logar segredos, tokens, senhas ou dados pessoais desnecessários.
- Toda ação crítica deve gerar trilha de auditoria.
- Upload e download de arquivos devem ser controlados por escopo de contratante.

### 2.4 Padrão de engenharia

- Código limpo e coeso por domínio.
- Arquitetura modular e escalável (separar domínio global e domínio específico).
- Cobertura mínima por testes de fluxo crítico (feature + regras de autorização).
- Sem regressão visual grave em desktop e mobile.

## 3. Estado atual validado (resumo)

- Painel admin multi-tenant com contexto de contratante.
- Loja virtual por contratante com catálogo, carrinho e checkout.
- Pedidos com pipeline no admin.
- Página de vendas (PDV) no padrão de pedidos.
- Financeiro Fase 1 (CRUD de lançamentos, anexos, filtros e paginação).
- Integração Mercado Pago em MVP (Pix + webhook).
- Fluxo de e-mail da loja corrigido (verificação e recuperação de senha).
- Paginação aplicada em módulos principais.
- Segurança de escopo reforçada em rotas críticas.

## 4. Matriz de módulos por escopo

## 4.1 Global para ambos os nichos (Comércio e Serviços)

Esses módulos são base da plataforma e devem funcionar em qualquer contratante:

1. Autenticação, 2FA, recuperação de senha e verificação de e-mail.
2. Multi-tenant (contexto, troca de contratante, escopo de dados).
3. Usuários, perfis, permissões e plano contratado.
4. Notificações in-app e e-mail.
5. Upload de arquivos com controle de acesso.
6. Relatórios assíncronos (jobs + exportação).
7. Auditoria de eventos críticos.
8. Branding e identidade por contratante.
9. Manuais e onboarding operacional.
10. Padrões visuais globais (menu, abas, cards, tabela, modais).

Status atual: **Parcial** (base sólida, com pendências em notificações e organização final de manuais).

## 4.2 Global para tipos de negócio do contratante

Esses módulos são globais no nível de operação empresarial e podem ser reutilizados por tipo de negócio:

1. Cadastros base: clientes, fornecedores, categorias.
2. Produtos/serviços (catálogo e variações por domínio).
3. Pedidos/vendas com status e histórico.
4. Financeiro gerencial (contas a pagar e receber).
5. Gateways e formas de pagamento.
6. Estoque (quando aplicável ao tipo de negócio).
7. Dashboard operacional com métricas por contexto.

Status atual: **Parcial** (estrutura existe, faltam capacidades avançadas e padronização final por tipo).

## 4.3 Específico por tipo de negócio (escopo inicial)

### A) Loja (física/virtual)

Entregue:

1. Loja virtual com login, cadastro, conta, favoritos e carrinho.
2. Checkout com opção integrada (Mercado Pago MVP) e base para fluxo manual.
3. Pedidos e vendas no admin com modal de detalhes e pipeline.
4. PDV com página dedicada de vendas.

Pendências para fechamento do módulo:

1. Edição completa de pedido/venda (cliente, itens, quantidade, desconto, total e estoque).
2. Produto com até 5 fotos, variações e promoção com cálculo seguro.
3. Categorias com subcategorias refletindo na loja virtual.
4. Taxa por forma de pagamento no PDV e na loja virtual.
5. Fluxo dual de checkout totalmente padronizado (integrado/manual).
6. Páginas legais LGPD (termos e política).

Status: **Parcial avançado (próximo de concluído)**.

### B) Barbearia

Objetivo:

1. Operação orientada a agenda.
2. Serviços, profissionais, horários e fila de atendimento.
3. Agendamento online com confirmação e status operacional.
4. Comanda/fechamento financeiro por atendimento.

Base já existente:

1. Estrutura de nicho serviços.
2. Módulos iniciais de serviços, catálogo e agenda (estrutura).

Pendências:

1. Modelagem de agenda por profissional e faixa de horário.
2. Regras de bloqueio/conflito de horário.
3. Fluxo de atendimento ponta a ponta (agendado, em atendimento, concluído, cancelado).
4. Dashboard específico de barbearia (ocupação, ticket médio, recorrência).

Status: **Pendente (fase de estruturação funcional)**.

### C) Contábil

Objetivo inicial (não fiscal nesta fase):

1. Gestão gerencial da operação contábil do contratante.
2. Organização financeira e documental por cliente.
3. Controle de prazos, honorários e acompanhamento de carteira.

Módulos recomendados para fase contábil gerencial:

1. Plano de contas gerencial.
2. Centro de custo.
3. Conciliação bancária por importação (CSV/OFX).
4. DRE gerencial e fluxo de caixa.
5. Gestão de honorários por cliente.
6. Agenda de vencimentos e obrigações internas.
7. Repositório de documentos por cliente com histórico.

Fora do escopo atual (futuro fiscal):

1. SPED/ECD/ECF.
2. Apuração tributária automatizada.
3. Entregas fiscais governamentais.

Status: **Pendente (fase de descoberta e desenho)**.

## 5. Loja (física/virtual) - backlog de fechamento

Prioridade para finalizar o módulo comercial antes dos demais tipos:

| ID | Item | Status | Prioridade |
| --- | --- | --- | --- |
| L1 | Edição completa de venda/pedido com recálculo e ajuste de estoque | Parcial | Crítica |
| L2 | Produto com 5 fotos + variações + promoção (%) | Pendente | Alta |
| L3 | Categorias com subcategorias na loja virtual | Pendente | Alta |
| L4 | Checkout dual padronizado (integrado/manual) | Parcial | Crítica |
| L5 | Taxa por forma de pagamento no pedido/venda | Pendente | Alta |
| L6 | Notificações operacionais (in-app/e-mail) no fluxo de pedido | Parcial | Alta |
| L7 | Páginas legais LGPD (termos/política) | Pendente | Alta |

Critério de conclusão do módulo Loja:

1. pedido criado, pago (ou manual), faturado e concluído sem brecha de fluxo;
2. edição segura com auditoria e sem inconsistência de estoque/total;
3. experiência consistente em desktop/mobile para loja e admin;
4. cobertura de testes nos fluxos críticos de checkout e operação.

## 6. Roadmap por fases

### Fase 1 - Fechamento Loja (agora)

1. concluir L1, L2, L3, L4 e L5;
2. consolidar notificações e páginas legais (L6, L7);
3. revisar regressão geral do nicho comércio.

### Fase 2 - Módulo Contábil (gerencial)

1. modelagem de dados contábeis gerenciais;
2. telas e fluxos operacionais mínimos;
3. relatórios gerenciais e auditoria de alterações.

### Fase 3 - Módulo Barbearia

1. agenda profissional com regras de disponibilidade;
2. fluxo de atendimento operacional completo;
3. dashboard específico do tipo de negócio.

## 7. Diretriz de storage de arquivos do contratante

Estratégia adotada no Veshop:

1. padrão inicial: storage centralizado pelo Veshop com isolamento por contratante;
2. segurança obrigatória: autorização por escopo, validação de acesso e links controlados;
3. evolução futura: opção de storage dedicado por contratante em plano avançado.

Observação:

- Centralizado simplifica operação, backup, monitoramento e suporte no estágio atual.
- Storage dedicado deve ser tratado como feature enterprise com governança própria.

## 8. Definição de pronto (DoD)

Toda entrega é considerada concluída somente se tiver:

1. regra multi-tenant validada;
2. textos em pt-BR (UTF-8) em backend e frontend;
3. tratamento de erro amigável para usuário;
4. logs sem dados sensíveis;
5. testes de fluxo crítico passando;
6. atualização deste arquivo com status real.

## 9. Próximo passo recomendado

Próximo passo de desenvolvimento: **Fechar a Fase 1 da Loja (L1 e L4 primeiro)**.

Ordem sugerida:

1. edição completa de venda/pedido com recálculo e estoque;
2. fechamento do checkout dual integrado/manual;
3. produto avançado (fotos, variações e promoção);
4. subcategorias e taxa por forma de pagamento.
