import subprocess, os, re, json

U = "/root/.claude/uploads/472f835e-6c9f-51ee-8a39-5dec54683ea2"
S = "/tmp/claude-0/-home-user-calude/472f835e-6c9f-51ee-8a39-5dec54683ea2/scratchpad"
OUT = "/home/user/calude/reels"
os.makedirs(f"{S}/clips", exist_ok=True)
os.makedirs(OUT, exist_ok=True)
W, H, FPS = 1080, 1920, 30
GAP = 0.25
ACCENT = "&H0000A5FF&"  # ASS BGR: laranja #FFA500 (cor da viseira)
FONT = "Inter Display Black"

def sh(cmd):
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    if r.returncode:
        print(r.stderr[-3000:]); raise SystemExit(cmd[:200])

def dur(f):
    return float(subprocess.check_output(
        f"ffprobe -v error -show_entries format=duration -of csv=p=0 '{f}'", shell=True))

lines = [l.strip() for l in open(f"{S}/lines.txt", encoding="utf-8") if l.strip()]
ld = [dur(f"{S}/tts/l{i+1}.wav") for i in range(len(lines))]
starts, t = [], 0.35
for d in ld:
    starts.append(t); t += d + GAP
TAIL = 1.4
TOTAL = t - GAP + TAIL

# ---------- narração ----------
inputs = " ".join(f"-i {S}/tts/l{i+1}.wav" for i in range(len(lines)))
delays = ";".join(f"[{i}]adelay={int(s*1000)}:all=1,apad[a{i}]" for i, s in enumerate(starts))
mix = "".join(f"[a{i}]" for i in range(len(lines)))
sh(f"ffmpeg -v error -y {inputs} -filter_complex \"{delays};{mix}amix=inputs={len(lines)}:normalize=0,"
   f"highpass=f=80,acompressor=threshold=-18dB:ratio=3,loudnorm=I=-14:TP=-1.5,atrim=0:{TOTAL}\" -ar 48000 {S}/narr.wav")

# ---------- trilha sintetizada (pulso + sub) e whooshes ----------
bpm = 100; beat = 60 / bpm
kick = f"sin(2*PI*(45+90*exp(-mod(t,{beat})*30))*t)*exp(-mod(t,{beat})*7)"
pad = "0.18*sin(2*PI*55*t)*(0.6+0.4*sin(2*PI*0.25*t))+0.08*sin(2*PI*82.4*t)"
sh(f"ffmpeg -v error -y -f lavfi -i \"aevalsrc='0.55*{kick}+{pad}':s=48000:d={TOTAL}\" "
   f"-af \"lowpass=f=900,afade=t=in:d=0.4,afade=t=out:st={TOTAL-1.2}:d=1.2,volume=0.30\" {S}/music.wav")

# ---------- cenas ----------
def img_clip(name, src, d, zoom=(1.0, 1.12), pan="center"):
    n = int(round(d * FPS))
    z0, z1 = zoom
    zexpr = f"{z0}+({z1}-{z0})*on/{n}"
    x = {"center": "iw/2-(iw/zoom/2)", "left": "(iw-iw/zoom)*0.2", "right": "(iw-iw/zoom)*0.8"}[pan]
    sh(f"ffmpeg -v error -y -loop 1 -i '{src}' -vf \"scale=2160:3840:force_original_aspect_ratio=increase,crop=2160:3840,"
       f"zoompan=z='{zexpr}':x='{x}':y='ih/2-(ih/zoom/2)':d={n}:s={W}x{H}:fps={FPS},eq=contrast=1.06:saturation=1.15,format=yuv420p\" "
       f"-frames:v {n} -c:v libx264 -crf 16 -preset fast {S}/clips/{name}.mp4")

def vid_clip(name, src, ss, d, speed=1.0, extra=""):
    n = int(round(d * FPS))
    sh(f"ffmpeg -v error -y -ss {ss} -i '{src}' -an -vf \"setpts=PTS/{speed},fps={FPS},scale={W}:{H}:force_original_aspect_ratio=increase,"
       f"crop={W}:{H}{extra},eq=contrast=1.06:saturation=1.15,format=yuv420p\" -frames:v {n} -c:v libx264 -crf 16 -preset fast {S}/clips/{name}.mp4")

HERO = f"{U}/4b0c9e8a-IMG_6226.mov"
PRINT = f"{U}/e141d06a-757E0FCD-675D-4E46-8811-B64D94757CC3.mov"
P = lambda h: f"{U}/{h}-image.jpg"

seg = []  # (name, dur)
def add(name, d): seg.append((name, d))

# limites de cada fala (cortes caem no começo de cada frase)
bounds = [0.0] + [starts[i] for i in range(1, len(lines))] + [TOTAL]
SD = [bounds[i+1] - bounds[i] for i in range(len(lines))]

# 1 gancho: vídeo 4K do capacete, em câmera lenta com push-in
d = SD[0]
vid_clip("s1", HERO, 0, d, speed=2.89 / d * 0.98,
         extra=",zoompan=z='1+0.10*on/%d':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=%dx%d:fps=%d" % (int(d*FPS), W, H, FPS))
add("s1", d)
# 2 impressão
d = SD[1]
vid_clip("s2a", PRINT, 0.5, d / 2, speed=1.0); add("s2a", d / 2)
vid_clip("s2b", PRINT, 9.0, d / 2, speed=1.0); add("s2b", d / 2)
# 3 acabamento / detalhes
d = SD[2] / 3
img_clip("s3a", P("98ab5f0a"), d, (1.15, 1.0), "left"); add("s3a", d)
img_clip("s3b", P("cfa9da57"), d, (1.0, 1.15), "right"); add("s3b", d)
img_clip("s3c", P("c0ea1ecf"), d, (1.2, 1.05)); add("s3c", d)
# 4 viseira
d = SD[3] / 2
img_clip("s4a", P("f5c419eb"), d, (1.25, 1.45)); add("s4a", d)
img_clip("s4b", P("7a160b21"), d, (1.0, 1.15)); add("s4b", d)
# 5 vestível
d = SD[4] / 3
img_clip("s5a", P("888f05bf"), d, (1.0, 1.12)); add("s5a", d)
img_clip("s5b", P("860e2147"), d, (1.12, 1.0)); add("s5b", d)
img_clip("s5c", P("cf47a251"), d, (1.0, 1.1)); add("s5c", d)
# 6 depoimento: fundo desfocado + print do WhatsApp (sem o número)
d = SD[5]; n = int(round(d * FPS))
sh(f"ffmpeg -v error -y -loop 1 -i '{P('c0ea1ecf')}' -loop 1 -i '{U}/e5978df5-image.png' -filter_complex "
   f"\"[0]scale={W}:{H}:force_original_aspect_ratio=increase,crop={W}:{H},gblur=sigma=28,eq=brightness=-0.22:saturation=1.2[bg];"
   f"[1]crop=820:330:40:1632,scale=980:-1,format=rgba[q];"
   f"[bg][q]overlay=x=(W-w)/2:y='H*0.36-h/2+40*exp(-t*6)':shortest=1,format=yuv420p\" "
   f"-frames:v {n} -r {FPS} -c:v libx264 -crf 16 -preset fast {S}/clips/s6.mp4")
add("s6", d)
# 7 cartão final da marca
d = SD[6]; n = int(round(d * FPS))
sh(f"ffmpeg -v error -y -loop 1 -i '{P('7a160b21')}' -vf \"scale=2160:3840:force_original_aspect_ratio=increase,crop=2160:3840,"
   f"zoompan=z='1.0+0.08*on/{n}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d={n}:s={W}x{H}:fps={FPS},"
   f"eq=brightness=-0.12:saturation=1.15,vignette=PI/4,format=yuv420p\" -frames:v {n} -c:v libx264 -crf 16 -preset fast {S}/clips/s7.mp4")
add("s7", d)

with open(f"{S}/clips/list.txt", "w") as f:
    for name, _ in seg:
        f.write(f"file '{S}/clips/{name}.mp4'\n")
sh(f"ffmpeg -v error -y -f concat -safe 0 -i {S}/clips/list.txt -c copy {S}/base.mp4")

# ---------- legendas (ASS) ----------
def ts(x):
    h = int(x // 3600); m = int(x % 3600 // 60); s = x % 60
    return f"{h}:{m:02d}:{s:05.2f}"

cuts = []
acc = 0
for _, d in seg:
    acc += d; cuts.append(acc)

ass = [f"""[Script Info]
ScriptType: v4.00+
PlayResX: {W}
PlayResY: {H}
WrapStyle: 2

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Cap,{FONT},96,&H00FFFFFF,&H00FFFFFF,&H00000000,&H96000000,0,0,0,0,100,100,0,0,1,7,4,2,60,60,560,1
Style: Brand,{FONT},44,&H00FFFFFF,&H00FFFFFF,&H00000000,&H80000000,0,0,0,0,100,100,4,0,1,3,0,8,40,40,110,1
Style: Hook,{FONT},84,&H00FFFFFF,&H00FFFFFF,&H00000000,&H00000000,0,0,0,0,100,100,0,0,3,24,0,8,70,70,260,1
Style: End,{FONT},120,&H00FFFFFF,&H00FFFFFF,&H00000000,&H00000000,0,0,0,0,100,100,2,0,1,8,0,5,40,40,0,1
Style: Url,Inter ExtraBold,56,&H00FFFFFF,&H00FFFFFF,&H00000000,&H00000000,0,0,0,0,100,100,1,0,3,18,0,5,40,40,0,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text"""]

KEY = {"master", "chief", "verdade?", "3d.", "camada", "acabamento,", "pintura", "montagem,", "espelhada,",
       "jogo.", "vestível!", "capricho!", "cardon", "studio", "3d."}

for i, line in enumerate(lines):
    if i == len(lines) - 1:
        continue  # a última fala vira o cartão final
    words = line.replace("...", "…").split()
    weights = [len(w) + 2 for w in words]
    tot = sum(weights); t0 = starts[i]
    chunks, cur = [], []
    for w in words:
        cur.append(w)
        if len(cur) == 3 or w[-1] in ".,!?…:":
            chunks.append(cur); cur = []
    if cur: chunks.append(cur)
    k = 0
    for c in chunks:
        cw = sum(weights[k:k+len(c)]); a = t0 + ld[i] * sum(weights[:k]) / tot
        b = a + ld[i] * cw / tot; k += len(c)
        txt = " ".join(
            (f"{{\\c{ACCENT}}}{w.upper()}{{\\c&H00FFFFFF&}}" if w.lower() in KEY else w.upper()) for w in c)
        ass.append(f"Dialogue: 1,{ts(a)},{ts(b + 0.04)},Cap,,0,0,0,,{{\\fad(0,40)\\fscx70\\fscy70\\t(0,90,\\fscx108\\fscy108)\\t(90,160,\\fscx100\\fscy100)}}{txt}")

# marca fixa no topo
ass.append(f"Dialogue: 0,{ts(0)},{ts(starts[-1])},Brand,,0,0,0,,{{\\alpha&H30&}}CARDON STUDIO {{\\c{ACCENT}}}3D")
# gancho no topo dos primeiros segundos
ass.append(f"Dialogue: 0,{ts(0.15)},{ts(SD[0])},Hook,,0,0,0,,{{\\fad(120,120)\\3c{ACCENT}\\c&H00000000&}}CAPACETE DO HALO\\NIMPRESSO EM 3D")
# selo do depoimento
ass.append(f"Dialogue: 0,{ts(bounds[5])},{ts(bounds[6])},Hook,,0,0,0,,{{\\fad(120,120)\\3c{ACCENT}\\c&H00000000&\\pos(540,330)}}FEEDBACK DO CLIENTE")
# cartão final
e0 = bounds[6]
ass.append(f"Dialogue: 2,{ts(e0)},{ts(TOTAL)},End,,0,0,0,,{{\\pos(540,760)\\fad(150,0)\\fscx60\\fscy60\\t(0,220,\\fscx100\\fscy100)}}QUER O SEU?")
ass.append(f"Dialogue: 2,{ts(e0+0.9)},{ts(TOTAL)},End,,0,0,0,,{{\\pos(540,1180)\\fad(150,0)\\fs104}}CARDON STUDIO {{\\c{ACCENT}}}3D")
ass.append(f"Dialogue: 2,{ts(e0+1.6)},{ts(TOTAL)},Url,,0,0,0,,{{\\pos(540,1330)\\fad(150,0)\\3c{ACCENT}\\c&H00000000&}}cardonstudio3d.com.br")
ass.append(f"Dialogue: 2,{ts(e0+2.3)},{ts(TOTAL)},Cap,,0,0,0,,{{\\pos(540,1560)\\fs64\\fad(150,0)}}ENCOMENDE PELO LINK NA BIO")

open(f"{S}/caps.ass", "w", encoding="utf-8").write("\n".join(ass) + "\n")

# whooshes nos cortes
wh = []
for i, c in enumerate([0] + cuts[:-1]):
    if c == 0: continue
    wh.append(c)
wh_inputs = " ".join(f"-f lavfi -t 0.35 -i \"anoisesrc=c=pink:a=0.5:r=48000\"" for _ in wh)
wh_f = ";".join(f"[{i+3}]bandpass=f=1800:w=1500,afade=t=in:d=0.2,afade=t=out:st=0.2:d=0.15,volume=0.22,adelay={int(max(c-0.2,0)*1000)}:all=1[w{i}]" for i, c in enumerate(wh))
wmix = "".join(f"[w{i}]" for i in range(len(wh)))

final = f"{OUT}/halo_reels_cardonstudio3d.mp4"
sh(f"ffmpeg -v error -y -i {S}/base.mp4 -i {S}/narr.wav -i {S}/music.wav {wh_inputs} -filter_complex "
   f"\"[0]ass={S}/caps.ass,fade=t=in:d=0.25,format=yuv420p[v];"
   f"{wh_f};[2]volume=1.0[m];[1][m]{wmix}amix=inputs={2+len(wh)}:normalize=0:duration=first,alimiter=limit=0.95[a]\" "
   f"-map [v] -map [a] -c:v libx264 -crf 18 -preset medium -profile:v high -pix_fmt yuv420p -r {FPS} "
   f"-c:a aac -b:a 192k -movflags +faststart -t {TOTAL} {final}")
print(json.dumps({"total": TOTAL, "starts": starts, "bounds": bounds}, indent=1))
print(final)
