# QA adversarial — Painel de Bordo 2.32.1

Bateria para achar comportamento errado ou quebra após ACL por módulo, Admin só Super-Admin, visão ampla explícita (2.32.0) e TV mute/CTRL+M.

**Camadas**

| Camada | Onde | Prova |
|--------|------|-------|
| A | `php tests/run_all.php` + `tests/run_smoke.ps1` | Código/contratos presentes |
| B | `tests/run_acl_tests.php` | Mapas ACL puros + gates nos arquivos |
| C | GLPI real | Sessão, Perfis, áudio, redirects |

Sem GLPI a camada C é obrigatória para rights reais. Ver também [`PERMISSIONS.md`](PERMISSIONS.md) e [`TESTING.md`](TESTING.md).

---

## Personas (criar no GLPI → Perfis → Painel de Bordo)

| ID | Nome sugerido | Rights |
|----|---------------|--------|
| P0 | Sem plugin | nenhum right do plugin |
| P1 | Só Overview | master **READ**; módulos **desmarcados** (0) |
| P2 | Técnico Chamados | master READ + **tickets READ** |
| P3 | Analista RO | master READ + **analysis READ** (sem UPDATE) |
| P4 | Analista editor | master READ + analysis **READ+UPDATE** |
| P5 | Recursos RO | master READ + **resources READ** |
| P6 | Recursos RW | master READ + resources **READ+UPDATE** |
| P7 | Visão ampla | master READ + **`plugin_paineldebordo_groups` READ**, módulos conforme uso, **não** Super-Admin / sem `config` UPDATE |
| P7b | Master UPDATE legado | master READ (+ UPDATE bit legado se existir), **sem** groups READ — **não** deve listar grupos alheios |
| P8 | Super-Admin | nome Super-Admin (ou Super-Administrador) + tudo |
| P9 | Admin renomeado | perfil com outro nome + GLPI **config UPDATE** + master READ |

Notas:

- P1: se a migrate copiou módulos automaticamente no passado, **desmarque** os 3 módulos na aba Perfis para simular “só Overview”.
- P7: deve ter visão ampla de grupos via checkbox explícito; **não** deve ver menu Admin.
- P7b: regressão 2.32.0 — UPDATE master sozinho **não** abre todos os grupos.
- Entity lock: persona com 1 entidade → select Entidade travado (sem “Todas as entidades”).
- TV: `view_mode` (Visão do mural) filtra Novo/Atribuído/…; Visões extras continuam como colunas opcionais.

---

## Matriz página × persona

Legenda: **OK** = abre; **H** = redirect home + mensagem; **SA** = só Super-Admin (P8/P9); **—** = N/A (sem entrar no plugin).

| Page | P0 | P1 | P2 | P3 | P4 | P5 | P6 | P7 | P8 | P9 |
|------|----|----|----|----|----|----|----|----|----|-----|
| `home` | — | OK | OK | OK | OK | OK | OK | OK | OK | OK |
| `tv` / `tv.php` | — | OK | OK | OK | OK | OK | OK | OK | OK | OK |
| `tickets` | — | H | OK | H | H | H | H | * | OK | * |
| `by_group` | — | H | OK | H | H | H | H | * | OK | * |
| `by_entity` | — | H | OK | H | H | H | H | * | OK | * |
| `charts` / `chart` | — | H | H | OK | OK | H | H | * | OK | * |
| `reports` / `report` | — | H | H | OK | OK | H | H | * | OK | * |
| `metrics` (BI) | — | H | H | OK | OK | H | H | * | OK | * |
| `map` | — | H | H | H | H | OK | OK | * | OK | * |
| `assets` | — | H | H | H | H | OK | OK | * | OK | * |
| `config` | — | H | H | H | H | H | H | H | SA | SA |
| `setup` → config | — | H | H | H | H | H | H | H | SA | SA |
| `groups` → chart | — | H | H | OK | OK | H | H | * | OK | * |
| page lixo / vazio | — | home | home | home | home | home | home | home | home | home |

\* P7/P9: se tiverem módulos migrados (READ em todos), OK nos módulos; se só master UPDATE, default pós-migrate costuma incluir analysis+resources UPDATE e tickets READ — ajuste o perfil para o cenário desejado.

Mensagens PT esperadas:

- Módulo negado: *Você não tem permissão para este módulo.*
- Config: *Somente o Super-Admin pode abrir a Configuração.*

---

## Matriz AJAX × right

| Endpoint | Método | Gate | P1 | P3 | P4 | P5 | P6 | P7 | P8 |
|----------|--------|------|----|----|----|----|----|----|-----|
| `overview_board.php` | GET | master READ | 200 | 200 | 200 | 200 | 200 | 200 | 200 |
| `overview_layout.php` | GET | master READ | 200 | 200 | 200 | 200 | 200 | 200 | 200 |
| `overview_layout.php` | POST save/reset | **analysis UPDATE** | 403 | 403 | 200 | 403 | 403 | * | 200 |
| `bi_board.php` | GET/POST | **analysis READ** | 403 | 200 | 200 | 403 | 403 | * | 200 |
| `bi_layout.php` | GET | **analysis READ** | 403 | 200 | 200 | 403 | 403 | * | 200 |
| `bi_layout.php` | POST save/reset | **analysis UPDATE** | 403 | 403 | 200 | 403 | 403 | * | 200 |
| `assets_board.php` | GET | **resources READ** | 403 | 403 | 403 | 200 | 200 | * | 200 |
| `assets_list.php` | GET | **resources READ** | 403 | 403 | 403 | 200 | 200 | * | 200 |
| `assets_item.php` | GET | **resources READ** | 403 | 403 | 403 | 200 | 200 | * | 200 |
| `map_coord.php` | POST | **resources UPDATE** | 403 | 403 | 403 | 403 | 200 | * | 200 |
| `tv_board.php` / `tv_events.php` | GET | sessão READ **ou** token device | OK se READ | … | … | … | … | … | OK |
| `tv_unpair.php` | POST | token | device mode | | | | | | |

Evidência: Network → status + JSON `{ "ok": false, "error": "forbidden" }` (não HTML de login GLPI).

---

## Casos “impossíveis” / abuso

Marque pass/fail. Evidência: screenshot ou JSON.

### URL / shell

| # | Caso | Passos | Esperado |
|---|------|--------|----------|
| U1 | `page=` vazio | Abrir `shell.php?page=` | home |
| U2 | `page=naoexiste` | | home |
| U3 | `page=../../etc/passwd` | | home (não path traversal) |
| U4 | `page=config%00` | | home ou bloqueio, **não** config |
| U5 | `page=metrics` sem analysis | P1/P2 | H + mensagem PT |
| U6 | `page=setup` P7 | | H (não Config) |
| U7 | `page=groups` P3 | | redirect chart (analysis) OK |
| U8 | Favicon + AJAX paralelo com deep-link negado | | shell H; AJAX 403 JSON |

### Config / Super-Admin

| # | Caso | Passos | Esperado |
|---|------|--------|----------|
| C1 | Menu Admin P7 | Logar P7 | sem grupo Admin |
| C2 | Forjar POST config P7 | POST branding/purge | bloqueado / redirect / sem efeito |
| C3 | Delete TV P7 vs P8 | | P7 sem botão/ação; P8 OK |
| C4 | P9 renomeado + config UPDATE | | Admin + Config OK |
| C5 | Branding reset P8 | modal DS (não confirm nativo) | OK |

### BI / mural

| # | Caso | Passos | Esperado |
|---|------|--------|----------|
| B1 | P3 vê BI, Salvar | Editar + Salvar | 403 JSON; layout não grava |
| B2 | P3 Reset defaults | | 403 |
| B3 | P4 Salvar + Reset | | 200 + toast |
| B4 | Layout JSON inválido POST | | 400 `layout` |
| B5 | Duplo Salvar rápido | | segundo OK ou CSRF tratado; sem tela Access denied |
| B6 | P3 customizar mural Overview | POST overview_layout | 403 |
| B7 | Regressão Operação | P4/P8 | KPIs/charts hidratam |

### Recursos / mapa / ativos

| # | Caso | Passos | Esperado |
|---|------|--------|----------|
| R1 | P5 mapa GET | | OK visual |
| R2 | P5 POST map_coord | | 403 |
| R3 | P6 POST map_coord | | 200 |
| R4 | P5 assets mural + list + item | | OK |
| R5 | Entity tab mapa P5 vs P6 | | P5 sem tab/save; P6 OK |

### TV mute / tip / CTRL+M

| # | Caso | Passos | Esperado |
|---|------|--------|----------|
| T1 | Lembrar mudo **off**, unmute, F5 | | inicia **desmutado** na UI (ainda pode pedir gesto) |
| T2 | Lembrar mudo **on**, muted true, F5 | | restaura muted |
| T3 | Lembrar on, muted false, F5 | | restaura unmuted |
| T4 | Tip CTRL+M 1ª carga | | toast tip empilhável, **sem** chime |
| T5 | Tip 2ª carga mesma sessão | | **não** repete tip |
| T6 | CTRL+M | | toggle = botão áudio |
| T7 | CTRL+M com foco em input prefs | | **não** toggle |
| T8 | Spam CTRL+M | | UI estável; toasts ≤ TOAST_MAX |
| T9 | Tip + toasts de evento | | stack não explode; tip some |
| T10 | Device token inválido | | pair / erro; não board vazia silenciosa |
| T11 | Exit unpair | | limpa token + volta pair |

### Sessão / regressão

| # | Caso | Passos | Esperado |
|---|------|--------|----------|
| S1 | Trocar perfil mid-session | P2 → P3 sem logout pleno se GLPI permitir | menu muda |
| S2 | P0 menu Inovare Hub | | plugin não aparece / sem READ |
| S3 | Migrate: perfil antigo master READ | atualizar plugin | módulos preenchidos; app não “some” |
| S4 | i18n bloqueios | UI PT-BR | sem inglês nas mensagens de negação |
| S5 | Home mural P1 | | KPIs/charts overview OK |
| S6 | BI abas / assets NOC | P8 | sem regressão 2.25–2.26 |

---

## Como registrar resultado

```
Caso: U5 | Persona: P1 | Data: ____ | Pass/Fail: ____ | Evidência: ____
```

Rodar local antes do GLPI:

```powershell
php tests/run_all.php
powershell -ExecutionPolicy Bypass -File tests/run_smoke.ps1
```
