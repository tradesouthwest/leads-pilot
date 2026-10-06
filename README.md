# leads-pilot
A multi-tenant CRM application with custom agency/company workspaces.

## File structure
```
├── src/
│   ├── Database.php      (Singleton PDO connection, execute/prepare error handling)
│   ├── Auth.php          (Session persistence, write permission guards)
│   ├── Helper.php        (XSS escaping & CSRF token generator/validator)
│   ├── Deal.php          (Agency-scoped deal updates & explicit INT binding)
│   └── Company.php       (Company CRUD operations & metric aggregates)
└── public/
    ├── index.php         (Grouped pipeline view, order toggle, notes/deal edit modal)
    ├── contacts.php      (Contact directory, company dropdown link, inline edit modal)
    ├── companies.php     (Company directory, contact/deal metrics, edit modal)
    └── admin.php         (Admin Control Panel, workspace metrics, deal deletion)
```

## Highlights

CSRF Protection: Embeds <?= Helper::csrfInput() ?> and validates the submission via Helper::validateCsrfOrDie().

XSS Escaping: Uses Helper::e() when re-populating submitted input values and rendering dynamic error messages.

Session Fixation & Multi-Tenancy: Invokes $auth->login(), which regenerates session IDs and maps agency_id to $_SESSION['agency_id'] for workspace isolation.

## Key Features Included
- Multi-Tenant Data Scoping: Every query requires $agencyId in the WHERE clause (WHERE agency_id = :agency_id), preventing cross-tenant data leaks.

- Flexible Stage Sorting: getPipelineGrouped() handles default ordering (Lead In $\rightarrow$ Closed Won) and reverse toggling.

- Archiving Controls: Includes archiveDeal(), restoreDeal(), and purgeDeal() for sales rep lifecycle tracking and admin review screens.

## Core Stack

dependency-free architecture:

1. schema.sql: Multi-tenant schema with agencies, users, companies, contacts, deals, and activities.
2. config/database.php: Secure PDO configuration file.src/Database.php: Singleton PDO wrapper class with error logging and statement execution helpers.
3. src/Auth.php: Multi-tenant session manager with agency_id scoping, role checks (admin / sales), and session fixation protection.
4. src/Helper.php: Cryptographic CSRF helpers, currency/date formatters, and global HTML escaping helper e().
5. src/Deal.php: Pipeline stage management, sorting (Lead In $\rightarrow$ Closed Won and reverse), and admin archiving/restoring/purging queries.
6. public/login.php: Secure workspace portal login controller and responsive form UI.
7. public/index.php: Main workspace view featuring stage switcher toggles, reverse pipeline ordering, and active deal totals.
8. public/archive.php: Admin-only control panel for viewing, restoring, or permanently purging archived pipeline entries.
9. public/logout.php: Clean session termination handler.
