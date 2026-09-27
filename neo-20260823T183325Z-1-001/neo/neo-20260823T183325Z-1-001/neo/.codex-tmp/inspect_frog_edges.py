from PIL import Image
from pathlib import Path
p=Path('static/images/profile-frames/frame-02-sapo-pop.png')
im=Image.open(p).convert('RGBA')
a=im.getchannel('A')
pix=a.load()
w,h=im.size
cols=[]
for x in range(w):
    c=sum(1 for y in range(h) if pix[x,y]>0)
    if c:
        cols.append((x,c))
print('bbox x', cols[0], cols[-1])
print('right cols', cols[-40:])
# print colored visible pixels near right side
for x in range(260,w):
    c=sum(1 for y in range(h) if pix[x,y]>0)
    if c:
        print(x,c)
