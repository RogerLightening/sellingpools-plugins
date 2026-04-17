# BK Agent Matcher

Suburb autocomplete and proximity-based agent matching for the BK Pools lead generation platform.

---

## Phase

Phase 2 of 5. Depends on **bk-pools-core** (Phase 1).

---

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress   | 6.0+    |
| PHP         | 8.0+    |
| BK Pools Core | 1.0.0+ |
| JetEngine   | 3.x (CCT suburbs table must exist) |
| JetFormBuilder | 3.x |

---

## What it does

### 1. Suburb Autocomplete
Provides a vanilla JS autocomplete widget for any input with `data-bk-suburb-autocomplete="true"` or `name="suburb_search"`. Searches 11,000 suburbs from the JetEngine CCT table with a debounced AJAX call.

**Hidden fields populated on selection** (configurable via data attributes):

| Data attribute | Default field name | Value populated |
|---|---|---|
| `data-bk-target-id` | `suburb_id` | CCT record `_ID` |
| `data-bk-target-suburb` | `suburb_name` | Suburb name |
| `data-bk-target-area` | `area_name` | Area name |
| `data-bk-target-province` | `province` | Province name |

### 2. Agent Matching
After the JetFormBuilder form (ID: 1037) creates a new estimate post, the matcher:
1. Resolves the suburb's lat/lng from `wp_jet_cct_suburbs`.
2. Fetches all active builders from `wp_builders_meta` in one SQL query.
3. Calculates Haversine distances using `BK_Haversine::distance()` from bk-pools-core.
4. Filters by `max_travel_radius_km`; falls back to all agents if fewer than 3 qualify.
5. Prices each agent: shell price + installed price + travel fee, all incl. VAT.
6. Writes 3 rows to `wp_bk_lead_agents`.
7. Writes snapshot meta to the estimate post.
8. Fires `bk_pools_agents_matched` for Phase 3.

---

## Plugin Structure

```
bk-agent-matcher/
├── bk-agent-matcher.php              # Bootstrap, dependency check, constants
├── includes/
│   ├── class-bk-suburb-lookup.php   # Suburb AJAX search + asset enqueuing
│   ├── class-bk-matcher.php         # Core matching + pricing logic
│   └── class-bk-matcher-hooks.php   # JFB + save_post hook integration
├── assets/
│   ├── css/suburb-autocomplete.css  # Dropdown styles
│   └── js/suburb-autocomplete.js    # Vanilla JS autocomplete
└── README.md
```

---

## Hooks

### Action: `bk_pools_agents_matched`

Fired after matching and all DB writes are complete.

```php
add_action( 'bk_pools_agents_matched', function( int $estimate_post_id, array $matched_agents ) {
    // Phase 3: generate PDF estimates and send emails here.
}, 10, 2 );
```

---

## Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `BK_MATCHER_VERSION` | `'1.0.0'` | Plugin version |
| `BK_MATCHER_PLUGIN_DIR` | Path | Absolute path with trailing slash |
| `BK_MATCHER_PLUGIN_URL` | URL | Public URL with trailing slash |
| `BK_MATCHER_FORM_ID` | `1037` | JetFormBuilder estimate form ID |

---

## Meta written to estimate post

| Meta key | Value |
|----------|-------|
| `customer_latitude` | Suburb latitude (float) |
| `customer_longitude` | Suburb longitude (float) |
| `shell_price` | Shell price excl. VAT at time of estimate |
| `pool_shape_name` | Pool shape name snapshot |
| `assigned_agents` | JSON array of matched agent data |
| `estimate_token` | 32-char random token |
| `estimate_url` | `/estimate/view/{token}` |
| `estimate_expiry` | Expiry datetime (from settings) |
| `estimate_created` | Creation datetime |
| `_bk_agents_matched` | `'1'` (processing flag) |

---

## Changelog

### 1.0.0
- Initial release. Suburb autocomplete, agent matching, pricing, JFB integration.

---

## Author

Lightning Digital — [lightningdigital.co.za](https://lightningdigital.co.za)
