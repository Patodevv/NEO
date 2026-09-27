from PIL import Image, ImageDraw
from pathlib import Path
frames = [
    ('Moldura Chama Rubi','frame-08-chama-rubi.png',72),
    ('Moldura Circuito Ciano','frame-10-circuito-ciano.png',72),
    ('Moldura Coroa Real','frame-05-coroa-real.png',69),
    ('Moldura Cristal Azul','frame-07-cristal-azul.png',72),
]
root=Path('static/images/profile-frames')
out=Image.new('RGB',(900,520),(8,15,34))
d=ImageDraw.Draw(out)
# approximate user duck/avatar from current screenshot is not available as asset; use colored circle to verify mask/fill/center
avatar=Image.new('RGBA',(320,320),(0,0,0,0))
ad=ImageDraw.Draw(avatar)
ad.ellipse((0,0,319,319), fill=(40,120,255,255))
ad.pieslice((40,20,280,310), 70, 290, fill=(255,235,40,255))
ad.ellipse((110,95,135,120), fill=(0,0,0,255))
ad.polygon([(155,115),(255,95),(255,145)], fill=(255,36,178,255))
for i,(name,file,photo_pct) in enumerate(frames):
    x=30+(i%2)*440; y=30+(i//2)*240
    d.rounded_rectangle((x,y,x+400,y+170), radius=16, fill=(18,18,18), outline=(38,67,140))
    stage=142
    sx=x+200-stage//2; sy=y+85-stage//2
    photo_size=round(stage*photo_pct/100)
    px=x+200-photo_size//2; py=y+85-photo_size//2
    # circular mask
    photo=avatar.resize((photo_size,photo_size), Image.LANCZOS)
    mask=Image.new('L',(photo_size,photo_size),0); md=ImageDraw.Draw(mask); md.ellipse((0,0,photo_size-1,photo_size-1), fill=255)
    out.paste(photo,(px,py),mask)
    fr=Image.open(root/file).convert('RGBA').resize((stage,stage), Image.LANCZOS)
    out.paste(fr,(sx,sy),fr)
    d.text((x,y+178),name,fill=(255,255,255))
out_path=Path('.codex-tmp/frame-preview.png')
out.save(out_path)
print(out_path.resolve())
