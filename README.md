# Painel de Bordo 2.34.0

App único (Tabler-like + DS Inovare). Overview NOC + studio **BI** + **NOC de Ativos** (inventário).

## Pasta
`plugins/paineldebordo`

## Versionamento
Semver `MAJOR.MINOR.PATCH` — ver [`docs/VERSIONING.md`](docs/VERSIONING.md).

- **PATCH** (`2.32.x`): correção e polimento
- **MINOR** (`2.32.0`): feature nova
- **Regra de ouro:** nunca retirar o que funciona, salvo pedido explícito; UI em PT-BR; ícone+texto

## Novidades 2.34.0
- **Auditoria de verdade**: agora registra **tentativa de acesso negado** (quem tentou abrir Configuração/Logs ou um módulo sem permissão) — destacada em vermelho na lista
- **Mudança de configuração diz o que mudou**: em vez de só "branding", grava os campos alterados com valor antes → depois
- **Exportar o log** em CSV e PDF (respeitando os filtros da tela); a própria exportação fica registrada
- Tabela com **paginação** (100 por página), **filtro por usuário**, e colunas novas de **dispositivo** (navegador/SO) e **entidade**
- **Purga por cron do GLPI** (Configurar → Ações automáticas), além da purga em tempo de uso — assim a retenção vale mesmo com o painel parado; presenças antigas também são limpas

## Novidades 2.33.1
- Correção definitiva dos warnings de `includes.php` no log: o guard de bootstrap do GLPI 11 agora está em **todos** os 32 pontos de entrada (`shell.php`, todos os `ajax/`, tickets, métricas, relatórios) — na 2.32.16 só os arquivos do Modo TV tinham
- Logs: tabelas passam a usar `TIMESTAMP` (o GLPI 11 desencoraja `DATETIME`); quem instalou a 2.33.0 é migrado automaticamente
- Logs: "Logins do GLPI" mostrava `#0` sem foto — o GLPI não guarda o id do usuário nesses eventos; agora o usuário é resolvido pelo login na mensagem (com avatar), e o que não dá pra resolver aparece como "—"
- Retenção de logs: padrão passa a ser **30 dias** (antes era "herdar GLPI")
- Design system: **botão só-ícone agora exige tooltip** (regra de ouro). O menu Exportar dos gráficos ficou só com ícone + tooltip

## Novidades 2.33.0
- **Logs e auditoria** (novo, no menu Admin, só Super-Admin): mostra quem está usando o Painel de Bordo (ativos agora, com foto), a trilha de ações sensíveis (abrir o painel, alterar configuração, exportar relatório/gráfico, parear/revogar TV) e o histórico de logins do GLPI — tudo com avatar do usuário. Retenção herda a política do GLPI por padrão, com override opcional em dias (purga automática)
- Correção: exportar gráfico como **JPG** saía com fundo preto; agora sai sempre com fundo branco (a exportação força fundo opaco e cores claras, independente do tema da tela)

## Novidades 2.32.16
- Correção definitiva do Modo TV em produção: `tv_board.php`/`tv_events.php` (e demais endpoints públicos) não geram mais o warning `include(/var/inc/includes.php)` no log. No GLPI 11 o núcleo já está carregado antes do arquivo rodar, então o bootstrap clássico é pulado em vez de calcular o caminho por `dirname` (que falhava em instalações Docker/volume)

## Novidades 2.32.15
- Tooltip de prioridade não repete mais o valor; tooltips do menu principal removidos onde o rótulo já está visível; auditoria de `title=` nativo residual em todo o plugin
- Exportar PDF (relatórios): botão com aparência real de botão; mostra quem gerou e quando; abre em nova aba pra pré-visualizar antes de baixar
- Gráficos: botões JPG/PDF agrupados num menu "Exportar"; PDF também pré-visualiza antes de baixar; corrige título ausente e fonte incorreta na exportação

## Novidades 2.32.14
- Tooltip (`ho-tip`): corrige balão sendo cortado pela borda das colunas do Modo TV — agora posicionado em JS (`position: fixed`, calculado via `getBoundingClientRect()`), sempre acima de tudo, em todo o plugin
- Relatórios: novo botão **Exportar PDF** ao lado do CSV — cabeçalho com marca, período e data de geração, tabela com bordas, rodapé paginado; reaproveita o TCPDF do próprio GLPI (sem biblioteca nova vendorizada)

## Novidades 2.32.13
- TV: prévia da descrição preserva títulos de seção (negrito) e quebras de linha em vez de virar texto corrido — importante pra descrições de formulário (pergunta/resposta)

## Novidades 2.32.12
- TV: corrige tamanho do ícone da categoria no card, que renderizava maior que os demais ícones da linha de datas mesmo com a fonte igual

## Novidades 2.32.11
- TV: corrige barra de rolagem horizontal indevida nas colunas; ícone no link "+N mais"; coluna extra **Observador** no quadro (Exibição → Visões extras); categoria movida pra linha própria abaixo do título
- Prévia: mostra a descrição completa (sem corte, com quebras de linha preservadas), modal um pouco maior com rolagem
- Tooltips: corrige balão cortado (id, prioridade, técnico, solicitante, grupo, observadores, categoria, título) causado pelo próprio truncamento de texto

## Novidades 2.32.10
- Correção: `tv_board.php`/`tv_events.php`/`tv_unpair.php`/`tv.php` paravam de carregar em produção (rota sem sessão do GLPI 11 não ajustava o diretório antes do `include`)
- Chamados: nova visão **Observador** (onde eu — ou meu grupo — sou observador)
- TV: card com **categoria** do chamado (junto das datas); botão **Prévia** (modal com a descrição); **"+N mais"** vira link pra lista filtrada (inclusive Validação/Aprovação)
- Tooltips estilizados (`ho-tip`) em todo o plugin, com rótulo + valor em vez do balão nativo do navegador repetindo o texto

## Novidades 2.32.9
- Relatórios e Gráficos: grade de tiles trocada por lista compacta ícone + nome completo (sem cortar); os 48 relatórios que caíam no ícone genérico agora têm ícone real; ícones de técnico/solicitante unificados com o padrão do Modo TV

## Novidades 2.32.8
- TV: card com técnico e solicitante em selos irmãos de mesmo peso lado a lado (quebram em telas estreitas); grupo volta a linha simples; ícone de headset pro técnico, pessoa pro solicitante, olho pros observadores; ícones unificados entre o card e a lista "Campos do card" nas preferências
- Configuração → Personalização: cores próprias pro modo escuro (primária/fundo/superfície/texto), além das já existentes pro claro
- Pareamento de TV: reabrir a tela no mesmo aparelho reaproveita o código pendente (renova o prazo) em vez de duplicar; códigos pendentes expirados somem sozinhos da lista de dispositivos

## Novidades 2.32.7
- TV: card reorganizado — selo único de grupo+técnico (posse do chamado) em destaque; solicitante vira linha simples abaixo; rodapé com cor por nível de prioridade (Muito baixa a Major)

## Novidades 2.32.6
- AJAX: 7 endpoints (overview/bi/assets) voltam a responder JSON `forbidden` quando falta o direito base do plugin, em vez de página HTML do core
- Ícone + rótulo: KPIs de listas de chamados, botões do hub de Configuração, exportar JPG/PDF, paginação de Ativos e modais (BI/Config) que estavam só texto
- i18n: completa `pt_BR.po` (arquivo/URL, logos, ajuda de escopo/upload, upload de marca, "Período", instalação, Visão geral)
- Hardening: remove do pacote arquivos legados órfãos com Bootstrap 2/Font Awesome/jQuery local/shim IE não roteados (movidos para `_legacy_ref/`)

## Novidades 2.32.5
- TV: ícones de grupo/técnico/observador; card folgado com 1–3 colunas; observadores não somem no modo denso; toasts no **inferior direito**

## Novidades 2.32.4
- Listas de chamados: **Todos** no Status inclui Solucionado/Fechado; KPI **Abertos** continua só abertos

## Novidades 2.32.3
- TV: KPIs com ícone + rótulos curtos; **Aprovado**; idade/observadores com ícone; cards densos com ≥6 colunas

## Novidades 2.32.2
- TV: título da coluna **Em atendimento** (sem “(atribuído)”); cabeçalhos em 1 linha com altura uniforme

## Novidades 2.32.1
- Fix: Desabilitar o plugin em Config → Plugins não deixa a página rodando infinitamente (ACL pesada saiu do `plugin_init`)

## Novidades 2.32.0
- ACL: direito **Visão ampla de grupos** (UPDATE master não libera mais todos os grupos — breaking)
- Aba Perfis com labels/ajuda honestos; entidade única travada no filtro
- TV: **Visão do mural** (filtra Novo/Atribuído/…) + **Visões extras** mantidas
- Docs PERMISSIONS/QA/ARCHITECTURE alinhados

## Novidades 2.31.0
- TV: **Solução aprovada** (solução aceita pelo requerente, status ACCEPTED); KPIs hoje/semana/mês = volume **criado** no período
- Períodos do shell: **Mês** (calendário) e **Geral**; migração de `30d` → `month`
- Chamados: **Abertos por mim**; ordenar/filtrar listas; TV pref da mesma visão
- UX: ícones recolher/expandir menu; paleta ampliada de gráficos; gráficos do detalhe de ativo no NOC corrigidos

## Novidades 2.30.0
- TV: faixa KPI visível no dark; coluna “Em atendimento (atribuído)”; KPI “No mês”; prefs por grupo / para mim / por entidade (ACL)
- Chamados: nav **Para mim**; links para o chamado no GLPI; KPI **Abertos**; período **Hoje**
- Filtros: entidade/grupo padrão do usuário na 1ª visita; tipagem de cards NOC Ativos
- ACL: opções de grupo sem vazamento `is_recursive`; Read/Update do perfil em PT-BR; avatar no menu superior

## Novidades 2.29.8
- Install: rebuild da tabela `config` (contorna MySQL 1170); log em Administração → Logs

## Novidades 2.29.7
- Install: remove índices secundários antes de TEXT

## Novidades 2.29.6
- Install: alargar `config.value` para TEXT

## Novidades 2.29.5–2.29.0
- Avatar menu / logo marketplace

## Novidades 2.28.7
- TV: dicas CTRL+M / CTRL+Espaço a cada carga (3s)

## Novidades 2.28.6
- Cards com título cortado: hover mostra o nome completo (tooltip)

## Novidades 2.28.5
- TV: scrollbar das colunas igual ao menu lateral

## Novidades 2.28.4
- Favicon: cor da Personalização passa a valer de verdade (sem SVG estático laranja concorrendo)

## Novidades 2.28.3
- Tema escuro: fonte **Source Sans 3** preservada (`--ho-font` no `:root`)

## Novidades 2.28.2
- GLPI **Configurar** (chave) → hub **Configuração** (`public/config.php`)
- Nav **rail + superior** unificada: só clique (sem hover concorrente / dois menus abertos)

## Novidades 2.28.1
- Personalização: **Restaurar padrão** ao lado de Salvar
- Créditos: **gcarvallho.dev** (WhatsApp) / **Inovare** (site) / Stevenes Donato

## Novidades 2.28.0
- TV: **modo escuro** consertado (branding não sobrescreve tokens)
- TV abre em **modo claro**; botão sol/lua + atalho **CTRL+Espaço**
- Toasts: **Som ativado** / **Modo escuro|claro ativado**

## Novidades 2.27.7
- Pareamento TV: countdown usa `ttl_sec` (fim do “182 min” por fuso sem TZ)
- Personalização: cor do **favicon** configurável
- Instalação: feedback por etapa restaurado na UI de Plugins

## Novidades 2.27.6
- Rail: flyout dos grupos volta a abrir no clique/hover (overflow + `is-open`)

## Novidades 2.27.5
- Pareamento: countdown em **min + s**
- Ícones: Visão geral = gráficos; Modo TV = TV (não engrenagem); Recursos = racks
- Favicon SVG (DASHBOARD) nas cores Inovare

## Novidades 2.27.4
- Menu **Modo TV**: **Parear TV** + **Abrir TV logado**
- Pareamento: API `tv_pair_api.php` (evita 403 do `/ajax/` sem login)

## Novidades 2.27.3
- TV: som ativo por padrão (não inicia mudo)
- Pareamento TV: create via GET + paths stateless (fim do 403 CSRF / `create_failed`)
- Links/QR: ignora `url_base` em localhost quando o host real é público

## Novidades 2.27.2
- Corrige load do plugin: `migrateModuleRights` idempotente (MySQL 1062 derrubava o plugin → “Não instalado”)

## Novidades 2.27.1
- QA adversarial: [`docs/QA_ADVERSARIAL.md`](docs/QA_ADVERSARIAL.md) (personas, páginas, AJAX, abuso, TV) + suite `tests/run_acl_tests.php`

## Novidades 2.27.0
- Permissões por módulo (Chamados / Análise / Recursos); menu **Admin** só Super-Admin — ver [`docs/PERMISSIONS.md`](docs/PERMISSIONS.md)
- Modo TV: corrige mute ao desmarcar “Lembrar mudo”; **CTRL+M** + tip empilhável

## Novidades 2.26.0
- BI: palette em acordeões **Chamados** / **Ativos** / Texto; widgets de Ativos (indicadores, gráficos e listas de alerta)
- BI: modal DS ao sair da edição sem salvar (Ver / Cancelar / menu)
- Polimento: scrollbar do menu lateral; confirms do Config no modal DS

## Novidades 2.25.2
- BI: nova aba não “apaga” Operação ao voltar; botão ✓ + toast ao renomear aba (ainda precisa **Salvar** para gravar)

## Novidades 2.25.1
- Parque: ícones na mesma linha dos Indicadores; link TV com `root_doc`; debug `?tv_pair_debug=1`

## Novidades 2.25.0
- NOC de Ativos: clique no indicador → listagem paginada → detalhe com gráficos de disco/RAM (snapshot) + Abrir no GLPI

## Novidades 2.24.4
- BI/AJAX: header XHR para CSRF não consumir o token (salvar de novo); draft após arrastar/redimensionar

## Novidades 2.24.3
- Menu superior: grupos fechados ao atualizar/mudar página (só abrem no clique); filtro Entidade em PT-BR

## Novidades 2.24.2
- BI Studio: Salvar + feedback, período sem reset, abas, títulos PT-BR, UI sem confirm nativo

## Novidades 2.24.1
- Fix TV pair AJAX/URL, Open link i18n, branding CSS order + PRG, rail flyout

## Novidades 2.24.0
- Personalização (cores, logos locais, textos) + menu lateral recolhível (rail)

## Novidades 2.23.7
- TV Pair: código|QR lado a lado; countdown + renovação; TTL 3/5/10 min; apelido; dispositivo/IP/fuso

## Novidades 2.23.6
- Config TV: copiar link de pareamento; Apagar registros; status em PT-BR; botões alinhados ao DS

## Novidades 2.23.5
- BI: novos widgets aparecem abaixo do conteúdo (não fora da tela); **Cancelar** e **Restaurar padrão** na barra de edição

## Novidades 2.23.4
- TV: QR para autorizar no celular (login GLPI); Sair revoga o dispositivo; token revogado não reentra via sessão

## Novidades 2.23.3
- BI: Salvar/Nova página/paleta só no modo Editar; adicionar widgets deixa de ser descartado; arrastar pelo cabeçalho

## Novidades 2.23.2
- Ativos: PT-BR completo; **parque** (não “frota”); tipos/tabelas sem cair no inglês do core

## Novidades 2.23.1
- Ativos: corrige SQL de agentes (JOIN) que derrubava a página; filtro de grupo inválido não gera mais erro de acesso; redirect legado absoluto

## Novidades 2.23.0
- **NOC de Ativos**: parque completo (computadores, monitores, rede, impressoras, telefones, periféricos)
- Alertas de inventário: disco quase cheio, candidatos a ampliação de RAM, inventário/agente defasado, garantia e licenças a vencer
- Gráficos: composição por tipo, status/fabricante/SO; AJAX `assets_board`

## Novidades 2.22.1
- Mapa: corrige tiles quebrados — CSS Leaflet no caminho certo (`map/css`), URL absoluta, `invalidateSize`

## Novidades 2.22.0
- BI: **Tela cheia** (kiosk como o Modo TV)

## Novidades 2.21.2
- BI: rascunho local ao editar

## Novidades 2.21.0
- **BI Studio** substitui Métricas
