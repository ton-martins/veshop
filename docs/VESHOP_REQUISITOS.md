# Veshop - Requisitos do Produto e da Plataforma

Versão: v1.1  
Última atualização: 14/03/2026

## 1. Visão do produto

O Veshop é um SaaS multiempresa para operação de contratantes, com foco em gestão prática e execução diária.

Objetivos principais:
- Centralizar operação comercial/serviços em uma única plataforma.
- Reduzir retrabalho entre vendas, estoque, financeiro e atendimento.
- Permitir branding por contratante.
- Entregar experiência simples para uso diário em desktop, tablet e celular.

## 2. Papéis e acessos (escopo atual)

Papéis ativos no sistema:
- `master`: dono do produto (gestão da plataforma).
- `admin`: usuário do contratante.

Não há cadastro público de contratantes/usuários.  
O `master` é responsável por cadastrar e manter contratantes e planos.

## 3. Modelo organizacional (contractor-first)

Padrões definidos:
- O sistema usa o termo **contractor** (não usar “tenant” na modelagem funcional).
- Usuário pode estar vinculado a múltiplos contratantes (relação N:N em `contractor_user`).
- Cada contratante possui:
  - branding própria (nome, cor primária, logo, avatar),
  - plano ativo,
  - nicho de negócio único.

Regra de negócio:
- O nicho do contratante é definido administrativamente e **não é editável pelo admin do contratante**.

## 4. Nichos e segmentação de módulos

Nichos ativos:
- `commercial` (Comércio)
- `services` (Serviços)

Regra central:
- O sistema deve exibir apenas módulos compatíveis com o nicho do contratante ativo.

### 4.1 Módulos do nicho Comércio

- Início (Visão Geral)
- Produtos
- Categorias
- Clientes
- Fornecedores
- Pedidos
- Estoque
- Financeiro:
  - Contas a pagar
  - Contas a receber
- Relatórios
- PDV (camada visual em construção dentro da visão geral)

### 4.2 Módulos do nicho Serviços

- Início (Visão Geral)
- Catálogo de serviços
- Categorias de serviços
- Ordens de serviço
- Agenda

## 5. Navegação e padrões de layout consolidados

### 5.1 Sidebar

- Bloco superior fixo do sistema: **Veshop**.
- Ícone do sistema:
  - usar ícone Veshop quando existir,
  - fallback para iniciais `VS` quando não houver ícone.
- Abaixo do nome do sistema: exibir **badge do nicho ativo**.
- No bloco do contratante: exibir **badge do plano ativo**.
- Menu com grupos de navegação.
- Modo colapsado funcionando sem quebrar rodapé.

### 5.2 Header de páginas internas

Padrão atual:
- Header simplificado: apenas título da página (sem ícones e sem fundo decorativo).
- Rotas internas em inglês, labels de menu em pt-BR.

### 5.3 Mobile

- Topo mobile com ações essenciais.
- Bottom navigation em estilo app.
- Primeiro item do menu bottom: `Menu`.

### 5.4 Notificações

- Botão de notificações flutuante global no canto inferior direito.
- Estilo do botão: fundo slate escuro (alinhado ao ícone/menu do Veshop).

## 6. Padrões de Auth

- Login e páginas Auth padronizadas visualmente.
- Botões das páginas Auth devem seguir o mesmo padrão do botão principal da tela de login.
- Card “Ecossistema Veshop” aparece **somente** na tela de login.

## 7. Landing page (estado consolidado)

- Conteúdo em pt-BR e UTF-8.
- FAQ com capitalização natural (apenas primeira letra maiúscula, sem “capitalize” artificial).
- Cards com `cursor: pointer` devem abrir modal explicativo.
- Card Veshop Ops:
  - interatividade interna estável,
  - cards inferiores clicáveis em estilo carrossel,
  - abertura de modal explicativo por card.
- Ajustes de carregamento para reduzir efeito de tela desformatada durante bootstrap dos assets.

## 8. Home / Visão Geral

Definições consolidadas:
- Nome de rota: `home` (inglês), label de menu: **Início**.
- Título da página: **Visão Geral**.
- Saudação: `Olá {nome}, acompanhe sua empresa`.
- Sessão “Seu Catálogo Público” com visual clean/profissional.
- Botão “Ver catálogo” seguindo cor/branding do contratante ativo.

## 9. Branding do contratante

- Tela de branding no padrão visual aprovado.
- Botão “Salvar alterações” no padrão de botão global do sistema.
- Contratante pode ajustar identidade visual, mas não altera nicho.

## 10. Seeders e dados de desenvolvimento

Contratantes definidos:
- Veshop Mix (nicho: comércio, perfil bazar)
- Veshop Store (nicho: comércio, perfil roupas)
- Veshop Services (nicho: serviços)

Usuários:
- Usuário `master` (já existente no projeto).
- Usuário `admin` Everton vinculado aos 3 contratantes.

Base de dados de desenvolvimento:
- Comércio: clientes, fornecedores, categorias e produtos por contratante.
- Serviços: categorias e catálogo de serviços.

## 11. Requisitos não funcionais

- Aplicação 100% responsiva (desktop, tablet e celular).
- Fluxos com boa usabilidade para público de 20 a 50 anos.
- Linguagem clara, direta, com baixa curva de aprendizado.
- UI diferenciada, mas sem poluição visual em fluxos transacionais.
- Padrão de idioma da plataforma: pt-BR.
- Codificação textual: UTF-8.

## 12. Diretrizes técnicas e organização

Backend:
- Estrutura modular por domínio e por nicho.
- Autorização por role e por módulo habilitado do contratante.
- Validações centralizadas com Form Requests.
- Controllers enxutos e regras em serviços quando necessário.

Frontend:
- Páginas por contexto de role (`Admin`, `Master`, `Auth`, `Public`).
- Componentes reutilizáveis para header, cards, tabelas, ações e feedback.
- Mesma linguagem visual entre módulos para reduzir curva de aprendizado.

## 13. Estratégia de execução (próximo passo)

Decisão vigente:
- O frontend foi priorizado para validação de UX.
- Próxima fase: desenvolvimento completo do backend, iniciando por **Comércio**.

Ordem sugerida para backend (Comércio):
1. Produtos e Categorias (CRUD + validações + políticas).
2. Clientes e Fornecedores.
3. Pedidos e itens de pedido.
4. Estoque e movimentações.
5. Financeiro (contas a pagar/receber).
6. Consolidação dos indicadores da Visão Geral.

## 14. Checklist de contexto para retomada em outra conta

Ao retomar o projeto, considerar como verdade:
- Sistema com dois papéis: `master` e `admin`.
- Segmentação por nicho é mandatória.
- Contratante vê apenas módulos do seu nicho.
- Layout padrão já definido (sidebar, header simples, botão flutuante de notificação, mobile app-like).
- Home principal é “Início / Visão Geral”.
- Landing e Auth já possuem padrão visual validado e interações de modal nos cards.
- Próxima macrofase é backend de Comércio, sem regressão de UX já aprovada.
