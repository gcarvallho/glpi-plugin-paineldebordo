# Painel de Bordo

**Central operacional para o GLPI 11** — painéis NOC, relatórios, BI e um modo TV pronto para telão, tudo em uma única aplicação, sem iframes.

[![Versão](https://img.shields.io/badge/vers%C3%A3o-2.34.0-E73E11)](changelog.txt)
[![GLPI](https://img.shields.io/badge/GLPI-11.0%2B-09141F)](https://glpi-project.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4)](https://www.php.net/)
[![Licença](https://img.shields.io/badge/licen%C3%A7a-GPLv2%2B-blue)](COPYING.txt)

O GLPI é excelente para registrar chamados, mas responder **“como estamos agora?”** costuma exigir muitos cliques. O Painel de Bordo entrega essa resposta em uma tela: indicadores em tempo real, filas por status, gráficos, relatórios exportáveis e um mural que pode ficar exposto na parede da equipe.

---

## Recursos

### Visão geral (NOC)
Mural operacional com KPIs de fluxo e de snapshot, filas por status e gráficos. Cada usuário personaliza o próprio mural — escolhe quais blocos aparecem, reordena e define o gráfico de destaque.

### Chamados
Listas filtráveis e ordenáveis por recorte: **abertos**, **para mim**, **abertos por mim**, **onde sou observador** (direto ou via grupo), **por grupo** e **por entidade** — sempre respeitando as permissões do GLPI.

### Gráficos e relatórios
Catálogo de gráficos com tela cheia e exportação em JPG/PDF, e cerca de 50 relatórios (SLA, custos, tarefas, sínteses, categorias, localidades…) com exportação em **CSV** e **PDF**. Todo PDF abre para pré-visualização antes de salvar.

### BI Studio
Canvas de widgets arrastáveis (KPI, gráfico e texto) com abas, período por página, modos visualizar/editar e tela cheia. Cada usuário monta seus próprios painéis.

### NOC de Ativos
Panorama do inventário: computadores, monitores, equipamentos de rede, impressoras, telefones e periféricos — cruzando discos, memória, agentes, sistemas operacionais, licenças e informações financeiras.

### Modo TV
Wallboard para telão, com atualização automática, avisos sonoros e visuais de novos chamados, e colunas configuráveis (incluindo *Aguardando validação* e *Solução aprovada*). O pareamento da TV é no estilo dos apps de streaming: a tela mostra um código, alguém autoriza pelo celular ou PC, e o telão passa a operar **sem manter uma sessão logada exposta**.

### Personalização
Cores, logotipo, favicon e nome do produto configuráveis — com paletas independentes para tema claro e escuro.

### Logs e auditoria
Quem está usando o painel agora (com foto), trilha de ações sensíveis (mudança de configuração com o antes/depois, exportações, pareamento de TV e **tentativas de acesso negado**) e o histórico de logins do próprio GLPI. Exportável em CSV/PDF, com retenção configurável e purga automática.

---

## Requisitos

| Item | Versão |
|------|--------|
| GLPI | 11.0 ou superior |
| PHP | 8.1 ou superior |
| Extensões PHP | `json` e (`mysqli` ou `pdo_mysql`) |
| Banco | MySQL 8+ / MariaDB 10.4+ |

## Instalação

1. Baixe o `.zip` da versão desejada em **[Releases](../../releases)** (ou empacote a partir do código-fonte — veja [Empacotamento](#empacotamento)).
2. Descompacte dentro da pasta de plugins do GLPI, mantendo o nome da pasta como `paineldebordo`:
   ```
   glpi/marketplace/paineldebordo     (ou glpi/plugins/paineldebordo)
   ```
3. No GLPI, acesse **Configurar → Plugins**, e clique em **Instalar** e depois em **Ativar**.
4. Libere o acesso aos perfis em **Administração → Perfis → aba Painel de Bordo**.

O menu aparece no topo do GLPI como **Inovare Hub**.

### Vindo do ST-Dashboard (plugin anterior)

O Painel de Bordo é a evolução do plugin **ST-Dashboard** (pasta `dashboard`), e **os dois não convivem**. Ao instalar, ele *migra* o que existia: renomeia as tabelas `glpi_plugin_dashboard_{count,map,config}` para o novo prefixo e converte o direito `plugin_dashboard` em `plugin_paineldebordo`. Isso preserva o mapa de localidades, as configurações e as permissões já concedidas — mas deixa o plugin antigo sem dados e sem direito, ou seja, inutilizado.

> ### ⚠️ Nunca use "Desinstalar" no ST-Dashboard
>
> A desinstalação dele executa `DROP TABLE` nas três tabelas, **sem `IF EXISTS`**. Isso dá problema nos dois momentos possíveis:
>
> - **Antes** de instalar o Painel de Bordo → apaga justamente os dados que seriam migrados (mapa e configurações se perdem para sempre).
> - **Depois** de instalar → as tabelas já foram renomeadas, o `DROP` falha e o GLPI 11 lança exceção, interrompendo a desinstalação com erro.
>
> O caminho correto é **desativar** e depois apagar a pasta do servidor — nunca desinstalar.

Ordem recomendada:

1. **Faça backup do banco.** A migração não tem volta automática.
2. Em **Configurar → Plugins**, clique em **Desativar** no ST-Dashboard (e só isso).
3. Instale e ative o Painel de Bordo (passos 1 a 3 acima). A migração roda sozinha e o resultado fica registrado no log de instalação.
4. Confira que o mapa e as configurações vieram junto, e que os perfis continuam com acesso.
5. Só então remova a pasta `dashboard` do servidor.

Instalando num GLPI que nunca teve o ST-Dashboard, nada disso se aplica — o instalador apenas registra "nenhuma tabela legada para renomear" e segue.

## Permissões

O plugin usa direitos próprios do GLPI, por módulo:

| Direito | Dá acesso a |
|---------|-------------|
| `plugin_paineldebordo` (mestre) | Aplicação, Visão geral, Modo TV e pareamento |
| `plugin_paineldebordo_tickets` | Listas de chamados |
| `plugin_paineldebordo_analysis` | Gráficos, Relatórios e BI |
| `plugin_paineldebordo_resources` | Ativos e Mapa |
| `plugin_paineldebordo_groups` | Visão ampla de grupos (além dos seus) |

**Configuração** e **Logs e auditoria** são restritos a Super-Admin. Detalhes em [`docs/PERMISSIONS.md`](docs/PERMISSIONS.md).

---

## Contribuindo

Contribuições são bem-vindas. O fluxo é o convencional:

1. Faça um *fork* e crie um branch a partir de `main` (`git checkout -b minha-melhoria`).
2. Implemente seguindo as **regras de ouro** abaixo.
3. Garanta que as duas suítes de teste passam.
4. Abra um *Pull Request* descrevendo o problema resolvido e como testar.

### Regras de ouro

Estas regras não são estilo pessoal — elas mantêm o plugin coerente e são verificadas por testes:

1. **Correção não muda a versão MINOR.** Bugfix e polimento sobem o PATCH (`2.34.0` → `2.34.1`); funcionalidade nova sobe o MINOR (`2.34.0` → `2.35.0`). Ver [`docs/VERSIONING.md`](docs/VERSIONING.md).
2. **Nunca remova o que está funcionando** para "simplificar" — só quando explicitamente solicitado.
3. **Interface em português.** Os *msgids* no código podem ser em inglês, mas `locales/pt_BR.po` (e o `.mo` compilado) precisam cobrir tudo.
4. **Nunca só texto** em ações, menus e preferências: sempre ícone + rótulo.
5. **Botão só com ícone exige tooltip** (`class="ho-tip" data-tip="…"` + `aria-label`).
6. **Um bump de versão só está completo** quando `setup.php`, `README.md`, `changelog.txt`, a documentação afetada e os testes de versão estão alinhados.

O design system está documentado em [`docs/DESIGN.md`](docs/DESIGN.md) — leia antes de criar componentes; provavelmente já existe um que serve.

### Rodando os testes

```bash
php tests/run_all.php
```

```bash
powershell -ExecutionPolicy Bypass -File tests/run_smoke.ps1
```

A primeira suíte cobre lint, testes unitários, ACL e assets; a segunda faz verificações estruturais (versões, i18n, rotas, regras do design system). **As duas precisam passar** antes de um PR. Mais detalhes em [`docs/TESTING.md`](docs/TESTING.md).

### Traduções

Edite `locales/pt_BR.po` e recompile o `.mo`:

```bash
powershell -ExecutionPolicy Bypass -File tests/build_mo.ps1
```

### Empacotamento

O `.zip` de distribuição deve conter a pasta `paineldebordo/` na raiz, **com caminhos usando barras normais** (`/`) — as ferramentas nativas do Windows gravam `\` e quebram a extração no Linux. Não inclua `_legacy_ref/`, `.git/` nem `Thumbs.db`.

---

## Estrutura do projeto

```
paineldebordo/
├── setup.php              # Registro do plugin, versão, firewall das rotas públicas
├── hook.php               # Instalação/desinstalação (tabelas, cron, perfis)
├── inc/
│   ├── layout.inc.php     # Chrome do app: navegação, tema, avatares, tooltips
│   ├── access.inc.php     # Direitos e regras de visibilidade
│   ├── audit.inc.php      # Logs e auditoria
│   ├── branding.inc.php   # Personalização visual
│   ├── icons.inc.php      # Ícones SVG embutidos
│   └── services/          # Regras de negócio (chamados, relatórios, BI, ativos…)
├── public/
│   ├── shell.php          # Roteador único da aplicação
│   ├── tv.php             # Modo TV (página autônoma)
│   ├── views/             # Telas
│   ├── ajax/              # Endpoints JSON
│   └── css/dashboard-tokens.css   # Design system
├── locales/               # Traduções
├── tests/                 # Suítes de teste
├── docs/                  # Documentação técnica
└── _legacy_ref/           # Museu do código legado (referência, não distribuído)
```

## Documentação

| Documento | Conteúdo |
|-----------|----------|
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Como as peças se conectam |
| [`docs/DESIGN.md`](docs/DESIGN.md) | Design system, componentes e princípios de UX |
| [`docs/PERMISSIONS.md`](docs/PERMISSIONS.md) | Modelo de direitos e visibilidade |
| [`docs/VERSIONING.md`](docs/VERSIONING.md) | Semver e regras de release |
| [`docs/TESTING.md`](docs/TESTING.md) | Estratégia e execução dos testes |

O histórico de mudanças de cada versão está em [`changelog.txt`](changelog.txt) (português e inglês).

## Licença

Distribuído sob a **GNU General Public License v2 ou posterior** — veja [`COPYING.txt`](COPYING.txt).

## Créditos

Desenvolvido por [gcarvallho.dev](https://wa.me/5591985390491) e [Inovare](https://inovareempreendimentos.com.br), com Stevenes Donato.
