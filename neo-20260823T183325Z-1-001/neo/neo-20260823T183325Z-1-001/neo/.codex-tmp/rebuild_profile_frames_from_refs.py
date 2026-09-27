from pathlib import Path
from shutil import copy2

from PIL import Image, ImageFilter


ROOT = Path(__file__).resolve().parents[1]
FRAME_DIR = ROOT / "static" / "images" / "profile-frames"
BACKUP_DIR = FRAME_DIR / "before-reference-regeneration-20260926"

SOURCES = {
    "frame-05-coroa-real.png": Path(r"C:\Users\yomax\OneDrive\Imagens\Capturas de tela\Captura de tela 2026-09-26 163120.png"),
    "frame-06-mago-estelar.png": Path(r"C:\Users\yomax\OneDrive\Imagens\Capturas de tela\Captura de tela 2026-09-26 163045.png"),
    "frame-07-cristal-azul.png": Path(r"C:\Users\yomax\OneDrive\Imagens\Capturas de tela\Captura de tela 2026-09-26 163117.png"),
    "frame-08-chama-rubi.png": Path(r"C:\Users\yomax\OneDrive\Imagens\Capturas de tela\Captura de tela 2026-09-26 163128.png"),
    "frame-09-concha-oceano.png": Path(r"C:\Users\yomax\OneDrive\Imagens\Capturas de tela\Captura de tela 2026-09-26 163124.png"),
    "frame-14-trevo-verde.png": Path(r"C:\Users\yomax\AppData\Local\Temp\codex-clipboard-268fe6db-1af5-4cd9-88b1-c73c86919b1e.png"),
    "frame-15-gema-real.png": Path(r"C:\Users\yomax\OneDrive\Imagens\Capturas de tela\Captura de tela 2026-09-26 163050.png"),
}


def alpha_from_black_background(image: Image.Image) -> Image.Image:
    rgba = image.convert("RGBA")
    pixels = rgba.load()
    alpha = Image.new("L", rgba.size, 0)
    alpha_pixels = alpha.load()

    for y in range(rgba.height):
        for x in range(rgba.width):
            r, g, b, a = pixels[x, y]
            brightness = max(r, g, b)
            if a == 0:
                value = 0
            elif brightness <= 14:
                value = 0
            elif brightness >= 42:
                value = 255
            else:
                value = int((brightness - 14) / 28 * 255)
            alpha_pixels[x, y] = value

    alpha = alpha.filter(ImageFilter.GaussianBlur(0.35))
    rgba.putalpha(alpha)
    return rgba


def rebuild(source: Path, target: Path) -> None:
    source_image = Image.open(source)
    extracted = alpha_from_black_background(source_image)
    bbox = extracted.getbbox()
    if bbox is None:
        raise RuntimeError(f"No artwork found in {source}")

    artwork = extracted.crop(bbox)
    canvas_size = 320
    max_artwork_size = 286
    scale = min(max_artwork_size / artwork.width, max_artwork_size / artwork.height)
    new_size = (round(artwork.width * scale), round(artwork.height * scale))
    artwork = artwork.resize(new_size, Image.Resampling.LANCZOS)

    canvas = Image.new("RGBA", (canvas_size, canvas_size), (0, 0, 0, 0))
    offset = ((canvas_size - artwork.width) // 2, (canvas_size - artwork.height) // 2)
    canvas.alpha_composite(artwork, offset)
    canvas.save(target)


def main() -> None:
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    for filename, source in SOURCES.items():
        if not source.is_file():
            raise FileNotFoundError(source)
        target = FRAME_DIR / filename
        if not target.is_file():
            raise FileNotFoundError(target)
        backup = BACKUP_DIR / filename
        if not backup.exists():
            copy2(target, backup)
        rebuild(source, target)
        print(f"rebuilt {filename}")


if __name__ == "__main__":
    main()
