# Case-insensitive text

KMP preserves customer-entered casing while comparing selected human-facing
values without regard to case. PostgreSQL databases use `citext` for curated
identity, name, label, and email columns. MySQL continues to use its existing
case-insensitive collations.

Free-text queries use `App\KMP\CaseInsensitiveQuery`, which emits portable
`LOWER(field)` conditions. Use it for identity lookups, autocomplete, grids,
and derived or joined fields rather than PostgreSQL-only `ILIKE`.

Security and machine values remain case-sensitive, including passwords,
tokens, hashes, public IDs, file paths, hostnames, slugs, workflow keys, PHP
class or method names, and controlled status values.

Before a PostgreSQL migration converts a uniquely indexed column, it checks
for values that collide after case normalization and stops without changing
the schema when a collision exists. Azure must allowlist `CITEXT` through the
Flexible Server `azure.extensions` setting before application migrations run.
