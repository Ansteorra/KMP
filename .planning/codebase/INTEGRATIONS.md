# External Integrations

**Analysis Date:** 2026-05-23

## Database

**Engine:** MariaDB 11 (Docker dev), MariaDB 10 (CI), PostgreSQL 16 (CI cross-DB check)

**Connection:** CakePHP ORM via `Cake\Database\Driver\Mysql`

**Environment Variables:**
- `DB_HOST` / `MYSQL_HOST` — database hostname (default: `localhost`)
- `DB_PORT` / `MYSQL_PORT` — port (default: `3306`)
- `DB_USERNAME` / `MYSQL_USERNAME` — database user
- `DB_PASSWORD` / `MYSQL_PASSWORD` — database password
- `DB_DATABASE` / `MYSQL_DB_NAME` — database name
- `DATABASE_URL` — optional full DSN URL (overrides individual vars)
- `DATABASE_TEST_URL` — optional test database DSN

**Config location:** `app/config/app.php` (`Datasources.default` and `Datasources.test`)

**Migrations:** `app/config/Migrations/`, `app/plugins/*/config/Migrations/`

---

## Email

**Development:** Mailpit — intercepts all outbound SMTP during development
- SMTP: `mailpit:1025` (inside Docker), exposed at `localhost:1025`
- Web UI: `http://localhost:8025`
- No auth required in dev

**Production Transport Options:**

**1. SMTP (default)**
- `app/config/app.php` → `EmailTransport.default` (className: `Smtp`)
- Environment variables:
  - `EMAIL_TRANSPORT_DEFAULT_URL` — full transport URL (overrides all below)
  - `EMAIL_SMTP_HOST` — SMTP hostname
  - `EMAIL_SMTP_PORT` — SMTP port
  - `EMAIL_SMTP_USERNAME` — SMTP username
  - `EMAIL_SMTP_PASSWORD` — SMTP password
- TLS disabled by default; enable per-transport if needed

**2. Azure Communication Services**
- Custom transport: `app/src/Mailer/Transport/AzureCommunicationTransport.php`
- Uses HMAC-SHA256 REST API (not SMTP)
- API Version: `2023-03-31`
- Environment variable: `AZURE_STORAGE_CONNECTION_STRING` (same conn string pattern)
- Inherits from `app/src/Mailer/Transport/ApiTransport.php`

**Email Queue:** Emails are dispatched via `queueMail()` through the `Queue` plugin (database-backed queue at `app/plugins/Queue/`). Dates must be pre-formatted using `TimezoneHelper` before passing to mailers — never pass `DateTime` objects to mailer methods.

**Sender address config:** `Email.SiteAdminSignature` app setting

---

## Authentication

**Web (Session-based):**
- Plugin: `cakephp/authentication ^3.0`
- Authenticators (in order):
  1. Session Authenticator — fastest, checks existing PHP session
  2. Form Authenticator — processes `POST /members/login`, field: `email_address`
- Custom identifier: `KMPBruteForcePassword` — wraps ORM resolver with brute-force protection
- Password hashing: Fallback hasher supporting bcrypt (preferred) and legacy MD5 (migrated on login)
- Session config: 30-minute timeout, `SameSite=Strict`, `HttpOnly`, `Secure` cookies
- Config: `app/src/Application.php` → `getAuthenticationService()`

**API (Bearer Token):**
- Routes under `/api/` bypass session auth entirely
- Custom authenticator: `ServicePrincipal`
  - Reads `Authorization: Bearer <token>` header
  - Also accepts `X-API-Key` header or `?api_key=` query param
- No redirects on API auth failure (returns HTTP error)

**Quick-Login PIN (Mobile):**
- Device-based PIN authentication for mobile member cards
- Upserts by `device_id`, intentionally reassigning device ownership to current authenticated member
- Handled in `app/assets/js/controllers/mobile-pin-gate-controller.js`

**Authorization:**
- Plugin: `cakephp/authorization ^3.1`
- Policy classes: `app/src/Policy/`, `app/plugins/*/src/Policy/`
- Controllers call `$this->Authorization->authorizeModel(...)`
- Base policy: `app/src/Policy/BasePolicy.php` (do not add native type hints to overridable methods)

---

## File Storage

**Abstraction:** `league/flysystem ^3.0` — unified filesystem interface

**Three adapter options** (selected via `Documents.storage.adapter` config key in `app/config/app.php`):

**1. Local (default)**
- Path: `ROOT/images/uploaded` (inside container: `/var/www/html/images/uploaded`)
- No additional env vars required

**2. Azure Blob Storage**
- Adapter: `azure-oss/storage-blob-flysystem ^1.2`
- Container: `documents` (configurable)
- Environment variable: `AZURE_STORAGE_CONNECTION_STRING`
- Format: `DefaultEndpointsProtocol=https;AccountName=...;AccountKey=...;EndpointSuffix=core.windows.net`
- Service: `app/src/Services/DocumentService.php`

**3. AWS S3 (or S3-compatible)**
- Built-in Flysystem S3 adapter
- Environment variables:
  - `AWS_S3_BUCKET` — bucket name
  - `AWS_DEFAULT_REGION` — region (default: `us-east-1`)
  - `AWS_ACCESS_KEY_ID` — optional (use instance role if omitted)
  - `AWS_SECRET_ACCESS_KEY` — optional
  - `AWS_SESSION_TOKEN` — optional
  - `AWS_S3_PREFIX` — optional key prefix
  - `AWS_S3_ENDPOINT` — optional custom endpoint (MinIO etc.)
  - `AWS_S3_USE_PATH_STYLE_ENDPOINT` — boolean for path-style addressing

**Max file size:** 50 MB (configurable in `app/config/app.php` → `Documents.maxFileSize`)

**Backup Storage:** `app/src/Services/BackupStorageService.php` — uses same adapter stack

---

## Image Processing

**Server-side:**
- `admad/cakephp-glide ^6.0` — on-the-fly image resizing/manipulation (profile photos)
- `ext-gd` — PHP GD extension for image operations
- `app/src/Services/ImageToPdfConversionService.php` — converts images to PDF

**Client-side:**
- `face-api.js ^0.22.2` — face detection for profile photo validation (must stay at this version)
- `@techstark/opencv-js ^4.12.0` — computer vision support
- Controller: `app/assets/js/controllers/face-photo-validator-controller.js`

---

## PDF Processing

**Generation:**
- `friendsofcake/cakepdf ^5.0` — CakePHP PDF view
- `setasign/fpdf ^1.8` + `setasign/fpdi ^2.6.4` — low-level PDF creation and manipulation
- `app/src/Services/PdfProcessingService.php`

**Parsing:**
- `smalot/pdfparser ^2.12.3` — reading/extracting text from PDFs
- `pdfjs-dist ^5.4.624` — client-side PDF rendering in browser

---

## GitHub API (Issue Submitter)

**Plugin:** `app/plugins/GitHubIssueSubmitter/`
**Purpose:** Anonymous in-app feedback → creates GitHub Issues
**Endpoint:** `POST https://api.github.com/repos/{owner}/{repo}/issues`
**Auth:** Personal access token stored in app settings (`KMP.GitHub.Token`)
**Config keys (app settings):**
- `KMP.GitHub.Owner` — repository owner
- `KMP.GitHub.Project` — repository name
- `KMP.GitHub.Token` — API token
**Controller:** `app/plugins/GitHubIssueSubmitter/src/Controller/IssuesController.php`
**Anonymously accessible:** Yes — `submit` action skips authentication

---

## Caching Layer

**APCu (default):** Shared-memory in-process cache, no external dependency
- Enabled in CLI via `apc.enable_cli=1`

**Redis (optional):**
- Activated via `CACHE_ENGINE=redis` environment variable
- Connection: `REDIS_URL` (full URL) or `REDIS_PASSWORD`
- Falls back to APCu if Redis extension not loaded or URL not set
- Config: `app/config/app.php` → `Cache` section

---

## Email Queue (Background Jobs)

**Plugin:** `app/plugins/Queue/` (database-backed job queue)
**Config:** `app/config/app_queue.php`
- Worker timeout: 1800 seconds
- Max workers: 1
- Sleep time between polls: 10 seconds
- Cleanup timeout: 604800 seconds (7 days)

---

## Calendar Export

**Service:** `app/src/Services/ICalendarService.php`
**Standard:** RFC 5545 (iCalendar)
**Output:** `.ics` files — downloadable or as a subscription feed
**No external dependency** — generated in pure PHP

---

## QR Code Generation

**Library:** `qrcode ^1.5.4` (npm)
**Usage:** Member mobile card display (client-side generation)
**Controller:** `app/assets/js/controllers/member-mobile-card-pwa-controller.js`

---

## Markdown Rendering

**Server-side:** `erusev/parsedown ^1.7`
- Helper: `app/src/View/Helper/MarkdownHelper.php`
- Used in: `app/src/Services/EmailTemplateRendererService.php`

**Client-side:** `easymde ^2.20.0` — WYSIWYG markdown editor (textarea enhancement)

---

## Deployment Targets

**Supported providers** (via `DEPLOYMENT_PROVIDER` / `KMP_DEPLOY_PROVIDER` env var):
- `docker` (default) — Docker Compose or standalone container
- `railway` — Railway.app
- `azure` — Azure Container Apps / App Service
- `aws` — AWS ECS / App Runner
- `fly` — Fly.io
- `vpc` — Self-hosted VPC
- `shared` — Shared hosting

**Container registry:** `ghcr.io/ansteorra/kmp` (GitHub Container Registry)

---

## Security Configuration

**CSRF:** Enabled for web routes; skipped for `/api/` routes (Bearer token auth instead)
**Session:** `SameSite=Strict`, `HttpOnly`, `Secure`, strict mode enabled
**Encryption salt:** `SECURITY_SALT` environment variable (32+ chars, required)
**Public endpoints (no auth required):**
- `Members/emailTaken` — used by registration form for email uniqueness validation
- `Members/PublicProfile` — public member profile (response must keep `data.external_links`)
- `GitHubIssueSubmitter/Issues/submit` — anonymous feedback

---

*Integration audit: 2026-05-23*
