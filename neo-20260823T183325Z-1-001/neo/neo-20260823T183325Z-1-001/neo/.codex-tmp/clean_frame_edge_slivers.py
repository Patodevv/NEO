from pathlib import Path
from PIL import Image
from collections import deque

folder = Path('static/images/profile-frames')
for path in sorted(folder.glob('frame-*.png')):
    im = Image.open(path).convert('RGBA')
    w, h = im.size
    alpha = im.getchannel('A')
    pix = alpha.load()
    visited = set()
    remove = set()
    comps = []
    for y in range(h):
        for x in range(w):
            if (x, y) in visited or pix[x, y] <= 24:
                continue
            q = deque([(x, y)])
            visited.add((x, y))
            pts = []
            touches_edge = False
            while q:
                px, py = q.popleft()
                pts.append((px, py))
                if px <= 1 or py <= 1 or px >= w - 2 or py >= h - 2:
                    touches_edge = True
                for nx, ny in ((px+1,py),(px-1,py),(px,py+1),(px,py-1)):
                    if 0 <= nx < w and 0 <= ny < h and (nx, ny) not in visited and pix[nx, ny] > 24:
                        visited.add((nx, ny)); q.append((nx, ny))
            comps.append((len(pts), touches_edge, pts))
    removed = 0
    for area, touches, pts in comps:
        # border slivers from sheet slicing are edge-touching and tiny compared with the frame
        if touches and area < 6000:
            remove.update(pts)
            removed += area
    if remove:
        data = im.load()
        for x, y in remove:
            data[x, y] = (0, 0, 0, 0)
        im.save(path)
    print(path.name, 'components', len(comps), 'removed_pixels', removed)
