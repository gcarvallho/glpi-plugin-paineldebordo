# Design System — Painel de Bordo

## Princípio
Um único produto. Zero retalhos. Reescrever views; não embrulhar legado.

## UX (Design Thinking)
- **Ícone + 1 linha:** navegação, KPIs e tiles de hub devem ser escaneáveis sem ler parágrafos.
- **Proibido** usar parágrafo introdutório longo como UI principal; detalhes vão em tooltip (`ho-tip`, ver Componentes) — não usar `title=""` nativo puro quando o conteúdo repete o texto visível sem contexto.
- **Uma job por seção:** um título, um propósito, um grid visual.
- Chrome: toggles de tema/layout são **botões-ícone** com `aria-label`.
- **Regra de ouro — botão só-ícone exige tooltip:** todo controle que exibe **apenas ícone** (sem rótulo visível) **deve obrigatoriamente** ter `class="ho-tip"` + `data-tip="…"` (+ `aria-label` com o mesmo texto). Sem rótulo na tela e sem tooltip, o botão é um enigma. Quando há rótulo visível, o tooltip é dispensável (não repetir o óbvio — ver `ho-tip` em Componentes). Isso libera economizar espaço com segurança: ex. o menu **Exportar** dos gráficos é só ícone + chevron, com tooltip "Exportar".

## Cores (Inovare GLPI)
| Token | Hex |
|-------|-----|
| Primary / sider | `#09141F` |
| Accent / links / active | `#E73E11` |
| Bg | `#f4f6f8` |
| Surface | `#ffffff` |
| Text | `#09141F` |
| Danger | `#b00020` |

Tema escuro: sider `#09141F`, bg `#071017`, accent `#E73E11`.

## Stack UI
- Tabler-like / CSS do core GLPI (`$CFG_GLPI['root_doc']`)
- Override: `public/css/dashboard-tokens.css` (também inline via `ho-tokens-inline`)
- Layout: `inc/layout.inc.php`
- Ícones: `inc/icons.inc.php` (SVG stroke inline, estilo Tabler — **sem CDN**)
- Views: `public/views/`

## Componentes
- `ho-nav` + `ho-nav__icon` / `ho-nav__label`
- `ho-nav-group` / `__title` / `__items` / `__caret` (IA agrupada)
- `ho-kpi` + `ho-kpi__icon` / `__label` / `__value` / `__meta` (+ `ho-kpi-grid--8` na Overview)
- `ho-dash-hero` / `ho-dash-grid` / `ho-dash-chart` (Overview mural)
- `ho-panel__head` / `__title` / `__icon` / `__link` (painel de gráfico)
- `ho-tile` / `ho-tile__icon` / `ho-tile__title` / `ho-tile__value` (hubs)
- `ho-icon-btn` (toggles)
- `card` / `table` / `btn` / `badge` (ponte Tabler)
- `ho-tip` (classe + `data-tip="Rótulo: valor"`) — tooltip estilizado, hover **e** `:focus-visible`; substitui `title=""` nu. CSS (`.ho-tip-bubble`) em `dashboard-tokens.css`; posicionamento em JS (`plugin_paineldebordo_tip_bubble_js()`, `inc/layout.inc.php`) — bolha única `position: fixed` no `<body>`, calculada via `getBoundingClientRect()` no hover/foco do elemento `.ho-tip[data-tip]`. Não é pseudo-elemento preso ao elemento-gatilho: isso evitaria ficar cortado por um ancestral com `overflow` (ex.: colunas do Modo TV) e garante que sempre aparece acima de tudo, inclusive modais. Emitido uma vez por página em `plugin_paineldebordo_page_end()` (shell) e separadamente em `public/tv.php` (página standalone, não passa pelo shell)
- `ho-ds-modal` / `__backdrop` / `__card` / `__title` / `__body` / `__actions` (modal padrão — Config, BI, prévia de chamado no TV)
- `ho-menu` / `__trigger` / `__panel` / `__item` — dropdown compacto pra agrupar ações relacionadas em vez de espalhar botões pelo cabeçalho (ex.: "Exportar" no gráfico junta JPG+PDF); `plugin_paineldebordo_menu_toggle_js()` (`inc/layout.inc.php`, emitido junto do `.ho-tip`) cuida do toggle/click-fora/Escape

## Relatórios (exportação)
`public/views/report_run.php` exporta o mesmo `headers`/`rows` já usado na tela (`inc/services/reports.php`) em dois formatos:
- **CSV** — `fputcsv` com BOM UTF-8, `;` como separador (Excel PT-BR)
- **PDF** — `inc/services/report_pdf.php`, reaproveita o TCPDF que o próprio GLPI já traz (`class_exists('TCPDF')`), sem vendorizar biblioteca própria; paisagem A4, cabeçalho com marca + data de geração + quem gerou (usuário da sessão) + período + total de registros, rodapé com paginação. Abre inline no visualizador do navegador (`Output(..., 'I')`), não baixa direto — todo PDF do sistema deve poder ser pré-visualizado antes de salvar. Se o GLPI não expuser `TCPDF` (instalação atípica), cai num erro HTTP 500 amigável em vez de fatal. **Não usa** a lib `html2pdf`/TCPDF antiga que ficou vendorizada em `public/inc/html2pdf/` até 2.32.13 — movida para `_legacy_ref/html2pdf/` por ser incompatível com PHP 8 (ver `_legacy_ref/README.md`)

Exportação de gráfico (JPG/PDF, `home.php` e `chart_show.php`, via Highcharts `exportChartLocal`): PDF também abre em nova aba em vez de baixar direto — feito trocando `Highcharts.downloadURL` por uma versão que abre a blob URL, só para essa chamada (JPG mantém o download normal). O título do gráfico é um `<h3>` HTML separado do Highcharts (`title: {text: null}` no chart, pra não duplicar visualmente) — por isso a exportação injeta o título e uma fonte seguros (`Arial, Helvetica, sans-serif`, já que o renderizador de exportação não herda a fonte web da página) via segundo argumento de `exportChartLocal(exportingOptions, chartOptions)`, que não altera o gráfico ao vivo na tela. O `exportChartOptions` também força `backgroundColor: '#ffffff'` + cores claras de rótulo/grade: o chart ao vivo usa fundo transparente, e JPG (sem canal alfa) renderiza pixel transparente como **preto** — por isso JPG saía escuro; o fundo opaco na exportação resolve, e o export sai sempre claro independente do tema da tela.

## Logs e auditoria (Admin)
`public/views/logs_hub.php` (só Super-Admin, mesmo gate `canConfigure()` da Configuração; item no grupo Admin do nav). Lógica em `inc/audit.inc.php`:
- **Tabelas** `glpi_plugin_paineldebordo_accesslog` (trilha append-only) e `glpi_plugin_paineldebordo_presence` (upsert 1 linha/usuário → "ativos agora"), criadas no install pelo padrão idempotente do plugin.
- **O que registra**: `plugin_paineldebordo_audit_touch()` (chamado no `shell.php` a cada load) faz o upsert de presença + um `session_open` por sessão; `plugin_paineldebordo_audit_log($action,$detail,$page)` registra as ações sensíveis (`access_denied`, `config_change`, `export_report`, `export_chart`, `export_logs`, `tv_pair_approve`, `tv_device_revoke`) plugado em `config_post.inc.php`, `report_run.php`, `tv_pair_approve.php` e — pro export de gráfico, que é client-side — via beacon `public/ajax/audit.php` (ação em whitelist). Cada linha guarda também a **entidade ativa** e o `user_agent`.
- **`access_denied` é o registro mais valioso**: os três gates do `shell.php` (Configuração, Logs, módulo sem permissão) logam a tentativa antes de redirecionar. Badge vermelho (`.ho-log-badge--denied`) porque é o que um auditor procura primeiro.
- **`config_change` nomeia o que mudou** (campo: antes → depois), não só a seção — "alguém mexeu no branding" não é auditoria, "alguém trocou primary de X para Y" é.
- **Exportação**: `public/views/logs_export.php` (roteado no `shell.php` antes do chrome, igual `report_run.php`) gera CSV ou PDF honrando os filtros da tela e reaproveitando `plugin_paineldebordo_report_pdf_output()`. Exportar auditoria é auditável: gera um `export_logs`.
- **Tabela**: paginada (100/página, `audit_count()` + `LIMIT/OFFSET`), com filtro por ação, por usuário (`audit_users()`), busca e período.
- **Avatar por usuário**: `plugin_paineldebordo_user_avatar(int $uid)` / `plugin_paineldebordo_avatar_html()` (em `layout.inc.php`, cache por request) generalizam o `user_chrome()` pra resolver foto/iniciais de qualquer usuário. Componentes: `.ho-online-grid`/`.ho-online-card` (ativos agora), `.ho-log-user`/`.ho-log-badge` (tabela de acesso).
- **Logins do GLPI**: lidos de `glpi_events` (`service='login'`), não é dado nosso.
- **Retenção**: `logs_retention_days` (config `users_id=0`), **padrão 30 dias**. `0` = herdar GLPI (sem purga própria); `N>0` apaga registros > N dias. Roda por dois caminhos complementares: `plugin_paineldebordo_audit_purge()` inline com throttle de 1×/hora (rede de segurança, só roda se alguém abrir o plugin) **e** a CronTask `PluginPaineldebordoAudit::cronAuditpurge` (`inc/audit.class.php`, registrada no install, aparece em Configurar → Ações automáticas) — que é o que faz a retenção valer numa instância parada. Ambas chamam `plugin_paineldebordo_audit_purge_now()`, que também limpa presenças antigas.
- **Colunas de data são `TIMESTAMP`**, não `DATETIME` — o GLPI 11 emite deprecation (`checkForDeprecatedTableOptions`) a cada CREATE/ALTER com DATETIME. `plugin_paineldebordo_audit_modify_to_timestamp()` converte quem instalou a 2.33.0 (que saiu com DATETIME), checando `information_schema` antes pra ser no-op em instalação nova.
- **Logins do GLPI**: `glpi_events.items_id` **não** guarda o id do usuário em evento de login (vem 0/-1) — o login é o primeiro token da mensagem localizada ("jdoe fez login no IP…"). Por isso o join é `glpi_users.name = SUBSTRING_INDEX(TRIM(message),' ',1)`; linhas não resolvidas aparecem com "—" em vez de um avatar falso "#0".

## IA do menu
Grupos: Chamados, Análise, Recursos, Admin. Visão geral e Modo TV ficam no nível raiz. Lateral: seções expansíveis. Topo: dropdown por grupo.

## Listas de chamados (shell)
- Filtro **Status → Todos** inclui Solucionado (5) e Fechado (6) na tabela
- KPI **Abertos** / **Atrasados** continuam só status abertos (`NOT IN (5,6)`), independentes do select
- Empty state: “Nenhum chamado” quando Todos ou filtro 5/6; “Nenhum chamado aberto” nos demais status abertos
- Navs **Para mim** / **Aberto por mim** / **Observador** (chamados onde o usuário ou um grupo dele é observador, GLPI type=3) seguem o mesmo padrão de página filtrada
- `page=tickets&view=validation|solution` — filtro por pseudo-status (Validação/Aprovação), reaproveita a mesma condição SQL que a coluna correspondente do Modo TV já usa pra contar; substitui o filtro normal de `status` enquanto ativo

## Modo TV
- Chrome: logo + relógio + prefs (engrenagem)/volume/fullscreen/sair; KPIs; colunas de status + Validação + Aprovado
- **Identificação (ícone + 1 linha):**
  - KPIs: ícone + rótulo curto (Hoje / Semana / Mês / Atrasados / Validação / **Aprovado**); nome longo no `title`
  - Coluna solution: **Aprovado** (não “Solução aprovada”)
  - Card: idade com ícone; **categoria** em linha própria logo abaixo do título; **grupo** (`by_group`) em linha simples; **técnico** (headset) e **solicitante** (pessoa) como dois selos de mesmo peso lado a lado (quebram para empilhado em cards estreitos); observadores com ícone de **olho**
  - Ícones dos campos: mesmos no card e na lista "Campos do card" das preferências (técnico = headset, solicitante = pessoa — distinto do ícone de grupo —, observadores = olho, categoria = lista, botão Ver = link externo)
  - Prioridade: cor do texto no rodapé por nível (`data-prio` 1–6); independente da borda de atraso (`tv-card--late`), que continua laranja
  - **Prévia**: botão no rodapé (ao lado de Ver, some com o card se o chamado não tiver descrição) abre `ho-ds-modal` com a descrição completa; mantém um subconjunto seguro de estrutura (títulos de seção viram linha em negrito, `<br>`/parágrafos preservam quebra de linha) em vez de virar texto corrido — importante pra descrições de formulário (pergunta/resposta); teto de segurança bem folgado contra descrição patológica cai pra texto puro; ação “Abrir chamado completo” dentro do modal
  - **“+N mais”** nas colunas: vira link — status real → `shell.php?page=tickets&status=X`; Validação/Aprovação → `...&view=validation|solution`
  - Chrome, ícones de coluna e datas cal/refresh: não trocar o que já está bom
- **Títulos densos (1 linha):** `.tv-col__title` com `nowrap` + ellipsis; status 2 = “Em atendimento”
- **Escala × `data-cols`:** comfortable 1–3 (título maior, mais linhas, “Ver” com texto); dense ≥6 (compacto, sem esconder observadores; date_mod pode ocultar)
- **Toasts:** canto **inferior direito** (não cobrem botões do topo); empilháveis; chime
- Media: não forçar `!important` que anula `data-cols` em TV larga
- **Visão do mural** (`view_mode`) + **Visões extras** (Para mim / Aberto por mim / Observador / Por grupo / Por entidade — colunas adicionais no quadro); entidade única travada; prefs localStorage

## Proibido
iframe, Bootstrap 2 do plugin, jQuery local, IE shims, meta-refresh, Font Awesome no chrome moderno (legado pode ainda referenciar FA em disco).
