# Contexto completo da conversa: AllPrint CRM (e LDK Site)

Documento para colar/subir no Claude Code local. Resume tudo que foi pedido, decidido, construído e testado, em ordem. O código está no repositório `elevewebsites-lang/calude`, branch `claude/zealous-fermi-hxdxp3`.

**Regras fixas do usuário/sessão**
- Responder SEMPRE em português (Brasil), tom direto.
- Desenvolver na branch `claude/zealous-fermi-hxdxp3`. Não abrir PR sem pedido.
- Commits terminam com:
  `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>` e `Claude-Session: https://claude.ai/code/session_01Nz8ZXJs9xNe9dMwu6emY62`
- NÃO commitar a planilha do cliente. `allprint-crm/data/` está no `.gitignore`; o `AlPrint_Controle.xlsx` só vai dentro do zip entregue (`allprint-crm.zip`, ignorado no git também). Para uma instalação local, coloque a planilha em `allprint-crm/data/AlPrint_Controle.xlsx` (ela se importa sozinha na ativação).
- A aba "Funcionários" da planilha NUNCA é importada (dado de RH). Linhas de folha de pagamento nos custos fixos entram só como custo "Funcionários", sem nome de pessoa.
- Uma senha de teste foi falada em áudio pelo usuário; foi sinalizado que deve ser trocada. Não registrar senha aqui.
- Pronomes: usar "o usuário"/"você"; não presumir gênero de terceiros.
- Seja honesto no relatório final: dizer o que foi testado e o que NÃO foi.

---

## 1. Linha do tempo dos pedidos do usuário

### Parte A — LDK (feito antes, só para contexto)
1. "oi", "sabe ldk". Depois pediu para refazer o site da LDK como plugin WordPress (`ldk-site`, v1.2.0): hero com feed do Instagram, serviços com imagem desfocada e degradê, responsivo, imagens nos posts, movimento ao rolar, formulário rápido na home que grava no CRM e abre o WhatsApp, página de contato, ícones de avaliação, sem bordas/hover vermelhos, bom espaçamento, imagens da internet (links Unsplash, não verificados offline).
2. "não quero com cara de IA, bem autêntico dentro da identidade": refeito com visual chapado dentro da IDV.
3. Pergunta sobre pixels queimados em MacBook M1 e "cade o plugin" (entregue). O zip `ldk-site.zip` foi colocado no `.gitignore`.

### Parte B — AllPrint CRM (plugin `allprint-crm`, gráfica de comunicação visual B2B em Taubaté/SP)
Ordem dos pedidos:
1. "Estuda esse CRM" (enviou `allprint-crm-NOTAS.md`, `allprint-crm.php`, zip). Depois mandou uma transcrição longa de uma demo e pediu um raciocínio de melhorias. Pontos da demo: áudio→texto, sinal de 50%, suspender tabela de preço, regras de laminação/corte, sem limite de largura de bobina (emenda/sobreposição), Pix sem peso de checkout (InfinitePay vs banco), QR de cadastro, funil/reativação 30 dias, estoque de rolo 100 m + 20% de perda, importar planilha, Kanban/modo TV, WhatsApp por etapa, cupom, R$ 50 de crédito para gráficas novas, R$ 200 mensal de manutenção, miniaturas para produção.
2. Implementar, com a planilha `AlPrint_Controle.xlsx` e 4 imagens de logo:
   - (a) leitor de CorelDRAW para a arte;
   - (b) Insumos reformulado para a realidade de gráfica (medir estoque);
   - (c) Apontamentos para todos os usuários, admin pode remover depois;
   - (d) inserir TODOS os dados da planilha no CRM (clientes e tudo);
   - (e) cupom de desconto e "crédito na loja" por cliente;
   - (f) nova logo sem fundo branco, profissional, contraste testado em tudo; logo e contraste mudam sozinhos quando muda tema/cor.
3. Google: ao vincular o Drive, uma pasta por cliente; Google Sheets atualizado a cada mudança de pedido/situação.
4. Pedido manual (cliente resiste a pedir no sistema e pede por WhatsApp): selecionar cliente e montar o pedido.
5. Ao mover para Entregue: popup "foi pago? e qual forma (Pix, cartão crédito/débito, dinheiro)"; tags coloridas por etapa e por situação de pagamento; aviso de "paga na retirada".
6. Renomear "parceiros" para "clientes".
7. Arquivos que o cliente sobe: preferir Google Drive (não ocupar hospedagem); se o Drive estiver sem espaço, aí sim deixar na hospedagem.
8. "Faz últimos testes, quero todos os testes, não posso ter erro."
9. Reportou: logo do painel (menu lateral) com contraste zero, logo escura sobre fundo escuro → corrigido (limpa logo antiga salva nas configurações).
10. Proposta comercial: tirar tudo de 3D e trazer a realidade da gráfica; link bonito com a proposta mostrando o serviço, o que foi orçado, nome do cliente etc., com possibilidade de gerar PDF. Pediu também "botão de atualizar dados".
11. "Alinha esses botões" (ficha do cliente: tipo de cliente/crédito).
12. "Quero baixar o CRM" (zip reenviado).
13. "Ainda tem item que fala em repor estoque de impressão 3D, remova tudo sobre 3D e adicione o normal da gráfica. Na proposta, preencher sozinha com nome etc., e com um clique mandar para o WhatsApp do cliente. O cliente que recebeu a proposta já vai automático para o lead como proposta enviada."
14. "Coloca também veículos para registrar todos os custos do carro da empresa (IPVA, gasolina…), tudo linkado com a planilha de custos do financeiro."
15. Logo da Eleve (crédito "Sistema personalizado desenvolvido por") invisível no fundo claro: corrigir no site inteiro, versão da logo por contraste. (Forneceu duas versões: texto claro e texto escuro.)
16. Este pedido: juntar todo o contexto para subir no Claude Code local.

---

## 2. Estado atual do plugin (v1.3.1)

Plugin WordPress em `allprint-crm/` (PHP 7.4+, sem Composer). Rotas próprias: `/entrar`, `/cadastro`, `/painel/<seção>`, `/cliente/<seção>`, `/orcamento/<token>` (proposta pública), `/pagar/...`. Ações do painel: `admin-post.php` com `action=ap` e `do=<nome>` chamando `ap_do_<nome>()` (nonce `ap_<nome>`). REST em `ap/v1`.

### Convenções do código
- Helpers: `ap_form()`, `ap_input/select/check`, `ap_require(área)`, `ap_back(msg,tipo,url)` (redireciona e dá exit), `ap_insert/ap_update/ap_get/ap_rows/ap_delete`, `ap_table()`, `ap_money()`, `ap_setting()`.
- Esquema com `dbDelta` em `includes/db.php` (roda quando `ap_version` muda: por isso a versão sobe a cada release que cria tabela/coluna).
- Configuração padrão em `includes/helpers.php` + `identity.php` (identidade AllPrint: cores `#15132B`, `#FDCC2A`, textos, funil, colunas do Kanban).
- Módulos opcionais em `includes/modules.php`: hoje só `estoque` (stock.php) e `frete` (shipping.php; desligado → `/painel/frete` dá 404 de propósito).
- Menu/atalhos em `includes/ui.php`; rotas em `includes/router.php`.

### Marca e contraste (`includes/brand.php`, `assets/brand.css`, `assets/brand/*.png`)
- `ap_luminance/ap_contrast/ap_mix/ap_ensure_contrast/ap_on_color`, tokens derivados (`--accent-text`, `--on-accent`, `--side-*`, `--ctop-*`, `--accent-ui`, tons `.tone-*`).
- Logo: `ap_logo_variant($bg)` escolhe `cor` (se contraste ≥ 4,5 e amarelo ≥ 1,3), senão `branca`/`mono`. `ap_logo_img()` emite duas imagens (`.lg-lt`/`.lg-dk`) quando tema claro/escuro divergem. Logos PNG transparentes geradas por `handoff/tests/mklogo.py`.
- Migração `ap_logo_reset_once()` limpa as configs antigas `logo`/`logo_cor` (causavam logo escura em fundo escuro).
- Crédito Eleve: `ap_credit_html($superfície)` usa `assets/brand/eleve-claro.png` e `eleve-escuro.png`, escolhidas por contraste, e a cor do texto vem de `ap_ensure_contrast`.
- Relatório de contraste em Configurações → Marca (66 verificações × 4 temas, todas OK).

### Importação da planilha (`includes/import.php`, `templates/panel/importar.php`)
- Leitor XLSX próprio (ZipArchive + SimpleXML). Abas lidas: Clientes, Pedidos, Custos Fixos, Tabela de Preços, Estoque (rolos), Estoque - Insumos, Revenda e Manutenção - Máquina. Idempotente via `ext_id`. Roda sozinha na primeira carga após ativar (`ap_import_auto`) e pelo painel.
- Totais conferidos: 92 clientes, 1006 pedidos, 1028 itens, 929 entradas pagas, 26 a receber, 99 custos fixos, 118 itens novos na tabela de preços (15 ligados ao catálogo online), 88 itens de estoque, 2 manutenções; soma dos pedidos R$ 337.992,21 igual à planilha.
- Preços por tabela: terceirizado (chave interna `parceiro`), empresa, pessoa física (`pf`). Cliente tem `kind` (Terceirizado/Empresa/Cliente P/F/Uso interno) que define a tabela.

### Pedidos, pagamento e Kanban
- `includes/paystatus.php`: `ap_order_payment()` (paid/partial/pickup/wait/none; crédito na loja não conta como sinal), cache em `$GLOBALS['ap_pay_cache']` zerado por `ap_order_payment_reset()` sempre que uma `transactions` muda (bug real corrigido), tags `.tone-*` por etapa e pagamento, `ap_delivery_payment_gate()` (API devolve 409 sem informar pagamento ao entregar), `ap_order_register_payment()`.
- Kanban (`templates/panel/projetos.php`, `assets/app.js`): arrastar para a última coluna abre popup de forma de pagamento. Cartão mostra miniatura da arte.
- Pedido manual: `includes/manual.php`, `templates/panel/novo-pedido.php`, `assets/manual.js`: cliente (ou cadastro rápido), tabela, valor ajustável por linha, desconto, cupom, crédito, pagamento pago/sinal/retirada/pendente.
- Pedido do cliente: `templates/client/novo-pedido.php`, `assets/pedido.js`, `assets/cart.js` (cupom e crédito conferidos no servidor via REST `ap/v1/coupon`). Bug corrigido: `cart.js` desistia ao carregar porque `window.AP` ainda não existia.
- Fluxo de cadastro: cliente cria conta em `/cadastro`, fica em análise, equipe aprova, ganha crédito de boas-vindas R$ 50 (`credito_boas_vindas`).

### Cupons e crédito (`includes/coupons.php`, `templates/panel/cupons.php`)
- Cupom percentual/fixo, mínimo, validade, limite de usos e por cliente, cupom específico de cliente. Crédito por cliente com extrato (`credits`). Total = subtotal − cupom − crédito. Testado: vencido, inativo, outro cliente, uso único, maior que o total, crédito limitado ao total, caso de maiúscula/minúscula.

### Estoque de gráfica (`includes/estoque.php`, `includes/stock.php`, `templates/panel/insumos.php`, `compras.php`)
- Bobinas em m² (com rolo em m²), tinta, peças da impressora, revenda; entrada de compra (custo médio), contagem de estoque (rolos + sobra), ajuste, baixa automática por produção com 20% de perda de máquina, ligação catálogo → insumo. Abaixo do mínimo entra na lista de compras.
- Todo código/UI de filamento, impressora 3D, carretel foi removido.

### Apontamentos
- Ativos para todos os usuários (`apontamentos` e `apontamentos_clientes` = '1' por padrão); admin pode desligar/remover depois.

### Google (`includes/drive.php`, `includes/bigupload.php`, `includes/sheets.php`)
- OAuth com escopos `drive.file` + `spreadsheets`. Uma pasta por cliente (`Clientes / <cliente> / Recebidos`) e por pedido.
- Upload do cliente vai direto do navegador para o Drive (sessão retomável). Se não houver espaço (checa `drive/v3/about`) ou o Drive falhar, o arquivo fica na hospedagem (`wp-<id>`, marcado `_ap_pending_drive`) e migra de hora em hora para o Drive, apagando a cópia local. O WordPress rejeitava CDR antigo (RIFF) na hospedagem: corrigido com filtro `wp_check_filetype_and_ext` para cdr/ai/eps/psd.
- Sheets: planilha com abas Pedidos (mesmo formato da planilha antiga + colunas do sistema, uma linha por item), Clientes e Custos (todas as saídas do financeiro, inclusive veículos). Fila de atualização executada no `shutdown` e de hora em hora; lock por transient; reparo "Sincronizar tudo de novo". Planilhas antigas ganham a aba Custos sozinhas. Filtro `ap_google_api_pre` permite simular o Google nos testes.

### Leitor de arte (`assets/artpreview.js`)
- No navegador, sem enviar nada ao servidor: CDR novo (ZIP, central directory + `DecompressionStream('deflate-raw')`), CDR antigo (RIFF com bloco `DISP` → BMP), JPG/PNG, PDF (pdf.js 3.11.174 via cdnjs; sem rede só lê MediaBox). Gera miniatura (data-URI guardada no JSON do item, `ap_clean_file_meta`), medida em cm e avisos (`apArtCheck`: proporção e dpi). Aparece no pedido do cliente, pedido manual, ficha do pedido, página do cliente e cartão do Kanban.
- Testado com CDR/imagens sintéticos, NÃO com CDR real da AllPrint nem com PDF (CDN bloqueada no sandbox).

### Propostas comerciais (antes "orçamentos"; rota ainda `/painel/orcamento(s)`)
- Editor `templates/panel/orcamento.php` + `assets/quote.js`: cliente, título, mensagem, imagens, itens (material do catálogo + medidas → preço pela tabela do cliente, editável; ou serviço avulso), desconto, prazo, validade, frete, condições. Número da proposta `ap_quote_number()` (id com 4 dígitos).
- Preenchimento automático: ao escolher o cliente vêm mensagem com o nome, título "Proposta para <cliente>", condições (`empresa_nota`) e prazo; atalhos de materiais.
- Página pública `templates/public/orcamento.php` + `assets/proposta.css`: capa, tabela de serviços com medidas, totais, imagens, condições, assinatura, cartão de aprovar/pagar. PDF: botão "Baixar PDF" chama `window.print()`; `?pdf=1` abre a impressão sozinho; CSS `@media print` em A4. (Não há gerador de PDF no servidor.)
- Botão **Atualizar dados** (`ap_do_quote_refresh`): reprecifica os itens do catálogo pela tabela atual do cliente, recalcula total e renova a validade.
- **Enviar no WhatsApp com um clique**: "Salvar e enviar no WhatsApp" (no editor) e "Enviar no WhatsApp" (proposta salva/rascunho) → `ap_quote_send_whatsapp()`: marca como enviada, abre `wa.me/<telefone>?text=...` com número, valor e link.
- **Lead automático**: `ap_quote_mark_sent()` cria/atualiza o lead na etapa "Proposta enviada" (`ap_funnel_proposal_stage()` cria a etapa se não existir), valor da proposta, liga `quotes.lead_id`, cria lembrete "Perguntar se viu a proposta" em 2 dias, não duplica lead. Cadastro novo continua em "Cadastro em análise" (`ap_funnel_signup_stage()`).
- "Salvar proposta" sozinho deixa rascunho (link público indisponível até enviar/liberar).

### Veículos (`includes/veiculos.php`, `templates/panel/veiculos.php`; menu Gestão, permissão `financeiro`)
- Cadastro (nome, placa, modelo, ano, combustível, km). Lançar custo: Combustível (litros + km), IPVA, Licenciamento, Seguro, Manutenção, Pneus, Multa, Lavagem, Pedágio, Documentação, Outros; parcelamento mês a mês (só a 1ª parcela pode já estar paga). Cada custo é uma `transactions` de saída, categoria "Veículos" (colunas novas: `vehicle_id`, `vtype`, `odometer`, `liters`), então aparece no Financeiro e na aba Custos do Sheets. Estatísticas: mês, ano, total, a pagar, km/l (entre abastecimentos), custo por km.

### Segurança verificada
- Anônimo e cliente barrados em rotas do painel; ações `ap_do_*` da equipe recusam cliente; REST do cupom ignora `client_id` enviado por cliente; cliente não move pedido nem vê pedido de outro; uploads recusam php/exe/html/svg/php disfarçado.

---

## 3. Como rodar os testes (ambiente de sandbox usado)

Pasta `handoff/tests/` tem os scripts. Requisitos: MariaDB, PHP 8.x com mysqli/zip, WordPress core (composer `johnpbloch/wordpress-core`), Node 22 + Playwright (Chromium), Python com PIL/numpy/openpyxl, `phpcs` com PHPCompatibility.
1. Instalar WP de teste (`inst.php`: `wp_install` com admin `admin` / senha de teste local à sua escolha, link simbólico `wp-content/plugins/allprint-crm` → repo; `act.php` ativa o plugin). Servir com `php -S 127.0.0.1:8082 -t <wp>`.
2. Desligar 2FA da equipe no teste: setting `seg_2fa` = '0'.
3. Rodar: `unit.php` (cupom, crédito, pagamento, preço, reimportação idempotente), `perm.php` (permissão), `up.php` (uploads), `deact.php` (desativar/reativar), `gtest-sheets-drive.php` (Google simulado: Sheets, Drive, hospedagem, aba Custos), `brandtest.php` (contraste dos 4 temas) e os `.js` de Playwright: `crawl.js` (todas as rotas, desktop e celular, erros de JS/PHP), `sec.js`, `e2e1.js` (cadastro→aprovação→pedido com cupom/crédito/arte), `kan.js` (popup de pagamento), `man.js` (pedido manual), `prop.js` e `acc.js` (proposta), `wa.js` (WhatsApp+lead), `veic.js`, `cred.js` (logo Eleve).
4. Lint: `php -l` em todos os `.php`, `node --check` nos `.js`, `phpcs --standard=PHPCompatibility --runtime-set testVersion 7.4-`.
Observação: os scripts têm caminhos `/tmp/...` e `localhost:8082`; ajuste ao seu ambiente. 404 esperados no crawl: `/painel/frete/` (módulo frete desligado).

## 4. Lições/armadilhas descobertas
- `ap_back()` termina a execução; em teste, não chame `ap_do_*` direto (use `ap_insert`).
- Mudou esquema/coluna/tabela → subir `AP_VERSION` (senão `dbDelta` não roda para quem já instalou).
- `window.AP` (nonce/REST) é impresso no rodapé: scripts que dependem dele não podem checar na carga.
- A cache de pagamento por requisição precisa ser zerada em toda escrita de `transactions`.
- `tests` de gtest dependem de ids/estado; use instalação limpa.
- Preço: `price_m2` do catálogo é a tabela "terceirizado"; `ap_row_price()` cai nela se a tabela escolhida estiver vazia.

## 5. O que NÃO foi feito (da demo; não pedido explicitamente) — confirmar com o usuário
- Acréscimo de R$ 30/m² para corte e as duas regras de laminação.
- Campos de emenda/sobreposição de bobina na interface.
- Sinal de 50% escolhido pelo cliente.
- Pix direto no banco/API bancária (hoje: InfinitePay).
- Transcrição de áudio.
- Modo TV do Kanban.
- Reativação automática de cliente parado há 30 dias.
- R$ 200 mensais de manutenção.

## 6. Não testado de verdade
- Conexão real com Google (OAuth, Drive, Sheets): só simulado.
- Miniatura de PDF (pdf.js por CDN) e CDR real da AllPrint.
- WhatsApp: validado só o endereço `wa.me` gerado.
- Atualização a partir do zip original 1.1.0 (não disponível): simulada com base antiga montada à mão.
- Imagens do site LDK (links Unsplash).

## 7. Histórico de commits relevantes (branch `claude/zealous-fermi-hxdxp3`)
`24def20` 1.2.0 logo/contraste → `7c7888a` importador → `aba0e86` pedido manual/cupons/tags/pagamento na entrega → `52952d8` parceiros→clientes → `226c2af` estoque de gráfica → `f31b1ed` leitor de arte, Drive-first, Sheets → `9f82423` limpa logo antiga → `0e38e21` proposta comercial → `1efc84c` 1.3.0 (sem 3D, WhatsApp+lead, veículos) → `1704ea2` 1.3.1 (logo Eleve por contraste).

## 8. Próximos passos sugeridos
1. Conectar o Google real e fazer um pedido de teste (pasta do cliente, linha na planilha, aba Custos).
2. Testar com CDRs reais e PDFs.
3. Decidir quais itens da seção 5 entram.
4. Trocar a senha de teste citada na gravação.
5. Rodar `handoff/tests` de novo no ambiente local antes de publicar no site real.

---

## 9. LDK CRM (`ldk-crm/`, prefixo `lk_`): equipe e permissões por perfil (v1.28.0)

Pedido: "ajuste certinho a parte de equipe e as permissões de acordo com o perfil; agora tem mais coisas pra adicionar, reuniões etc."

**Estado antes:** `lk_areas()` tinha 12 áreas; Reuniões e várias telas novas (agenda, contratos, briefings, redes, mensagens, prospecção) ou estavam abertas a toda a equipe ou presas à área "clientes"/"leads".

**O que foi feito**
- `includes/helpers.php`: `lk_area_groups()` (áreas em grupos: Dia a dia, Conteúdo e entrega, Clientes, Comercial, Financeiro, Pedidos e estoque), `lk_areas()` achatada, `lk_team_roles()` com 10 perfis (Gestor, Comercial, Atendimento, Social, Redator, Designer, Videomaker, Tráfego, Financeiro, Outro; 3º item = descrição, 4º = aparece no cadastro por link; Gestor só o admin atribui), `lk_role_areas()`.
- Novas áreas: `reunioes`, `agenda`, `contratos`, `formularios`, `mensagens`, `redes`, `prospeccao` (+ `acessos` agora listada). Roteador (`includes/router.php`), menu (`includes/ui.php`) e ações (`lk_require`) passaram a usar essas chaves. A API de chat com clientes exige `mensagens`.
- Escopos por pessoa (user meta): `lk_only_own` (tarefas), `lk_only_clients` (carteira = colunas designer_id/social_id/atendimento_id/trafego_id/revisor_id do cliente) e `lk_only_meet` (reuniões que ela marcou). `lk_clients()` já filtra pela carteira; ficha do cliente e contrato fora da carteira dão "sem acesso"; reuniões filtradas por `lk_meetings_scope_sql()`/`lk_meeting_visible()`.
- Migração `lk_perms_migrate()` (roda uma vez, option `lk_perms_v2`): quem já era da equipe ganha `reunioes`; quem tinha `clientes` ganha agenda/contratos/formularios/redes/mensagens; quem tinha `leads` ganha prospecção. Ninguém perde acesso que tinha.
- Tela Equipe (`templates/panel/equipe.php`): lista de perfis com descrição, matriz de permissões por grupo ("marcar todos"), copiar de outra pessoa, escopos, resumo dos limites na lista.
- Cadastro por link da equipe só oferece perfis com a flag pública; aprovação aplica `lk_role_areas()`.
- Testes: `handoff/tests/ldk-permtest.php` (35 verificações: perfis × áreas, migração, escopo de clientes e reuniões, ações barradas), `ldk2.js` (cada perfil percorre 28 telas e confere menu), `ldk3.js` (criar pessoa pela tela, escopos, carteira, reuniões, celular). Todos passaram. WP de teste em `/tmp/wpl` (porta 8085, banco `wptl`).
- Limitação conhecida: o escopo "só meus clientes" cobre listas, ficha, contrato e reuniões; ações enviadas à mão (POST forjado) que recebem `client_id` de outro cliente não foram auditadas uma a uma.
- Observação: o LDK CRM ainda carrega módulos de herança 3D (estoque, slicer, produtos); estão desligados no `identity.php` e não foram mexidos.
