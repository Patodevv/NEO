from pathlib import Path
from PIL import Image
folder = Path('static/images/profile-frames')
for path in sorted(folder.glob('frame-*.png')):
    im = Image.open(path).convert('RGBA')
    w, h = im.size
    data = im.load()
    # remove slicing residues on the outer safety margins; frames are centered and do not need these pixels
    margin = 8
    removed = 0
    for y in range(h):
        for x in range(w):
            if x < margin or y < margin or x >= w - margin or y >= h - margin:
                if data[x, y][3] != 0:
                    data[x, y] = (0, 0, 0, 0)
                    removed += 1
    im.save(path)
    print(path.name, 'edge_margin_removed', removed)
