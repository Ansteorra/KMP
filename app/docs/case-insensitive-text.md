# Case-insensitive text

KMP preserves customer-entered casing while comparing selected human-facing
values without regard to case. PostgreSQL databases use `citext` for curated
identity, name, label, and email columns. MySQL continues to use its existing
case-insensitive collations.

Free-text queries use `App\KMP\CaseInsensitiveQuery`, which emits portable
`LOWER(field)` conditions. Use it for identity lookups, autocomplete, grids,
and derived or joined fields rather than PostgreSQL-only `ILIKE`.

Fields named `sca_name` or ending in `_sca_name` also compare without
diacritics. PostgreSQL queries fold stored values through the `unaccent`
extension and applies the same `UNACCENT(LOWER(...))` expression to the bound
search term. Keeping both operands inside PostgreSQL avoids differences between
database rules and PHP Unicode transliteration. MySQL continues to use its
accent-insensitive collation.

Security and machine values remain case-sensitive, including passwords,
tokens, hashes, public IDs, file paths, hostnames, slugs, workflow keys, PHP
class or method names, and controlled status values.

Before a PostgreSQL migration converts a uniquely indexed column, it checks
for values that collide after case normalization and stops without changing
the schema when a collision exists. Azure must allowlist `CITEXT` and
`UNACCENT` through the Flexible Server `azure.extensions` setting before
application migrations run. The shared Azure deployment then migrates the
default application database, platform database, and every active or suspended
tenant database before web cutover. Tenant databases already current across
all app and plugin migration histories are verified and skipped without a
backup. Pending tenants use the standard recovery marker and backup flow;
backup keys are reconciled first, and the deployment fails on the first tenant
error.
