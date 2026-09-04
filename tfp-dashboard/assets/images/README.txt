Two placeholder logo files are already here so the sidebar isn't broken
out of the box:

1. logo-expanded.svg   → shown when the sidebar is expanded (full width)
2. logo-collapsed.svg  → shown when the sidebar is collapsed (icon-only)

TO USE YOUR OWN LOGOS:
Just replace these two files with your real logos, keeping the EXACT
same filenames (logo-expanded.svg / logo-collapsed.svg). If you want to
use PNG/JPG instead of SVG:
  1. Add your files here as logo-expanded.png / logo-collapsed.png
     (or .jpg)
  2. Open includes/shell.php, find the tfp_dashboard_render_sidebar()
     function, and change the two file extensions in the <img src="...">
     lines from .svg to whatever you used.

Recommended sizes:
  - logo-expanded.svg  ≈ 160x40px (wide/horizontal logo)
  - logo-collapsed.svg ≈ 40x40px  (square/icon mark only)

This replaces the old theme logo (get_theme_mod('custom_logo')) that was
previously pulled from Appearance > Customize > Site Identity.

