// =============================================================================
// KMP Azure environment — infrastructure (Postgres + encrypted-backup seeding)
//
// Deploys:
//   - Log Analytics workspace             (Container Apps logs)
//   - Azure Container Registry            (Basic SKU; nightly image mirror from GHCR)
//   - User-Assigned Managed Identity      (ACR pull + Key Vault read + Blob RBAC)
//   - Azure Key Vault                     (RBAC; app secrets incl. backup encryption key)
//   - Azure Storage Account               (private document blobs via managed identity)
//   - Azure Database for PostgreSQL Flex  (B1ms, PG 16, public w/ Allow Azure services)
//   - Container Apps Environment          (Consumption)
//   - Container App: <prefix>-web         (ingress external, scale 1→3)
//   - Fixed Container Apps schedule-shape Jobs (not per tenant):
//       migrate, restore, provision, queue, sched-hourly, sched-daily,
//       sched-weekly, sched-nightly
//   - Optional Azure Front Door Standard/Premium profile in front of the web app
//
// Seeding model: the image ships /opt/kmp/seed/nightly-seed.kmpbackup (an
// encrypted dev-data snapshot produced by `deploy/azure/seed/bake-seed.sh`).
// The reset job decrypts it with BACKUP_ENCRYPTION_KEY (fetched from Key
// Vault) and restores via `bin/cake backup restore`. See deploy/azure/seed/
// for the full workflow.
// =============================================================================

@description('Azure region for all resources.')
param location string = resourceGroup().location

@minLength(3)
@maxLength(11)
@description('Short lowercase-alphanumeric prefix used to name all resources.')
param namePrefix string = 'kmpnightly'

@description('Container image reference (without tag) in the internal ACR. Typically "<acr-login-server>/kmp".')
param imageRepository string

@description('Image tag to deploy (e.g. "nightly" or "nightly-2026-04-17").')
param imageTag string = 'nightly'

@description('Release channel exposed to the application runtime.')
param releaseChannel string = 'nightly'

@description('Runtime environment exposed as KMP_ENV.')
param runtimeEnvironment string = 'nightly'

@description('Postgres admin login name.')
param postgresAdminUser string = 'kmpadmin'

@secure()
@description('Postgres admin password (also used as the application credential for nightly — no separate app role).')
param postgresAdminPassword string

@description('Postgres application database name.')
param postgresDatabaseName string = 'kmp_nightly'

@description('Postgres platform metadata database name. Platform schedules, tenants, jobs, and secret metadata live here.')
param platformPostgresDatabaseName string = 'kmp_platform'

@description('PostgreSQL Flexible Server compute SKU.')
param postgresSkuName string = 'Standard_B1ms'

@allowed([
  'Burstable'
  'GeneralPurpose'
  'MemoryOptimized'
])
@description('PostgreSQL Flexible Server compute tier.')
param postgresSkuTier string = 'Burstable'

@minValue(32)
@description('PostgreSQL storage size in GB.')
param postgresStorageSizeGB int = 32

@minValue(7)
@maxValue(35)
@description('PostgreSQL backup retention in days.')
param postgresBackupRetentionDays int = 7

@allowed([
  'Disabled'
  'Enabled'
])
@description('Enable geographically redundant PostgreSQL backups when supported by the region.')
param postgresGeoRedundantBackup string = 'Disabled'

@allowed([
  'Disabled'
  'SameZone'
  'ZoneRedundant'
])
@description('PostgreSQL high-availability mode. Burstable SKUs should use Disabled.')
param postgresHighAvailabilityMode string = 'Disabled'

@secure()
@description('CakePHP Security.salt value (generate with `openssl rand -hex 32`).')
param securitySalt string

@secure()
@description('Backup encryption key. Must match the key used to bake the nightly .kmpbackup file. Keep this in sync with deploy/azure/seed/.')
param backupEncryptionKey string

@secure()
@description('Master key used by the database-backed platform secret store. Generate with `openssl rand -hex 32`.')
param platformSecretsMasterKey string

@description('SMTP host for outbound mail (e.g. Mailpit reused from UAT).')
param emailSmtpHost string

@description('SMTP port for outbound mail.')
param emailSmtpPort int = 1025

@description('SMTP username (leave empty for unauthenticated Mailpit).')
param emailSmtpUsername string = ''

@secure()
@description('SMTP password (leave empty for unauthenticated Mailpit).')
param emailSmtpPassword string = ''

@description('Whether SMTP uses TLS.')
param emailSmtpTls bool = false

@description('From address for outgoing mail.')
param emailFrom string

@description('Object ID of the principal that should have Key Vault Secrets Officer access for initial secret population (usually the deployer).')
param deployerPrincipalId string

// =============================================================================
// Names (derived)
// =============================================================================
@description('ACR name. Must be pre-computed by the bootstrap script so that the image can be imported before the rest of the deployment runs.')
param acrName string

@description('Storage account redundancy SKU for tenant documents.')
param documentStorageSkuName string = 'Standard_LRS'

@minValue(7)
@maxValue(365)
@description('Soft-delete retention for tenant document blobs and containers.')
param documentStorageDeleteRetentionDays int = 14

@minValue(7)
@maxValue(90)
@description('Key Vault soft-delete retention.')
param keyVaultSoftDeleteRetentionDays int = 7

@description('Enable Key Vault purge protection.')
param keyVaultPurgeProtection bool = false

@description('Provision Azure Managed Redis for shared caches and sessions.')
param enableManagedRedis bool = false

@description('Azure Managed Redis SKU.')
param managedRedisSkuName string = 'Balanced_B0'

@allowed([
  'NoCluster'
])
@description('Azure Managed Redis clustering policy. KMP requires NoCluster because CakePHP uses a single-node Redis client.')
param managedRedisClusteringPolicy string = 'NoCluster'

@description('Enable Azure Managed Redis data replication.')
param managedRedisHighAvailability bool = false

@description('Reuse Redis connections across requests handled by the same PHP worker.')
param managedRedisPersistentConnections bool = false

@description('Enable host-based tenant resolution.')
param tenancyEnabled bool = false

@description('Enable the isolated platform administration portal.')
param platformAdminPortalEnabled bool = false

@description('Comma-separated platform administration hosts. When empty, the Container App default hostname is used.')
param platformAdminHosts string = ''

@description('Optional Container App custom domains. Each item must be { name: string, hostName: string } and have valid CNAME/asuid DNS records.')
param containerAppCustomDomains array = []

@description('Provision a workspace-based Application Insights component and wire it to the application.')
param enableApplicationInsights bool = false

@description('Enable request, performance, error, and sampled SQL telemetry when Application Insights is enabled.')
param enableFullApplicationTelemetry bool = false

@allowed([
  'direct'
  'otlp'
])
@description('Application Insights export transport. Use otlp with the Container Apps managed OpenTelemetry agent.')
param applicationInsightsTransport string = 'otlp'

@minValue(1)
@maxValue(100)
@description('Percentage of SQL query telemetry sent to Application Insights.')
param applicationInsightsQuerySampleRate int = 10

@description('Deploy a copy of the KMP telemetry workbook bound to this environment Application Insights component.')
param deployTelemetryWorkbook bool = false

@description('Enable the platform data console.')
param platformDataConsoleEnabled bool = false

@minValue(0)
@description('Minimum web replicas.')
param webMinReplicas int = 1

@minValue(1)
@description('Maximum web replicas.')
param webMaxReplicas int = 3

@description('Whether to provision Azure Front Door in front of the Container App.')
param deployFrontDoor bool = false

@allowed([
  'Standard_AzureFrontDoor'
  'Premium_AzureFrontDoor'
])
@description('Front Door SKU to use when deployFrontDoor is true.')
param frontDoorSku string = 'Standard_AzureFrontDoor'

@description('Optional custom domains for Front Door. Each item should be { name: string, hostName: string }. DNS/certificate validation remains an operational step.')
param frontDoorCustomDomains array = []

// =============================================================================
// Fixed schedule-shape job controls
// =============================================================================
@description('Global switch for provisioning Container Apps Jobs. Jobs are fixed schedule shapes; tenant fan-out happens in platform schedules and queue tables.')
param enableContainerJobs bool = true

@description('Enable the manual migration/canary job.')
param enableMigrateJob bool = true

@description('Enable the manual restore-from-seed job.')
param enableRestoreJob bool = true

@description('Enable the manual tenant provision job shape. Operators override args when starting it for a specific tenant.')
param enableProvisionJob bool = true

@description('Enable the unified scheduled background worker for due schedules, default and tenant queues, and platform jobs.')
param enableQueueWorkerJob bool = true

@description('Cron for the unified background worker.')
param queueWorkerCron string = '*/3 * * * *'

@minValue(1)
@maxValue(10)
@description('Maximum concurrent queue worker replicas per scheduled execution.')
param queueWorkerParallelism int = 1

@minValue(60)
@maxValue(3600)
@description('Replica timeout, in seconds, for each unified worker execution.')
param queueWorkerReplicaTimeoutSeconds int = 3600

@description('Enable the legacy hourly platform schedule dispatcher job.')
param enableScheduleHourlyJob bool = false

@description('Cron for the due platform schedule dispatcher.')
param scheduleHourlyCron string = '* * * * *'

@description('Enable the daily platform schedule dispatcher job.')
param enableScheduleDailyJob bool = false

@description('Cron for the daily platform schedule dispatcher.')
param scheduleDailyCron string = '15 7 * * *'

@description('Enable the weekly platform schedule dispatcher job.')
param enableScheduleWeeklyJob bool = false

@description('Cron for the weekly platform schedule dispatcher.')
param scheduleWeeklyCron string = '30 7 * * 1'

@description('Enable the nightly maintenance platform schedule dispatcher job.')
param enableScheduleNightlyJob bool = false

@description('Cron for the nightly maintenance platform schedule dispatcher.')
param scheduleNightlyCron string = '0 3 * * *'

@minValue(1)
@maxValue(3)
@description('Maximum concurrent replicas for each platform schedule dispatcher execution. Keep at 1 unless schedule rows are idempotent.')
param scheduleDispatcherParallelism int = 1

var suffix = uniqueString(resourceGroup().id)
var lawName = '${namePrefix}-law'
var appInsightsName = '${namePrefix}-appi'
var kvName = take('${namePrefix}-kv-${take(suffix, 6)}', 24)
var pgName = '${namePrefix}-pg-${take(suffix, 6)}'
var managedRedisName = take('${namePrefix}-redis-${toLower(managedRedisClusteringPolicy)}-${take(suffix, 6)}', 60)
var managedOpenTelemetryEnabled = enableApplicationInsights && applicationInsightsTransport == 'otlp'
var uamiName = '${namePrefix}-id'
var documentStorageName = '${namePrefix}docs${take(suffix, 6)}'
var documentContainerPrefix = 'documents'
var acaEnvName = '${namePrefix}-acaenv'
var webAppName = '${namePrefix}-web'
var migrateJobName = '${namePrefix}-migrate'
var restoreJobName = '${namePrefix}-restore'
var provisionJobName = '${namePrefix}-provision'
var queueWorkerJobName = '${namePrefix}-queue'
var scheduleHourlyJobName = '${namePrefix}-sched-hourly'
var scheduleDailyJobName = '${namePrefix}-sched-daily'
var scheduleWeeklyJobName = '${namePrefix}-sched-weekly'
var scheduleNightlyJobName = '${namePrefix}-sched-nightly'
var frontDoorProfileName = '${namePrefix}-fd'
var frontDoorEndpointName = take('${namePrefix}-fd-${take(suffix, 6)}', 46)
var frontDoorOriginGroupName = '${namePrefix}-web-og'
var frontDoorOriginName = '${namePrefix}-aca-origin'
var frontDoorRouteName = '${namePrefix}-web-route'

// =============================================================================
// Log Analytics workspace
// =============================================================================
resource law 'Microsoft.OperationalInsights/workspaces@2023-09-01' = {
  name: lawName
  location: location
  properties: {
    sku: { name: 'PerGB2018' }
    retentionInDays: 30
  }
}

resource appInsights 'Microsoft.Insights/components@2020-02-02' = if (enableApplicationInsights) {
  name: appInsightsName
  location: location
  kind: 'web'
  properties: {
    Application_Type: 'web'
    Flow_Type: 'Bluefield'
    Request_Source: 'rest'
    WorkspaceResourceId: law.id
    IngestionMode: 'LogAnalytics'
    publicNetworkAccessForIngestion: 'Enabled'
    publicNetworkAccessForQuery: 'Enabled'
  }
}

resource telemetryWorkbook 'Microsoft.Insights/workbooks@2022-04-01' = if (enableApplicationInsights && deployTelemetryWorkbook) {
  name: guid(resourceGroup().id, '${namePrefix}-telemetry-dashboard')
  location: location
  kind: 'shared'
  tags: {
    'hidden-title': 'KMP Production Telemetry Dashboard'
  }
  properties: {
    displayName: 'KMP Production Telemetry Dashboard'
    serializedData: loadTextContent('workbooks/kmp-telemetry-dashboard.json')
    version: 'Notebook/1.0'
    sourceId: appInsights.id
    category: 'performance'
  }
}

// =============================================================================
// Azure Container Registry (Basic)
// =============================================================================
resource acr 'Microsoft.ContainerRegistry/registries@2023-11-01-preview' = {
  name: acrName
  location: location
  sku: { name: 'Basic' }
  properties: {
    adminUserEnabled: false
  }
}

// =============================================================================
// User-Assigned Managed Identity (shared by web + jobs)
// =============================================================================
resource uami 'Microsoft.ManagedIdentity/userAssignedIdentities@2023-01-31' = {
  name: uamiName
  location: location
}

// AcrPull role on the ACR
resource acrPullRole 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  name: guid(acr.id, uami.id, 'acrpull')
  scope: acr
  properties: {
    principalId: uami.properties.principalId
    principalType: 'ServicePrincipal'
    // AcrPull
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', '7f951dda-4ed3-4680-a7ca-43fe172d538d')
  }
}

// =============================================================================
// Storage account for tenant document blobs.
// The app uses managed identity + RBAC instead of per-tenant storage secrets.
// Tenant provision writes tenant_config.documents.blob_container; if absent,
// runtime config derives "<documentContainerPrefix>-<tenant-slug>".
// =============================================================================
resource documentStorage 'Microsoft.Storage/storageAccounts@2023-05-01' = {
  name: documentStorageName
  location: location
  sku: {
    name: documentStorageSkuName
  }
  kind: 'StorageV2'
  properties: {
    accessTier: 'Hot'
    allowBlobPublicAccess: false
    allowSharedKeyAccess: false
    minimumTlsVersion: 'TLS1_2'
    supportsHttpsTrafficOnly: true
  }
}

resource documentBlobService 'Microsoft.Storage/storageAccounts/blobServices@2023-05-01' = {
  parent: documentStorage
  name: 'default'
  properties: {
    deleteRetentionPolicy: {
      enabled: true
      days: documentStorageDeleteRetentionDays
    }
    containerDeleteRetentionPolicy: {
      enabled: true
      days: documentStorageDeleteRetentionDays
    }
  }
}

// UAMI -> Storage Blob Data Contributor on the document storage account.
// This lets the app create/write tenant containers without account keys.
resource documentBlobContributorRole 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  name: guid(documentStorage.id, uami.id, 'blob-data-contributor')
  scope: documentStorage
  properties: {
    principalId: uami.properties.principalId
    principalType: 'ServicePrincipal'
    // Storage Blob Data Contributor
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', 'ba92f5b4-2d11-453d-a403-e96b0029c9fe')
  }
}

// =============================================================================
// Key Vault (RBAC mode) with secrets
// =============================================================================
resource kv 'Microsoft.KeyVault/vaults@2023-07-01' = {
  name: kvName
  location: location
  properties: {
    tenantId: subscription().tenantId
    sku: { family: 'A', name: 'standard' }
    enableRbacAuthorization: true
    enableSoftDelete: true
    softDeleteRetentionInDays: keyVaultSoftDeleteRetentionDays
    enablePurgeProtection: keyVaultPurgeProtection
    publicNetworkAccess: 'Enabled'
  }
}

// UAMI -> Key Vault Secrets User (read)
resource kvSecretsUserToUami 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  name: guid(kv.id, uami.id, 'secretsuser')
  scope: kv
  properties: {
    principalId: uami.properties.principalId
    principalType: 'ServicePrincipal'
    // Key Vault Secrets User
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', '4633458b-17de-408a-b874-0445c86b69e6')
  }
}

// Deployer -> Key Vault Secrets Officer (read/write, for subsequent rotations)
resource kvSecretsOfficerToDeployer 'Microsoft.Authorization/roleAssignments@2022-04-01' = if (!empty(deployerPrincipalId)) {
  name: guid(kv.id, deployerPrincipalId, 'secretsofficer')
  scope: kv
  properties: {
    principalId: deployerPrincipalId
    principalType: 'User'
    // Key Vault Secrets Officer
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', 'b86a8fe4-44ce-4948-aee5-eccb2c155cd7')
  }
}

// =============================================================================
// Azure Database for PostgreSQL — Flexible Server (PG 16)
// The baseline uses admin credentials directly (no separate app role).
// =============================================================================
resource pg 'Microsoft.DBforPostgreSQL/flexibleServers@2024-08-01' = {
  name: pgName
  location: location
  sku: {
    name: postgresSkuName
    tier: postgresSkuTier
  }
  properties: {
    version: '16'
    administratorLogin: postgresAdminUser
    administratorLoginPassword: postgresAdminPassword
    storage: {
      storageSizeGB: postgresStorageSizeGB
      autoGrow: 'Enabled'
    }
    backup: {
      backupRetentionDays: postgresBackupRetentionDays
      geoRedundantBackup: postgresGeoRedundantBackup
    }
    highAvailability: { mode: postgresHighAvailabilityMode }
    network: {
      publicNetworkAccess: 'Enabled'
    }
  }
}

// Firewall rule: allow all Azure services (0.0.0.0 - 0.0.0.0 is the Azure "special" rule)
resource pgFwAzure 'Microsoft.DBforPostgreSQL/flexibleServers/firewallRules@2024-08-01' = {
  parent: pg
  name: 'AllowAzureServices'
  properties: {
    startIpAddress: '0.0.0.0'
    endIpAddress: '0.0.0.0'
  }
}

// Application database
resource pgDb 'Microsoft.DBforPostgreSQL/flexibleServers/databases@2024-08-01' = {
  parent: pg
  name: postgresDatabaseName
  properties: {
    charset: 'UTF8'
    collation: 'en_US.utf8'
  }
}

// Platform metadata database (tenants, schedules, jobs, secret metadata).
resource pgPlatformDb 'Microsoft.DBforPostgreSQL/flexibleServers/databases@2024-08-01' = {
  parent: pg
  name: platformPostgresDatabaseName
  properties: {
    charset: 'UTF8'
    collation: 'en_US.utf8'
  }
}

// =============================================================================
// Azure Managed Redis — shared cache and session storage
// =============================================================================
resource managedRedis 'Microsoft.Cache/redisEnterprise@2025-07-01' = if (enableManagedRedis) {
  name: managedRedisName
  location: location
  sku: {
    name: managedRedisSkuName
  }
  properties: {
    encryption: {}
    highAvailability: managedRedisHighAvailability ? 'Enabled' : 'Disabled'
    minimumTlsVersion: '1.2'
    publicNetworkAccess: 'Enabled'
  }
}

resource managedRedisDatabase 'Microsoft.Cache/redisEnterprise/databases@2025-07-01' = if (enableManagedRedis) {
  parent: managedRedis
  name: 'default'
  properties: {
    accessKeysAuthentication: 'Enabled'
    clientProtocol: 'Encrypted'
    clusteringPolicy: managedRedisClusteringPolicy
    evictionPolicy: 'AllKeysLRU'
    modules: []
    port: 10000
  }
}

// =============================================================================
// Key Vault secrets (after Postgres resource so we can compose DATABASE_URL).
// DATABASE_URL is stored as a single secret so the container entrypoint can
// consume it directly via secretRef — no in-container composition needed.
// =============================================================================
var databaseUrlValue = 'postgres://${postgresAdminUser}:${postgresAdminPassword}@${pg.properties.fullyQualifiedDomainName}:5432/${postgresDatabaseName}?sslmode=require'
var platformDatabaseUrlValue = 'postgres://${postgresAdminUser}:${postgresAdminPassword}@${pg.properties.fullyQualifiedDomainName}:5432/${platformPostgresDatabaseName}?sslmode=require'
var redisUrlValue = enableManagedRedis ? 'rediss://:${managedRedisDatabase.listKeys().primaryKey}@${managedRedis.properties.hostName}:10000/0' : 'unused'

resource secretSecuritySalt 'Microsoft.KeyVault/vaults/secrets@2023-07-01' = {
  parent: kv
  name: 'security-salt'
  properties: { value: securitySalt }
}
resource secretDatabaseUrl 'Microsoft.KeyVault/vaults/secrets@2023-07-01' = {
  parent: kv
  name: 'database-url'
  properties: { value: databaseUrlValue }
}
resource secretPlatformDatabaseUrl 'Microsoft.KeyVault/vaults/secrets@2023-07-01' = {
  parent: kv
  name: 'platform-database-url'
  properties: { value: platformDatabaseUrlValue }
}
resource secretPlatformSecretsMasterKey 'Microsoft.KeyVault/vaults/secrets@2023-07-01' = {
  parent: kv
  name: 'platform-secrets-master-key'
  properties: { value: platformSecretsMasterKey }
}
resource secretPostgresAdmin 'Microsoft.KeyVault/vaults/secrets@2023-07-01' = {
  parent: kv
  name: 'postgres-admin-password'
  properties: { value: postgresAdminPassword }
}
resource secretBackupKey 'Microsoft.KeyVault/vaults/secrets@2023-07-01' = {
  parent: kv
  name: 'backup-encryption-key'
  properties: { value: backupEncryptionKey }
}
resource secretSmtpPassword 'Microsoft.KeyVault/vaults/secrets@2023-07-01' = {
  parent: kv
  name: 'email-smtp-password'
  properties: { value: empty(emailSmtpPassword) ? 'unused' : emailSmtpPassword }
}
resource secretRedisUrl 'Microsoft.KeyVault/vaults/secrets@2023-07-01' = {
  parent: kv
  name: 'redis-url'
  properties: { value: redisUrlValue }
}
resource secretAppInsightsConnectionString 'Microsoft.KeyVault/vaults/secrets@2023-07-01' = if (enableApplicationInsights) {
  parent: kv
  name: 'appinsights-connection-string'
  properties: { value: appInsights.properties.ConnectionString }
}

// =============================================================================
// Container Apps Environment
// =============================================================================
resource acaEnv 'Microsoft.App/managedEnvironments@2024-10-02-preview' = {
  name: acaEnvName
  location: location
  properties: union({
    appLogsConfiguration: {
      destination: 'log-analytics'
      logAnalyticsConfiguration: {
        customerId: law.properties.customerId
        sharedKey: law.listKeys().primarySharedKey
      }
    }
    zoneRedundant: false
  }, managedOpenTelemetryEnabled ? {
    appInsightsConfiguration: {
      connectionString: appInsights.properties.ConnectionString
    }
    openTelemetryConfiguration: {
      logsConfiguration: {
        destinations: [
          'appInsights'
        ]
      }
      tracesConfiguration: {
        destinations: [
          'appInsights'
        ]
      }
    }
  } : {})
}

resource containerAppManagedCertificate 'Microsoft.App/managedEnvironments/managedCertificates@2024-03-01' = [for domain in containerAppCustomDomains: {
  parent: acaEnv
  name: domain.name
  location: location
  properties: {
    subjectName: domain.hostName
    domainControlValidation: 'CNAME'
  }
}]

var defaultWebHost = '${webAppName}.${acaEnv.properties.defaultDomain}'
var effectivePlatformAdminHosts = empty(platformAdminHosts) ? defaultWebHost : platformAdminHosts

// =============================================================================
// Common container env for web + jobs
// =============================================================================
var directApplicationInsightsEnv = applicationInsightsTransport == 'direct' ? [
  { name: 'APPINSIGHTS_CONNECTION_STRING', secretRef: 'appinsights-connection-string' }
  { name: 'APPLICATIONINSIGHTS_CONNECTION_STRING', secretRef: 'appinsights-connection-string' }
] : []
var applicationInsightsEnv = enableApplicationInsights ? concat([
  { name: 'APPINSIGHTS_TRANSPORT', value: applicationInsightsTransport }
  { name: 'APPINSIGHTS_LOG_ENABLED', value: 'true' }
  { name: 'APPINSIGHTS_ERROR_LOG_ENABLED', value: 'true' }
  { name: 'APPINSIGHTS_QUERY_LOG_ENABLED', value: string(enableFullApplicationTelemetry) }
  { name: 'APPINSIGHTS_QUERY_SAMPLE_RATE', value: string(applicationInsightsQuerySampleRate) }
  { name: 'APPINSIGHTS_CLOUD_ROLE', value: namePrefix }
  { name: 'APPLICATIONINSIGHTS_CLOUD_ROLE_NAME', value: namePrefix }
  { name: 'PERF_REQUEST_LOG_ENABLED', value: string(enableFullApplicationTelemetry) }
  { name: 'PERF_LOG_ALL_REQUESTS', value: string(enableFullApplicationTelemetry) }
  { name: 'PERF_DB_QUERY_LOG_ENABLED', value: string(enableFullApplicationTelemetry) }
], directApplicationInsightsEnv) : []

var commonEnv = concat([
  // entrypoint.prod.sh parses DATABASE_URL to auto-detect engine + compose
  // config/app_local.php. postgres:// prefix triggers Postgres behaviour
  // (pg_isready probe, sslmode=require honoured by the PDO driver).
  { name: 'DATABASE_URL', secretRef: 'database-url' }
  { name: 'PLATFORM_DATABASE_URL', secretRef: 'platform-database-url' }
  { name: 'SECURITY_SALT', secretRef: 'security-salt' }
  { name: 'BACKUP_ENCRYPTION_KEY', secretRef: 'backup-encryption-key' }
  { name: 'KMP_DB_DRIVER', value: 'postgres' }
  { name: 'KMP_SECRETS_DRIVER', value: 'database' }
  { name: 'KMP_SECRETS_DB_MASTER_DRIVER', value: 'env' }
  { name: 'KMP_SECRETS_DB_MASTER_KEY_NAME', value: 'platform.master_kek' }
  { name: 'KMP_SECRET_PLATFORM_MASTER_KEK', secretRef: 'platform-secrets-master-key' }
  { name: 'KMP_ENV', value: runtimeEnvironment }
  { name: 'APP_NAME', value: namePrefix }
  { name: 'DEBUG', value: 'false' }
  { name: 'REQUIRE_HTTPS', value: 'true' }
  { name: 'TRUST_PROXY', value: 'true' }
  { name: 'KMP_TENANCY_ENABLED', value: string(tenancyEnabled) }
  { name: 'KMP_PLATFORM_ADMIN_PORTAL_ENABLED', value: string(platformAdminPortalEnabled) }
  { name: 'KMP_PLATFORM_DATA_CONSOLE_ENABLED', value: string(platformDataConsoleEnabled) }
  { name: 'KMP_PLATFORM_ADMIN_DETAILED_LOGIN_ERRORS', value: 'false' }
  { name: 'CACHE_ENGINE', value: enableManagedRedis ? 'redis' : 'apcu' }
  { name: 'REDIS_URL', secretRef: 'redis-url' }
  { name: 'REDIS_PERSISTENT', value: string(enableManagedRedis && managedRedisPersistentConnections) }
  { name: 'KMP_SESSION_DEFAULTS', value: enableManagedRedis ? 'cache' : 'php' }
  { name: 'KMP_SESSION_CACHE_CONFIG', value: 'default' }
  { name: 'EMAIL_DRIVER', value: 'smtp' }
  { name: 'EMAIL_SMTP_HOST', value: emailSmtpHost }
  { name: 'EMAIL_SMTP_PORT', value: string(emailSmtpPort) }
  { name: 'EMAIL_SMTP_USERNAME', value: emailSmtpUsername }
  { name: 'EMAIL_SMTP_PASSWORD', secretRef: 'email-smtp-password' }
  { name: 'EMAIL_SMTP_TLS', value: string(emailSmtpTls) }
  { name: 'EMAIL_FROM', value: emailFrom }
  { name: 'RELEASE_CHANNEL', value: releaseChannel }
  { name: 'DOCUMENT_STORAGE_ADAPTER', value: 'azure' }
  { name: 'AZURE_STORAGE_AUTH_MODE', value: 'managedIdentity' }
  { name: 'AZURE_STORAGE_ACCOUNT_NAME', value: documentStorage.name }
  { name: 'AZURE_STORAGE_CONTAINER_PREFIX', value: documentContainerPrefix }
], applicationInsightsEnv)

var webEnv = concat(commonEnv, [
  { name: 'AZURE_CLIENT_ID', value: uami.properties.clientId }
  { name: 'KMP_PLATFORM_ADMIN_HOSTS', value: effectivePlatformAdminHosts }
  { name: 'KMP_SKIP_CRON', value: 'true' }
  { name: 'KMP_SKIP_MIGRATIONS', value: 'true' }
])

// Secrets (pulled from Key Vault via UAMI)
var commonSecrets = concat([
  {
    name: 'database-url'
    keyVaultUrl: secretDatabaseUrl.properties.secretUri
    identity: uami.id
  }
  {
    name: 'platform-database-url'
    keyVaultUrl: secretPlatformDatabaseUrl.properties.secretUri
    identity: uami.id
  }
  {
    name: 'security-salt'
    keyVaultUrl: secretSecuritySalt.properties.secretUri
    identity: uami.id
  }
  {
    name: 'platform-secrets-master-key'
    keyVaultUrl: secretPlatformSecretsMasterKey.properties.secretUri
    identity: uami.id
  }
  {
    name: 'backup-encryption-key'
    keyVaultUrl: secretBackupKey.properties.secretUri
    identity: uami.id
  }
  {
    name: 'email-smtp-password'
    keyVaultUrl: secretSmtpPassword.properties.secretUri
    identity: uami.id
  }
  {
    name: 'redis-url'
    keyVaultUrl: secretRedisUrl.properties.secretUri
    identity: uami.id
  }
], enableApplicationInsights ? [
  {
    name: 'appinsights-connection-string'
    keyVaultUrl: secretAppInsightsConnectionString.properties.secretUri
    identity: uami.id
  }
] : [])

var commonRegistries = [
  {
    server: acr.properties.loginServer
    identity: uami.id
  }
]

var fullImage = '${imageRepository}:${imageTag}'

// =============================================================================
// Container App — web
// =============================================================================
resource web 'Microsoft.App/containerApps@2024-03-01' = {
  name: webAppName
  location: location
  identity: {
    type: 'UserAssigned'
    userAssignedIdentities: { '${uami.id}': {} }
  }
  properties: {
    managedEnvironmentId: acaEnv.id
    configuration: {
      activeRevisionsMode: 'Single'
      ingress: {
        external: true
        targetPort: 80
        transport: 'auto'
        allowInsecure: false
        traffic: [
          { latestRevision: true, weight: 100 }
        ]
        customDomains: [for (domain, i) in containerAppCustomDomains: {
          name: domain.hostName
          bindingType: 'SniEnabled'
          certificateId: containerAppManagedCertificate[i].id
        }]
      }
      registries: commonRegistries
      secrets: commonSecrets
    }
    template: {
      containers: [
        {
          name: 'web'
          image: fullImage
          resources: { cpu: json('0.5'), memory: '1Gi' }
          env: webEnv
          probes: [
            {
              type: 'Liveness'
              httpGet: { path: '/livez', port: 80 }
              initialDelaySeconds: 30
              periodSeconds: 60
              timeoutSeconds: 2
              failureThreshold: 3
            }
            {
              type: 'Readiness'
              httpGet: { path: '/health', port: 80 }
              initialDelaySeconds: 30
              periodSeconds: 60
              timeoutSeconds: 5
              failureThreshold: 3
            }
          ]
        }
      ]
      scale: {
        minReplicas: webMinReplicas
        maxReplicas: webMaxReplicas
        rules: [
          {
            name: 'http'
            http: { metadata: { concurrentRequests: '50' } }
          }
        ]
      }
    }
  }
  dependsOn: [
    acrPullRole
    kvSecretsUserToUami
    documentBlobContributorRole
    pgDb
    pgPlatformDb
  ]
}

// =============================================================================
// Optional Azure Front Door — safe default is disabled for existing nightly usage.
// Staging parameter files can enable this to mirror the intended production edge
// topology while keeping the Container App as the origin.
// =============================================================================
resource frontDoorProfile 'Microsoft.Cdn/profiles@2024-02-01' = if (deployFrontDoor) {
  name: frontDoorProfileName
  location: 'global'
  sku: {
    name: frontDoorSku
  }
}

resource frontDoorEndpoint 'Microsoft.Cdn/profiles/afdEndpoints@2024-02-01' = if (deployFrontDoor) {
  parent: frontDoorProfile
  name: frontDoorEndpointName
  location: 'global'
  properties: {
    enabledState: 'Enabled'
  }
}

resource frontDoorOriginGroup 'Microsoft.Cdn/profiles/originGroups@2024-02-01' = if (deployFrontDoor) {
  parent: frontDoorProfile
  name: frontDoorOriginGroupName
  properties: {
    loadBalancingSettings: {
      sampleSize: 4
      successfulSamplesRequired: 3
    }
    healthProbeSettings: {
      probePath: '/health'
      probeRequestType: 'GET'
      probeProtocol: 'Https'
      probeIntervalInSeconds: 100
    }
    sessionAffinityState: 'Disabled'
  }
}

resource frontDoorOrigin 'Microsoft.Cdn/profiles/originGroups/origins@2024-02-01' = if (deployFrontDoor) {
  parent: frontDoorOriginGroup
  name: frontDoorOriginName
  properties: {
    hostName: web.properties.configuration.ingress.fqdn
    originHostHeader: web.properties.configuration.ingress.fqdn
    httpPort: 80
    httpsPort: 443
    priority: 1
    weight: 1000
    enabledState: 'Enabled'
    enforceCertificateNameCheck: true
  }
}

resource frontDoorCustomDomain 'Microsoft.Cdn/profiles/customDomains@2024-02-01' = [for domain in frontDoorCustomDomains: if (deployFrontDoor) {
  parent: frontDoorProfile
  name: domain.name
  properties: {
    hostName: domain.hostName
    tlsSettings: {
      certificateType: 'ManagedCertificate'
      minimumTlsVersion: 'TLS12'
    }
  }
}]

resource frontDoorRoute 'Microsoft.Cdn/profiles/afdEndpoints/routes@2024-02-01' = if (deployFrontDoor) {
  parent: frontDoorEndpoint
  name: frontDoorRouteName
  properties: {
    originGroup: {
      id: frontDoorOriginGroup.id
    }
    supportedProtocols: [
      'Https'
    ]
    patternsToMatch: [
      '/*'
    ]
    forwardingProtocol: 'HttpsOnly'
    linkToDefaultDomain: 'Enabled'
    httpsRedirect: 'Enabled'
    enabledState: 'Enabled'
    customDomains: [for (domain, i) in frontDoorCustomDomains: {
      id: frontDoorCustomDomain[i].id
    }]
  }
  dependsOn: [
    frontDoorOrigin
  ]
}

// =============================================================================
// Fixed schedule-shape Container Apps Jobs
// =============================================================================
var jobEnvWorker = concat(commonEnv, [
  { name: 'KMP_SKIP_CRON', value: 'true' }
  { name: 'KMP_SKIP_MIGRATIONS', value: 'true' }
])

var jobEnvMigrate = concat(commonEnv, [
  { name: 'KMP_SKIP_CRON', value: 'true' }
  // migrate job keeps migrations enabled so entrypoint applies app migrations.
])

// Restore job: force local backup storage adapter so the restore reads the
// bundled .kmpbackup file from ${ROOT}/backups/ instead of Azure Blob.
var jobEnvRestore = concat(commonEnv, [
  { name: 'KMP_SKIP_CRON', value: 'true' }
  { name: 'KMP_SKIP_MIGRATIONS', value: 'true' }
  { name: 'DOCUMENT_STORAGE_ADAPTER', value: 'local' }
])

var manualShapeJobDefinitions = [
  {
    enabled: enableMigrateJob
    name: migrateJobName
    containerName: 'migrate'
    timeout: 1800
    retryLimit: 1
    cpu: '0.5'
    memory: '1Gi'
    env: jobEnvMigrate
    command: [ '/usr/local/bin/docker-entrypoint.sh' ]
    args: [
      '/bin/sh'
      '-lc'
      'bin/cake migrations migrate && bin/cake updateDatabase && bin/cake platform_migrate migrate'
    ]
  }
  {
    enabled: enableRestoreJob
    name: restoreJobName
    containerName: 'restore'
    timeout: 3600
    retryLimit: 0
    cpu: '1.0'
    memory: '2Gi'
    env: jobEnvRestore
    command: [ '/usr/local/bin/docker-entrypoint.sh' ]
    args: [ '/opt/kmp/reset-and-seed.sh' ]
  }
  {
    enabled: enableProvisionJob
    name: provisionJobName
    containerName: 'provision'
    timeout: 1800
    retryLimit: 0
    cpu: '0.5'
    memory: '1Gi'
    env: jobEnvWorker
    command: [ '/usr/local/bin/docker-entrypoint.sh' ]
    // Safe default: print help. Operators override args at start time for a tenant.
    args: [ 'bin/cake', 'tenant', 'provision', '--help' ]
  }
]

var scheduledShapeJobDefinitions = [
  {
    enabled: enableQueueWorkerJob
    name: queueWorkerJobName
    containerName: 'queue'
    cron: queueWorkerCron
    timeout: queueWorkerReplicaTimeoutSeconds
    retryLimit: 1
    parallelism: queueWorkerParallelism
    completionCount: 1
    cpu: '0.5'
    memory: '1Gi'
    env: jobEnvWorker
    command: [ '/usr/local/bin/docker-entrypoint.sh' ]
    args: [
      'bin/cake'
      'platform'
      'worker'
      'run'
      '--schedule-limit'
      '100'
      '--max-jobs'
      '100'
      '--max-runtime'
      '45'
      '--cycle-budget'
      '240'
      '--platform-limit'
      '1'
      '--json'
    ]
  }
  {
    enabled: enableScheduleHourlyJob
    name: scheduleHourlyJobName
    containerName: 'sched-hourly'
    cron: scheduleHourlyCron
    timeout: 900
    retryLimit: 1
    parallelism: scheduleDispatcherParallelism
    completionCount: 1
    cpu: '0.5'
    memory: '1Gi'
    env: jobEnvWorker
    command: [ '/usr/local/bin/docker-entrypoint.sh' ]
    args: [ 'bin/cake', 'platform', 'schedule', 'due' ]
  }
  {
    enabled: enableScheduleDailyJob
    name: scheduleDailyJobName
    containerName: 'sched-daily'
    cron: scheduleDailyCron
    timeout: 1200
    retryLimit: 1
    parallelism: scheduleDispatcherParallelism
    completionCount: 1
    cpu: '0.5'
    memory: '1Gi'
    env: jobEnvWorker
    command: [ '/usr/local/bin/docker-entrypoint.sh' ]
    args: [ 'bin/cake', 'platform', 'schedule', 'due' ]
  }
  {
    enabled: enableScheduleWeeklyJob
    name: scheduleWeeklyJobName
    containerName: 'sched-weekly'
    cron: scheduleWeeklyCron
    timeout: 1800
    retryLimit: 1
    parallelism: scheduleDispatcherParallelism
    completionCount: 1
    cpu: '0.5'
    memory: '1Gi'
    env: jobEnvWorker
    command: [ '/usr/local/bin/docker-entrypoint.sh' ]
    args: [ 'bin/cake', 'platform', 'schedule', 'due' ]
  }
  {
    enabled: enableScheduleNightlyJob
    name: scheduleNightlyJobName
    containerName: 'sched-nightly'
    cron: scheduleNightlyCron
    timeout: 1800
    retryLimit: 1
    parallelism: scheduleDispatcherParallelism
    completionCount: 1
    cpu: '0.5'
    memory: '1Gi'
    env: jobEnvWorker
    command: [ '/usr/local/bin/docker-entrypoint.sh' ]
    args: [ 'bin/cake', 'platform', 'schedule', 'due' ]
  }
]

resource manualShapeJobs 'Microsoft.App/jobs@2024-03-01' = [for job in manualShapeJobDefinitions: if (enableContainerJobs && job.enabled) {
  name: job.name
  location: location
  identity: {
    type: 'UserAssigned'
    userAssignedIdentities: { '${uami.id}': {} }
  }
  properties: {
    environmentId: acaEnv.id
    configuration: {
      triggerType: 'Manual'
      replicaTimeout: job.timeout
      replicaRetryLimit: job.retryLimit
      manualTriggerConfig: {
        parallelism: 1
        replicaCompletionCount: 1
      }
      registries: commonRegistries
      secrets: commonSecrets
    }
    template: {
      containers: [
        {
          name: job.containerName
          image: fullImage
          resources: { cpu: json(job.cpu), memory: job.memory }
          env: concat(job.env, [
            { name: 'AZURE_CLIENT_ID', value: uami.properties.clientId }
          ])
          command: job.command
          args: job.args
        }
      ]
    }
  }
  dependsOn: [
    acrPullRole
    kvSecretsUserToUami
    documentBlobContributorRole
    pgDb
    pgPlatformDb
  ]
}]

resource scheduledShapeJobs 'Microsoft.App/jobs@2024-03-01' = [for job in scheduledShapeJobDefinitions: if (enableContainerJobs && job.enabled) {
  name: job.name
  location: location
  identity: {
    type: 'UserAssigned'
    userAssignedIdentities: { '${uami.id}': {} }
  }
  properties: {
    environmentId: acaEnv.id
    configuration: {
      triggerType: 'Schedule'
      replicaTimeout: job.timeout
      replicaRetryLimit: job.retryLimit
      scheduleTriggerConfig: {
        cronExpression: job.cron
        parallelism: job.parallelism
        replicaCompletionCount: job.completionCount
      }
      registries: commonRegistries
      secrets: commonSecrets
    }
    template: {
      containers: [
        {
          name: job.containerName
          image: fullImage
          resources: { cpu: json(job.cpu), memory: job.memory }
          env: concat(job.env, [
            { name: 'AZURE_CLIENT_ID', value: uami.properties.clientId }
          ])
          command: job.command
          args: job.args
        }
      ]
    }
  }
  dependsOn: [
    acrPullRole
    kvSecretsUserToUami
    documentBlobContributorRole
    pgDb
    pgPlatformDb
  ]
}]

// =============================================================================
// Outputs (consumed by bootstrap + deploy workflow)
// =============================================================================
output acrLoginServer string = acr.properties.loginServer
output acrName string = acr.name
output postgresFqdn string = pg.properties.fullyQualifiedDomainName
output postgresServerName string = pg.name
output postgresAdminUser string = postgresAdminUser
output postgresDatabaseName string = postgresDatabaseName
output platformPostgresDatabaseName string = platformPostgresDatabaseName
output managedRedisName string = enableManagedRedis ? managedRedis.name : ''
output managedRedisHostName string = enableManagedRedis ? managedRedis.properties.hostName : ''
output keyVaultName string = kv.name
output documentStorageAccountName string = documentStorage.name
output documentStorageContainerPrefix string = documentContainerPrefix
output uamiId string = uami.id
output uamiPrincipalId string = uami.properties.principalId
output webAppFqdn string = web.properties.configuration.ingress.fqdn
output webAppName string = web.name
output migrateJobName string = migrateJobName
output restoreJobName string = restoreJobName
output provisionJobName string = provisionJobName
output queueJobName string = queueWorkerJobName
output queueWorkerJobName string = queueWorkerJobName
output scheduleHourlyJobName string = scheduleHourlyJobName
output scheduleDailyJobName string = scheduleDailyJobName
output scheduleWeeklyJobName string = scheduleWeeklyJobName
output scheduleNightlyJobName string = scheduleNightlyJobName
// Backward-compatible aliases consumed by existing scripts/workflows.
output syncJobName string = scheduleDailyJobName
output resetJobName string = restoreJobName
output acaEnvName string = acaEnv.name
output appInsightsName string = enableApplicationInsights ? appInsights.name : ''
output telemetryWorkbookId string = enableApplicationInsights && deployTelemetryWorkbook ? telemetryWorkbook.id : ''
output frontDoorProfileName string = deployFrontDoor ? frontDoorProfile.name : ''
output frontDoorEndpointHostName string = deployFrontDoor ? frontDoorEndpoint.properties.hostName : ''
