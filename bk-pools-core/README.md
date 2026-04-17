# BK Pools Core

Foundation plugin for the BK Pools lead generation platform. Provides custom database tables, WordPress roles, a settings page, the Haversine distance library, and shared helper utilities used by all other BK Pools plugins.

---

## Phase

Phase 1 of 5 — Core foundation. All other BK Pools plugins depend on this one.

---

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress   | 6.0+    |
| PHP         | 8.0+    |
| JetEngine   | 3.x     |

---

## Plugin Structure

```
bk-pools-core/
├── bk-pools-core.php              # Main plugin file, bootstrap, activation/deactivation
├── includes/
│   ├── class-bk-database.php     # Custom table creation and schema migrations
│   ├── class-bk-roles.php        # Custom role and capability management
│   ├── class-bk-settings.php     # Admin settings page (WP Settings API)
│   ├── class-bk-helpers.php      # Shared utility functions
│   └── class-bk-haversine.php    # Great-circle distance calculations
├── assets/
│   ├── css/admin.css             # Admin settings page styles
│   └── js/admin.js               # Media picker script
├── templates/                    # Shared template partials (future use)
└── README.md
```

---

## Database Tables

Three custom tables are created on activation:

| Table | Purpose |
|-------|---------|
| `{prefix}bk_agent_pricing` | Per-agent per-pool-shape installed prices |
| `{prefix}bk_lead_agents`   | Junction table: estimates ↔ assigned agents (core CRM table) |
| `{prefix}bk_estimate_pdfs` | Generated PDF file references per agent per estimate |

Tables are **never dropped on deactivation** — only on explicit uninstallation — to prevent accidental data loss.

---

## Custom Roles

| Role | Display Name | Purpose |
|------|--------------|---------|
| `bk_agent`   | BK Agent         | Pool installation agents. Front-end only, no WP admin access. |
| `bk_manager` | BK Pools Manager | Platform managers. Editor-level WP access + full BK caps. |

### Custom Capabilities

| Capability | Who | Description |
|------------|-----|-------------|
| `bk_view_leads`      | agent, manager | View assigned / all leads |
| `bk_manage_leads`    | agent, manager | Update lead status, notes, rating |
| `bk_manage_pricing`  | agent          | Edit own pricing |
| `bk_manage_profile`  | agent          | Edit own agent profile |
| `bk_view_reports`    | manager        | View performance reports |
| `bk_manage_agents`   | manager        | Manage agent accounts |
| `bk_manage_settings` | manager        | Manage BK Pools settings |

---

## Settings

All settings are stored as a single serialised array under the option key `bk_pools_settings`.

Retrieve a setting from any plugin:

```php
$vat = BK_Settings::get_setting( 'vat_rate', 0.15 );
```

| Key | Default | Description |
|-----|---------|-------------|
| `vat_rate` | `0.15` | VAT rate (decimal) |
| `estimate_validity_days` | `30` | Days before estimate expires |
| `stale_lead_days` | `7` | Days before new lead becomes stale |
| `company_name` | `BK Pools` | Company name for estimates |
| `company_email` | `''` | Company email for notifications |
| `company_phone` | `''` | Company phone |
| `company_logo_id` | `''` | Attachment ID for company logo |
| `reward_top_seller_discount` | `0.05` | Top seller discount rate |
| `reward_milestone_10` | `0` | Bonus at 10 lifetime sales (Rand) |
| `reward_milestone_25` | `0` | Bonus at 25 lifetime sales (Rand) |
| `reward_milestone_50` | `0` | Bonus at 50 lifetime sales (Rand) |

---

## Helper Usage

```php
// Distance between two coordinates
$km = BK_Haversine::distance( -26.2041, 28.0473, -33.9249, 18.4241 );

// Find nearest 3 agents to a suburb
$agents = BK_Haversine::find_nearest_agents( $suburb_id, 3 );

// VAT calculation
$incl = BK_Helpers::calculate_vat( 100000.00 ); // → 115000.00

// Currency formatting
echo BK_Helpers::format_currency( 1234.56 ); // → "R 1 234.56"

// Table name reference (for use in other plugins)
$table = BK_Database::get_table_name( 'lead_agents' ); // → "wp_bk_lead_agents"
```

---

## Action Hook

Other BK Pools plugins should hook into `bk_pools_loaded` to confirm this plugin is active:

```php
add_action( 'bk_pools_loaded', function () {
    // Safe to use BK Pools Core APIs here.
} );
```

---

## Changelog

### 1.0.0
- Initial release. Scaffold, database tables, roles, settings, Haversine library, and helpers.

---

## Author

Lightning Digital — [lightningdigital.co.za](https://lightningdigital.co.za)
