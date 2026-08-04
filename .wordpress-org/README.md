# WordPress.org Assets

This directory contains all assets needed for WordPress.org plugin repository deployment.

## Directory Structure

- **`banners/`** — Plugin banner images
  - `banner-772x250.png` — Small banner for plugin listing page
  - `banner-1544x500.png` — Large banner for plugin details page

- **`icons/`** — Plugin icon images
  - `icon-128x128.png` — Small icon (128x128)
  - `icon-256x256.png` — Large icon (256x256)
  - `icon.svg` — Vector icon (optional, for better scaling)

- **`screenshots/`** — Feature screenshot images
  - `screenshot-1.png` — First screenshot (680x800 recommended)
  - `screenshot-2.png` — (Optional) Additional screenshots

## Guidelines

### Banner Images
- **Small Banner (772x250):** Used in plugin listings, search results
- **Large Banner (1544x500):** Used on plugin details page
- Format: PNG, GIF, or JPG
- Should represent the plugin's purpose and key features

### Icons
- **128x128 & 256x256:** PNG format, transparent background preferred
- **SVG:** Optional, provides sharp display at any size
- Icon should be recognizable at small sizes

### Screenshots
- **Dimensions:** 680x800 px (width x height minimum)
- **Format:** PNG, GIF, or JPG
- **Count:** 1-5 screenshots showing key features
- **Naming:** `screenshot-1.png`, `screenshot-2.png`, etc.
- Screenshots will be displayed in plugin carousel

## Notes

- All assets are tracked in git for easy maintenance and distribution
- WordPress.org will automatically resize images, but providing correct sizes helps
- Use descriptive, high-quality images
- Ensure images clearly demonstrate plugin features
- Avoid animated GIFs or video in banner/icons
