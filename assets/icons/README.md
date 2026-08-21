# App icon assets

The repository currently uses editable SVG placeholders so pull-request patches
remain text-only. The following binary files were removed and should be created
from the corresponding SVG artwork before production deployment:

| File to create | Size | Source artwork | Use |
| --- | ---: | --- | --- |
| `apple-touch-icon.png` | 180 × 180 px | `apple-touch-icon.svg` | iPhone/iPad home screen |
| `icon-192.png` | 192 × 192 px | `icon.svg` | PWA manifest icon |
| `icon-512.png` | 512 × 512 px | `icon.svg` | PWA manifest/install icon |

Export each image as a full-size, non-indexed PNG in the sRGB color space. Do not
add transparency around the outer green background. After adding the PNG files,
replace the SVG Apple touch icon reference in `index.html`, add both PWA PNG sizes
to `manifest.webmanifest`, and add all three files to the service-worker app shell.

The existing `icon-maskable.svg` includes its own safe-area layout and remains the
text-based maskable manifest icon.
