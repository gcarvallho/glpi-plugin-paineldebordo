# Proposta — Personalização + menu lateral recolhível

Documento de produto/técnico (ainda **não implementado**, exceto o hotfix do redirect TV citado no fim).
Versão-alvo sugerida quando for codar: **MINOR** (ex.: `2.24.0`) — são features novas pedidas pelo usuário.

---

## 1. Configuração → Personalização

### Onde entra no hub

Novo card/seção em `public/views/config_hub.php`, após Escopo / Dispositivos TV (ou grupo próprio no nav `config`):


| Bloco                    | Objetivo                              |
| ------------------------ | ------------------------------------- |
| **Cores**                | Override dos tokens CSS do tema       |
| **Marca / logo**         | Logo larga + ícone recolhido + textos |
| **Extras** (opcional v1) | Nome do produto, eyebrow, raio, fonte |


Gate: mesmo de hoje — `plugin_paineldebordo_canConfigure()` / `UPDATE` no `shell.php?page=config`.

### O que já existe (base pronta)

Arquivo `public/css/dashboard-tokens.css`:

- Tokens light/dark: `--ho-primary`, `--ho-accent`, `--ho-bg`, `--ho-sider`, `--ho-surface`, `--ho-text`, `--ho-border`, etc.
- Logos já via CSS:
  - `--ho-logo` → logo completa (URL remota Inovare hoje)
  - `--ho-logo-collapsed` → logo “só o I” (já existe no CSS, ainda sem modo rail)

Brand no layout (`inc/layout.inc.php`):

- `.ho-brand__logo` + textos `Inovare - Hub` / `Painel de Bordo` hardcoded.

Config global: padrão atual é **por usuário** (`glpi_plugin_paineldebordo_config.users_id`). Para marca/cores do hub (vale para todos / TV), preferir `users_id = 0` (mesmo padrão do TTL TV).

### MVP recomendado (v1)

1. **Paleta (color pickers + reset Inovare)**
  - Primária / sidebar (`--ho-primary`, `--ho-sider`)  
  - Destaque (`--ho-accent`)  
  - Fundo / superfície / texto (light; dark derivado ou 2º bloco)  
  - Preview ao vivo no próprio card (CSS variables inline no `:root` / `[data-theme]`)
2. **Logo**
  - Upload SVG/PNG (máx. ~512 KB) → `files/_plugins/paineldebordo/brand/` (ou pasta do plugin `public/brand/` se política GLPI permitir)  
  - Campos: logo expandida + logo compacta (fallback: gera/usa a mesma)  
  - URLs externas opcionais (como hoje) se upload for bloqueado no servidor  
  - Botão “Restaurar logos Inovare”
3. **Textos de marca**
  - Eyebrow (ex.: “Inovare - Hub”)  
  - Título (ex.: “Painel de Bordo”)  
  - Aplicar em shell, `tv_pair`, `tv_approve` (hoje repetem o brand)

Persistência sugerida (JSON global `branding`):

```json
{
  "accent": "#E73E11",
  "primary": "#09141F",
  "bg": "#f4f6f8",
  "logo_url": "...",
  "logo_collapsed_url": "...",
  "eyebrow": "Inovare - Hub",
  "product_name": "Painel de Bordo"
}
```

Injeção: `layout.inc.php` emite `<style id="ho-branding">:root { … }</style>` + troca `background-image` das logos.

### Extras que valem a pena (v1.1 / v2)


| Ideia                                              | Valor                    | Esforço                   |
| -------------------------------------------------- | ------------------------ | ------------------------- |
| Presets (“Inovare”, “Alto contraste”, “Cliente X”) | Onboarding rápido        | Baixo                     |
| Favicon / título da aba do shell                   | Identidade               | Baixo                     |
| Cor do modo TV (fundo + accent toasts)             | Wallboard alinhado       | Médio                     |
| Fonte (whitelist Google/local)                     | Identidade               | Médio                     |
| Border-radius / densidade                          | Polimento                | Baixo                     |
| CSS custom livre (textarea)                        | Poder total / risco XSS  | Só super-admin + sanitize |
| Export/import JSON do tema                         | Migração entre ambientes | Baixo                     |
| ~~Dark mode independente (não só invert)~~ — **implementado**: 2º bloco de cores (primária/fundo/superfície/texto) só para o escuro em Configuração → Personalização (`inc/branding.inc.php`) | Qualidade | — |




### Fora de escopo (inicial)

- Trocar o tema do **GLPI core** (só o shell do plugin).
- White-label completo de e-mails / PDF GLPI.
- Editor visual tipo Figma.

---



## 2. Menu lateral — recolher / expandir (só ícones)



### Comportamento desejado

- Botão no rodapé (ou topo) do `.ho-sider`: **Recolher** / **Expandir**.
- Estado recolhido (~64–72px):
  - Some labels (`.ho-nav__label`, eyebrow, título longo)
  - Ícones centralizados
  - Logo troca para `--ho-logo-collapsed` (já previsto no CSS)
  - Grupos: só ícone do grupo; flyout/tooltip no hover com o nome
- Preferência persistida: `nav_collapsed=1` (por usuário, localStorage + espelho em config)
- Atalho opcional: `]` / botão; respeitar `prefers-reduced-motion`



### Encaixe no layout atual

- Grid hoje: `.ho-app { grid-template-columns: 240px 1fr; }`
- Modo proposto: `.ho-app--rail { grid-template-columns: 72px 1fr; }`
- Já existe `nav_layout` = `side` | `top`. Rail só faz sentido com **side**; em **top** o botão fica oculto ou vira “compactar topnav” (fase 2).



### Detalhes de UX

- Tooltips nativos (`title`) ou tip custom nos ícones.
- Grupos abertos: em rail, expandir em **popover** ao lado (não empurrar a coluna).
- Mobile (<992px): continua drawer/overlay atual; rail é desktop.



### Implementação estimada


| Parte         | Arquivos                                                    |
| ------------- | ----------------------------------------------------------- |
| CSS rail      | `dashboard-tokens.css`                                      |
| Toggle + aria | `layout.inc.php` + JS pequeno                               |
| Pref          | `filters.inc.php` (`nav_collapsed`) + localStorage imediato |
| i18n          | “Recolher menu” / “Expandir menu”                           |


---



## 3. Ordem de entrega sugerida

1. **PATCH** — bug redirect pós-vínculo TV (abaixo) ← hotfix independente
2. **MINOR A** — menu rail (recolher/expandir) — alto impacto, escopo fechado
3. **MINOR B** — Personalização cores + logo + textos (card no hub)
4. **PATCH/MINOR** — presets, TV theme, export JSON

Decisão de produto: dá para fazer **A+B** no mesmo `2.24.0` se o prazo permitir; senão priorizar o menu rail (menos dependência de upload/files).

---



## 4. Bug observado — “Vinculado com sucesso” → Acesso negado



### Sintoma

Revogar dispositivo → vincular de novo → mensagem de sucesso no hub → em seguida tela do GLPI **Acesso negado**. A vinculação no banco **funciona**.

### Causa

Em `config_hub.php`, após AJAX OK:

```js
location.reload();
```

A página do hub após **Revogar** foi renderizada como resposta de um **POST**. O `reload()` do browser **reenvia esse POST** (revoke/save) com token CSRF **já gasto** (o approve AJAX consumiu o token da página) → o core do GLPI responde Access denied.

Comentário no endpoint `ajax/tv_pair_approve.php` já alertava CSRF single-use; o problema aqui é o **reload pós-POST**, não a aprovação em si.

### Correção

Trocar por navegação **GET** explícita:

```js
location.href = 'shell.php?page=config';
```

Melhoria futura (PRG): tratar POSTs de config **antes** de `page_start()` no `shell.php` e redirecionar com flash — evita o mesmo padrão em Salvar / Revogar / Apagar.

---



## 5. Inventário técnico (exploração do código)

Hub hoje tem **5 cards** (Escopo, Mapa, Dispositivos TV, Acesso, Sistema) — **nenhum** de personalização visual.


| Preferência UI atual  | Onde                     | Nota                            |
| --------------------- | ------------------------ | ------------------------------- |
| `ui_theme` light/dark | GET + config por usuário | Mantém                          |
| `nav_layout` side/top | GET + config por usuário | Rail só em `side`               |
| `tv_pair_ttl`         | global `users_id=0`      | Modelo a reusar para `branding` |
| Prefs TV display      | `localStorage`           | Fora do hub                     |


**Reuso direto:** `get/setConfigValue`, `plugin_paineldebordo_page_start` / brand em `layout.inc.php`, `nav_tree()` + `icons.inc.php`, injeção `#ho-tokens-inline`, `plugin_paineldebordo_asset_url()`.

**Débito para personalização funcionar de verdade:** várias regras ainda usam `#E73E11` / `#09141F` literais (nav ativo, botões, TV pair/approve). No MVP, migrar esses pontos para `var(--ho-accent)` / `var(--ho-primary)` senão o picker “não pega” em metade da UI.

**Logo:** só URLs remotas no CSS; sem arquivo local de marca. Favicon já existe: `public/img/dash.ico`. `tv_pair` / `tv_approve` são **texto** de brand, sem `.ho-brand__logo`. `--ho-logo-collapsed` está definido e **ainda não usado** — encaixa no modo rail.

---



## 6. Critérios de aceite (quando implementar)

**Personalização**

- [x] Admin altera accent/primary e vê no shell sem hard refresh completo (ou com 1 reload)
- [x] Reset restaura Inovare
- [x] Logo custom aparece no sider; rail usa logo compacta
- [x] Textos de marca no shell + telas TV pair/approve
- [x] Sem quebrar dark/light

**Menu rail**

- [x] Toggle recolhe para só ícones; expandir restaura labels
- [x] Estado persiste entre páginas
- [x] Tooltips nos ícones; teclado/aria ok
- [x] Layout top inalterado

**Bug TV**

- [x] Após revoke → link → sucesso, permanece no hub (lista atualizada), sem Access denied