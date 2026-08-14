# Versionamento — Painel de Bordo

## Semver do plugin
Formato `MAJOR.MINOR.PATCH` em `setup.php` → `plugin_version_paineldebordo()['version']`.

Exemplo atual da linha 2.16: `2.16.0` (feature) → `2.16.1` / `2.16.2` (só correção).

| Tipo | Casa | Quando |
|------|------|--------|
| **PATCH** | 3ª (`2.16.**2**`) | Bugfix, polimento, ícones, i18n, install/TV hotfix, hardening, testes, docs de release |
| **MINOR** | 2ª (`2.**17**.0`) | Feature **nova** pedida pelo usuário (UI/relatório/TV/prefs), retrocompatível; **ou** mudança de modelo de permissões com migrate (ex.: 2.32.0 visão ampla explícita) |
| **MAJOR** | 1ª (`**3**.0.0`) | Quebra de API/instalação ou mudança incompatível de pasta/direitos **sem** caminho de migrate/documentação |

### Regras de ouro (obrigatórias)

1. **Correção e polimento NÃO alteram a 2ª casa.**  
   Errado: hotfix → `2.17.0`. Certo: hotfix → `2.16.3` (ou o próximo PATCH da MINOR corrente).

2. **Nunca retirar o que está funcionando**, a menos que o usuário peça explicitamente (“retire X” / “remova Y”).  
   Corrigir ≠ apagar notificação, endpoint, preferência, KPI, toast ou mensagem de install “para simplificar”.

3. **Interface em PT-BR.** Nenhum texto em inglês na UI, salvo termo sem tradução usual (ex.: SLA, JPG, PDF, KPI). Msgids no código podem ser EN; `pt_BR.po` + `.mo` devem cobrir tudo.

4. **Nunca só texto** em ações/menus/prefs — ícone + rótulo (exceto se ícone for claramente pior).

5. Bump em `setup.php` **só fecha** quando README + changelog + docs relevantes + testes de versão estão alinhados.

### Decisão rápida

```text
É bug / regressão / polish / i18n / zip / teste?
  → PATCH (2.16.x)

É capacidade nova que o usuário pediu?
  → MINOR (2.17.0)

Quebra install/perfil/pasta de forma incompatível?
  → MAJOR (3.0.0) — só com pedido explícito
```

## Checklist pré-release (obrigatório)
1. Bump `'version'` em `setup.php` (+ hub Config se exibir versão)
2. Atualizar `README.md` (título + novidades da versão)
3. Entrada PT + EN em `changelog.txt`
4. Alinhar `docs/DESIGN.md` / `docs/TESTING.md` / este arquivo se a política ou UX mudou
5. `php tests/run_all.php` → ALL SUITES PASSED
6. `tests/run_smoke.ps1` → 0 failed (assert README = versão)
7. Empacotar zip **sem** `_legacy_ref`:

```powershell
# Exemplo
robocopy paineldebordo _stage\paineldebordo /E /XD _legacy_ref
# Zip da pasta _stage\paineldebordo → paineldebordo-X.Y.Z.zip
```

8. Nome do artefato: `paineldebordo-X.Y.Z.zip` com raiz interna `paineldebordo/`

## O que entra no zip
- Código do plugin, `locales/`, `public/`, `inc/`, `docs/`, `tests/`, README, changelog
- **Excluir:** `_legacy_ref/` (referência de desenvolvimento)

## Regra Cursor / time
- Política espelhada em `.cursor/rules/paineldebordo-golden.mdc` (`alwaysApply`).
- Em dúvida entre PATCH e MINOR: **PATCH**, e pergunte ao usuário se parece feature.
