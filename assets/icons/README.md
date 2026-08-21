# App icon assets

Keep all icon files in this directory. None is required at the web root because
`index.html` and the PWA manifest use explicit paths.

Required production files:

| File | Size | Use |
| --- | ---: | --- |
| `android-chrome-192x192.png` | 192 × 192 px | PWA and Android icon |
| `android-chrome-512x512.png` | 512 × 512 px | PWA and Android install icon |
| `apple-touch-icon.png` | 180 × 180 px | iPhone and iPad home screen |
| `favicon.ico` | Multi-size ICO | Legacy and fallback browser favicon |
| `favicon-16x16.png` | 16 × 16 px | Small browser favicon |
| `favicon-32x32.png` | 32 × 32 px | Standard browser favicon |

The 512 × 512 Android icon is also declared as maskable. Its important artwork
must remain inside the central safe area so Android launchers do not crop it.
