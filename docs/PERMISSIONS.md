# Permissões — Painel de Bordo 2.32.1

## Matriz (aba Perfis)

| Right | Leitura | Atualização |
|-------|---------|-------------|
| **Acesso ao Painel** (`plugin_paineldebordo`) | Entrar no app (Visão geral + Modo TV) | — (sem coluna) |
| **Visão ampla de grupos** (`plugin_paineldebordo_groups`) | Ver **todos** os grupos nos filtros/chamados/TV | — (sem coluna) |
| **Chamados** (`plugin_paineldebordo_tickets`) | Menu Chamados | — |
| **Análise** (`plugin_paineldebordo_analysis`) | Gráficos, Relatórios, BI | Salvar layout BI e mural da Visão geral |
| **Recursos** (`plugin_paineldebordo_resources`) | Mapa, Ativos | Editar coordenadas do mapa |
| **Admin** | — | Só **Super-Admin** (nome `Super-Admin` / `Super-Administrador` **ou** right GLPI `config` UPDATE) |

### Regras importantes

- **UPDATE no acesso ao Painel não libera grupos.** Visão ampla é o right `plugin_paineldebordo_groups` (ou Super-Admin).
- Sem visão ampla: dropdown GRUPO e SQL usam só `glpigroups` do usuário.
- Super-Admin sempre tem visão ampla (runtime), além do checkbox no perfil.
- Entidade: se o usuário só tem **1** entidade, o select fica **travado** nela (sem “Todas as entidades”).

### Breaking 2.32.0 (upgrade)

Quem tinha só **Atualizar** no master e via todos os grupos **perde** essa visão até o admin marcar **Visão ampla de grupos** na aba Perfis. Intencional (least privilege).

## Defaults

| Perfil | Acesso | Visão ampla | Chamados | Análise | Recursos |
|--------|--------|-------------|----------|---------|----------|
| Super-Admin (install) | ✓ | ✓ | ✓ | R+U | R+U |
| Técnico típico | ✓ | ✗ | ✓ | conforme admin | conforme admin |
| Overview-only | ✓ | ✗ | ✗ | ✗ | ✗ |

Migrate: rows novas de módulos faltantes começam em **0** (não auto-READ×3). Rows já existentes **não** são sobrescritas.

## Dívida (documentada, não implementada)

Mural da Visão geral continua gated por **Análise UPDATE**. Split futuro opcional para capability própria.

## Onde é aplicado

- Menu: `plugin_paineldebordo_nav_tree()`
- Páginas: `public/shell.php`
- AJAX: boards/layouts por módulo
- Grupos: `seesAllGroups()` / `sqlGroupScope()` / `tickets_scope()`
- Config / branding / purge TV: Super-Admin estrito

## Configuração

GLPI → Administração → Perfis → aba **Painel de Bordo** (bloco de ajuda acima da matriz).

## Testes

Matriz adversarial: [`QA_ADVERSARIAL.md`](QA_ADVERSARIAL.md).
