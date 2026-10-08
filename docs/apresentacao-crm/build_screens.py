#!/usr/bin/env python3
"""Gera as telas de demonstração do CRM (HTML com o CSS real do painel + dados fictícios) e tira as capturas em PNG."""
import re, subprocess, pathlib, html as H

ROOT = pathlib.Path(__file__).resolve().parent
CRM = ROOT.parent.parent / 'ldk-crm'
OUT = ROOT / 'telas'
OUT.mkdir(exist_ok=True)
CHROME = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome'

# Ícones reais do painel (helpers.php)
src = (CRM / 'includes/helpers.php').read_text(encoding='utf-8')
ICONS = dict(re.findall(r"^\s*'([\w-]+)'\s*=>\s*'(<.*?>)',\s*$", src, re.M))
def ic(n, s=18):
    return f'<svg class="ic" width="{s}" height="{s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{ICONS.get(n, "")}</svg>'

FIRM = 'Silva &amp; Costa'
THEME = '''
:root{--ink:#0B1F3A;--ink-2:#13294b;--ink-3:#1d3560;--bg:#F3F4F6;--accent:#C9A24B;--accent-soft:#f6efdc;--on-accent:#0B1F3A;--on-ink:#fff;--accent-text:#8a6a1f;--font:'Inter',sans-serif;--head:'Inter',sans-serif;}
body.lk{font-family:'Inter',sans-serif}
.side{background:#0B1F3A}.side-nav a.is-active{background:#13294b}
.side-brand{font-weight:800;font-size:20px;color:#fff;letter-spacing:-.02em;padding:6px 10px 2px}.side-brand small{display:block;font-size:11px;font-weight:500;color:#C9A24B;letter-spacing:.14em;text-transform:uppercase}
.btn--primary{background:#0B1F3A}.btn--primary:hover{background:#13294b}
.stat--dark{background:#0B1F3A}
html,body{overflow:hidden}.side{height:900px;position:static}
'''

NAV = [
 ('Operação', [('dashboard','Painel','dashboard'),('agenda','Prazos e agenda','calendario'),('tarefas','Tarefas','tarefas'),('chat','Chat da equipe','chat')]),
 ('Clientes', [('clientes','Clientes','clientes'),('contratos','Contratos','proposta'),('formularios','Fichas e briefings','lista'),('vencimentos','Vencimentos','globo'),('mensagens','Mensagens','chat')]),
 ('Comercial', [('leads','Funil de captação','funil'),('propostas','Propostas','proposta')]),
 ('Financeiro', [('cobrancas','Honorários e cobranças','financeiro')]),
]
def shell(active, title, body, actions='', badges=None):
    badges = badges or {}
    nav = ''
    for g, items in NAV:
        nav += f'<span class="side-label">{g}</span>'
        for slug, lab, icon in items:
            b = f'<em class="side-count">{badges[slug]}</em>' if slug in badges else ''
            nav += f'<a href="#" class="{"is-active" if slug==active else ""}">{ic(icon)}<span>{lab}</span>{b}</a>'
    return f'''<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><link rel="stylesheet" href="file://{CRM}/assets/app.css"><style>{THEME}</style></head>
<body class="lk lk-panel"><div class="app"><aside class="side"><div class="side-brand">{FIRM}<small>Advogados</small></div>
<button class="side-search">{ic('busca',16)}<span>Buscar…</span><kbd>⌘K</kbd></button><nav class="side-nav">{nav}</nav>
<div class="side-user"><span class="avatar">MS</span><span class="side-user-name">Dra. Marina Silva<small>Administradora</small></span></div></aside>
<main class="main"><header class="top"><h1 class="top-title">{title}</h1><div class="top-actions">{actions}</div></header><div class="content">{body}</div></main></div></body></html>'''

def badge(t, k=''): return f'<em class="badge {k}">{t}</em>'

# ---------------- telas ----------------
screens = {}

# 1 Painel
screens['painel'] = shell('dashboard', 'Bom dia, Marina', f'''
<section class="stats stats--4">
 <div class="stat"><span class="stat-label">Clientes ativos</span><strong>48</strong><small>3 novos este mês</small></div>
 <div class="stat"><span class="stat-label">Contratos pendentes</span><strong>3</strong><small>1 sem assinar há 2 dias</small></div>
 <div class="stat stat--warn"><span class="stat-label">Prazos desta semana</span><strong>7</strong><small>2 vencem amanhã</small></div>
 <div class="stat stat--dark"><span class="stat-label">Honorários a receber</span><strong>R$ 38.400</strong><small>neste mês</small></div>
</section>
<div class="dash-grid"><div class="dash-col">
 <section class="card"><div class="card-head"><h3>Para hoje</h3><span class="muted small">5 itens</span></div><ul class="checklist">
  <li class="check-item"><button class="tick"></button><span class="check-title">Protocolar petição inicial · Carlos Menezes<small>Prazo hoje · Dr. Paulo</small></span><span class="check-meta">{badge('urgente','badge--urgente')}</span></li>
  <li class="check-item"><button class="tick"></button><span class="check-title">Enviar contrato de honorários · Padaria Estrela Ltda<small>Rascunho pronto</small></span></li>
  <li class="check-item is-done"><button class="tick"></button><span class="check-title">Reunião de alinhamento · Família Andrade<small>Concluída</small></span></li>
  <li class="check-item"><button class="tick"></button><span class="check-title">Retornar ligação · Ana Beatriz Lopes<small>Lead do site</small></span></li>
  <li class="check-item"><button class="tick"></button><span class="check-title">Revisar minuta de contrato social · Tech Norte<small>Amanhã</small></span></li></ul></section>
 <section class="card"><div class="card-head"><h3>Contratos pendentes</h3><a class="small" href="#">ver todos</a></div><ul class="mini-list">
  <li><a href="#"><strong>Padaria Estrela Ltda</strong><span>Honorários mensais · rascunho</span></a>{badge('rascunho')}</li>
  <li><a href="#"><strong>Carlos Menezes</strong><span>Contencioso trabalhista · enviado há 2 dias</span></a>{badge('sem assinar 2 dias','badge--late')}</li>
  <li><a href="#"><strong>Tech Norte Ltda</strong><span>Consultoria societária · falta a assinatura do escritório</span></a>{badge('falta o escritório','badge--warn')}</li></ul></section>
</div><div class="dash-col">
 <section class="card"><div class="card-head"><h3>Vencimentos próximos</h3><a class="small" href="#">ver todos</a></div><ul class="mini-list">
  <li><a href="#"><strong>Certificado digital · Dr. Paulo</strong><span>vence 18/10</span></a>{badge('em 10 dias','badge--warn')}</li>
  <li><a href="#"><strong>Domínio · silvacosta.adv.br</strong><span>Registro.br · vence 02/11</span></a>{badge('em 25 dias','badge--warn')}</li>
  <li><a href="#"><strong>Hospedagem · site do escritório</strong><span>vence 02/11</span></a>{badge('em 25 dias','badge--warn')}</li></ul></section>
 <section class="card"><div class="card-head"><h3>Novos contatos</h3></div><ul class="mini-list">
  <li><a href="#"><strong>Ana Beatriz Lopes</strong><span>Direito de família · site</span></a>{badge('novo')}</li>
  <li><a href="#"><strong>Roberto Faria ME</strong><span>Consultoria preventiva · indicação</span></a>{badge('em contato')}</li></ul></section>
</div></div>''', badges={'contratos':3})

# 2 Clientes
rows = [('Carlos Menezes','CPF 123.***.***-10','(12) 99811-2030','carlos@email.com','2 ativos','ativo'),
        ('Padaria Estrela Ltda','CNPJ 12.345.678/0001-90','(12) 98877-4411','contato@padariaestrela.com','1 ativo','ativo'),
        ('Família Andrade','CPF 987.***.***-55','(12) 99700-1188','andrade@email.com','1 ativo','ativo'),
        ('Tech Norte Ltda','CNPJ 45.678.901/0001-22','(11) 97766-5500','juridico@technorte.com','3 ativos','ativo'),
        ('Ana Beatriz Lopes','CPF 321.***.***-77','(12) 99123-4567','ana@email.com','—','convidado'),
        ('Roberto Faria ME','CNPJ 56.789.012/0001-33','(12) 98111-2299','roberto@faria.com','1 ativo','ativo')]
tb = ''.join(f'<a class="table-row" href="#"><span class="cell-main"><span class="avatar">{"".join(w[0] for w in n.split()[:2]).upper()}</span><span><strong>{n}</strong><small>{d}</small></span></span><span>{w}<small>{e}</small></span><span>{p}</span><span>{badge("Acesso criado","badge--ok") if s=="ativo" else badge("Convite enviado")}</span><span class="cell-arrow">{ic("seta",16)}</span></a>' for n,d,w,e,p,s in rows)
screens['clientes'] = shell('clientes','Clientes', f'''<div class="toolbar"><input type="search" class="filter" placeholder="Filtrar por nome, empresa ou e-mail…"><span class="muted small">48 clientes</span></div>
<div class="table"><div class="table-row table-head"><span>Cliente</span><span>Contato</span><span>Casos</span><span>Acesso</span><span></span></div>{tb}</div>''',
 f'<button class="btn btn--primary">{ic("mais",16)}<span>Novo cliente</span></button>')

# 3 Contratos (nova tela)
def navlink(t,n,act=False): return f'<a class="{"is-active" if act else ""}" href="#"><span>{t}</span><em>{n}</em></a>'
crow = [('Padaria Estrela Ltda','Honorários mensais','Consultoria preventiva','R$ 1.800,00','rascunho','','—'),
        ('Carlos Menezes','Honorários · reclamação trabalhista','Contencioso','R$ 2.500,00','aguardando a assinatura do cliente','','2 dias'),
        ('Tech Norte Ltda','Consultoria societária','Societário','R$ 3.200,00','assinado pelo cliente','badge--warn','—'),]
tr = ''
for c,t,a,v,s,k,w in crow:
    late = 'badge--late' if 'dias' in w else 'badge--off'
    esp = f'<em class="badge {late}">{w}</em>' if w!='—' else '—'
    tr += f'<tr><td><a class="net-cli" href="#"><span class="avatar avatar--sm">{c[0]}</span><strong>{c}</strong></a></td><td><a href="#"><strong>{t}</strong></a><br><small class="muted">12 meses</small></td><td>{a}</td><td>{v}</td><td><em class="badge {k}">{s}</em></td><td>{esp}</td><td class="net-act"><a class="btn btn--ghost btn--sm" href="#">Abrir</a></td></tr>'
screens['contratos'] = shell('contratos','Contratos', f'''
<section class="stats stats--4"><div class="stat"><span class="stat-label">Pendentes</span><strong>3</strong><small>rascunho, enviado ou sem o escritório</small></div>
<div class="stat"><span class="stat-label">Sem assinar há 1 dia ou mais</span><strong class="text-late">1</strong><small>avisamos você automaticamente</small></div>
<div class="stat"><span class="stat-label">Assinados</span><strong>41</strong><small>pelas duas partes</small></div>
<div class="stat"><span class="stat-label">Honorários em contrato</span><strong class="money">R$ 61.300</strong><small>contratos assinados</small></div></section>
<div class="ctr-layout"><aside class="ctr-nav card"><span class="side-label" style="color:#7a7a76">Situação</span>
{navlink('Pendentes',3,True)}{navlink('Aguardando o cliente',1)}{navlink('Falta o escritório',1)}{navlink('Rascunhos',1)}{navlink('Assinados',41)}{navlink('Cancelados',2)}{navlink('Todos',47)}
<span class="side-label" style="color:#7a7a76">Assunto</span>{navlink('Contencioso',1)}{navlink('Consultoria preventiva',1)}{navlink('Societário',1)}{navlink('Família e sucessões',0)}{navlink('Sem assunto',0)}</aside>
<section class="card ctr-main"><div class="card-head"><h3>Pendentes</h3><input type="search" placeholder="Buscar por cliente ou título…" style="max-width:260px"></div>
<div class="pros-wrap"><table class="pros-table ctr-table"><thead><tr><th>Cliente</th><th>Contrato</th><th>Assunto</th><th>Mensal</th><th>Status</th><th>Esperando</th><th></th></tr></thead><tbody>{tr}</tbody></table></div></section></div>''',
 '<button class="btn btn--ghost">Subir contrato assinado</button> <button class="btn btn--primary">Gerar contrato</button>', badges={'contratos':3})

# 4 Gerar contrato (modal sobre a tela)
screens['gerar'] = shell('contratos','Contratos','''<div style="opacity:.25;pointer-events:none"><section class="stats stats--4"><div class="stat"><strong>3</strong></div><div class="stat"><strong>1</strong></div><div class="stat"><strong>41</strong></div><div class="stat"><strong>R$ 61.300</strong></div></section></div>
<div style="position:absolute;inset:0;background:rgba(5,8,11,.5);display:flex;align-items:center;justify-content:center"><div class="card" style="width:640px;padding:0;overflow:hidden">
<div style="display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid var(--line)"><h3 style="margin:0">Gerar contrato</h3><span style="font-size:22px">×</span></div>
<div class="stack" style="padding:22px 24px">
<label class="field"><span>Cliente</span><select><option>Padaria Estrela Ltda</option></select></label>
<fieldset class="ctr-types"><legend>Serviços do contrato</legend>
<label class="check"><input type="checkbox" checked><span><strong>Consultoria preventiva</strong><small>Análise contratual · Parecer mensal · Atendimento por WhatsApp</small></span></label>
<label class="check"><input type="checkbox" checked><span><strong>Societário</strong><small>Alterações contratuais · Registro na Junta Comercial</small></span></label>
<label class="check"><input type="checkbox"><span><strong>Contencioso</strong><small>Acompanhamento processual · Audiências · Recursos</small></span></label>
<label class="check"><input type="checkbox"><span><strong>Família e sucessões</strong><small>Inventário · Divórcio · Planejamento sucessório</small></span></label></fieldset>
<div class="grid-3"><label class="field"><span>Valor mensal (R$)</span><input value="1.800,00"></label><label class="field"><span>Implantação (R$)</span><input value=""></label><label class="field"><span>Meses de contrato</span><input value="12"></label></div>
<p class="muted small">Os dados do cliente e do escritório entram sozinhos no texto. O contrato nasce como rascunho: você confere, inclui cláusulas e só então envia para assinatura.</p>
<div class="form-actions"><button class="btn btn--ghost">Cancelar</button><button class="btn btn--primary">Gerar contrato</button></div></div></div></div>''',
 '<button class="btn btn--ghost">Subir contrato assinado</button> <button class="btn btn--primary">Gerar contrato</button>')

# 5 Contrato (documento) + assinaturas
screens['contrato'] = shell('contratos','Contrato · Padaria Estrela Ltda', f'''
<section class="card flow-card"><div class="flow-now"><span class="muted small">Contrato de honorários mensais</span><strong>{badge('rascunho')}</strong><span class="muted small">R$ 1.800,00/mês · 12 meses · vence dia 10</span></div>
<div class="row-btns"><button class="btn btn--primary">{ic('email',16)}<span>Enviar para assinatura</span></button><button class="btn btn--ghost">Editar dados</button><button class="btn btn--ghost">Duplicar</button></div></section>
<section class="card"><div class="card-head"><h3>Briefing de cada serviço</h3><span class="muted small">peça ao cliente as informações do que ele contratou</span></div>
<div class="pay-row"><span><strong>Consultoria preventiva</strong><small>Modelo: Ficha de atendimento · empresa</small></span><button class="btn btn--primary btn--sm">Enviar briefing</button></div>
<div class="pay-row"><span><strong>Societário</strong><small>Modelo: Ficha societária</small></span><button class="btn btn--primary btn--sm">Enviar briefing</button></div></section>
<div class="contract-grid"><section class="card contract-doc"><div class="contract-text"><h3>CONTRATO DE PRESTAÇÃO DE SERVIÇOS ADVOCATÍCIOS</h3>
<p>Pelo presente instrumento particular, de um lado <strong>Silva &amp; Costa Advogados</strong>, inscrita no CNPJ sob o nº 11.222.333/0001-44, neste ato representada por Marina Silva, doravante CONTRATADA, e de outro <strong>Padaria Estrela Ltda</strong>, inscrita no CNPJ sob o nº 12.345.678/0001-90, representada por João Estrela, doravante CONTRATANTE, têm entre si justo e contratado o seguinte:</p>
<h3>CLÁUSULA 1ª · DO OBJETO</h3><p>A CONTRATADA prestará assessoria jurídica preventiva e societária, compreendendo:</p><ul><li>Análise e elaboração de contratos</li><li>Parecer mensal de conformidade</li><li>Alterações contratuais e registros</li></ul>
<h3>CLÁUSULA 2ª · DOS HONORÁRIOS</h3><p>Pelos serviços, a CONTRATANTE pagará o valor mensal de <strong>R$ 1.800,00</strong> (mil e oitocentos reais), com vencimento todo dia 10, por Pix, boleto ou cartão.</p></div></section>
<aside class="contract-side"><section class="card"><div class="card-head"><h3>Assinaturas</h3></div><div class="sig-box"><strong>Padaria Estrela Ltda</strong><small class="muted">aguardando</small></div><div class="sig-box"><strong>Silva &amp; Costa</strong><small class="muted">aguardando</small></div></section></aside></div>''',
 f'<a class="btn btn--ghost" href="#">{ic("olho",16)}<span>Ver como o cliente</span></a>')

# 6 Fichas/briefings por serviço
mods = [('Ficha de atendimento · empresa','Consultoria preventiva',9),('Ficha societária','Societário',11),('Ficha do caso trabalhista','Contencioso',14),('Ficha de família e sucessões','Família e sucessões',12),('Pesquisa de satisfação','Todos os serviços',8)]
ml = ''.join(f'<li><a href="#"><strong>{a}</strong><span>{b} · {n} perguntas</span></a><button class="btn btn--ghost btn--sm">Enviar</button><button class="btn btn--ghost btn--sm">Editar</button></li>' for a,b,n in mods)
screens['fichas'] = shell('formularios','Fichas e briefings', f'''<section class="card"><div class="card-head"><h3>Um modelo para cada tipo de serviço</h3><span class="muted small">edite as perguntas, ordem e tipo de resposta</span></div><ul class="mini-list">{ml}</ul></section>
<section class="card"><div class="card-head"><h3>Respostas recentes</h3></div><ul class="mini-list">
<li><a href="#"><strong>Ficha do caso trabalhista · Carlos Menezes</strong><span>respondida hoje às 09:41</span></a>{badge('respondido','badge--ok')}</li>
<li><a href="#"><strong>Ficha societária · Tech Norte Ltda</strong><span>enviada há 1 dia</span></a>{badge('aguardando o cliente','badge--wait')}</li></ul></section>''',
 '<button class="btn btn--primary">Novo modelo</button>')

# 7 Vencimentos
vr = [('Dr. Paulo Costa','Certificado digital A3','Soluti','18/10/2026','10 dias','badge--warn','R$ 240,00'),
      ('Silva &amp; Costa','Domínio · silvacosta.adv.br','Registro.br','02/11/2026','25 dias','badge--warn','R$ 40,00'),
      ('Silva &amp; Costa','Hospedagem · site do escritório','Hostinger','02/11/2026','25 dias','badge--warn','R$ 389,00'),
      ('Padaria Estrela Ltda','Domínio · padariaestrela.com.br','Registro.br','14/01/2027','98 dias','badge--ok','R$ 40,00'),
      ('Tech Norte Ltda','Hospedagem · technorte.com.br','Locaweb','20/02/2027','135 dias','badge--ok','R$ 540,00'),
      ('Dra. Marina Silva','Anuidade OAB','OAB/SP','31/03/2027','174 dias','badge--ok','R$ 1.250,00')]
vt = ''.join(f'<tr><td><a class="net-cli" href="#"><strong>{c}</strong></a></td><td>{i}</td><td>{p}</td><td>{d} <em class="badge {k}">em {n}</em></td><td>{v}</td><td class="net-act"><a class="btn btn--ghost btn--sm" href="#">Abrir</a></td></tr>' for c,i,p,d,n,k,v in vr)
screens['vencimentos'] = shell('vencimentos','Hospedagem e domínios', f'''<section class="stats stats--3" style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px"><div class="stat"><span class="stat-label">Vencidos</span><strong>0</strong><small>precisam de renovação</small></div><div class="stat"><span class="stat-label">Vencem em 30 dias</span><strong>3</strong><small>avisamos a equipe antes</small></div><div class="stat"><span class="stat-label">Cadastrados</span><strong>6</strong><small>hospedagens, domínios e outros</small></div></section>
<section class="card"><div class="card-head"><span class="chips"><a class="btn btn--sm btn--primary" href="#">Todos</a><a class="btn btn--sm btn--ghost" href="#">Próximos 30 dias</a><a class="btn btn--sm btn--ghost" href="#">Vencidos</a></span></div>
<div class="pros-wrap"><table class="pros-table"><thead><tr><th>Cliente</th><th>Item</th><th>Fornecedor</th><th>Vence em</th><th>Valor</th><th></th></tr></thead><tbody>{vt}</tbody></table></div></section>''', badges={'contratos':3})

# 8 Ficha do cliente com vencimentos
screens['ficha'] = shell('clientes','Padaria Estrela Ltda', f'''<section class="card" id="vencimentos"><div class="card-head"><h3>Hospedagem, domínio e vencimentos</h3><button class="btn btn--primary btn--sm">+ Adicionar</button></div>
<div class="pay-row"><span><strong>Domínio · padariaestrela.com.br</strong><small>Registro.br · vence 14/01/2027 · R$ 40,00 · Anual</small></span>{badge('em 98 dias','badge--ok')}<button class="btn btn--ghost btn--sm">Renovado</button><button class="btn btn--ghost btn--sm">Editar</button></div>
<div class="pay-row"><span><strong>Hospedagem · site</strong><small>Hostinger · vence 14/01/2027 · R$ 389,00 · Anual</small></span>{badge('em 98 dias','badge--ok')}<button class="btn btn--ghost btn--sm">Renovado</button><button class="btn btn--ghost btn--sm">Editar</button></div>
<div class="pay-row"><span><strong>Certificado digital da empresa</strong><small>Serasa · vence 05/12/2026 · R$ 210,00 · Anual</small></span>{badge('em 58 dias','badge--warn')}<button class="btn btn--ghost btn--sm">Renovado</button><button class="btn btn--ghost btn--sm">Editar</button></div></section>
<section class="card" id="contratos"><div class="card-head"><h3>Contratos</h3><span class="row-btns"><button class="btn btn--ghost btn--sm">Subir contrato assinado</button><button class="btn btn--primary btn--sm">+ Novo contrato</button></span></div><ul class="mini-list"><li><a href="#"><strong>Contrato de honorários mensais</strong><small>R$ 1.800,00/mês · 12 meses · rascunho</small></a></li></ul></section>
<section class="card"><div class="card-head"><h3>Dados do cliente</h3></div><div class="grid-2"><label class="field"><span>Razão social</span><input value="Padaria Estrela Ltda"></label><label class="field"><span>CNPJ</span><input value="12.345.678/0001-90"></label><label class="field"><span>Responsável</span><input value="João Estrela"></label><label class="field"><span>WhatsApp</span><input value="(12) 98877-4411"></label></div></section>''')

# 9 Funil
def kc(t,v,s,n=''):
    return f'<article class="kcard kcard--lead"><div class="kcard-top"><span class="badge">{s}</span><strong class="kcard-value">{v}</strong></div><h4>{t}</h4>{n}<div class="kcard-foot"><span class="muted small">contato há 1 dia</span></div></article>'
cols = [('Novo contato',[kc('Ana Beatriz Lopes','R$ 4.000','Site'),kc('Roberto Faria ME','R$ 1.500','Indicação')]),('Consulta agendada',[kc('Condomínio Vila Real','R$ 2.200','Google')]),('Proposta enviada',[kc('Clínica Bem Estar','R$ 3.000','Indicação','<div class="kcard-next"><span class="due due--soon">amanhã</span><span>Ligar para confirmar</span></div>')]),('Fechado',[kc('Padaria Estrela Ltda','R$ 1.800','Site')])]
kb = ''.join(f'<section class="kcol"><header class="kcol-head"><h3>{n}</h3><span class="kcount">{len(c)}</span></header><div class="kcol-body">{"".join(c)}</div></section>' for n,c in cols)
screens['funil'] = shell('leads','Funil de captação', f'''<section class="stats stats--4"><div class="stat"><span class="stat-label">Contatos em aberto</span><strong>5</strong></div><div class="stat stat--dark"><span class="stat-label">Em negociação</span><strong>R$ 10.700</strong><small>soma do valor estimado</small></div><div class="stat stat--warn"><span class="stat-label">Retornos para hoje</span><strong>2</strong></div><div class="stat"><span class="stat-label">Taxa de conversão</span><strong>38%</strong><small>fechados ÷ (fechados + perdidos)</small></div></section>
<div class="kanban kanban--leads">{kb}</div>''', f'<button class="btn btn--primary">{ic("mais",16)}<span>Novo contato</span></button>')

# 10 Cobranças
cr = [('Tech Norte Ltda','Honorários · outubro','10/10','R$ 3.200,00','pago','badge--ok'),('Padaria Estrela Ltda','Honorários · outubro','10/10','R$ 1.800,00','a vencer','badge--wait'),('Carlos Menezes','Honorários · outubro','05/10','R$ 2.500,00','vencido há 3 dias','badge--late'),('Família Andrade','Honorários · outubro','15/10','R$ 1.200,00','a vencer','badge--wait'),('Roberto Faria ME','Honorários · outubro','20/10','R$ 1.500,00','a vencer','badge--wait')]
ct = ''.join(f'<tr><td><strong>{c}</strong></td><td>{d}</td><td>{v}</td><td>{m}</td><td><em class="badge {k}">{s}</em></td><td class="net-act"><a class="btn btn--ghost btn--sm" href="#">Link de pagamento</a></td></tr>' for c,d,v,m,s,k in cr)
screens['cobrancas'] = shell('cobrancas','Honorários e cobranças', f'''<section class="stats stats--4"><div class="stat"><span class="stat-label">Previsto no mês</span><strong>R$ 38.400</strong></div><div class="stat"><span class="stat-label">Recebido</span><strong>R$ 21.900</strong><small>57%</small></div><div class="stat stat--warn"><span class="stat-label">A vencer</span><strong>R$ 13.200</strong></div><div class="stat"><span class="stat-label">Vencido</span><strong class="text-late">R$ 3.300</strong><small>2 clientes</small></div></section>
<section class="card"><div class="card-head"><h3>Outubro</h3></div><div class="pros-wrap"><table class="pros-table"><thead><tr><th>Cliente</th><th>Descrição</th><th>Vence</th><th>Valor</th><th>Situação</th><th></th></tr></thead><tbody>{ct}</tbody></table></div></section>''')


# 11 Assinatura do cliente (página pública real: .ctr-*)
sig_svg = '<svg viewBox="0 0 600 200" style="height:120px;width:100%"><path d="M60 140 C 90 60, 130 60, 150 120 S 200 160, 230 90 S 290 60, 320 130 S 400 150, 450 80" fill="none" stroke="#0B1F3A" stroke-width="3" stroke-linecap="round"/></svg>'
screens['assinatura'] = f"""<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><link rel="stylesheet" href="file://{CRM}/assets/app.css"><style>{THEME} html,body{{overflow:hidden}} .ctr{{max-width:860px;margin:0 auto;padding:0 20px}} .ctr-doc{{max-height:330px;overflow:hidden}}</style></head><body class="lk ctr-page"><div class="ctr">
<header class="ctr-top"><strong style="font-size:20px;color:#0B1F3A">Silva &amp; Costa <span style="color:#C9A24B;font-size:11px;letter-spacing:.14em">ADVOGADOS</span></strong><span class="ctr-st">Aguardando a sua assinatura</span><button class="ctr-print">Salvar em PDF / imprimir</button></header>
<article class="ctr-doc"><h3>CONTRATO DE PRESTAÇÃO DE SERVIÇOS ADVOCATÍCIOS</h3><p>Pelo presente instrumento particular, de um lado <strong>Silva &amp; Costa Advogados</strong>, doravante CONTRATADA, e de outro <strong>Carlos Menezes</strong>, doravante CONTRATANTE, têm entre si justo e contratado o seguinte:</p><h3>CLÁUSULA 1ª · DO OBJETO</h3><p>A CONTRATADA prestará assessoria e patrocínio na reclamação trabalhista, compreendendo acompanhamento processual, audiências e recursos.</p><h3>CLÁUSULA 2ª · DOS HONORÁRIOS</h3><p>Pelos serviços, a CONTRATANTE pagará o valor mensal de <strong>R$ 2.500,00</strong>, com vencimento todo dia 5.</p></article>
<section class="ctr-sign"><h2>Assinar o contrato</h2><p>Enviamos o código para <strong>c•••••@email.com</strong> (vale 10 minutos).</p>
<div class="ctr-grid"><label><span>Código recebido</span><input value="482 915"></label><label><span>Nome completo</span><input value="Carlos Menezes"></label><label><span>CPF</span><input value="123.456.789-10"></label></div>
<div class="sig-pad"><span class="sig-pad-t"></span>{sig_svg}</div>
<label class="ctr-check"><input type="checkbox" checked> Li e concordo com todas as cláusulas deste contrato e com a assinatura eletrônica.</label><button class="ctr-btn" style="background:#0B1F3A">✍️ Assinar contrato</button></section></div></body></html>"""

# 12 Área do cliente
CNAV = [('Início','dashboard'),('Meu caso','arquivo'),('Contratos','proposta'),('Fichas e perguntas','lista'),('Reuniões','calendario'),('Mensagens','chat')]
cn = ''.join(f'<a href="#" class="{"is-active" if i==0 else ""}">{ic(ico)}<span>{lab}</span></a>' for i,(lab,ico) in enumerate(CNAV))
screens['cliente'] = f"""<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><link rel="stylesheet" href="file://{CRM}/assets/app.css"><style>{THEME}</style></head>
<body class="lk lk-panel"><div class="app"><aside class="side"><div class="side-brand">{FIRM}<small>Área do cliente</small></div><nav class="side-nav" style="margin-top:20px">{cn}</nav><div class="side-user"><span class="avatar">CM</span><span class="side-user-name">Carlos Menezes<small>Cliente</small></span></div></aside>
<main class="main"><header class="top"><h1 class="top-title">Olá, Carlos 👋</h1></header><div class="content">
<section class="stats stats--4"><div class="stat"><span class="stat-label">Contrato</span><strong style="font-size:18px">{badge('para assinar','badge--wait')}</strong></div><div class="stat"><span class="stat-label">Próxima reunião</span><strong style="font-size:22px">Qui, 14h</strong></div><div class="stat"><span class="stat-label">Fichas pendentes</span><strong>1</strong></div><div class="stat"><span class="stat-label">Documentos</span><strong>6</strong></div></section>
<div class="dash-grid"><div class="dash-col"><section class="card card--accent"><div class="card-head"><h3>Falta pouco: assine o seu contrato</h3></div><p class="muted">O contrato de honorários está pronto. Você recebe um código no e-mail e assina em poucos minutos, pelo celular ou computador.</p><button class="btn btn--primary">Ler e assinar</button></section>
<section class="card"><div class="card-head"><h3>Responda a ficha do seu caso</h3></div><p class="muted small">14 perguntas · leva cerca de 8 minutos</p><div class="progress"><span style="width:30%"></span></div><br><button class="btn btn--ghost">Continuar</button></section></div>
<div class="dash-col"><section class="card"><div class="card-head"><h3>Contratos e documentos</h3></div><ul class="mini-list"><li><a href="#"><strong>Contrato de honorários</strong><span>enviado em 06/10</span></a>{badge('para assinar','badge--wait')}</li><li><a href="#"><strong>Procuração</strong><span>12/09</span></a>{badge('assinado','badge--ok')}</li><li><a href="#"><strong>Petição inicial (cópia)</strong><span>20/09</span></a>{badge('arquivo')}</li></ul></section></div></div></div></main></div></body></html>"""

def shot(name, html, w=1440, h=1100):
    f = OUT / f'{name}.html'
    f.write_text(html, encoding='utf-8')
    subprocess.run([CHROME, '--headless=new', '--no-sandbox', '--disable-gpu', '--hide-scrollbars', f'--window-size={w},{h}', '--force-device-scale-factor=1.5', '--virtual-time-budget=6000', f'--screenshot={OUT / (name + ".png")}', f'file://{f}'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    from PIL import Image
    p = OUT / (name + '.png')
    im = Image.open(p)
    im.crop((0, 0, 2160, 1350)).save(p)

for n, h in screens.items():
    shot(n, h)
    print('ok', n)
