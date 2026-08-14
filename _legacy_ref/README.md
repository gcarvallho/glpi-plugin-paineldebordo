# Legacy reference (from paineldebordo-2.3.1.zip)

Read-only SQL/UI museum. Not served by the web app.

## `inc/`, `sh/`, `metrics/` (2.32.6)

`inc/changelog.php`, `sh/media.php`, `metrics/jquery.min.js` were moved here from `public/` — they were unreferenced by any active route (verified: no include/require anywhere in `public/`/`inc/`) but still shipped in the release zip and were directly web-accessible. They used IE compatibility shims, the plugin's own Bootstrap 2, Font Awesome and a locally-vendored jQuery — all prohibited in the modern chrome per `docs/DESIGN.md`. Kept for reference, not packaged (this folder is excluded from release zips per `docs/VERSIONING.md`).

| Legacy file | Modern report id |
|-------------|------------------|
| rel_usuario.php | by_requester |
| rel_tecnico.php, rel_tecnicos.php | by_tech_detail |
| rel_grupo.php, rel_grupos.php | by_group_detail |
| rel_grupo_tec.php | group_tech |
| rel_grupo_req.php | group_requester |
| rel_entidade.php, rel_entidades.php | by_entity_detail |
| rel_categoria.php, rel_categorias.php, rel_categoria_sons.php | by_category_tree |
| rel_localidade.php, rel_localidades.php | by_location |
| rel_data.php | by_date |
| rel_tickets.php, rel_tickets1.php | open_list / tickets_period |
| rel_sla.php, rel_slas.php | sla_by_policy |
| rel_sltsa.php, rel_sltsas.php | sla_tto |
| rel_sltsr.php, rel_sltsrs.php | sla_ttr |
| rel_oltsa.php, rel_oltsas.php | ola_tto |
| rel_oltsr.php, rel_oltsrs.php | ola_ttr |
| rel_satisfacao.php | csat_detail |
| rel_custo_tec.php | cost_by_tech |
| rel_custo_req.php | cost_by_requester |
| rel_custo_loc.php | cost_by_location |
| rel_custo_ent.php | cost_by_entity |
| rel_assets.php | assets_summary |
| rel_tarefa.php, rel_tarefa_cham.php, rel_tarefa_cham_group.php | tasks_list / tasks_by_ticket / tasks_by_group |
| rel_task_req.php, rel_task_ent.php | tasks_by_req / tasks_by_ent |
| rel_projects.php, rel_projecttasks.php | projects / project_tasks |
| rel_sint*.php | synth_overview (+ entity/tech/req/group variants) |

## `html2pdf/` (2.32.14)

`public/inc/html2pdf/` (Laurent Minguet's HTML2PDF 4.03, bundling TCPDF 5.0.002 from ~2013) was moved here. It only had legacy consumers already archived above (`rel_sint_*_pdf.php`) and was otherwise dead code. When wiring a new PDF export for `report_run.php`, it turned out fatally incompatible with PHP 8 (curly-brace string/array offset syntax, `count()` on non-countable) — some of those were patched here for reference, but the fixes stopped once a fatal surfaced inside TCPDF's Unicode/bidi text engine, deep enough that a safe fix wasn't confident without a proper audit. The PDF export instead reuses GLPI core's own bundled TCPDF (`class_exists('TCPDF')`, see `inc/services/report_pdf.php`), which is guaranteed compatible with whatever PHP version GLPI itself requires — no vendored library, no PHP8 patching to maintain.
