# Case-insensitive text

KMP preserves entered casing while comparing selected human-facing values without regard to
case. PostgreSQL tenant and platform migrations use `citext` for curated identity, name,
label, email, lifecycle, and descriptive columns. They do not make every text field
case-insensitive.

For free-text and joined-field predicates, use `App\KMP\CaseInsensitiveQuery` instead of
hand-written `LOWER()`, `ILIKE`, or PHP-side normalization. It provides `equals`,
`notEquals`, `contains`, `startsWith`, and `endsWith` conditions and keeps the legacy database
compatibility path in one place.

Fields named `sca_name` or ending in `_sca_name` are also diacritic-insensitive. PostgreSQL
uses `UNACCENT(LOWER(...))` for both the trusted field expression and bound value. The active
`default` connection determines which database expression is used, so tenant context must be
established before building the query.

## Values that remain exact

Passwords, tokens, hashes, public IDs, object keys and paths, workflow keys, PHP identifiers,
and controlled machine values remain case-sensitive unless their owning contract explicitly
says otherwise. Do not apply human-text normalization to credentials or opaque identifiers.

Hostnames are different: DNS host matching is inherently case-insensitive, and
`TenantHostResolver` normalizes incoming hosts to lowercase and removes a trailing dot. Store
and compare canonical hostnames through that resolver rather than treating host casing as a
security boundary.

## Migration rules

- Add `citext` and `unaccent` only through migrations; managed PostgreSQL must allow both
  extensions before tenant migrations run.
- Before changing a uniquely indexed column, detect values that collide after normalization
  and fail without altering the schema.
- Put tenant-domain changes in core or plugin tenant migrations and platform-registry changes
  in `config/PlatformMigrations`.
- Fleet releases must run the normal platform and tenant migration catalog across active and
  intentionally included suspended tenants. Do not patch one tenant manually.
- Add equality, substring, Unicode/diacritic, duplicate, and migration-collision tests for any
  newly normalized field.

Source references: `src/KMP/CaseInsensitiveQuery.php`,
`config/Migrations/20260721120000_EnableCaseInsensitiveHumanText.php`,
`config/Migrations/20260721120500_ExpandCaseInsensitiveHumanText.php`, and
`config/PlatformMigrations/20260721121100_EnableCaseInsensitivePlatformText.php`.
