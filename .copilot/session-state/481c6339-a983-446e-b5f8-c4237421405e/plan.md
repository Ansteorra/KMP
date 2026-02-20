# Backup/Restore Feature Plan

## Problem
Single-instance KMP deployments have no UI-driven backup/restore. Users must know SQL and have direct DB access to protect their data. We need a database-agnostic, encrypted backup system usable from the admin UI with zero SQL knowledge.

## Approach
Use CakePHP ORM to export/import all application data as JSON, compress with gzip, encrypt with AES-256-GCM (mandatory). Store backups using the existing Flysystem storage adapter (local/S3/Azure). Schedule via the Queue plugin.

### Backup Format: `.kmpbackup`
- Inner structure: JSON with `{"meta": {...}, "tables": {"table_name": [rows...]}}` 
- Compressed: gzip
- Encrypted: AES-256-GCM with user-set key (derived via PBKDF2)
- File naming: `kmp-backup-YYYYMMDD-HHMMSS.kmpbackup`

## Todos

### Phase 1: Database + Service Layer
- [ ] 1. Migration: Create `backups` table
- [ ] 2. BackupService: Core export/import logic
- [ ] 3. BackupStorageService: Flysystem adapter for backup file storage

### Phase 2: Commands + Queue Task
- [ ] 4. BackupCommand: CLI backup create/restore
- [ ] 5. BackupTask: Queue task for scheduled backups

### Phase 3: Controller + UI
- [ ] 6. BackupsController + templates
- [ ] 7. Navigation: Add to Config nav group
- [ ] 8. Stimulus controller for async progress

### Phase 4: Scheduled Backups
- [ ] 9. Cron + AppSettings integration
