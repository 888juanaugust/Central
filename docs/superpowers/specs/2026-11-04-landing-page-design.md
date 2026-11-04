# Landing page — the old site's missing pieces, the portal, WhatsApp and the FAQ

Status: built (2026-11-04).

## Why

The owner wants the public home page to hold what the previous system's landing page held,
and more, in Central's look. Central's home already carries most of the old page (promo
carousel, hero, categories, brands, who we serve, the three steps, about and partners, the
closing sign-in banner). Still missing from the old page: the company at a glance, the faint
oversized mark behind the hero, and the cookie notice. The owner also asked for a showcase of
the buyer portal, a WhatsApp button and an FAQ, real company content, and a polish pass that
keeps the site's style (the workspace's tokens, Geist, one blue, light and dark).

## Decisions

- **The mark behind the hero**: the company's mark, oversized, low opacity, bottom right;
  the dark twin in dark mode. Decorative: empty alt, hidden from assistive technology.
- **Company at a glance**: a panel under the hero with the mark, the name, the tagline, who we
  serve, and four facts read from the system: categories, brands, branches, and the credit
  term of the default payment term (left out when there is none).
- **Portal showcase**: what a buyer gets after sign-in, in the order the portal puts it
  (reorder the last order, free credit always shown, the catalogue at the customer's own
  prices, order history with invoice and surat jalan PDFs, open invoices with due dates),
  beside a static picture of the reorder card. No amount and no price anywhere.
- **WhatsApp**: a floating button on every page and an action in the hero, both to
  `wa.me/<digits>`; hidden when no number is set.
- **FAQ**: bilingual questions and answers in the site copy, editable on the Website screen
  (a new FAQ tab). An answer may say `:credit_days` (the default payment term) and
  `:claim_days` (the terms of sale), filled at render so the numbers never drift from the
  terms page. Rendered as `<details>`, so it opens without JavaScript; `FAQPage` structured
  data on the home page.
- **Cookie notice**: shown once, dismissed with "Understood"; the acknowledgement lives in
  the browser's local storage, not a cookie. The privacy policy says so (version 1.1).
- **Two names**: customers see the trading name **Javaindo** on the site (title, header,
  footer, structured data with the legal name beside it), in the buyer portal (its brand, and
  the app name for portal requests, so the password-reset mail signs as Javaindo) and in the
  mail sent to them (the invitation, the debt notice: sender and subject). Staff keep
  **Central**: `config('client.names.staff')` wins over the company's name in the panel's
  brand. The customer name is the Website screen's short name, default
  `config('client.names.customer')`.
- **Real content**: stays editable on the Website and Website Images screens; Launch
  Readiness keeps flagging placeholders until the owner's values are in.

## Base edits

`AdminPanelProvider::brandName()` reads `config('client.names.staff')` first (the panel's
brand); `PanelBootTest` covers the base's rule and the override.

## Tests

`SiteRoutesTest` (facts, portal section, FAQ numbers, WhatsApp link, cookie notice, still no
price), `SiteCopyTest` (FAQ pairs complete), `WebsiteScreensTest` (the FAQ tab saves).
