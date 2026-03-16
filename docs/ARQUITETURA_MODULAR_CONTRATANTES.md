# Arquitetura Modular de Contratantes

## Objetivo

Padronizar a evolução do sistema com separação clara entre:

- `business_niche`: visão macro do produto (`commercial` ou `services`).
- `business_type`: tipo real de operação do contratante (loja, barbearia, contabilidade etc.).
- `modules`: capacidades habilitadas por contratante.

## Modelo de dados

- `contractors.business_type`:
  - Define o perfil operacional do contratante dentro do nicho.
- `modules`:
  - Catálogo de módulos globais e específicos.
  - Campos chave: `code`, `scope`, `niche`, `business_types`, `is_default`.
- `contractor_module`:
  - Relação N:N entre contratantes e módulos habilitados.

## Regras aplicadas

1. Todo contratante recebe módulos obrigatórios globais.
2. Todo contratante recebe o módulo base do nicho (`commercial` ou `services`).
3. Módulos específicos são filtrados por nicho e tipo de negócio.
4. Se não houver seleção manual, aplica-se preset padrão por `business_type`.
5. Sem módulos configurados (legado), o sistema usa fallback por nicho.

## Fluxo de configuração (Master)

1. Definir nicho.
2. Definir tipo de contratante.
3. Revisar módulos globais.
4. Revisar módulos específicos.
5. Salvar contratante.

## Evolução recomendada

- Novos mercados entram por `business_type` + preset de módulos.
- Evitar espalhar `if` por nicho em controllers.
- Regras de habilitação devem ficar centralizadas em `ContractorCapabilitiesService`.
