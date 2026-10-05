# Colocar o app da Meta ao vivo (30 clientes sem convite de testador)

Objetivo: qualquer cliente conectar o Instagram/Facebook pelo link do CRM, sem ser adicionado como testador.
Prazo realista: 2 a 4 semanas (a Meta revisa cada permissão; reprovação é comum na 1ª vez).
Fontes: [App Review for Instagram API](https://developers.facebook.com/docs/instagram-platform/app-review) · [Instagram Platform](https://developers.facebook.com/documentation/instagram-platform/overview)

Regra da Meta: para atender contas que **não** têm função no app, é preciso **Acesso Avançado**, que exige **App Review + Verificação da Empresa**.

## 0. Antes de começar (tenha pronto)
- Site da Eleve/LDK com **Política de Privacidade** pública (URL) e página de **exclusão de dados** (URL ou e-mail de contato).
- CNPJ, razão social, endereço e um documento/comprovante em nome da empresa (para a verificação).
- Uma conta Instagram **Comercial** de teste e uma Página do Facebook, para gravar os vídeos.
- O CRM instalado num site em **HTTPS**.

## 1. Verificação da Empresa (Business Verification)
1. business.facebook.com → Configurações da empresa → **Centro de segurança** → **Iniciar verificação**.
2. Enviar documentos da empresa (CNPJ/contrato social) e confirmar telefone/e-mail/domínio.
3. Aguardar aprovação (de poucos dias a ~2 semanas). Pode rodar em paralelo ao passo 2.

## 2. Configurar o app (developers.facebook.com → seu app)
1. **Configurações do app → Básico**: ícone, categoria, e-mail de contato, **URL da Política de Privacidade**, **URL de exclusão de dados**, domínio do site. Vincular o app à sua **Empresa** verificada.
2. **Instagram → API com login do Instagram → Configuração**: em **URI de redirecionamento OAuth** cadastre exatamente:
   - `https://SEU-SITE/wp-admin/admin-post.php?action=lk_social_cb&net=instagram`
3. **Facebook Login → Configurações**: em **URIs de redirecionamento OAuth válidos** cadastre:
   - `https://SEU-SITE/wp-admin/admin-post.php?action=lk_social_cb&net=facebook`
4. No CRM: **Configurações → Instagram, Facebook e Meta Ads** → preencher Instagram App ID/Secret e Meta App ID/Secret.

## 3. Permissões para pedir no App Review
O plugin pede estas (veja `lk_social_auth_url` em `includes/social.php`):

| Rede | Permissão | Para que serve no CRM |
|---|---|---|
| Instagram | `instagram_business_basic` | identificar a conta (nome, foto, seguidores) |
| Instagram | `instagram_business_content_publish` | publicar o post agendado |
| Instagram | `instagram_business_manage_insights` | relatórios de desempenho |
| Instagram | `instagram_business_manage_comments` | ler/responder comentários |
| Facebook | `pages_show_list` | listar as Páginas do cliente |
| Facebook | `pages_manage_posts` | publicar na Página |
| Facebook | `pages_read_engagement` | ler engajamento |
| Facebook | `read_insights` | métricas da Página |
| Facebook | `business_management` | só se realmente usar; **se não precisar, remova** (a Meta reprova permissão sem uso demonstrado) |

Dica: peça só o que o CRM usa de fato. Menos permissões = revisão mais rápida.

## 4. Gravar um vídeo por permissão (screencast)
A Meta exige a jornada completa **em cada permissão**: login → tela de consentimento pedindo aquela permissão → a permissão sendo usada.
- `content_publish`: abrir o link de conexão → entrar no Instagram → autorizar → no CRM criar um post com legenda e hashtags → agendar/publicar → mostrar o post no Instagram.
- `manage_insights`: mostrar a tela de relatórios do CRM com os números da conta.
- `manage_comments`: mostrar comentários sendo lidos/respondidos.
- Páginas do Facebook: conectar, escolher a Página, publicar.
- Gravar em tela cheia, legível, em inglês nos textos de interface se possível, sem cortes.
- Conta de teste real, com dados reais (não mockup).

## 5. Textos de justificativa (copie e adapte)
**instagram_business_content_publish**
> Our agency CRM lets social media managers schedule and publish organic feed posts, carousels and reels to Instagram professional accounts of our clients, who authorize the connection themselves via Instagram Login. The permission is used only to publish content that the client approved inside the platform.

**instagram_business_manage_insights**
> We show each client a performance report (reach, impressions, followers, top posts) built from the account's insights, so the client can follow results of the content we publish for them.

**instagram_business_manage_comments**
> The team reads and replies to comments on the client's posts from a single inbox, so customers receive quick answers.

**instagram_business_basic**
> Used to identify the connected account (username, name, profile picture) so we show the right account in the client's profile.

**pages_manage_posts / pages_show_list**
> Our clients authorize us to publish approved posts to their Facebook Page. We list their Pages so they choose which one to connect.

## 6. Enviar e colocar ao vivo
1. App Review → **Permissões e recursos** → solicitar **Acesso Avançado** de cada permissão, anexando o vídeo e a justificativa.
2. Preencher **Uso de dados** (Data Use Checkup) quando pedido.
3. Após aprovação: topo do painel do app, trocar **Modo de desenvolvimento → Ao vivo**.
4. Testar com **um cliente que não é testador**: abrir o link de conexão dele e conectar.

## 7. Se reprovar
Leia o motivo, regrave só o vídeo da permissão reprovada (geralmente faltou mostrar a tela de consentimento ou o uso real) e reenvie.

## 8. Depois do ao vivo (30 clientes)
Mandar o link de conexão de cada cliente (ficha do cliente → Redes sociais → Copiar link). Requisito dos clientes: Instagram **Comercial ou Criador**; Facebook: ser admin da Página.

## 9. LinkedIn e YouTube pelo mesmo link (versão 1.5.4)
A página do cliente agora tem Instagram, Facebook, LinkedIn e YouTube. As duas últimas não dependem da Meta:
- **LinkedIn** (developer.linkedin.com): criar o app, pedir o produto **Community Management API** (precisa de aprovação do LinkedIn) e cadastrar o redirecionamento
  `https://SEU-SITE/wp-admin/admin-post.php?action=lk_social_cb&net=linkedin`. Preencher Client ID/Secret em Configurações → Redes sociais. O cliente precisa ser administrador da Página da empresa.
- **YouTube** (Google Cloud): ativar a **YouTube Data API v3**, criar o OAuth (o mesmo do Drive) e cadastrar o redirecionamento
  `https://SEU-SITE/wp-admin/admin-post.php?action=lk_social_cb&net=google`.
  Atenção: vídeos enviados por app não verificado pelo Google ficam **privados** até a verificação do app (tela de consentimento OAuth em produção).
