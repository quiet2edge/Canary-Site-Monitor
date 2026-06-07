# WP Canary Site Setup Instructions

## Overview

This guide walks you through importing the WP Canary site content into a fresh WordPress installation and configuring it to match the brand.

---

## Step 1: Install WordPress

1. Set up a fresh WordPress install at **getwpcanary.com** (or locally for staging).
2. During installation, set:
   - **Site Title:** WP Canary
   - **Tagline:** Because you shouldn't wake up to a broken site.
3. Create an admin user with username `admin`.

---

## Step 2: Install a Block-Compatible Theme

The content uses full-site editing (FSE) Gutenberg blocks with inline styles. A block theme is strongly recommended.

**Recommended free themes (in order of preference):**

1. **Kadence** (wordpress.org/themes/kadence/) — excellent typography defaults, great performance
2. **GeneratePress** (wordpress.org/themes/generatepress/) — lightweight, clean canvas
3. **Blocksy** (wordpress.org/themes/blocksy/) — good block support, flexible header
4. **Twenty Twenty-Four** (built-in) — works, minimal styling, adequate fallback

After installing your chosen theme:
- Go to **Appearance → Customize** (or **Appearance → Editor** for FSE themes)
- Set the primary color to `#1e6fa5` and accent/heading color to `#1a2e5a`
- Set the body font to **Inter** or **DM Sans** (available via Google Fonts in most themes)

---

## Step 3: Install Required Plugins Before Import

Install these plugins **before** running the import so their shortcodes and blocks parse correctly:

### Essential
| Plugin | Source | Purpose |
|--------|--------|---------|
| **WordPress Importer** | Tools → Import → WordPress | Required to run the WXR import |

### Strongly Recommended
| Plugin | Source | Purpose |
|--------|--------|---------|
| **Yoast SEO** | wordpress.org/plugins/wordpress-seo/ | SEO meta, sitemaps, schema |
| **WP Super Cache** | wordpress.org/plugins/wp-super-cache/ | Page caching, performance |
| **Redirection** | wordpress.org/plugins/redirection/ | 301 redirects, log 404s |
| **WP Mail SMTP** | wordpress.org/plugins/wp-mail-smtp/ | Reliable email delivery for contact forms and digest |

### Optional but Useful
| Plugin | Source | Purpose |
|--------|--------|---------|
| **Contact Form 7** | wordpress.org/plugins/contact-form-7/ | Contact page (if you add one) |
| **Query Monitor** | wordpress.org/plugins/query-monitor/ | Development debugging |
| **Simple Custom CSS** | wordpress.org/plugins/simple-custom-css/ | Add brand CSS tweaks without a child theme |

---

## Step 4: Run the WordPress Import

1. In your WordPress admin, go to **Tools → Import**.
2. Under **WordPress**, click **Run Importer**. (If prompted, install the WordPress Importer plugin first.)
3. Click **Choose File** and select:
   ```
   getwpcanary-import.xml
   ```
4. Click **Upload file and import**.
5. On the next screen:
   - **Assign authors:** Map imported author `admin` to your existing admin user.
   - Check **"Download and import file attachments"** (safe to leave checked — no media in this import).
6. Click **Submit**.

The importer will create:
- **6 Pages:** Home, Pricing, Documentation, Blog, Changelog, Privacy Policy
- **1 Post:** "Why WordPress Plugins Mysteriously Deactivate (And How to Catch It)"
- **1 Category:** WordPress Tips

---

## Step 5: Configure Reading Settings

1. Go to **Settings → Reading**.
2. Under "Your homepage displays," select **A static page**.
3. Set:
   - **Homepage:** Home
   - **Posts page:** Blog
4. Click **Save Changes**.

---

## Step 6: Configure Permalinks

1. Go to **Settings → Permalinks**.
2. Select **Post name** (`/%postname%/`).
3. Click **Save Changes**.

This ensures URLs match the imported slugs:
- `/pricing/`
- `/docs/`
- `/blog/`
- `/changelog/`

---

## Step 7: Set Up Navigation Menu

1. Go to **Appearance → Menus** (classic themes) or **Appearance → Editor → Navigation** (block themes).
2. Create a **Primary Navigation** menu with:
   - Home (`/`)
   - Pricing (`/pricing/`)
   - Docs (`/docs/`)
   - Blog (`/blog/`)
   - Changelog (`/changelog/`)
   - **Button:** "Get Pro" → `https://checkout.freemius.com/plugin/31489/plan/51656/` (style as CTA button)

3. Create a **Footer Menu** with:
   - Privacy Policy (`/privacy-policy/`)
   - Changelog (`/changelog/`)
   - Documentation (`/docs/`)

---

## Step 8: Yoast SEO Configuration

After installing Yoast SEO:

1. Run the **Yoast SEO Setup Wizard** (SEO → General → Configuration Wizard).
2. Set organization name to **WP Canary**.
3. Set site type to **Software/App**.
4. Configure homepage SEO:
   - **Title:** WP Canary — WordPress Early-Warning System
   - **Meta description:** WP Canary monitors your WordPress site overnight and alerts you to plugin deactivations, database errors, 404 floods, and debug log issues before your customers notice.
5. Submit your sitemap to Google Search Console:
   - Sitemap URL: `https://getwpcanary.com/sitemap_index.xml`

---

## Step 9: Recommended Custom CSS

Add this to **Appearance → Customize → Additional CSS** (or via Simple Custom CSS plugin) to polish the brand colors across all themes:

```css
/* WP Canary Brand Colors */
:root {
  --wp-canary-blue: #1e6fa5;
  --wp-canary-navy: #1a2e5a;
  --wp-canary-yellow: #f5c518;
}

/* Ensure hero sections edge-to-edge */
.hero-section {
  margin-left: calc(-1 * var(--wp--style--root--padding-left, 0px));
  margin-right: calc(-1 * var(--wp--style--root--padding-right, 0px));
}

/* Pricing table zebra stripe */
.wp-block-table tr:nth-child(even) td {
  background-color: #f8f9fa;
}

/* CTA button hover effect */
.wp-block-button__link:hover {
  opacity: 0.92;
  transform: translateY(-1px);
  transition: all 0.15s ease;
}

/* FAQ details block styling */
.wp-block-details summary {
  cursor: pointer;
  list-style: none;
  padding: 4px 0;
}
.wp-block-details summary::-webkit-details-marker {
  display: none;
}
.wp-block-details summary::before {
  content: '+ ';
  color: #1e6fa5;
  font-weight: 700;
}
.wp-block-details[open] summary::before {
  content: '− ';
}
```

---

## Step 10: Post-Import Checklist

- [ ] Homepage displays correctly at `/`
- [ ] `/pricing/` page loads with pricing table
- [ ] `/docs/` page loads with doc links
- [ ] `/blog/` shows the sample blog post
- [ ] `/changelog/` shows v1.0.0 release notes
- [ ] Freemius checkout link works: `https://checkout.freemius.com/plugin/31489/plan/51656/`
- [ ] WordPress.org download link is correct: `https://wordpress.org/plugins/wp-canary/`
- [ ] Navigation menu is assigned to correct locations
- [ ] Sitemap is accessible at `/sitemap_index.xml` (after Yoast install)
- [ ] Google Search Console verified
- [ ] WP Super Cache enabled and tested
- [ ] Contact/support email set up (e.g., support@getwpcanary.com)

---

## Notes on the Import File

**File:** `getwpcanary-import.xml`

**Post IDs assigned:**
| ID | Type | Title | Slug |
|----|------|-------|------|
| 1 | page | Home | `/home` (set as front page in Reading Settings) |
| 2 | page | Pricing | `/pricing/` |
| 3 | page | Documentation | `/docs/` |
| 4 | page | Blog | `/blog/` |
| 5 | page | Changelog | `/changelog/` |
| 6 | page | Privacy Policy | `/privacy-policy/` |
| 7 | post | Why WordPress Plugins... | `/blog/why-wordpress-plugins-mysteriously-deactivate/` |

**Note on the Home page slug:** WordPress will import the page with slug `home`. After setting it as the static front page in Settings → Reading, the `/` URL will serve it correctly regardless of the slug.

---

## Future Pages to Create (Documentation Section)

The `/docs/` page links to these sub-pages which should be created manually or imported in a future batch:

- `/docs/getting-started/`
- `/docs/installation/`
- `/docs/database-scanner/`
- `/docs/plugin-watchdog/`
- `/docs/404-monitor/`
- `/docs/ad-network-monitor/`
- `/docs/debug-log-analyzer/`
- `/docs/php-resource-monitor/`
- `/docs/wp-cron-health-monitor/`
- `/docs/morning-email-digest/`
- `/docs/settings-reference/`
- `/docs/upgrading-free-to-pro/`
- `/docs/multisite-setup/`
- `/docs/compatibility/`

All doc pages should use parent page ID 3 (Documentation) so they nest under `/docs/` in the permalink structure. Set this in Page Attributes → Parent Page when creating each one.
