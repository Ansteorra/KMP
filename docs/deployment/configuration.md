---
layout: default
title: "Legacy Self-Hosted Configuration Appendix"
description: "Archived configuration reference for existing single-database self-hosted KMP installations."
---

# Retired self-hosted configuration

KMP supports PostgreSQL and managed SaaS hosting for kingdoms. The former VPC
Compose template, database environment example and native database configuration
have been removed. Current application images do not support MySQL/MariaDB.

Use [Environment Setup and Variables](../8.1-environment-setup.md) and the
[managed Azure runbook](https://github.com/Ansteorra/KMP/blob/main/deploy/azure/README.md)
for the supported configuration. Keep tenant and platform databases separate;
ordinary web/queue credentials are DML-only and administrative URLs belong only
to dedicated administrative jobs.

This removal does not migrate or delete existing installations. Preserve and
verify off-host backups, and plan any data migration on a disposable clone before
upgrading. Existing logical archives retain a PostgreSQL import path; historical
single-database SQL dumps cannot reconstruct the managed platform registry and
tenant fleet. See [backup and restore](backup-restore.md) and the
[legacy retirement instructions](https://github.com/Ansteorra/KMP/blob/main/installer/README.md).
