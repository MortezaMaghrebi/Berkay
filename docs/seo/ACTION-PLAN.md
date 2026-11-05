# SEO action plan

Implemented: category H1/canonical, product canonical and sample exclusion, private/development noindex, magazine metadata and Article schema, published guides, homepage editorial links and an editorial sitemap discoverable through robots.

Before launch:
1. Choose the domain and server; configure APP_URL and Bagisto channel hostname to the final HTTPS origin. Enable only one canonical host and redirect HTTP and alternate hosts.
2. Replace all sample products and prices. Enter unique product descriptions, actual dimensions, materials, weight, strap length, capacity and care instructions. Keep sample SKUs excluded; remove obsolete sample URLs rather than reusing them for unrelated goods.
3. Enter real contact details, delivery cost/times, return policy and business identity. Do not add trust marks without authorization or reviews without customers.
4. Generate the native catalog sitemap in admin. Inspect /robots.txt, /sitemap.xml and /content-sitemap.xml on the final domain; verify all locations use HTTPS and the final host.
5. Verify Search Console domain ownership, submit both sitemaps and inspect homepage, category, real product and guide URLs. Confirm the production host is not inadvertently noindex. Avoid indexing search/filter permutations and personal pages.
6. Review Product schema including valid currency/IRR conversion; only mark real availability/reviews. Run Rich Results Test, mobile PageSpeed and rendered URL Inspection. Investigate actual failures before claiming certification.
7. Review article accuracy and keep them current in admin. Expand content around real customer questions and link guides to real stocked category/product pages. Do not mass-produce near-duplicate location or keyword pages.

Measure after launch: indexed landing pages, queries/impressions/clicks in Search Console, product-view to cart/checkout conversion, and actual sales. Improvements may take weeks or longer; there is no guaranteed ranking schedule.

Deploy content on a new server with: php artisan berkay:publish-guides. This command does not overwrite previously edited content. No SMS, payment or external publishing operation is needed.
