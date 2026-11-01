#!/usr/bin/env bash
set -euo pipefail
echo 'This legacy seed-dump builder is retired. KMP supports PostgreSQL only.' >&2
echo 'Use app/tests/pg_seed_baseline.sql and the PostgreSQL dev-reset-db.sh --seed workflow.' >&2
exit 1
