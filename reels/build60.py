import subprocess, os, json, sys

U = "/root/.claude/uploads/472f835e-6c9f-51ee-8a39-5dec54683ea2"
S = "/tmp/claude-0/-home-user-calude/472f835e-6c9f-51ee-8a39-5dec54683ea2/scratchpad"
OUT = "/home/user/calude/reels"
LOGO = os.environ.get("LOGO", "")  # PNG do logo (opcional); sem ele usa o logo em texto
C = f"{S}/clips60"
os.makedirs(C, exist_ok=True); os.makedirs(OUT, exist_ok=True)
W, H, FPS = 1080, 1920, 30
GAP, LEAD, TAIL = 0.2, 0.3, 1.6
ACCENT = "&H0000A5FF&"  # laranja #FFA500 em BGR
FONT = "Inter Display Black"
VOICE = f"{S}/voices/pt-br-edresson-low.onnx"

def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    if r.returncode:
        print(r.stderr[-3000:]); sys.exit(cmd[:300])

def dur(f):
    return float(subprocess.check_output(
        f"ffprobe -v error -show_entries format=duration -of csv=p=0 '{f}'", shell=True))

lines = [l.strip() for l in open(f"{S}/lines60.txt", encoding="utf-8") if l.strip()]
for i, l in enumerate(lines):
    subprocess.run(f"piper -m {VOICE} --length-scale 0.9 -f {S}/tts60/l{i+1}.wav", shell=True,
                   input=l, text=True, capture_output=True, check=True)
ld = [dur(f"{S}/tts60/l{i+1}.wav") for i in range(len(lines))]
starts, t = [], LEAD
for d in ld:
    starts.append(t); t += d + GAP
TOTAL = t - GAP + TAIL
bounds = [0.0] + starts[1:] + [TOTAL]
SD = [bounds[i+1] - bounds[i] for i in range(len(lines))]

# ---------- áudio ----------
inputs = " ".join(f"-i {S}/tts60/l{i+1}.wav" for i in range(len(lines)))
delays = ";".join(f"[{i}]adelay={int(s*1000)}:all=1,apad[a{i}]" for i, s in enumerate(starts))
mix = "".join(f"[a{i}]" for i in range(len(lines)))
sh(f"ffmpeg -v error -y {inputs} -filter_complex \"{delays};{mix}amix=inputs={len(lines)}:normalize=0,"
   f"highpass=f=80,acompressor=threshold=-18dB:ratio=3,loudnorm=I=-14:TP=-1.5,atrim=0:{TOTAL}\" -ar 48000 {S}/narr60.wav")

beat = 60 / 100
kick = f"sin(2*PI*(45+90*exp(-mod(t,{beat})*30))*t)*exp(-mod(t,{beat})*7)"
hat = f"0.05*(random(0)*2-1)*exp(-mod(t+{beat/2},{beat})*40)"
pad = "0.18*sin(2*PI*55*t)*(0.6+0.4*sin(2*PI*0.25*t))+0.08*sin(2*PI*82.4*t)"
sh(f"ffmpeg -v error -y -f lavfi -i \"aevalsrc='0.55*{kick}+{pad}+{hat}':s=48000:d={TOTAL}\" "
   f"-af \"lowpass=f=2500,afade=t=in:d=0.4,afade=t=out:st={TOTAL-1.5}:d=1.5,volume=0.28\" {S}/music60.wav")

# ---------- cenas ----------
HERO = f"{U}/4b0c9e8a-IMG_6226.mov"
PRINT = f"{U}/e141d06a-757E0FCD-675D-4E46-8811-B64D94757CC3.mov"
P = lambda h: f"{U}/{h}-image.jpg"
GRADE = "eq=contrast=1.07:saturation=1.18"
seg = []

def img(src, d, z0=1.0, z1=1.12, fx=0.5, fy=0.5):
    """Foto com zoom suave centrado em (fx, fy), frações da imagem."""
    name = f"c{len(seg):02d}"; n = int(round(d * FPS))
    z = f"({z0}+({z1}-{z0})*on/{n})"
    x = f"max(0,min(iw-iw/{z},{fx}*iw-iw/{z}/2))"
    y = f"max(0,min(ih-ih/{z},{fy}*ih-ih/{z}/2))"
    sh(f"ffmpeg -v error -y -loop 1 -i '{src}' -vf \"scale=2160:3840:force_original_aspect_ratio=increase,crop=2160:3840,"
       f"zoompan=z='{z}':x='{x}':y='{y}':d={n}:s={W}x{H}:fps={FPS},{GRADE},format=yuv420p\" "
       f"-frames:v {n} -c:v libx264 -crf 16 -preset fast {C}/{name}.mp4")
    seg.append((name, d))

def vid(src, ss, d, speed=1.0, push=0.0):
    name = f"c{len(seg):02d}"; n = int(round(d * FPS))
    zp = (f",zoompan=z='1+{push}*on/{n}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s={W}x{H}:fps={FPS}"
          if push else "")
    sh(f"ffmpeg -v error -y -ss {ss} -i '{src}' -an -vf \"setpts=PTS/{speed},fps={FPS},scale={W}:{H}:force_original_aspect_ratio=increase,"
       f"crop={W}:{H}{zp},{GRADE},format=yuv420p\" -frames:v {n} -c:v libx264 -crf 16 -preset fast {C}/{name}.mp4")
    seg.append((name, d))

def split(d, k): return [d / k] * k

# 1 gancho
vid(HERO, 0, SD[0], speed=2.89 / SD[0] * 0.98, push=0.12)
# 2 apresentação
a, b = split(SD[1], 2)
img(P("7a160b21"), a, 1.25, 1.0); img(P("cfa9da57"), b, 1.0, 1.15, 0.5, 0.45)
# 3-5 impressão
vid(PRINT, 0.3, SD[2])
vid(PRINT, 5.0, SD[3], speed=2.0)
vid(PRINT, 16.5, SD[4])
# 6 virada para o acabamento
img(P("98ab5f0a"), SD[5], 1.15, 1.0, 0.4, 0.5)
# 7 lixa, primer, pintura, montagem: closes
for (src, fx, fy), d in zip([("c0ea1ecf", 0.42, 0.72), ("98ab5f0a", 0.7, 0.65), ("cfa9da57", 0.75, 0.55)], split(SD[6], 3)):
    img(P(src), d, 1.7, 1.9, fx, fy)
# 8 detalhes: entradas de ar, parafusos, queixo
for (src, fx, fy), d in zip([("7a160b21", 0.52, 0.31), ("f5c419eb", 0.9, 0.62), ("7a160b21", 0.72, 0.74)], split(SD[7], 3)):
    img(P(src), d, 1.9, 2.2, fx, fy)
# 9 viseira
a, b = split(SD[8], 2)
img(P("f5c419eb"), a, 1.5, 1.85, 0.55, 0.52); img(P("c0ea1ecf"), b, 1.6, 1.3, 0.53, 0.5)
# 10-11 vestível
img(P("888f05bf"), SD[9], 1.0, 1.15, 0.5, 0.4)
for (src, z0, z1), d in zip([("860e2147", 1.15, 1.0), ("cf47a251", 1.0, 1.12), ("888f05bf", 1.35, 1.2)], split(SD[10], 3)):
    img(P(src), d, z0, z1, 0.5, 0.35)
# 12 depoimento (print sem o número de telefone)
d = SD[11]; n = int(round(d * FPS)); name = f"c{len(seg):02d}"
sh(f"ffmpeg -v error -y -loop 1 -i '{P('c0ea1ecf')}' -loop 1 -i '{U}/e5978df5-image.png' -filter_complex "
   f"\"[0]scale={W}:{H}:force_original_aspect_ratio=increase,crop={W}:{H},gblur=sigma=28,eq=brightness=-0.22:saturation=1.2[bg];"
   f"[1]crop=820:330:40:1632,scale=980:-1,format=rgba[q];"
   f"[bg][q]overlay=x=(W-w)/2:y='H*0.36-h/2+40*exp(-t*6)':shortest=1,format=yuv420p\" "
   f"-frames:v {n} -r {FPS} -c:v libx264 -crf 16 -preset fast {C}/{name}.mp4")
seg.append((name, d))
# 13 pergunta para comentários: montagem rápida
for (src, z0, z1), d in zip([("98ab5f0a", 1.1, 1.0), ("f5c419eb", 1.0, 1.1), ("860e2147", 1.1, 1.0), ("cfa9da57", 1.0, 1.1)], split(SD[12], 4)):
    img(P(src), d, z0, z1)
# 14 cartão final
d = SD[13]; n = int(round(d * FPS)); name = f"c{len(seg):02d}"
sh(f"ffmpeg -v error -y -loop 1 -i '{P('7a160b21')}' -vf \"scale=2160:3840:force_original_aspect_ratio=increase,crop=2160:3840,"
   f"zoompan=z='1.0+0.08*on/{n}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d={n}:s={W}x{H}:fps={FPS},"
   f"eq=brightness=-0.16:saturation=1.15,vignette=PI/4,format=yuv420p\" -frames:v {n} -c:v libx264 -crf 16 -preset fast {C}/{name}.mp4")
seg.append((name, d))

with open(f"{C}/list.txt", "w") as f:
    for name, _ in seg:
        f.write(f"file '{C}/{name}.mp4'\n")
sh(f"ffmpeg -v error -y -f concat -safe 0 -i {C}/list.txt -c copy {S}/base60.mp4")
cuts, acc = [], 0
for _, d in seg:
    acc += d; cuts.append(acc)

# ---------- legendas e grafismos (ASS) ----------
def ts(x):
    return f"{int(x // 3600)}:{int(x % 3600 // 60):02d}:{x % 60:05.2f}"

ass = [f"""[Script Info]
ScriptType: v4.00+
PlayResX: {W}
PlayResY: {H}
WrapStyle: 2

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Cap,{FONT},96,&H00FFFFFF,&H00FFFFFF,&H00000000,&H96000000,0,0,0,0,100,100,0,0,1,7,4,2,60,60,560,1
Style: Brand,{FONT},44,&H00FFFFFF,&H00FFFFFF,&H00000000,&H80000000,0,0,0,0,100,100,4,0,1,3,0,8,40,40,110,1
Style: Hook,{FONT},80,&H00000000,&H00FFFFFF,{ACCENT},&H00000000,0,0,0,0,100,100,0,0,3,24,0,8,70,70,260,1
Style: End,{FONT},120,&H00FFFFFF,&H00FFFFFF,&H00000000,&H00000000,0,0,0,0,100,100,2,0,1,8,0,5,40,40,0,1
Style: Url,Inter ExtraBold,56,&H00000000,&H00FFFFFF,{ACCENT},&H00000000,0,0,0,0,100,100,1,0,3,18,0,5,40,40,0,1
Style: Bar,{FONT},10,{ACCENT},{ACCENT},&H00000000,&H00000000,0,0,0,0,100,100,0,0,1,0,0,7,0,0,0,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text"""]

KEY = {"3d!", "3d.", "3d?", "master", "chief,", "halo,", "real.", "camada", "horas", "tamanho", "profissional:",
       "pintura", "montagem,", "detalhes:", "queixo.", "viseira?", "espelhada,", "laranja,", "jogo.", "vestível!",
       "cosplay,", "capricho!", "comenta", "personagem", "cardon", "studio"}

def emph(w): return w.lower().strip("\"'") in KEY

for i, line in enumerate(lines[:-1]):  # a última fala vira o cartão final
    words = line.split()
    weights = [len(w) + 2 for w in words]; tot = sum(weights)
    chunks, cur = [], []
    for w in words:
        cur.append(w)
        if len(cur) == 3 or w[-1] in ".,!?:":
            chunks.append(cur); cur = []
    if cur: chunks.append(cur)
    k = 0
    for c in chunks:
        a = starts[i] + ld[i] * sum(weights[:k]) / tot
        b = a + ld[i] * sum(weights[k:k+len(c)]) / tot; k += len(c)
        txt = " ".join(f"{{\\c{ACCENT}}}{w.upper()}{{\\c&H00FFFFFF&}}" if emph(w) else w.upper() for w in c)
        ass.append(f"Dialogue: 1,{ts(a)},{ts(b + 0.04)},Cap,,0,0,0,,{{\\fad(0,40)\\fscx70\\fscy70\\t(0,90,\\fscx108\\fscy108)\\t(90,160,\\fscx100\\fscy100)}}{txt}")

end0 = bounds[13]
# barra de progresso (segura a retenção)
ass.append(f"Dialogue: 3,{ts(0)},{ts(TOTAL)},Bar,,0,0,0,,{{\\pos(0,0)\\p1\\fscx0\\t(0,{int(TOTAL*1000)},\\fscx100)}}m 0 0 l {W} 0 {W} 12 0 12{{\\p0}}")
if not LOGO:
    ass.append(f"Dialogue: 0,{ts(0)},{ts(end0)},Brand,,0,0,0,,{{\\alpha&H30&}}CARDON STUDIO {{\\c{ACCENT}}}3D")
# selos de capítulo
for i, txt in [(0, "CAPACETE DO HALO\\NIMPRESSO EM 3D"), (2, "PASSO 1: IMPRESSÃO"), (5, "PASSO 2: ACABAMENTO"),
               (8, "PASSO 3: VISEIRA"), (9, "TESTE FINAL: VESTIR"), (11, "FEEDBACK DO CLIENTE")]:
    y = 330 if i == 11 else 260
    ass.append(f"Dialogue: 0,{ts(bounds[i] + 0.1)},{ts(bounds[i+1] if i != 2 else bounds[5])},Hook,,0,0,0,,"
               f"{{\\pos(540,{y})\\fad(120,120)\\fscx80\\fscy80\\t(0,150,\\fscx100\\fscy100)}}{txt}")
ass.append(f"Dialogue: 0,{ts(bounds[12])},{ts(bounds[13])},Hook,,0,0,0,,{{\\pos(540,260)\\fad(120,120)}}COMENTA AQUI EMBAIXO")
# cartão final
ass.append(f"Dialogue: 2,{ts(end0)},{ts(TOTAL)},End,,0,0,0,,{{\\pos(540,1150)\\fad(150,0)\\fscx60\\fscy60\\t(0,220,\\fscx100\\fscy100)}}QUER O SEU?")
if not LOGO:
    ass.append(f"Dialogue: 2,{ts(end0+0.8)},{ts(TOTAL)},End,,0,0,0,,{{\\pos(540,760)\\fad(150,0)\\fs104}}CARDON STUDIO {{\\c{ACCENT}}}3D")
ass.append(f"Dialogue: 2,{ts(end0+1.4)},{ts(TOTAL)},Url,,0,0,0,,{{\\pos(540,1320)\\fad(150,0)}}cardonstudio3d.com.br")
ass.append(f"Dialogue: 2,{ts(end0+2.0)},{ts(TOTAL)},Cap,,0,0,0,,{{\\pos(540,1560)\\fs64\\fad(150,0)}}ENCOMENDE PELO LINK NA BIO")
open(f"{S}/caps60.ass", "w", encoding="utf-8").write("\n".join(ass) + "\n")

# ---------- render final ----------
wh = [c for c in cuts[:-1]]
nin = 3 + (1 if LOGO else 0)
logo_in = f"-loop 1 -i '{LOGO}'" if LOGO else ""
wh_in = " ".join("-f lavfi -t 0.35 -i \"anoisesrc=c=pink:a=0.5:r=48000\"" for _ in wh)
wh_f = ";".join(f"[{nin+i}]bandpass=f=1800:w=1500,afade=t=in:d=0.2,afade=t=out:st=0.2:d=0.15,volume=0.2,"
                f"adelay={int(max(c-0.2,0)*1000)}:all=1[w{i}]" for i, c in enumerate(wh))
wmix = "".join(f"[w{i}]" for i in range(len(wh)))
if LOGO:
    # logo pequeno no topo durante o vídeo e grande no cartão final
    vf = (f"[3]format=rgba,split[l1][l2];[l1]scale=240:-1,colorchannelmixer=aa=0.9[ls];[l2]scale=620:-1[lb];"
          f"[0][ls]overlay=x=(W-w)/2:y=60:enable='lt(t,{end0})'[v1];"
          f"[v1][lb]overlay=x=(W-w)/2:y=760-h/2:enable='gte(t,{end0+0.8})'[v2];"
          f"[v2]ass={S}/caps60.ass,fade=t=in:d=0.2,format=yuv420p[v]")
else:
    vf = f"[0]ass={S}/caps60.ass,fade=t=in:d=0.2,format=yuv420p[v]"
final = f"{OUT}/halo_reels_1min_cardonstudio3d.mp4"
sh(f"ffmpeg -v error -y -i {S}/base60.mp4 -i {S}/narr60.wav -i {S}/music60.wav {logo_in} {wh_in} -filter_complex "
   f"\"{vf};{wh_f};[1][2]{wmix}amix=inputs={2+len(wh)}:normalize=0:duration=first,alimiter=limit=0.95[a]\" "
   f"-map [v] -map [a] -c:v libx264 -crf 19 -preset medium -profile:v high -pix_fmt yuv420p -r {FPS} "
   f"-c:a aac -b:a 192k -movflags +faststart -t {TOTAL} {final}")
print(json.dumps({"total": round(TOTAL, 2), "bounds": [round(b, 2) for b in bounds]}))
print(final)
