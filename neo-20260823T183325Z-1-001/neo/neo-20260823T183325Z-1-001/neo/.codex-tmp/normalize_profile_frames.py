from pathlib import Path
from PIL import Image
from collections import deque
import json, shutil

folder = Path('static/images/profile-frames')
backup = folder / 'originals-before-hole-normalize'
backup.mkdir(exist_ok=True)
report = []

def inner_hole_bbox(alpha, threshold=32):
    w, h = alpha.size
    pix = alpha.load()
    cx, cy = w // 2, h // 2
    # find a transparent start point near center, in case exact center has anti-aliased pixels
    start = None
    for radius in range(0, min(w, h)//2):
        for x in range(cx-radius, cx+radius+1):
            for y in (cy-radius, cy+radius):
                if 0 <= x < w and 0 <= y < h and pix[x, y] < threshold:
                    start = (x, y); break
            if start: break
        if start: break
        for y in range(cy-radius, cy+radius+1):
            for x in (cx-radius, cx+radius):
                if 0 <= x < w and 0 <= y < h and pix[x, y] < threshold:
                    start = (x, y); break
            if start: break
        if start: break
    if start is None:
        return (0, 0, w - 1, h - 1)
    seen = {start}
    q = deque([start])
    xs, ys = [], []
    while q:
        x, y = q.popleft()
        xs.append(x); ys.append(y)
        for nx, ny in ((x+1,y), (x-1,y), (x,y+1), (x,y-1)):
            if 0 <= nx < w and 0 <= ny < h and (nx, ny) not in seen and pix[nx, ny] < threshold:
                seen.add((nx, ny)); q.append((nx, ny))
    return (min(xs), min(ys), max(xs), max(ys))

for path in sorted(folder.glob('frame-*.png')):
    original_backup = backup / path.name
    if not original_backup.exists():
        shutil.copy2(path, original_backup)
    im = Image.open(original_backup).convert('RGBA')
    w, h = im.size
    hole = inner_hole_bbox(im.getchannel('A'))
    hx = (hole[0] + hole[2]) / 2
    hy = (hole[1] + hole[3]) / 2
    dx = round(w / 2 - hx)
    dy = round(h / 2 - hy)
    normalized = Image.new('RGBA', (w, h), (0, 0, 0, 0))
    normalized.alpha_composite(im, (dx, dy))
    normalized.save(path)
    report.append({
        'file': path.name,
        'hole': hole,
        'hole_center': [round(hx, 2), round(hy, 2)],
        'shift': [dx, dy],
    })

Path('.codex-tmp/frame-normalization-report.json').write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf-8')
for item in report:
    print(f"{item['file']} shift={item['shift']} hole_center={item['hole_center']}")
