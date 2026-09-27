from pathlib import Path
from shutil import copy2

from PIL import Image


frames = Path('static/images/profile-frames')
backup = frames / 'before-stray-fragment-cleanup'
backup.mkdir(exist_ok=True)

# These are detached pieces from neighboring sprite cuts.  The primary artwork is
# intentionally never touched; only the exact isolated boxes below are cleared.
fragments = {
    'frame-01-gato-neon.png': [(84, 299, 116, 302)],
    'frame-02-sapo-pop.png': [(300, 80, 302, 105)],
    'frame-03-asas-celestes.png': [(164, 295, 169, 298)],
    'frame-04-raposa-fogo.png': [(18, 79, 22, 110)],
    'frame-08-chama-rubi.png': [(76, 295, 93, 302), (232, 294, 249, 302)],
    'frame-09-concha-oceano.png': [(148, 298, 168, 302), (178, 298, 197, 302)],
    'frame-10-circuito-ciano.png': [(147, 285, 173, 300)],
    'frame-11-lua-sonho.png': [(291, 109, 293, 121), (292, 168, 293, 177)],
}

# A few sprite cuts have faint, disconnected pixels spread across an outer edge.
# These lines start after the main connected artwork for the corresponding asset.
edge_cuts = {
    'frame-01-gato-neon.png': ('bottom', 282),
    'frame-02-sapo-pop.png': ('right', 280),
    'frame-03-asas-celestes.png': ('bottom', 281),
    'frame-04-raposa-fogo.png': ('left', 41),
    'frame-08-chama-rubi.png': ('bottom', 284),
    'frame-09-concha-oceano.png': ('bottom', 278),
    'frame-10-circuito-ciano.png': ('bottom', 274),
}

for name, boxes in fragments.items():
    path = frames / name
    original = backup / name
    if not original.exists():
        copy2(path, original)

    image = Image.open(path).convert('RGBA')
    pixels = image.load()
    for left, top, right, bottom in boxes:
        # One transparent-pixel safety margin removes anti-aliased residue while
        # keeping a clear gap from the connected artwork.
        for y in range(max(0, top - 1), min(image.height, bottom + 1)):
            for x in range(max(0, left - 1), min(image.width, right + 1)):
                pixels[x, y] = (0, 0, 0, 0)

    edge, point = edge_cuts.get(name, (None, None))
    if edge == 'bottom':
        for y in range(point, image.height):
            for x in range(image.width):
                pixels[x, y] = (0, 0, 0, 0)
    elif edge == 'right':
        for y in range(image.height):
            for x in range(point, image.width):
                pixels[x, y] = (0, 0, 0, 0)
    elif edge == 'left':
        for y in range(image.height):
            for x in range(point):
                pixels[x, y] = (0, 0, 0, 0)
    image.save(path)
    print(f'cleaned {name}')
