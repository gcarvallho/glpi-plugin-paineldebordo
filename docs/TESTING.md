# Testes — Painel de Bordo 2.32.1

## Posso testar só no GLPI?

**Quase.** Há dois níveis:

| Nível | Onde | O que cobre |
|-------|------|-------------|
| Unitário / lint / assets | Local: `php tests/run_all.php` | Sintaxe PHP, TV helpers, CSS inline, asset URLs, ícones |
| Smoke estático | Local: `tests/run_smoke.ps1` | Arquivos críticos, versão, redirects |
| Integração / UI | Servidor GLPI | Menu, Perfis, SQL real, áudio, TV ao vivo |

Sem GLPI **não** dá para simular sessão, entidades e tickets de verdade. Com os scripts locais você reduz o risco de quebrar parse/CSS/assets antes do deploy.

## Como rodar localmente

```powershell
# Preferido (só PHP — menos atrito com antivírus)
php tests/run_all.php

# Ou via wrapper PowerShell
powershell -ExecutionPolicy Bypass -File tests/run_all.ps1

# Smoke estático (PowerShell)
powershell -ExecutionPolicy Bypass -File tests/run_smoke.ps1
```

Suites em `run_all.php`:

1. `run_lint_all.php` — `php -l` em todo o plugin (exceto `_legacy_ref` e html2pdf legado)
2. `run_unit_tests.php` — lógica TV + asserts de regressão
3. `run_acl_tests.php` — mapas ACL puros + gates AJAX/shell/TV + doc QA
4. `run_asset_tests.php` — CSS inline, Highcharts, `asset_base`, layout renderizado

## Scripts `.ps1` e Kaspersky / antivírus

Os `.ps1` em `tests/` são **ferramentas de desenvolvimento** criadas pelo time do plugin. **Não** fazem parte do runtime GLPI — o plugin em produção nunca executa PowerShell.

| Script | Função |
|--------|--------|
| `tests/run_all.ps1` | Orquestra `php tests/run_all.php` |
| `tests/run_smoke.ps1` | Smoke estático (existência de arquivos, versão) |
| `tests/build_mo.ps1` | Compila `locales/pt_BR.mo` a partir do `.po` |
| `tests/apply_legacy_redirects.ps1` | Utilitário one-shot para stubs de redirect |

Se o Kaspersky (ou outro AV) bloquear:

1. Abra a pasta `plugins/paineldebordo/tests/` (ou a cópia de trabalho)
2. Adicione **whitelist / exclusão** só para essa pasta `tests/`
3. Prefira `php tests/run_all.php` no dia a dia (não precisa de `.ps1`)

Não renomeamos os scripts para `.php` — o caminho principal já é PHP puro.

## Checklist no GLPI após copiar a pasta

1. Pasta em `plugins/paineldebordo` (nome exato)
2. Plugin aparece como **Painel de Bordo** (versão alinhada ao `setup.php`)
3. Perfil Super-Admin tem direitos master + módulos na aba Painel de Bordo
4. Menu **Inovare - Hub → Painel de Bordo**
5. Home = mural: 8 KPIs + 12 gráficos Highcharts (hero Evolution + mosaico)
6. **Modo TV**: colunas status + Validação + Aprov. solução; KPIs 6; botão Ver; engrenagem Exibição; observadores; toasts/chime; tip CTRL+M; “Lembrar mudo” desmarcado não força mudo no reload
7. Usuário técnico **sem** Visão ampla de grupos vê só seus grupos; com o right `plugin_paineldebordo_groups` (ou Super-Admin) vê todos da entidade
8. Admin / Configuration **só Super-Admin** (não basta Update do plugin; Update ≠ visão ampla)
9. Nav filtra Chamados / Análise / Recursos conforme rights de módulo
10. 1 entidade → filtro Entidade travado; TV Visão do mural + Visões extras

## QA adversarial (permissões + TV)

Checklist resumido abaixo. **Matriz completa (personas, páginas, AJAX, abuso):** [`docs/QA_ADVERSARIAL.md`](QA_ADVERSARIAL.md).

1. Deep-link `?page=metrics` sem Análise → redirect home + mensagem
2. Análise READ sem UPDATE → vê BI; Salvar layout → 403 JSON
3. Recursos READ sem UPDATE → vê mapa; salvar coord → 403
4. Plugin UPDATE sem Super-Admin → sem menu Admin; `?page=config` bloqueado
5. Perfil admin renomeado com `config` UPDATE → ainda abre Config
6. TV device token: tip CTRL+M uma vez por sessão; mute não “gruda” se lembrar desmarcado
7. Spam CTRL+M / toasts respeitam `TOAST_MAX`; tip sem chime
8. Troca de perfil mid-session → menu/módulos atualizam
9. AJAX sem right → JSON `forbidden`, não HTML de login
10. Só master READ → Overview + TV; módulos off somem do menu
11. Install fresco Super-Admin → todos módulos + Admin
12. Técnico só Chamados → home/TV + grupo Chamados apenas

Suite local ACL: `php tests/run_acl_tests.php` (também via `run_all.php`).

## Sons

O Modo TV usa **tons gerados no browser** (Web Audio) por tipo de evento — não depende de MP3 no servidor para funcionar.
