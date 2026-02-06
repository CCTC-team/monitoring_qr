# Monitoring QR External Module Documentation

## Overview

The **Monitoring QR** module is a REDCap External Module designed to support Source Data Verification (SDV) monitoring workflows within clinical research projects. It leverages REDCap's Data Resolution Workflow to provide an independent monitoring system for verifying data quality and integrity.

**Version:** 1.0.0
**Framework Version:** 14
**Namespace:** `CCTC\MonitoringQRModule`

### Authors
- Richard Hardy (University of Cambridge - Cambridge Cancer Trials Centre)
- Mintoo Xavier (Cambridge University Hospital - Cambridge Cancer Trials Centre)

### Compatibility
- **PHP:** 8.0.27 - 8.2.29
- **REDCap:** 13.8.1 - 15.9.1

---

## Purpose

This module enables monitors to:
- Review and verify data entered into REDCap forms
- Raise queries against specific fields (flagged or unflagged)
- Track verification status of forms throughout a project
- Maintain an audit trail of all monitoring activities
- Export monitoring query logs for reporting purposes

---

## Architecture

### File Structure

```
monitoring_qr_v1.0.0/
├── config.json              # Module configuration and settings schema (includes auth-ajax-actions)
├── MonitoringQRModule.php   # Main module class extending AbstractExternalModule
├── Utility.php              # Date/time utilities and helper functions
├── Rendering.php            # HTML table and form rendering utilities
├── MonitoringData.php       # Database query layer with input validation helpers
├── index.php                # Main monitoring log page UI
├── csv_export.php           # CSV export functionality
├── MonQR_ajax.php           # AJAX endpoint for status updates (with error handling)
├── getparams.php            # Request parameter validation and sanitization
├── classes/
│   ├── RoleManager.php      # User role detection and permissions
│   └── CacheManager.php     # In-memory caching with TTL support
├── js/
│   └── monitoring-qr.js     # Common JavaScript functions (extracted from PHP)
├── sql-setup/
│   └── 0010__create_GetMonitorQueries.sql  # Stored procedure (with indexed temp tables)
└── automated_tests/         # Cypress test fixtures and step definitions
```

### Core Classes

#### MonitoringQRModule
The main module class that:
- Handles system enable/disable hooks to inject custom code into REDCap core files
- Manages the monitoring workflow UI on data entry forms
- Processes query status updates via delegated classes
- Validates module settings (regex syntax, field suffix format, enum index validation)
- Defines class constants for query statuses (`QUERY_OPEN`, `QUERY_CLOSED`) and response types
- Delegates role detection to `RoleManager`
- Uses `CacheManager` for project settings and field name caching
- Bulk-loads project settings via `preloadProjectSettings()` to reduce database round-trips
- Provides `jsEncode()` and `attrEncode()` helpers for safe JavaScript/HTML output

#### RoleManager
Manages user role detection:
- `userHasMonitorRole()` - Checks if user has the monitor role
- `userHasDataEntryRole()` - Checks if user has a data entry role (supports multiple)
- `userHasDataManagerRole()` - Checks if user has the data manager role
- `getCurrentUserRoleType()` - Returns the user's role type as a string

#### CacheManager
Provides in-memory caching with TTL support:
- `get(key)` / `set(key, value)` - Basic cache operations
- `remember(key, callback)` - Cache-through pattern
- 5-minute default TTL with automatic expiration

#### MonitoringData
Database access layer:
- `GetQueries()` - Retrieves monitoring query data via stored procedure
- `GetUserDags()` - Gets DAG membership for the current user
- `escapeString()` / `escapeInt()` - Input sanitization helpers for safe SQL parameter handling
- `validateDirection()` / `validateQueryStatus()` - Whitelist validation for enumerated parameters
- All stored procedure parameters are validated before execution

#### Utility
Helper functions for:
- URL generation and manipulation
- Date/time formatting in user's preferred format
- Array grouping operations

#### Rendering
HTML generation for:
- Monitoring log tables
- Filter dropdowns (status, event, instance, form, field, flag, response)
- Pagination controls

#### getparams.php
Request parameter validation:
- `sanitizeString()` - Sanitizes string inputs via `filter_input()`
- `validateInt()` - Validates integer inputs with bounds checking
- `validateInArray()` - Whitelist validation for enumerated parameters

---

## Installation

### System-Level Setup

When the module is enabled at the system level, it automatically:

1. **Creates a stored procedure** (`GetMonitorQueries`) in the REDCap database for querying monitoring data

2. **Injects code into Hooks.php** to create a custom hook:
   ```php
   public static function redcap_save_record_mon_qr($result){}
   ```

3. **Injects code into DataEntry.php** to call the custom hook when records are saved:
   ```php
   Hooks::call('redcap_save_record_mon_qr', array($field_values_changed, ...));
   ```

4. **Injects code into DataQuality.js** to support the monitoring UI

When disabled at the system level, all injected code and the stored procedure are automatically removed.

**Important:** When updating the module to a new version, disable and re-enable the module at the system level to ensure code injections are updated.

---

## Project Configuration

### Prerequisites

1. The project must use **Data Resolution Workflow** (enabled in Project Setup)
2. The project can be longitudinal with repeating instruments/events
3. Only non-survey instruments support monitoring

### Required Project Settings

| Setting Key | Description |
|-------------|-------------|
| `monitoring-field-suffix` | Suffix identifying the monitoring status field (e.g., `_monstat`) |
| `monitoring-flags-regex` | Regex to identify fields flagged for monitoring (e.g., `@ENDPOINT-[A-Z]+`) |
| `monitoring-role` | REDCap role assigned to monitors |
| `data-entry-roles` | REDCap role(s) assigned to data entry users (supports multiple) |
| `data-manager-role` | REDCap role assigned to data managers |
| `monitoring-field-verified-key` | Dropdown index for "Verification complete" status |
| `monitoring-requires-verification-key` | Dropdown index for "Requires verification" status |
| `monitoring-requires-verification-due-to-data-change-key` | Dropdown index for "Requires verification due to data change" |
| `monitoring-not-required-key` | Dropdown index for "Not required" status |
| `monitoring-verification-in-progress-key` | Dropdown index for "Verification in progress" status |
| `trigger-requires-verification-for-change` | Behavior when verified data is changed |
| `resolve-issues-behaviour` | How to handle monitoring queries on Resolve Issues page |

### Optional Project Settings

| Setting Key | Description |
|-------------|-------------|
| `ignore-for-monitoring-action-tag` | Action tag to exclude fields from monitoring |
| `monitoring-role-show-inline` | Show queries inline for monitors |
| `data-entry-role-show-inline` | Show queries inline for data entry users |
| `data-manager-role-show-inline` | Show queries inline for data managers |
| `monitors-only-query-flagged-fields` | Restrict monitors to only query flagged fields |
| `do-not-hide-save-and-cancel-buttons-for-non-data-entry` | Allow non-data-entry users to interact with forms |
| `do-not-make-fields-readonly` | Allow non-data-entry users to edit fields |
| `include-field-label-in-inline-form` | Include field labels in monitoring table |
| `allow-data-managers-to-respond-to-queries` | Allow data managers to respond to queries |

### Configuration Validation

The module validates settings when saved:
- **Regex syntax validation** - Ensures the monitoring flags regex is valid
- **Field suffix format validation** - Checks the suffix follows expected patterns
- **Numeric validation for enum indices** - Verifies status keys are valid integers
- **Duplicate index detection** - Prevents multiple statuses from sharing the same dropdown index

### Monitoring Status Field Setup

Each form requiring monitoring must include a dropdown field with a variable name ending in the configured suffix (e.g., `form1_monstat`). The dropdown should have these options:

```
1, Verified
2, Requires verification
3, Requires verification due to data change
4, Not required
5, Verification in progress
```

The status field is automatically managed by the module and hidden from users.

---

## Workflow

### Class Constants

The module defines constants to replace magic values throughout the codebase:

#### Query Status Constants
| Constant | Value | Description |
|----------|-------|-------------|
| `QUERY_OPEN` | `'OPEN'` | A monitoring query is currently active |
| `QUERY_CLOSED` | `'CLOSED'` | The monitoring query has been closed |

#### Response Type Constants
| Constant | Value |
|----------|-------|
| `RESPONSE_VALUE_UPDATED` | `'value_updated_as_per_source'` |
| `RESPONSE_VALUE_CORRECT` | `'value_correct_as_per_source'` |
| `RESPONSE_SOURCE_UPDATED` | `'value_correct_error_in_source_updated'` |
| `RESPONSE_MISSING_DATA` | `'missing_data_not_done'` |

These constants are also injected into JavaScript via the `MonQR` object for consistent usage across PHP and JS.

### Query Status States

| Status | Constant | Description |
|--------|----------|-------------|
| NONE | `NO_QUERY` | No monitoring query has been raised |
| OPEN | `QUERY_OPEN` | A monitoring query is currently active |
| CLOSED | `QUERY_CLOSED` | The monitoring query has been closed |

### Monitor Status States

| Status | Description |
|--------|-------------|
| Verified | Form has been verified and is complete |
| Requires verification | Form needs initial verification |
| Requires verification due to data change | Previously verified form needs re-verification |
| Not required | Monitoring is not required for this form |
| Verification in progress | An active query is being processed |

### Response Types

When data entry users respond to queries, they can select:
- "Value updated as per source"
- "Value correct as per source"
- "Value correct, error in source updated"
- "Missing data not done"

### Typical Workflow

1. **Record Creation**
   - If the form has flagged fields, status is set to "Requires verification"
   - If no flagged fields exist, status is set to "Not required"

2. **Monitor Review**
   - Monitor reviews the form data
   - Options: "Close as verified", "Close as not required", or "Raise monitor query"

3. **Query Resolution** (if queries raised)
   - Data entry users respond to each queried field
   - Monitor reviews responses and either accepts or re-raises queries
   - Process repeats until all queries are resolved

4. **Data Changes to Verified Forms**
   - Depending on `trigger-requires-verification-for-change` setting, changes may invalidate the verified status

---

## User Interface

### Monitoring QR Log Page

Accessed via the left menu link "Monitoring QR", this page provides:

#### Filters
- Record ID (autocomplete)
- Query status (OPEN, CLOSED, NONE, or negations)
- Monitor status
- Date range (with quick presets: day, week, month, year)
- Event, instance, form, field, flag, response
- Query text search
- Username

#### Display
- Paginated table of monitoring entries
- Clickable links to navigate to forms
- Different columns for OPEN vs CLOSED queries

#### Export Options
- Export current page
- Export all pages
- Export everything (ignoring filters)

---

## Security Features

### Input Validation
- All GET parameters are sanitized via `sanitizeString()`, `validateInt()`, and `validateInArray()` helpers in `getparams.php`
- POST parameters in `MonQR_ajax.php` are validated using `filter_var()` with appropriate filters
- Integer parameters enforce bounds checking (e.g., page size, page number)
- Enumerated parameters are validated against whitelists (e.g., query status, sort direction)

### SQL Injection Prevention
- `MonitoringData.php` uses `escapeString()` and `escapeInt()` helpers for all stored procedure parameters
- `validateDirection()` restricts sort direction to `ASC`/`DESC` only
- `validateQueryStatus()` restricts status values to known valid states
- All user-supplied values are validated before reaching SQL

### XSS Prevention
- `jsEncode()` helper encodes values for safe JavaScript context using `json_encode()` with `JSON_HEX_TAG`, `JSON_HEX_APOS`, `JSON_HEX_QUOT`, `JSON_HEX_AMP` flags
- `attrEncode()` helper encodes values for safe HTML attribute context using `htmlspecialchars()` with `ENT_QUOTES`
- Inline event handlers replaced with `data-*` attributes to separate data from code
- All dynamically generated JavaScript uses encoded values

### Access Control
- Role-based permissions determine available actions via `RoleManager`
- DAG restrictions are respected in queries
- Form visibility follows REDCap's native permissions
- AJAX actions declared in `config.json` via `auth-ajax-actions` for framework-level authentication

### Error Handling
- `redcap_data_entry_form` and `redcap_save_record_mon_qr` hooks wrapped in try-catch blocks
- `MonQR_ajax.php` returns structured JSON error responses with logging
- `setMonitorStatus` throws exceptions on errors for proper error propagation

---

## Database

### Stored Procedure: GetMonitorQueries

The module creates a stored procedure that:
- Retrieves monitoring query data with pagination
- Returns multiple result sets for efficient filtering
- Supports complex filtering by various criteria
- Handles DAG-based access restrictions
- Uses indexed temporary tables for performance (individual indexes on record, event_id, instance, form_name, query_status, username, timestamp, field_name; composite index on record + event_id + field_name + instance for JOIN optimization)

---

## AJAX Endpoints

### MonQR_ajax.php

Handles status updates via POST requests. Declared in `config.json` under `auth-ajax-actions` as `"setMonitorStatus"` for framework-level authentication.

**Parameters:**
- `projectId` - Project ID (required, validated as integer via `filter_var()`)
- `eventId` - Event ID (required, validated as integer via `filter_var()`)
- `record` - Record ID (required, sanitized string)
- `monitorField` - Monitor field name (required, sanitized string)
- `statusInt` - New status value (required, validated as integer)
- `instrument` - Form name (required, sanitized string)
- `repeatInstance` - Instance number (optional, validated as integer)

**Response:** JSON with `success: true` or structured error details with logging

**Error Handling:** All operations are wrapped in try-catch blocks. Errors are logged via the module's logging system and returned as JSON with appropriate HTTP status codes.

---

## Hooks Used

- `redcap_module_system_enable` - Setup on system enable
- `redcap_module_system_disable` - Cleanup on system disable
- `redcap_module_link_check_display` - Link visibility control
- `redcap_data_entry_form` - Inject monitoring UI into data entry
- `redcap_save_record_mon_qr` - Custom hook for post-save processing

---

## JavaScript Architecture

Common JavaScript functions are extracted into [js/monitoring-qr.js](js/monitoring-qr.js) and loaded via `addCommonJS()`. The module injects a `MonQR` configuration object into the page containing:
- Class constants (`QUERY_OPEN`, `QUERY_CLOSED`, response types)
- Project-specific settings
- Base URLs for AJAX calls

Inline event handlers use `data-*` attributes to pass values safely, with JavaScript attaching event listeners dynamically rather than embedding code in HTML attributes.

---

## Integration with Embellish Fields Module

This module is designed to work alongside the Embellish Fields module to display endpoint flags visually within the data entry form interface.

---

## Troubleshooting

### Common Issues

1. **Status not updating when buttons clicked**
   - Check that the monitoring status field suffix matches the configuration
   - Verify the form data structure for repeating instruments

2. **Module not functioning after REDCap upgrade**
   - Disable and re-enable the module at system level to re-inject code

3. **Invalid regex error**
   - Ensure the regex in settings does not include leading/trailing slashes

4. **Data Resolution Workflow not enabled**
   - Enable Data Resolution Workflow in Project Setup before using this module

---

## Performance Considerations

### Caching
- `CacheManager` provides in-memory caching with 5-minute TTL and automatic expiration
- Project settings are bulk-loaded via `preloadProjectSettings()` in a single database call, replacing individual `getProjectSetting()` calls with `getCachedProjectSetting()`
- Field names are cached per request to avoid repeated lookups
- Dynamic data (form data, query history) is intentionally not cached as it changes frequently

### Stored Procedure Optimization
- Temporary tables in `GetMonitorQueries` are indexed with individual indexes (`idx_record`, `idx_event_id`, `idx_instance`, `idx_form_name`, `idx_current_query_status`, `idx_username`, `idx_ts`, `idx_field_name`)
- A composite index on `(record, event_id, field_name, instance)` optimizes JOIN operations

### General
- Large exports may require increased PHP memory limits
