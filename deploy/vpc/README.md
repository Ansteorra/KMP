# Retired VPC deployment

KMP supports PostgreSQL and managed SaaS hosting for kingdoms. The former
single-installation VPC Compose template and database configuration have been
removed. Use the [managed Azure runbook](../azure/README.md) for supported
infrastructure. This change does not convert or delete an existing installation's
data; preserve verified backups and plan any migration separately.
