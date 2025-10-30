
# KingdomMangementPortal
![CodeRabbit Pull Request Reviews](https://img.shields.io/coderabbit/prs/github/jhandel/KMP?utm_source=oss&utm_medium=github&utm_campaign=jhandel%2FKMP&labelColor=171717&color=FF570A&link=https%3A%2F%2Fcoderabbit.ai&label=CodeRabbit+Reviews)

Membership management system for SCA Kingdoms.

Please review the wiki for solution details https://github.com/Ansteorra/KMP/wiki

Dev users all have the password "TestPassword"

Dev Users : 
* admin@amp.ansteorra.org - System super user
* agatha@ampdemo.com - Local MoAS
* bryce@ampdemo.com - Local Seneschal
* caroline@ampdemo.com - Regional Seneschal
* devon@ampdemo.com - Regional Armored
* eirik@ampdemo.com - Kingdom Seneschal
* garun@ampdemo.com - Kingdom Rapier
* haylee@ampdemo.com - Kingdom MoAS
* iris@ampdemo.com - Basic User
* jael@ampdemo.com - Pricipality Coronet
* kal@ampdemo.com - Local Landed Nobility with a Canton
* forest@ampdemo.com - Crown
* leonard@ampdemo.com - Local Landed Nobility with Stronghold
* mel@ampdemo.com - Local Exchequer and Kingdom Social Media

## Utility Scripts

### fix_permissions.sh
Fixes file permissions for Apache web server access. Run this if you encounter permission errors with logs, tmp, or images directories:
```bash
./fix_permissions.sh
```

### reset_dev_database.sh
Resets the development database to a clean state with seed data:
```bash
./reset_dev_database.sh
```

### load_test.sh
Runs load testing against the application:
```bash
./load_test.sh
```

### security-checker.sh
Runs security checks on the application:
```bash
./security-checker.sh
```

### create_erd.sh
Generates Entity Relationship Diagrams for the database schema:
```bash
./create_erd.sh
```

### make_amp_seed_db.sh
Creates a seed database for the application:
```bash
./make_amp_seed_db.sh
```

### merge_from_upstream.sh
Merges changes from the upstream repository:
```bash
./merge_from_upstream.sh
```
