# LDK CRM · Contexto e continuidade

Documento para continuar o trabalho em outra conta/sessão. Escrito em 08/10/2026.

## 1. Projeto

- **Repositório:** `elevewebsites-lang/calude` · **branch de trabalho:** `claude/charming-dijkstra-ioq2t7` (tudo já enviado; nenhum PR aberto).
- **Quem pede:** Eleve Websites / LDK Marketing Digital (agência de social media). O usuário fala português e prefere respostas curtas e diretas.
- **O que é:** plugin WordPress `ldk-crm` (versão atual **1.31.0**), um CRM de agência: painel da equipe + área do cliente, contratos com assinatura eletrônica, briefings, conteúdo (posts), relatórios, financeiro, funil de leads etc. Há também o plugin `ldk-site` (site institucional, não mexido) e `docs/` (guia Meta, páginas legais, apresentação em PDF).
- **Ambiente de trabalho (sessão em nuvem):** não existe WordPress/MySQL lá. Só dá para rodar `php -l` (sintaxe), harnesses PHP com funções do WordPress simuladas, Node (pdf.js) e Chromium headless para capturas. **Nada do que foi feito foi testado dentro de um WordPress real.** O primeiro passo ao continuar deve ser instalar a versão 1.31.0 no site e testar os fluxos abaixo.

## 2. Convenções do código (para manter o estilo)

- Prefixo `lk_` em funções; tabelas `wp_lk_*` via `lk_table()`; CRUD com `lk_get/lk_rows/lk_insert/lk_update/lk_delete`.
- Ações de formulário: `lk_form('nome')` envia para `admin-post.php` com `do=nome` e a função `lk_do_nome()` trata (nonce `lk_nome`). Terminam com `lk_back(msg, tipo, url)`.
- Páginas do painel: `templates/panel/<slug>.php`, registradas em `includes/router.php` (slug → [view, permissão]); menu em `includes/ui.php` (`lk_panel_start`). Páginas públicas em `templates/public/`.
- Includes carregados em `ldk-crm.php` (lista `foreach ... 'contratos-pacotes', 'contratos-servicos', 'vencimentos', 'importar', 'modo-gestao', 'relatorio-mlabs' ...`). **Atualização de tabelas:** o plugin roda `dbDelta` quando `LK_VERSION` muda → sempre subir a versão ao mexer em `includes/db.php`.
- Textos de interface em português do Brasil. CSS global em `assets/app.css` (blocos novos adicionados no fim do arquivo).
- Commits terminam com as linhas de co-autoria/sessão exigidas pelo ambiente; push com `git push -u origin claude/charming-dijkstra-ioq2t7`.

## 3. Linha do tempo do que foi feito (por pedido do usuário)

### 3.1 Contratos (1.28.0)
Pedido: ver contratos pendentes, briefing por tipo de serviço, botão **Gerar contrato** (nunca automático), aviso se não assinar em 1 dia, menu lateral organizado por assunto.
- `includes/contratos-servicos.php` (novo): catálogo `lk_service_types()` (Social media, Tráfego pago, Site, Identidade visual, Vídeo, CRM, Consultoria, **Hospedagem e domínio**), cada um com serviços padrão e modelo de briefing (`lk_service_briefings()`, mesclados em `lk_form_defaults()`; a função original virou `lk_form_defaults_base()` em `formularios.php`). Filtro `lk_service_types` permite trocar o catálogo (ex.: assuntos de advocacia).
- Coluna `contracts.subject` (chaves separadas por vírgula) e `contracts.reminded_at`.
- `templates/panel/contratos.php` reescrito: abre em **Pendentes** (rascunho/enviado/falta a agência); menu lateral próprio por situação e por assunto; coluna "Esperando" (dias sem assinar); menu principal mostra contagem de pendentes.
- Botão/janela **Gerar contrato** (`lk_do_contract_generate`): cliente + serviços marcados → rascunho com dados do cliente/agência preenchidos. O fluxo antigo (lead → contrato, "Novo contrato" na ficha) continua.
- Aviso: `lk_contract_unsigned_alert` no hook `lk_hourly`: contrato enviado há ≥24h sem assinatura → notificação aos admins + e-mail (uma vez, marca `reminded_at`). Botão "Lembrar por e-mail" (`lk_do_contract_remind`) na tela do contrato. Na tela do contrato: botão "Enviar briefing" por serviço (usa `form_quick`).

### 3.2 Hospedagem, domínio e vencimentos (1.28.0 → 1.28.1)
- Tabela nova `renewals` (client_id, kind, label, provider, due_date, value, cycle, **paid**, notes, notified_days). `includes/vencimentos.php`: tipos (Hospedagem, Domínio, SSL, E-mail, Suporte, Licença, Outro com nome livre), avisos 30/15/7/1/0 dias (hooks `lk_daily` e `lk_daily_catchup`), "Renovado" (empurra data pelo ciclo e zera "pago"), "Marcar pago".
- Página `Hospedagem e domínios` (`templates/panel/vencimentos.php`) lista tudo; na ficha do cliente há o bloco por cliente.
- **Tags de serviço ativo + pago** (`lk_client_service_tags_html`): serviços de contratos assinados (estado vem da mensalidade do cliente: pago / a vencer / atrasado / sem cobrança — não existe pagamento por serviço) e hospedagem/domínio/outros como serviços à parte (pago / não pago / não pago · venceu). Aparecem na ficha e na lista de clientes.

### 3.3 Importar planilha Excel/CSV (1.29.0 → 1.29.1)
- `includes/importar.php` + `templates/panel/importar.php`: lê `.xlsx` (ZipArchive + SimpleXML, sem bibliotecas) ou `.csv`; reconhece colunas por cabeçalho (Name/Nome, Data, Produto, Arte, Legenda, Vídeo, Data entrega, Pessoa); mostra prévia; só importa linhas com texto (ignora vazias, "." e títulos de mês, e cancelados); não duplica (cliente+título+data); grava direto em `posts` sem disparar publicação/notificação/gamificação.
- Mapeamento: Produto→formato; status mais atrasado de Arte/Legenda/Vídeo→etapa (Postado/Agendado→Publicado; p/ aprovação→Aprovação; para entregar/gravação→Design; criar legenda→Planejamento); sem status e data passada→Publicado, data futura→Planejamento. "Agendado" do CRM nunca é usado na importação porque publicaria sozinho.
- Testado com `MEGAMOTOS` (Monday): 1.052 posts, 65 vazios, 12 cancelados. Responsáveis (coluna Pessoa) vão para as observações, não são atribuídos a usuários.
- Botão na página Conteúdo e na ficha do cliente (aba Posts) — `lk_client_import_html`.

### 3.4 Ficha do cliente em abas, Salvar tudo e modo gerenciamento (1.30.0)
- `templates/panel/cliente.php` reescrito: cabeçalho com avatar/tags/atalhos e abas **Resumo, Dados, Contratos, Briefing e fichas, Posts, Drive e arquivos, Acessos, Vencimentos, Relatórios, Reuniões** (+ Redes sociais só fora do modo gerenciamento). JS em `assets/cliente-abas.js` (hash na URL; links antigos como `#contratos`, `#vencimentos` abrem a aba certa).
- **Salvar tudo:** formulário único na aba Dados (`lk_do_client_save_all` = `lk_client_team_apply()` + `lk_do_client_save()`), botão no topo e barra fixa.
- Arquivos e acessos por cliente: colunas `client_id` em `files` e `access`; `includes/modo-gestao.php` (`lk_client_files_html`, `lk_client_access_html`, ações `cfile_*`/`cacc_*`). Senhas criptografadas (`lk_encrypt`), revelar com o botão "Ver" (API `access/{id}` já existente).
- **Modo gerenciamento** (`modo_gestao`, padrão **ligado**; Configurações → "Modo de uso"): `lk_manage_only()`. Esconde Redes conectadas, aba Redes, "Onde publicar", "Publicar agora", aviso "Instagram não vinculado", seção Redes e anúncios das configurações; **a publicação automática não roda**; cada post ganha o cartão **Para agendar no mLabs** (arte para baixar, legenda com hashtags para copiar, botão "Marcar como agendado no mLabs" → etapa Agendado).
- Polimento visual leve no fim de `assets/app.css`.

### 3.5 Relatório a partir do PDF do mLabs (1.31.0)
Motivo: sem vínculo com as redes não há API; o mLabs envia um PDF mensal, o CRM lê e monta o relatório padrão.
- **Leitura no navegador** (privacidade e independência do servidor): `assets/mlabs/pdf.min.js` + `pdf.worker.min.js` (pdf.js 3.11.174, Apache-2.0, ~1,4 MB), `pdf-prims.js` (texto/imagens/desenhos vetoriais com posição e cor), `mlabs-parse.js` (parser), `importar.js` (interface). O parser é independente de layout fixo: descobre blocos pelos ícones de rede social, títulos, faixas de cabeçalho de tabela e cores. Variação ↑/↓ vem da cor do selo (verde/vermelho/azul). Gráficos de linha/barras são reconstruídos lendo os caminhos vetoriais e a escala dos eixos; pizza pelos rótulos; tabelas por colunas; miniaturas recortadas da página renderizada (JPEG ~144×180).
- Teste real com `Relatório Setembro 2026 - Propósito Seguros` (16 páginas): 23 seções, 32 miniaturas, valores conferidos (±1–2 unidades nos valores lidos de gráficos).
- Servidor: `includes/relatorio-mlabs.php` — `lk_do_mlabs_save` (valida/limpa o JSON, salva miniaturas em `uploads/lk-mlabs/`, cria linha em `reports` com `kind='mlabs'`, reaproveita mesmo cliente+mês), `lk_do_mlabs_edit` (análise, textos reescritos, seções escondidas, publicar/enviar), desenho em SVG/HTML (`lk_mlabs_slides`). Página pública: `templates/public/relatorio-mlabs.php` + `assets/relatorio-mlabs.css` (telas horizontais escuras, mesmo estilo do relatório existente em `/relatorio/<token>/`).
- Telas: `templates/panel/relatorio-importar.php` (upload), `templates/panel/relatorio.php` (ramo `mlabs` com editor), lista em `relatorios.php`, aba Relatórios na ficha.
- **Postado automático:** `lk_publish_due` (cron a cada 5 min) em modo gerenciamento move posts em "Agendado" com horário vencido para "Publicado".
- Tráfego pago (`trafego`) também fica fora do menu/rotas no modo gerenciamento.

## 4. Apresentação em PDF para escritórios de advocacia
- `docs/apresentacao-crm/apresentacao-crm-advocacia.pdf` (16 páginas A4 paisagem). Gerada por `build_screens.py` (telas HTML com o CSS real do painel e dados fictícios — tema azul/dourado "Silva & Costa", **não são capturas do sistema rodando**) e `build_pdf.py` (HTML→PDF pelo Chromium).
- Valores comerciais: a página "Investimento" está com `R$ ________`. Gerar de novo com `IMPLANTACAO="R$ X" MENSALIDADE="R$ Y" python3 build_pdf.py` dentro de `docs/apresentacao-crm/`.
- O PDF é **anterior** às abas do cliente, ao modo gerenciamento e ao relatório do mLabs — vale atualizar.
- Ferramentas usadas: Chromium em `/opt/pw-browsers/chromium-1194/chrome-linux/chrome`, Python com Pillow.

## 5. Decisões e regras do usuário a respeitar
1. **Contrato nunca é gerado sozinho** — sempre por clique no botão.
2. **Por enquanto sem postar e sem vincular redes** (modo gerenciamento ligado). A vinculação (Instagram etc.) fica para a "próxima parte". Agendamento é manual no mLabs; o atendimento envia arte/legenda pelo post.
3. Só importar o que tem texto (planilhas); nada de linhas vazias.
4. Hospedagem e domínio são **serviços à parte**, com tag própria e controle pago/não pago.
5. Interface: menos informação por tela, abas, visual mais atraente.
6. Não criar pull request sem pedido explícito. Não inventar valores comerciais.

## 6. Pendências e próximos passos sugeridos
- **Testar tudo em um WordPress real** (1.31.0): ativação/atualização de tabelas (`renewals`, colunas novas), contratos, importação do Excel, abas do cliente, salvar tudo, upload do PDF do mLabs, publicação automática no horário.
- Conferir se a extensão `ZipArchive` existe no servidor (importação de `.xlsx`; sem ela, usar CSV).
- Relatório do mLabs: testar com outros PDFs (outras redes, outros meses). Pontos que podem precisar de ajuste: rede ≠ Instagram/Facebook (cor do ícone), tabelas com colunas diferentes, gráficos de barras com poucos itens.
- Atualizar o PDF de apresentação (novas telas) e preencher implantação/mensalidade quando o usuário passar os valores.
- Assuntos de contrato e briefings estão com serviços de agência; para o escritório de advocacia, trocar via filtro `lk_service_types` (contencioso, família, societário…).
- Possíveis melhorias citadas: criar o vencimento (hospedagem/domínio) automaticamente quando o contrato desses serviços for assinado (hoje é manual); atribuir responsáveis do Excel aos usuários da equipe; pagamento por serviço (hoje o estado "pago" das tags de contrato vem da mensalidade do cliente).
- Próxima fase prevista pelo usuário: vinculação das redes (desligar o modo gerenciamento nas Configurações reativa tudo que foi escondido).

## 7. Arquivos novos/alterados mais importantes
```
ldk-crm/ldk-crm.php                      versão 1.31.0 e lista de includes
ldk-crm/includes/db.php                  tabela renewals; colunas contracts.subject/reminded_at, files/access.client_id
ldk-crm/includes/contratos-servicos.php  assuntos, briefings por serviço, gerar contrato, aviso de 1 dia
ldk-crm/includes/vencimentos.php         vencimentos + tags de serviço/pagamento
ldk-crm/includes/importar.php            leitura de xlsx/csv e importação de posts
ldk-crm/includes/modo-gestao.php         modo só gerenciamento, cartão mLabs, arquivos e acessos por cliente
ldk-crm/includes/relatorio-mlabs.php     relatório a partir do PDF do mLabs
ldk-crm/includes/{modules,social,content,formularios,router,ui,reports,helpers}.php  ajustes pontuais
ldk-crm/templates/panel/{cliente,contratos,contrato,conteudo,post,relatorios,relatorio,relatorio-importar,importar,vencimentos,config,dashboard}.php
ldk-crm/templates/public/relatorio-mlabs.php
ldk-crm/assets/{app.css,cliente-abas.js,relatorio-mlabs.css}
ldk-crm/assets/mlabs/{pdf.min.js,pdf.worker.min.js,pdf-prims.js,mlabs-parse.js,importar.js,PDFJS-LICENSE.txt}
docs/apresentacao-crm/                   apresentação em PDF e geradores
```

## 8. Como retomar rápido
1. `git fetch origin claude/charming-dijkstra-ioq2t7 && git checkout claude/charming-dijkstra-ioq2t7`.
2. Ler este arquivo e `ldk-crm/ldk-crm.php` (lista de includes) para se orientar.
3. Validar sintaxe: `for f in $(git ls-files 'ldk-crm/*.php'); do php -l $f; done`.
4. Testar o parser do mLabs fora do WordPress (Node): instalar `pdfjs-dist@3.11.174`, carregar `assets/mlabs/pdf-prims.js` e `mlabs-parse.js` e chamar `parse(pages)` com as páginas de `pagePrims(pdfjsLib, page)`.
5. Para novas funções que alterem tabelas, subir `Version` e `LK_VERSION` em `ldk-crm.php`.
