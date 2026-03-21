# Veshop - Guia Mestre de Produto e Arquitetura

Versão: v4.2  
Última atualização: 21/03/2026

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
- É proibido converter textos funcionais para ASCII sem necessidade.
- Se houver texto sem acentuação por falha de implementação, a correção é obrigatória no mesmo ciclo de entrega.

### 2.1.1 Regra interna de implementação (obrigatória)

1. Toda implementação deve incluir revisão final de textos em pt-BR com acentuação correta.
2. A validação de UTF-8 deve ocorrer antes de considerar qualquer tarefa como concluída.
3. Se o output sair sem acentos, a entrega deve ser tratada como incompleta até correção.

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
- Fase 1 do módulo Loja concluída (L1 ao L10).

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
2. Checkout dual padronizado (integrado/manual), com CTA de WhatsApp no fluxo manual.
3. Pedidos e vendas no admin com modal de detalhes, edição completa, recálculo e auditoria.
4. PDV com página dedicada, taxas por forma de pagamento e edição segura.
5. Produtos com galeria (limite técnico de 5), variações (SKU/preço/estoque) e controle de quota.
6. Categorias com subcategorias refletidas na vitrine pública.
7. Notificações de pedidos em in-app e e-mail para admin e cliente.
8. Páginas legais (Termos e Privacidade) publicadas na landing institucional e vinculadas no footer global.

Status: **Concluído (Fase 1 finalizada, L1 ao L10)**.

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

## 5. Loja (física/virtual) - backlog completo de fechamento

Prioridade para finalizar o módulo comercial antes dos demais tipos:

| ID | Item | Status | Prioridade |
| --- | --- | --- | --- |
| L1 | Edição completa de pedido/venda com recálculo, ajuste de estoque e auditoria | Concluído | Crítica |
| L2 | Checkout dual padronizado (integrado/manual) | Concluído | Crítica |
| L3 | Taxa por forma de pagamento no total e exibição ao cliente | Concluído | Crítica |
| L4 | Produto com galeria de fotos (até 5), processamento e limite por plano | Concluído | Alta |
| L5 | Variações de produto (cor, tamanho, etc.) com SKU, preço e estoque por variação | Concluído | Crítica |
| L6 | Categorias com subcategorias refletidas na loja virtual | Concluído | Alta |
| L7 | Notificações operacionais (in-app/e-mail) no fluxo de pedido | Concluído | Alta |
| L8 | Páginas legais LGPD institucionais (landing page Veshop + footer global) | Concluído | Alta |
| L9 | Governança de storage por contratante/plano (quota e bloqueio de upload) | Concluído | Alta |
| L10 | Cobertura de testes críticos e hardening final de release | Concluído | Crítica |

### 5.1 Decisões fechadas para a Fase 1 da Loja

1. **Taxa no valor final:** a taxa de pagamento deve impactar o total final e ser exibida explicitamente ao cliente no checkout.
2. **Checkout manual sem gateway:** se não houver gateway integrado ativo, o pedido deve ser criado normalmente e a tela de sucesso deve oferecer CTA para WhatsApp do contratante com mensagem pre-montada.
3. **Persistência do pedido:** o pedido deve existir mesmo que o cliente não conclua a conversa no WhatsApp.
4. **Fotos por produto:** não será livre/ilimitado; haverá limite por plano, com teto técnico máximo de 5 fotos por produto.
5. **Controle de custo de storage:** uploads de imagem devem ser processados (compressão/padronização) e submetidos a quota por contratante.

### 5.2 Critério de conclusão do módulo Loja

1. pedido criado, pago (ou manual), faturado e concluído sem brecha de fluxo;
2. edição segura com auditoria e sem inconsistências de estoque/total;
3. checkout com composição de total transparente (subtotal, frete, taxa, total);
4. variações de produto operando em admin, loja e pedidos;
5. experiência consistente em desktop/mobile para loja e admin;
6. cobertura de testes nos fluxos críticos de checkout e operação.

## 6. Plano de execução da Fase 1 (Loja)

Status da Fase 1: **Concluída em 21/03/2026**.

### Etapa 0 - Alinhamento e contrato técnico (concluída)

1. consolidar este documento como fonte única do fechamento da Loja;
2. travar decisões de produto e regra de negócio da fase;
3. definir escopo "não entra" para evitar crescimento de backlog durante execução.

### Etapa 1 - Núcleo transacional (L1, L2, L3) - concluída

1. finalizar edição completa de pedido no admin, alinhada com a edição de venda;
2. padronizar checkout dual:
3. modo integrado quando houver gateway/método válido;
4. modo manual quando não houver integração ativa;
5. criar CTA WhatsApp no sucesso do checkout manual;
6. implementar taxa por forma de pagamento no pedido/venda e refletir no total final;
7. garantir snapshot da taxa no momento da compra para histórico.

### Etapa 2 - Catálogo avançado (L4, L5, L6, L9) - concluída

1. implementar galeria de imagens por produto com limite por plano e teto técnico;
2. implementar variações por atributos (cor, tamanho, etc.) com combinações válidas;
3. controlar preço/estoque/SKU por variação;
4. refletir seleção de variação no carrinho, checkout, pedido e PDV;
5. implementar subcategorias e navegação hierárquica na loja;
6. aplicar quota real de storage por contratante com bloqueio de upload ao atingir limite.

### Etapa 3 - Compliance e comunicação (L7, L8) - concluída

1. concluir notificações de pedido em in-app e e-mail;
2. publicar páginas legais de termos de uso e política de privacidade na landing page institucional do Veshop;
3. vincular os links legais no footer global do Veshop.

### Etapa 4 - Estabilização e release (L10) - concluída

1. fechar matriz de testes críticos e regressão;
2. revisar logs, auditoria e mensagens de erro para usuário final;
3. validar DoD completo e atualizar status final no documento.

## 7. Diretriz de storage de arquivos do contratante

Estratégia adotada no Veshop:

1. padrão inicial: storage centralizado pelo Veshop com isolamento por contratante;
2. segurança obrigatória: autorização por escopo, validação de acesso e links controlados;
3. quota por contratante definida por plano (fonte principal: `plans.storage_limit_gb`);
4. evolução futura: opção de storage dedicado por contratante em plano avançado.

Regras operacionais da Loja:

1. imagem de produto deve passar por processamento para reduzir consumo;
2. cada upload deve validar quota restante antes de persistir;
3. excluir/substituir imagem deve liberar espaço contabilizado;
4. limite técnico máximo de 5 fotos por produto;
5. sugestão de limite por plano:
6. planos até 1 GB: até 3 fotos por produto;
7. planos acima de 1 GB: até 5 fotos por produto.

Observação:

- storage dedicado deve ser tratado como feature enterprise com governança própria.

## 8. Definição de pronto (DoD)

Toda entrega é considerada concluída somente se tiver:

1. regra multi-tenant validada;
2. textos em pt-BR (UTF-8) em backend e frontend;
3. revisão final de acentuação pt-BR validada;
4. tratamento de erro amigável para usuário;
5. logs sem dados sensíveis;
6. auditoria de ações críticas;
7. testes de fluxo crítico passando;
8. sem regressão visual grave em desktop/mobile;
9. atualização deste arquivo com status real.

## 9. Matriz mínima de testes da Loja (obrigatória)

1. checkout integrado (Pix/gateway) com atualização de status;
2. checkout manual sem gateway com pedido criado e CTA WhatsApp;
3. aplicação de taxa por forma de pagamento no total exibido e persistido;
4. edição de pedido/venda com recálculo e estoque sem inconsistências;
5. variações de produto (selecionar, precificar, reservar/baixar estoque);
6. limite de fotos e bloqueio por quota de storage;
7. subcategorias filtrando corretamente a vitrine;
8. notificações de pedido para admin e cliente;
9. acesso às páginas LGPD pela landing page do Veshop e links válidos no footer global;
10. testes de segurança de escopo em rotas críticas.

## 10. Roadmap macro após Loja

### Fase 2 - Módulo Contábil (gerencial)

1. modelagem de dados contábeis gerenciais;
2. telas e fluxos operacionais mínimos;
3. relatórios gerenciais e auditoria de alterações.

### Fase 3 - Módulo Barbearia

1. agenda profissional com regras de disponibilidade;
2. fluxo de atendimento operacional completo;
3. dashboard específico do tipo de negócio.

## 11. Próximo passo recomendado

Próximo passo de desenvolvimento: **iniciar Fase 2 (Módulo Contábil gerencial) com discovery técnico e contrato de escopo**.

Ordem de implementação recomendada para a próxima fase:

1. modelagem do domínio contábil gerencial (plano de contas, centros de custo e entidades base);
2. definição dos fluxos operacionais mínimos (cadastros, lançamentos e conciliação por importação);
3. fechamento da matriz de testes críticos e critérios de aceite da Fase 2 antes de implementar UI.
