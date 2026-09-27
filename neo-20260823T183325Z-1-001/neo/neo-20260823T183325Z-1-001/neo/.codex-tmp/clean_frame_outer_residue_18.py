from pathlib import Path
from PIL import Image

folder = Path('static/images/profile-frames')
for path in sorted(folder.glob('frame-*.png')):
    im = Image.open(path).convert('RGBA')
    w, h = im.size
    data = im.load()
    removed = 0
    # remove residues from neighboring cells without touching the frame body.
    # all generated rings are designed to live in the central safe area.
    margin = 18
    for y in range(h):
        for x in range(w):
            if x < margin or x >= w - margin or y < margin or y >= h - margin:
                if data[x, y][3] != 0:
                    data[x, y] = (0, 0, 0, 0)
                    removed += 1
    im.save(path)
    print(path.name, 'outer residue removed', removed)
