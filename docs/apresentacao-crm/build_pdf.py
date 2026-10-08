#!/usr/bin/env python3
"""Monta o PDF de apresentação do CRM (HTML → PDF pelo Chromium).

Valores comerciais: edite IMPLANTACAO e MENSALIDADE abaixo (ou passe por variável de ambiente) e rode de novo:
    IMPLANTACAO="R$ 4.900,00" MENSALIDADE="R$ 490,00" python3 build_pdf.py
"""
import os, pathlib, subprocess, html

ROOT = pathlib.Path(__file__).resolve().parent
T = ROOT / 'telas'
IMPLANTACAO = os.environ.get('IMPLANTACAO', '')
MENSALIDADE = os.environ.get('MENSALIDADE', '')
def money(v): return html.escape(v) if v else 'R$ ________'

# (kicker, título, resumo, passos, benefício, [imagens])
PAGES = [
 ('Visão geral', 'Tudo do escritório em um só lugar',
  'Um sistema feito sob medida: cadastro de clientes, contratos com assinatura online, fichas de atendimento, prazos, vencimentos, honorários e a área onde o próprio cliente acompanha tudo.',
  ['O cliente entra pelo site ou por indicação e cai no funil de captação.', 'Vira cliente: a ficha reúne dados, contratos, documentos e vencimentos.', 'Você gera o contrato com um clique, o cliente assina pelo celular e você é avisado.', 'Os honorários são cobrados e acompanhados no mesmo painel.'],
  'Menos planilha, menos papel e menos “cadê aquele documento?”.', ['painel']),
 ('Painel', 'O dia do escritório em uma tela',
  'Ao abrir o sistema você vê o que precisa de atenção hoje: tarefas, prazos, contratos esperando assinatura, vencimentos próximos e novos contatos.',
  ['Os números principais ficam no topo: clientes, contratos pendentes, prazos e honorários a receber.', 'A lista “Para hoje” se marca sozinha conforme a equipe conclui os itens.', 'Contratos sem assinatura e vencimentos aparecem em destaque, com cores de alerta.'],
  'Nada se perde: o que está atrasado fica visível antes de virar problema.', ['painel']),
 ('Clientes', 'A ficha completa de cada cliente',
  'Pessoas e empresas cadastradas com documento, contatos, responsável no escritório, contratos, fichas respondidas, hospedagem e domínio, tudo na mesma página.',
  ['Cadastre o cliente ou mande um convite para ele mesmo preencher os dados.', 'Os dados da ficha entram sozinhos nos contratos (nome, CPF/CNPJ, endereço).', 'Contratos, documentos e vencimentos ficam dentro da ficha, sempre à mão.'],
  'O cadastro é feito uma vez e reaproveitado em todo documento.', ['clientes', 'ficha']),
 ('Contratos', 'Todos os contratos, organizados por situação e por assunto',
  'A tela de Contratos tem um menu lateral próprio: de um lado a situação (pendentes, aguardando o cliente, falta o escritório, rascunhos, assinados, cancelados) e do outro o assunto (contencioso, consultoria, societário…).',
  ['Abre direto em “Pendentes”: o que ainda precisa de ação.', 'Clique em um assunto para ver só os contratos daquele tipo.', 'Cada linha mostra há quantos dias o cliente está sem assinar.', 'O menu do sistema mostra a quantidade de contratos pendentes.'],
  'Você encontra qualquer contrato em segundos e sabe quais estão parados.', ['contratos']),
 ('Gerar contrato', 'Um botão, dados preenchidos',
  'Nada é criado sozinho: quando você decide, clica em “Gerar contrato”, escolhe o cliente e marca os serviços. Os dados do cliente e do escritório entram automaticamente no texto.',
  ['Escolha o cliente e marque os serviços contratados.', 'Informe valor mensal, implantação e prazo (ou deixe os valores da ficha).', 'O contrato nasce como rascunho: você confere, inclui cláusulas e só então envia.'],
  'Em vez de montar o contrato do zero, você apenas revisa.', ['gerar', 'contrato']),
 ('Assinatura online', 'O cliente assina pelo celular, com validade e comprovante',
  'O cliente recebe o link por e-mail ou WhatsApp, confirma um código enviado ao e-mail dele e assina com o dedo. Se passar um dia sem assinatura, o sistema avisa você.',
  ['Envie o contrato por e-mail e WhatsApp; o texto é “congelado” e protegido por código (SHA-256).', 'O cliente confirma o e-mail por código, desenha a assinatura e aceita.', 'O sistema registra data, hora, IP e navegador e gera o certificado de assinatura.', 'Sem assinatura após 1 dia: você recebe um aviso no painel e por e-mail, e pode lembrar o cliente com um clique.', 'A cópia assinada vai para os dois e-mails e para a pasta do cliente no Google Drive.'],
  'Assinatura eletrônica com registro de autoria, nos termos da Lei 14.063/2020 e da MP 2.200-2/2001, art. 10, § 2º.', ['assinatura']),
 ('Fichas e briefings', 'Uma ficha de atendimento para cada tipo de serviço',
  'Cada serviço tem o seu questionário: o cliente responde pelo celular, em etapas curtas, e as respostas ficam salvas no cadastro. Você monta e edita as perguntas.',
  ['Escolha o modelo do serviço (ou crie o seu) e envie para o cliente.', 'Ele responde passo a passo, com múltipla escolha e campos de texto.', 'Você é avisado quando ele responder e as respostas ficam na ficha.'],
  'Você começa o caso já com as informações certas, sem ligar três vezes para pedir.', ['fichas']),
 ('Vencimentos', 'Hospedagem, domínio, certificado digital e o que mais vencer',
  'Cadastre o que vence para cada cliente (ou para o próprio escritório): hospedagem, domínio, certificado digital, anuidade ou qualquer outro item que você nomear.',
  ['Na ficha do cliente, clique em “Adicionar”, escolha o tipo e a data de vencimento.', 'O sistema avisa a equipe 30, 15, 7 e 1 dia antes, e no dia.', 'Ao renovar, um clique em “Renovado” empurra a data para o próximo ciclo.', 'A tela “Vencimentos” lista tudo, do mais próximo ao mais distante.'],
  'Ninguém mais descobre que o domínio venceu quando o site já saiu do ar.', ['vencimentos', 'ficha']),
 ('Captação', 'Do primeiro contato ao cliente fechado',
  'Quadro com colunas por etapa: novo contato, consulta agendada, proposta enviada, fechado. Cada cartão tem origem, valor estimado, próximo passo e botão de WhatsApp.',
  ['Os contatos do site entram sozinhos; você também cadastra os de indicação.', 'Arraste o cartão de coluna conforme a conversa avança.', 'Quando o cliente aceita, clique em “Gerar contrato” direto do cartão.'],
  'Você enxerga quanto está em negociação e quem precisa de retorno hoje.', ['funil']),
 ('Honorários', 'Cobranças e honorários acompanhados mês a mês',
  'Veja quanto está previsto, recebido, a vencer e vencido, por cliente, com link de pagamento para enviar.',
  ['O valor mensal do contrato assinado alimenta a cobrança do cliente.', 'Cada cobrança mostra a situação: paga, a vencer ou vencida.', 'Envie o link de pagamento (Pix, boleto ou cartão) pelo WhatsApp ou e-mail.'],
  'Fluxo de caixa claro e menos cobrança manual.', ['cobrancas']),
 ('Área do cliente', 'O cliente acompanha tudo sem precisar ligar',
  'Cada cliente tem o próprio acesso, com a marca do escritório: assina contratos, responde fichas, vê documentos, reuniões e mensagens.',
  ['Você manda o convite e o cliente cria a senha.', 'Ele vê o que está pendente para ele, como contrato a assinar ou ficha a responder.', 'Funciona no celular, sem instalar nada.'],
  'Atendimento mais ágil e uma imagem mais profissional.', ['cliente']),
]

CSS = '''
@page{size:297mm 210mm;margin:0}
*{box-sizing:border-box}html{-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{margin:0;font-family:'Inter',sans-serif;color:#0B1F3A}
.pg{width:297mm;height:210mm;position:relative;overflow:hidden;page-break-after:always;background:#fff;padding:16mm 18mm 14mm}
.pg:last-child{page-break-after:auto}
.top{position:absolute;left:18mm;right:18mm;top:9mm;display:flex;justify-content:space-between;font-size:8.5pt;color:#7b8794;letter-spacing:.08em;text-transform:uppercase}
.foot{position:absolute;left:18mm;right:18mm;bottom:7mm;display:flex;justify-content:space-between;font-size:7.5pt;color:#9aa5b1}
.kick{color:#8a6a1f;font-weight:700;font-size:9pt;letter-spacing:.14em;text-transform:uppercase;margin:6mm 0 2mm}
h2{font-size:22pt;line-height:1.12;margin:0 0 4mm;letter-spacing:-.02em}
.lead{font-size:10.5pt;line-height:1.5;color:#3b4a5c;margin:0 0 5mm}
.cols{display:grid;grid-template-columns:96mm 1fr;gap:10mm;height:170mm;align-items:center}
ol{margin:0 0 5mm;padding:0;list-style:none;counter-reset:n}
ol li{counter-increment:n;position:relative;padding-left:9mm;font-size:9.5pt;line-height:1.4;margin-bottom:2.6mm}
ol li::before{content:counter(n);position:absolute;left:0;top:0;width:6mm;height:6mm;border-radius:50%;background:#0B1F3A;color:#fff;font-size:8pt;font-weight:700;display:flex;align-items:center;justify-content:center}
.gain{border-left:3px solid #C9A24B;background:#faf6ea;padding:3mm 4mm;font-size:9.5pt;line-height:1.4;font-weight:600;border-radius:0 3mm 3mm 0}
.shots{display:flex;flex-direction:column;gap:5mm;align-items:center;justify-content:center}
.shot{border-radius:3mm;border:.3mm solid #d9dee4;box-shadow:0 3mm 8mm rgba(11,31,58,.18);overflow:hidden;width:100%;background:#fff}
.shot img{display:block;width:100%}
.shots.two .shot{width:86%}
.cover{background:#0B1F3A;color:#fff;padding:0}
.cover .in{position:absolute;left:24mm;top:48mm;width:150mm}
.cover .tag{color:#C9A24B;font-weight:700;letter-spacing:.2em;font-size:10pt;text-transform:uppercase}
.cover h1{font-size:38pt;line-height:1.05;margin:6mm 0;letter-spacing:-.03em}
.cover p{font-size:12pt;color:#c5cfdb;line-height:1.5}
.cover .by{position:absolute;left:24mm;bottom:20mm;font-size:10pt;color:#9fb0c4}
.cover .by b{color:#fff}
.cover .art{position:absolute;right:-14mm;top:30mm;width:150mm;transform:rotate(-4deg);border-radius:4mm;overflow:hidden;box-shadow:0 8mm 20mm rgba(0,0,0,.5);border:.4mm solid rgba(255,255,255,.2)}
.cover .art img{display:block;width:100%}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:5mm;margin-top:6mm}
.box{border:.3mm solid #d9dee4;border-radius:3mm;padding:5mm;background:#fff}
.box h3{margin:0 0 2mm;font-size:11pt}.box p{margin:0;font-size:9pt;line-height:1.45;color:#3b4a5c}
.box .ico{width:9mm;height:9mm;border-radius:2.4mm;background:#0B1F3A;color:#C9A24B;display:flex;align-items:center;justify-content:center;font-weight:800;margin-bottom:3mm}
.price{display:grid;grid-template-columns:1fr 1fr;gap:8mm;margin-top:8mm}
.pc{border-radius:4mm;padding:9mm;background:#0B1F3A;color:#fff}.pc.alt{background:#faf6ea;color:#0B1F3A;border:.3mm solid #e6d8a8}
.pc small{font-size:8.5pt;letter-spacing:.14em;text-transform:uppercase;color:#C9A24B;font-weight:700}.pc.alt small{color:#8a6a1f}
.pc strong{display:block;font-size:28pt;margin:3mm 0;letter-spacing:-.02em}
.pc ul{margin:4mm 0 0;padding-left:5mm;font-size:9.5pt;line-height:1.55}
.note{font-size:8.5pt;color:#7b8794;margin-top:6mm}
'''

def page(n, kick, title, lead, steps, gain, imgs):
    shots = ''.join(f'<div class="shot"><img src="telas/{i}.png"></div>' for i in imgs)
    two = ' two' if len(imgs) > 1 else ''
    li = ''.join(f'<li>{html.escape(s)}</li>' for s in steps)
    return f'''<section class="pg"><div class="top"><span>CRM sob medida · escritórios de advocacia</span><span>{html.escape(kick)}</span></div>
<div class="cols"><div><div class="kick">{html.escape(kick)}</div><h2>{html.escape(title)}</h2><p class="lead">{html.escape(lead)}</p><ol>{li}</ol><div class="gain">{html.escape(gain)}</div></div><div class="shots{two}">{shots}</div></div>
<div class="foot"><span>Telas ilustrativas, com dados fictícios, no layout real do sistema.</span><span>{n}</span></div></section>'''

out = []
out.append('''<section class="pg cover"><div class="in"><div class="tag">Apresentação</div><h1>O CRM do seu escritório, feito sob medida</h1><p>Clientes, contratos com assinatura online, fichas de atendimento, vencimentos e honorários em um só sistema, com a cara e o jeito de trabalhar do escritório.</p></div>
<div class="art"><img src="telas/contratos.png"></div><div class="by">Apresentado por <b>Eleve Websites</b></div></section>''')

# visão geral com grade de módulos
mods = [('Clientes','Ficha completa com documentos, contratos e vencimentos.'),('Contratos','Gerar com um clique, assinar online e acompanhar pendentes.'),('Fichas e briefings','Questionário próprio para cada tipo de serviço.'),('Vencimentos','Hospedagem, domínio, certificado digital e outros, com aviso antes.'),('Funil de captação','Do primeiro contato ao cliente fechado.'),('Honorários','Cobranças, link de pagamento e situação mês a mês.'),('Agenda e reuniões','Prazos, compromissos e reuniões com os clientes.'),('Área do cliente','Acesso próprio para assinar, responder e acompanhar.'),('Segurança','Acesso protegido, dados criptografados e foco na LGPD.')]
bx = ''.join(f'<div class="box"><div class="ico">{i+1}</div><h3>{t}</h3><p>{d}</p></div>' for i,(t,d) in enumerate(mods))
out.append(f'''<section class="pg"><div class="top"><span>CRM sob medida · escritórios de advocacia</span><span>O que está disponível</span></div><div class="kick" style="margin-top:12mm">O que está disponível</div><h2>Tudo o que o sistema faz hoje</h2><p class="lead" style="max-width:190mm">Cada módulo é ligado ou desligado conforme a necessidade do escritório. Nas próximas páginas, cada um aparece em tela, com o passo a passo de como funciona.</p><div class="grid">{bx}</div><div class="foot"><span>Telas ilustrativas, com dados fictícios, no layout real do sistema.</span><span>2</span></div></section>''')

for i, p in enumerate(PAGES[1:], start=3):
    out.append(page(i, *p))

n = len(PAGES) + 2
sec = [('Acesso protegido','Login com confirmação por código e senhas guardadas de forma criptografada.'),('Quem vê o quê','Cada pessoa da equipe tem permissões: financeiro, contratos e clientes podem ser restritos.'),('Cada cliente, o seu acesso','O cliente só enxerga o que é dele.'),('Registro das assinaturas','Data, hora, IP, e-mail confirmado e código do documento em cada contrato assinado.'),('LGPD','Tratamento de dados pessoais com cláusula própria nos contratos e controle de quem acessa.'),('Cópia no Google Drive','Contratos e fichas respondidas podem ser guardados na pasta de cada cliente.')]
sb = ''.join(f'<div class="box"><div class="ico">✓</div><h3>{t}</h3><p>{d}</p></div>' for t,d in sec)
out.append(f'''<section class="pg"><div class="top"><span>CRM sob medida · escritórios de advocacia</span><span>Segurança e privacidade</span></div><div class="kick" style="margin-top:12mm">Segurança e privacidade</div><h2>Dados de clientes tratados com seriedade</h2><p class="lead" style="max-width:190mm">O escritório lida com informação sensível. O sistema foi pensado para limitar o acesso e deixar rastro de quem fez o quê.</p><div class="grid">{sb}</div><div class="foot"><span></span><span>{n}</span></div></section>''')

cs = [('Marca e cores','Nome, logotipo e cores do escritório no sistema e na área do cliente.'),('Módulos','Ligamos só o que o escritório usa e desligamos o resto.'),('Modelos de contrato','Seu texto de honorários, suas cláusulas e seus pacotes, com os dados entrando automaticamente.'),('Assuntos e serviços','As áreas de atuação (contencioso, família, societário…) e o que entra em cada contrato.'),('Fichas de atendimento','Um questionário por área, com as perguntas que o escritório precisa.'),('Vencimentos','Tipos de item e prazos de aviso conforme a rotina do escritório.')]
cb = ''.join(f'<div class="box"><div class="ico">{i+1}</div><h3>{t}</h3><p>{d}</p></div>' for i,(t,d) in enumerate(cs))
out.append(f'''<section class="pg"><div class="top"><span>CRM sob medida · escritórios de advocacia</span><span>Personalização</span></div><div class="kick" style="margin-top:12mm">Sob medida</div><h2>Adaptado ao jeito do seu escritório</h2><p class="lead" style="max-width:190mm">Na implantação, o sistema recebe a identidade, os modelos e os fluxos do escritório. Não é um programa genérico: o que você vê nas telas é o ponto de partida.</p><div class="grid">{cb}</div><div class="foot"><span></span><span>{n+1}</span></div></section>''')

out.append(f'''<section class="pg"><div class="top"><span>CRM sob medida · escritórios de advocacia</span><span>Investimento</span></div><div class="kick" style="margin-top:12mm">Investimento</div><h2>Implantação e mensalidade</h2><p class="lead" style="max-width:200mm">Um valor único para deixar o sistema pronto e personalizado, e uma mensalidade para manter tudo funcionando, seguro e atualizado.</p>
<div class="price"><div class="pc"><small>Implantação · valor único</small><strong>{money(IMPLANTACAO)}</strong><ul><li>Instalação e configuração do sistema</li><li>Identidade do escritório (marca, cores, acesso)</li><li>Modelos de contrato, assuntos e fichas</li><li>Cadastro inicial e treinamento da equipe</li></ul></div>
<div class="pc alt"><small>Mensalidade</small><strong>{money(MENSALIDADE)}<span style="font-size:12pt"> /mês</span></strong><ul><li>Hospedagem e manutenção do sistema</li><li>Atualizações e novas melhorias</li><li>Suporte para a equipe</li><li>Cópias de segurança</li></ul></div></div>
<p class="note">Condições, prazos e forma de pagamento definidos em proposta e contrato.</p><div class="foot"><span></span><span>{n+2}</span></div></section>''')

out.append(f'''<section class="pg cover"><div class="in"><div class="tag">Próximos passos</div><h1>Vamos montar o seu?</h1><p>1. Conversa rápida para entender a rotina do escritório.<br>2. Definimos módulos, modelos de contrato e fichas.<br>3. Implantamos, treinamos a equipe e acompanhamos o início.</p></div><div class="by"><b>Eleve Websites</b> · elevewebsites.com.br</div></section>''')

(ROOT / 'apresentacao.html').write_text(f'<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>CRM sob medida para escritórios de advocacia</title><style>{CSS}</style></head><body>{"".join(out)}</body></html>', encoding='utf-8')
subprocess.run(['/opt/pw-browsers/chromium-1194/chrome-linux/chrome', '--headless=new', '--no-sandbox', '--disable-gpu', '--no-pdf-header-footer', '--virtual-time-budget=8000', f'--print-to-pdf={ROOT / "apresentacao-crm-advocacia.pdf"}', f'file://{ROOT / "apresentacao.html"}'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
print('ok')
