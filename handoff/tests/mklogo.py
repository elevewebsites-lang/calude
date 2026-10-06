import numpy as np
from PIL import Image
IMG='/tmp/claude-0/-home-user-calude/2a14d884-5cd2-549f-8f23-cd89bc4d13b8/images/'
OUT='/home/user/calude/allprint-crm/assets/brand/'
B=np.array([247,247,247],float)

def sample(a,x0,y0,x1,y1):
    return np.median(a[y0:y1,x0:x1].reshape(-1,3),axis=0)
v=np.array(Image.open(IMG+'3.jpg').convert('RGB'),float)
# regiões sólidas do vertical: A (cinza escuro), triângulo azul, triângulo amarelo
DARK=sample(v,300,630,470,660); NAVY=sample(v,560,250,640,340); YEL=sample(v,780,100,900,200)
print('paleta', DARK, NAVY, YEL)
PAL=[DARK,NAVY,YEL]

def unmix(path):
    a=np.array(Image.open(path).convert('RGB'),float)
    h,w,_=a.shape
    best_res=np.full((h,w),1e9); alpha=np.zeros((h,w)); idx=np.zeros((h,w),int)
    for i,F in enumerate(PAL):
        d=F-B
        t=((a-B)@d)/(d@d); t=np.clip(t,0,1)
        rec=B+t[...,None]*d
        res=((a-rec)**2).sum(-1)
        m=res<best_res
        best_res[m]=res[m]; alpha[m]=t[m]; idx[m]=i
    alpha=np.where(alpha>0.97,1,np.where(alpha<0.04,0,alpha))
    return alpha,idx

def render(alpha,idx,colors):
    h,w=alpha.shape
    out=np.zeros((h,w,4),np.uint8)
    rgb=np.zeros((h,w,3))
    for i in range(3): rgb[idx==i]=colors[i]
    out[...,:3]=rgb.astype(np.uint8); out[...,3]=(alpha*255).round().astype(np.uint8)
    return Image.fromarray(out,'RGBA')

def trim(im,pad=0.02):
    bb=im.getchannel('A').point(lambda p:255 if p>8 else 0).getbbox()
    im=im.crop(bb); p=int(max(im.size)*pad)
    c=Image.new('RGBA',(im.width+2*p,im.height+2*p),(0,0,0,0)); c.paste(im,(p,p)); return c

INK=(21,19,43); WHITE=(255,255,255); YELLOW=tuple(int(x) for x in YEL); NAVYc=tuple(int(x) for x in NAVY); DARKc=tuple(int(x) for x in DARK)
for name,src,tri in [('h','2.jpg',lambda y,x:(x<385)&(y<200)),('v','3.jpg',lambda y,x:(y<420))]:
    alpha,idx=unmix(IMG+src); h,w=alpha.shape
    yy,xx=np.mgrid[0:h,0:w]
    navy_mask=(idx==1)
    tri_mask=navy_mask&tri(yy,xx)
    # cor
    trim(render(alpha,idx,[DARKc,NAVYc,YELLOW])).save(OUT+f'alprint-{name}-cor.png',optimize=True)
    # branca (fundo escuro): letras e A em branco; triângulo azul e amarelo mantidos
    idx2=idx.copy(); rgb=np.zeros((h,w,3))
    rgb[idx==0]=WHITE; rgb[idx==1]=WHITE; rgb[tri_mask]=NAVYc; rgb[idx==2]=YELLOW
    o=np.zeros((h,w,4),np.uint8); o[...,:3]=rgb.astype(np.uint8); o[...,3]=(alpha*255).round().astype(np.uint8)
    trim(Image.fromarray(o,'RGBA')).save(OUT+f'alprint-{name}-branca.png',optimize=True)
    # mono azul-marinho (para fundos amarelos/claros com pouco contraste com o amarelo)
    rgb=np.zeros((h,w,3)); rgb[:]=INK
    o=np.zeros((h,w,4),np.uint8); o[...,:3]=rgb.astype(np.uint8); o[...,3]=(alpha*255).round().astype(np.uint8)
    trim(Image.fromarray(o,'RGBA')).save(OUT+f'alprint-{name}-mono.png',optimize=True)
    if name=='v':
        # ícone: só o monograma A (acima do texto)
        mark=alpha.copy(); mark[430+ (0):,:]=mark[430:,:]  # placeholder
for name,fn in [('cor',None),('branca',None),('mono',None)]:
    im=Image.open(OUT+f'alprint-v-{name}.png'); w,h=im.size
    # monograma = parte de cima (até antes do "alPrint"): ~ 53% da altura útil
    top=im.crop((0,0,w,int(h*0.54)))
    ic=trim(top,0.06); s=max(ic.size); sq=Image.new('RGBA',(s,s),(0,0,0,0)); sq.paste(ic,((s-ic.width)//2,(s-ic.height)//2))
    sq=sq.resize((512,512),Image.LANCZOS); sq.save(OUT+f'alprint-icone-{name}.png',optimize=True)
import os
for f in sorted(os.listdir(OUT)): print(f, Image.open(OUT+f).size, os.path.getsize(OUT+f))
