# Berkay SEO implementation audit

## Production follow-up, 2026-11-15

- High, confirmed: the live `robots.txt` advertised two loopback URLs and the live sitemap index contained no maps. The release indexes the live catalog and published content directly and falls back to the verified public host if production still has a loopback URL configured.
- Medium, confirmed in source: home and category templates lacked complete social sharing cards, and product descriptions differed across search and social tags. The release shares a common Open Graph and Twitter partial for home, categories, products, and articles.
- Medium, confirmed in source: rewards and admin pages lacked consistent indexing directives. The release adds explicit noindex signals for those surfaces.
- Pending: post-deploy XML, rendered HTML, canonical redirects, Search Console coverage and field Core Web Vitals. The installed SEO scripts could not run without Requests and Beautiful Soup. Live robots and sitemap evidence was obtained directly with curl.

Scope: local storefront, category, product, magazine, three editorial guides, robots and content sitemap. Evidence: HTTP responses and parsed HTML collected with the installed seo skill's parse_html.py, plus local HTTP/XML checks. This is deployment readiness, not a live Google visibility score.

## Confirmed findings and fixes

- Category headings: the original category view had no H1. Added one server-rendered category name and a self canonical. Local /women-bags returned 200 with exactly one H1 and canonical.
- Editorial discovery: magazine was missing meta description and canonical, and editorial URLs were absent from the native sitemap. Added metadata, /content-sitemap.xml and its discovery in /robots.txt. XML parses and published guide URLs return 200.
- Product URL consolidation: product metadata lacked a canonical. Added the public product URL. Sample SKU pages now receive noindex on public hosts.
- Content: published three original Persian guides (buying, maintenance, everyday bag types), editable through the existing article admin. The repeatable berkay:publish-guides command preserves any existing record with the same slug, including drafts. Added server-rendered homepage article links. No fake reviews, guarantees or manufacturer claims.
- Article entities: JSON-LD includes headline, description, real publication/modification timestamps, canonical page, language and the store organization author. Parsed article pages each contain one JSON-LD block.
- Index scope: customer, checkout, search and removed comparison pages are excluded with noindex. All loopback-host storefront pages are noindex for development. Public product samples remain noindex after deployment.

## Validation

Nine URLs returned HTTP 200; content sitemap parsed as XML; robots includes the extra sitemap. Existing 41 service/admin/payment tests passed with 143 assertions. Blade cache succeeded. Skill installed at ~/.codex/skills/seo. The skill's fetch helper refused loopback addresses, so a deliberately local HTTP collector provided HTML to its parser. No public PageSpeed or Search Console evidence is available for localhost.

## Unknown or outstanding

No public domain/server exists. Google cannot crawl this development computer. Search Console ownership, actual indexing, rankings, real-user Core Web Vitals, HTTPS, redirects and production sitemap hostnames require deployment. Native catalog sitemap requires generation in admin. Product content and photos are still illustrative; replace them with accurate inventory, dimensions, material, care and return terms. Product heading/body depend on the native Vue rendering; public rendered indexing needs URL Inspection after deployment. Rich Product results are not certified: review the native schema and its currency output (tomans vs valid IRR) before enabling it. No ranking score or sales forecast is asserted.
