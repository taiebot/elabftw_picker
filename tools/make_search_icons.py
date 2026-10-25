#!/usr/bin/env python3
"""Make the icons used as thumbnails in the Smart Picker results.

The thumbnails of a search result cannot follow the theme (no CSS filter), and they fill the whole
thumbnail box. So the search icons are:
  - a neutral gray, readable on both the light and the dark theme,
  - padded, so they are not bigger than the icon of the provider above the list.

Usage, from the root of the app (this script is not part of the app package):
    python3 tools/make_search_icons.py img

Reads  img/experiment.svg and img/resource.svg (Font Awesome icons)
writes img/experiment-search.svg and img/resource-search.svg.
"""
import re
import sys
from pathlib import Path

COLOR = '#8a8a8a'
# Share of the icon size added on each side (0.3: the icon fills about 62% of the box)
PADDING = 0.3

ICONS = ['experiment', 'resource']


def convert(src: Path, dst: Path) -> None:
    svg = src.read_text(encoding='utf-8')

    root = re.search(r'<svg\b[^>]*>', svg)
    view_box = re.search(r'viewBox="([-\d.,\s]+)"', root.group(0)) if root else None
    if root is None or view_box is None:
        raise SystemExit(f'{src}: no <svg viewBox="..."> found')

    x, y, width, height = (float(v) for v in re.split(r'[\s,]+', view_box.group(1).strip()))
    side = max(width, height) * (1 + 2 * PADDING)
    new_x = x + width / 2 - side / 2
    new_y = y + height / 2 - side / 2

    new_root = view_box.re.sub(
        f'viewBox="{new_x:g} {new_y:g} {side:g} {side:g}"', root.group(0), count=1
    )
    # A fixed width and height on the root would stop the icon from filling its box
    new_root = re.sub(r'\s(?:width|height)="[^"]*"', '', new_root)
    svg = svg.replace(root.group(0), new_root, 1)

    svg = re.sub(r'\sfill="[^"]*"', '', svg)
    svg = svg.replace('<path ', f'<path fill="{COLOR}" ')
    dst.write_text(svg, encoding='utf-8')


def main() -> None:
    folder = Path(sys.argv[1] if len(sys.argv) > 1 else 'img')
    for name in ICONS:
        src = folder / f'{name}.svg'
        if not src.exists():
            raise SystemExit(f'{src} does not exist')
        dst = folder / f'{name}-search.svg'
        convert(src, dst)
        print(f'{src} -> {dst}')


if __name__ == '__main__':
    main()
