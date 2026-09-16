#!/usr/bin/env python3
"""Build static and lightweight animated Nexus Moodle course banners."""

from __future__ import annotations

import math
from pathlib import Path

from PIL import Image, ImageDraw, ImageEnhance, ImageFilter, ImageFont


ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIR = ROOT / "assets" / "nexus-course-banner-sources"
OUTPUT_DIR = ROOT / "local-nexusdemodata" / "pix" / "banners"
WIDTH = 1200
HEIGHT = 400

COURSES = {
    "mhf4u": ("MHF4U", "Advanced Functions", (111, 205, 255), (236, 190, 92)),
    "sbi4u": ("SBI4U", "Biology", (119, 225, 174), (235, 201, 116)),
    "sch4u": ("SCH4U", "Chemistry", (100, 184, 255), (255, 179, 71)),
    "eng4u": ("ENG4U", "English", (246, 222, 193), (184, 75, 82)),
    "asm4m": ("ASM4M", "Media Arts", (176, 129, 255), (44, 216, 232)),
    "sph4u": ("SPH4U", "Physics", (126, 217, 255), (209, 217, 226)),
    "tdj4m": ("TDJ4M", "Technological Design", (128, 204, 255), (246, 121, 47)),
    "avi1o": ("AVI1O", "Visual Arts", (255, 168, 139), (122, 151, 255)),
}

FONT_BOLD = Path("/System/Library/Fonts/Supplemental/Arial Bold.ttf")
FONT_REGULAR = Path("/System/Library/Fonts/Supplemental/Arial.ttf")


def fit_source(source: Image.Image) -> Image.Image:
    """Centre-crop to the exact 3:1 banner ratio without stretching."""
    source = source.convert("RGB")
    src_ratio = source.width / source.height
    dst_ratio = WIDTH / HEIGHT
    if src_ratio > dst_ratio:
        crop_width = round(source.height * dst_ratio)
        left = (source.width - crop_width) // 2
        source = source.crop((left, 0, left + crop_width, source.height))
    elif src_ratio < dst_ratio:
        crop_height = round(source.width / dst_ratio)
        top = (source.height - crop_height) // 2
        source = source.crop((0, top, source.width, top + crop_height))
    return source.resize((WIDTH, HEIGHT), Image.Resampling.LANCZOS)


def overlay_typography(image: Image.Image, code: str, name: str, accent: tuple[int, int, int]) -> Image.Image:
    canvas = image.convert("RGBA")
    shade = Image.new("RGBA", canvas.size, (0, 0, 0, 0))
    shade_draw = ImageDraw.Draw(shade)
    for x in range(0, 735):
        ratio = x / 735
        alpha = round(88 * (1 - ratio) + 12)
        shade_draw.line((x, 0, x, HEIGHT), fill=(0, 5, 18, alpha))
    canvas = Image.alpha_composite(canvas, shade)

    draw = ImageDraw.Draw(canvas)
    brand_font = ImageFont.truetype(str(FONT_BOLD), 17)
    code_font = ImageFont.truetype(str(FONT_BOLD), 78)
    name_font = ImageFont.truetype(str(FONT_REGULAR), 34)

    draw.rounded_rectangle((58, 62, 66, 329), radius=4, fill=accent + (255,))
    draw.text((91, 70), "NEXUS EDUCATION PRIVATE SCHOOL", font=brand_font, fill=(245, 247, 252, 230))
    draw.text((87, 120), code, font=code_font, fill=(255, 255, 255, 255), stroke_width=1, stroke_fill=(0, 0, 0, 80))
    draw.text((91, 222), name, font=name_font, fill=(240, 244, 250, 244))
    draw.line((91, 285, 415, 285), fill=accent + (210,), width=3)
    draw.text((91, 304), "ONTARIO SECONDARY COURSE", font=brand_font, fill=(222, 229, 239, 210))
    return canvas


def add_motion_layer(
    base: Image.Image,
    frame_index: int,
    primary: tuple[int, int, int],
    accent: tuple[int, int, int],
) -> Image.Image:
    motion = Image.new("RGBA", base.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(motion)
    particles = [(784, 78, 4), (875, 305, 3), (1012, 96, 3), (1110, 265, 4), (735, 230, 2)]
    for index, (x, y, radius) in enumerate(particles):
        phase = (frame_index + index) * (math.pi / 2)
        px = x + round(math.sin(phase) * 7)
        py = y + round(math.cos(phase) * 5)
        colour = primary if index % 2 == 0 else accent
        draw.ellipse((px - radius, py - radius, px + radius, py + radius), fill=colour + (135,))
    motion = motion.filter(ImageFilter.GaussianBlur(1.2))
    return Image.alpha_composite(base, motion)


def build_banner(slug: str, details: tuple[str, str, tuple[int, int, int], tuple[int, int, int]]) -> None:
    code, name, primary, accent = details
    source = fit_source(Image.open(SOURCE_DIR / f"{slug}.png"))
    source = ImageEnhance.Contrast(source).enhance(1.04)
    base = overlay_typography(source, code, name, accent)

    static_path = OUTPUT_DIR / f"{slug}-static.webp"
    base.convert("RGB").save(static_path, "WEBP", quality=78, method=6)

    frames = [add_motion_layer(base, i, primary, accent).convert("RGB") for i in range(4)]
    animated_path = OUTPUT_DIR / f"{slug}.webp"
    frames[0].save(
        animated_path,
        "WEBP",
        save_all=True,
        append_images=frames[1:],
        duration=650,
        loop=0,
        quality=70,
        method=6,
        minimize_size=True,
    )


def main() -> None:
    if not FONT_BOLD.is_file() or not FONT_REGULAR.is_file():
        raise SystemExit("Required system fonts were not found.")
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    for slug, details in COURSES.items():
        build_banner(slug, details)


if __name__ == "__main__":
    main()
