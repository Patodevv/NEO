from pathlib import Path
from PIL import Image
from collections import deque
folder = Path('static/images/profile-frames')
for p in sorted(folder.glob('frame-*.png')):
    im = Image.open(p).convert('RGBA')
    w,h=im.size
    alpha=im.getchannel('A')
    cx,cy=w//2,h//2
    pix=alpha.load()
    seen={(cx,cy)}
    q=deque([(cx,cy)])
    xs=[]; ys=[]
    while q:
        x,y=q.popleft(); xs.append(x); ys.append(y)
        for nx,ny in ((x+1,y),(x-1,y),(x,y+1),(x,y-1)):
            if 0<=nx<w and 0<=ny<h and (nx,ny) not in seen and pix[nx,ny] < 32:
                seen.add((nx,ny)); q.append((nx,ny))
    bbox=(min(xs),min(ys),max(xs),max(ys))
    bw=bbox[2]-bbox[0]+1; bh=bbox[3]-bbox[1]+1
    ratio=min(bw/w,bh/h)
    print(p.name, 'bbox', bbox, 'ratio', round(ratio,3), 'photo@126', round(ratio*126*1.06,1), 'photo@132', round(ratio*132*1.06,1))
