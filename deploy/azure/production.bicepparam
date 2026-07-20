using './main.bicep'

// Manual production release environment. Secure values are supplied by the
// operator environment and must never be committed.
param location = 'northcentralus'
param namePrefix = 'kmpprod'
param releaseChannel = 'release'
param runtimeEnvironment = 'production'

param acrName = readEnvironmentVariable('AZURE_ACR_NAME')
param imageRepository = readEnvironmentVariable('KMP_PRODUCTION_IMAGE_REPOSITORY')
param imageTag = readEnvironmentVariable('KMP_PRODUCTION_IMAGE_TAG')

param postgresAdminUser = 'kmpadmin'
param postgresAdminPassword = readEnvironmentVariable('POSTGRES_ADMIN_PASSWORD')
param postgresDatabaseName = 'kmp_production'
param platformPostgresDatabaseName = 'kmp_production_platform'
param postgresSkuName = 'Standard_B1ms'
param postgresSkuTier = 'Burstable'
param postgresStorageSizeGB = 32
param postgresBackupRetentionDays = 35
param postgresGeoRedundantBackup = 'Enabled'
param postgresHighAvailabilityMode = 'Disabled'

param securitySalt = readEnvironmentVariable('SECURITY_SALT')
param backupEncryptionKey = readEnvironmentVariable('BACKUP_ENCRYPTION_KEY')
param platformSecretsMasterKey = readEnvironmentVariable('PLATFORM_SECRETS_MASTER_KEY')

param emailSmtpHost = readEnvironmentVariable('EMAIL_SMTP_HOST')
param emailSmtpPort = int(readEnvironmentVariable('EMAIL_SMTP_PORT', '1025'))
param emailSmtpUsername = readEnvironmentVariable('EMAIL_SMTP_USERNAME', '')
param emailSmtpPassword = readEnvironmentVariable('EMAIL_SMTP_PASSWORD', '')
param emailSmtpTls = bool(readEnvironmentVariable('EMAIL_SMTP_TLS', 'false'))
param emailFrom = readEnvironmentVariable('EMAIL_FROM')

param deployerPrincipalId = readEnvironmentVariable('AZURE_DEPLOYER_PRINCIPAL_ID')

param documentStorageSkuName = 'Standard_GRS'
param documentStorageDeleteRetentionDays = 35
param keyVaultSoftDeleteRetentionDays = 90
param keyVaultPurgeProtection = true

param enableManagedRedis = true
param managedRedisSkuName = 'Balanced_B0'
param managedRedisClusteringPolicy = 'NoCluster'
param managedRedisHighAvailability = false
param managedRedisPersistentConnections = true

param enableApplicationInsights = true
param enableFullApplicationTelemetry = true
param applicationInsightsTransport = 'otlp'
param applicationInsightsQuerySampleRate = 10
param deployTelemetryWorkbook = true

param tenancyEnabled = true
param platformAdminPortalEnabled = true
param platformAdminHosts = ''
param platformDataConsoleEnabled = false
param containerAppCustomDomains = [
  {
    name: 'poc-production.kmpdev.ansteo-kmpprod--260715230107'
    hostName: 'poc-production.kmpdev.ansteorra.org'
  }
]

param webMinReplicas = 1
param webMaxReplicas = 3

param deployFrontDoor = false
param frontDoorCustomDomains = []

param enableContainerJobs = true
param enableMigrateJob = true
param enableRestoreJob = true
param enableProvisionJob = true
param enableQueueWorkerJob = true
param queueWorkerCron = '*/3 * * * *'
param queueWorkerParallelism = 1
param queueWorkerReplicaTimeoutSeconds = 3600
param enableScheduleHourlyJob = false
param enableScheduleDailyJob = false
param enableScheduleWeeklyJob = false
param enableScheduleNightlyJob = false
