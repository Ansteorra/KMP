---
layout: default
---
[← Back to Table of Contents](index.md)

# 1. Introduction

## 1.1 About KMP

The Kingdom Management Portal (KMP) is a comprehensive web-based membership management system specifically designed for SCA (Society for Creative Anachronism) Kingdoms. Built on the CakePHP 5.x framework, KMP provides a robust and extensible platform that allows SCA Kingdoms to manage their membership data, activities, officers, awards, and various administrative functions.

### Key Features

- **Member Management**: Registration, profile management, and membership tracking
- **Branch Management**: Hierarchical organization of Kingdom branches (Baronies, Shires, etc.)
- **Officer Management**: Officer warrants, reporting, and roster management
- **Award Recommendations**: Processing and tracking of award recommendations
- **Activity Management**: Event registration and participation tracking
- **Role-Based Access Control**: Granular permissions system to control access to features

KMP is designed to be modular through its plugin architecture, allowing for customization and extension to meet the specific needs of different SCA Kingdoms.

## 1.2 Project Purpose

The Society for Creative Anachronism (SCA) is an international non-profit volunteer educational organization dedicated to researching and re-creating pre-17th century European history. Each SCA Kingdom requires significant administrative infrastructure to manage its members, events, officers, and awards.

The purpose of the Kingdom Management Portal is to:

1. **Centralize Member Data**: Provide a single source of truth for membership information
2. **Streamline Administrative Processes**: Automate workflows for warrants, awards, and reporting
3. **Enhance Communication**: Facilitate communication between members, officers, and administrators
4. **Ensure Data Security**: Protect sensitive member information through secure authentication and authorization (role-based access control)
5. **Support Decision Making**: Generate reports and analytics to support Kingdom leadership
6. **Maintain Historical Records**: Preserve the history of awards, offices, and activities

KMP addresses these needs through a modern web application that is accessible both to technical and non-technical users, ensuring that SCA Kingdoms can focus on their core mission rather than administrative overhead.

## 1.3 System Requirements

### Server Requirements

To run the Kingdom Management Portal, your server should meet the following requirements:

- **PHP**: Version 8.3 or higher
  - Required Extensions:
    - **Core & Basics**:
      - intl (Internationalization functions)
      - mbstring (Multibyte string handling)
      - xml, SimpleXML, xmlreader, xmlwriter (XML processing)
      - openssl, sodium (Cryptography and secure communications)
      - json (JSON parsing and generation)
    - **Database**:
      - pdo_mysql (MySQL database connectivity)
      - mysqli, mysqlnd (MySQL native driver)
      - pdo_sqlite, sqlite3 (SQLite support, needed for DebugKit)
    - **Special Requirements**:
      - yaml (YAML parsing and emission)
      - posix (UNIX system interface)
      - gd (Image processing)
      - zip, zlib (Compression and archive handling)
      - opcache (Performance optimization)
    - **Recommended Extensions**:
      - apcu (In-memory caching)
      - xdebug (For development environments only)
      - pcntl (Process control for queue workers)
      - FFI (Foreign Function Interface, for advanced integrations)

- **Database**: MySQL 5.7+ or MariaDB 10.2+

- **Web Server**:
  - Apache with mod_rewrite enabled
  - Nginx with proper URL rewrite configuration

- **Composer**: PHP Dependency Manager (version 2.0+)

- **Node.js and NPM**: For frontend asset compilation
  - Node.js 18+ recommended
  - NPM 9+ recommended

### Development Environment Requirements

For developers working on KMP, the following additional tools are recommended:

- **Git**: Version control system (latest version)
- **Docker**: For containerized development (optional but recommended)
- **PHPUnit**: For running automated tests
- **PHP_CodeSniffer**: For code style checking
- **PHPStan**: For static analysis

### Browser Support

KMP is designed to work with modern web browsers:

- Chrome (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- Edge (latest 2 versions)

Mobile browser support is also included for responsive layouts on iOS and Android devices.

### Memory and Disk Requirements

- Minimum 2GB RAM for the web server
- At least 500MB disk space for the application
- Additional disk space for database and uploaded files (varies based on usage)

---

This concludes the introduction to the Kingdom Management Portal. The following sections will dive deeper into the installation, configuration, and architecture of the system.