using './main.bicep'

// Phase 0 staging parameters. This file intentionally contains no secrets:
// secure values are read from the operator's environment at validation/deploy time.
param location = readEnvironmentVariable('AZURE_REGION', 'centralus')
param namePrefix = 'kmpstage'
param releaseChannel = 'staging'

param acrName = readEnvironmentVariable('AZURE_ACR_NAME', 'kmpstageacrplaceholder')
param imageRepository = readEnvironmentVariable('KMP_STAGING_IMAGE_REPOSITORY', '<acr-login-server>/kmp')
param imageTag = readEnvironmentVariable('KMP_STAGING_IMAGE_TAG', 'staging')

param postgresAdminUser = 'kmpadmin'
param postgresAdminPassword = readEnvironmentVariable('POSTGRES_ADMIN_PASSWORD')
param postgresDatabaseName = 'kmp_staging'
param platformPostgresDatabaseName = 'kmp_staging_platform'

param securitySalt = readEnvironmentVariable('SECURITY_SALT')
param backupEncryptionKey = readEnvironmentVariable('BACKUP_ENCRYPTION_KEY')
param platformSecretsMasterKey = readEnvironmentVariable('PLATFORM_SECRETS_MASTER_KEY')

param emailSmtpHost = readEnvironmentVariable('EMAIL_SMTP_HOST', 'smtp.example.org')
param emailSmtpPort = int(readEnvironmentVariable('EMAIL_SMTP_PORT', '1025'))
param emailSmtpUsername = readEnvironmentVariable('EMAIL_SMTP_USERNAME', '')
param emailSmtpPassword = readEnvironmentVariable('EMAIL_SMTP_PASSWORD', '')
param emailSmtpTls = bool(readEnvironmentVariable('EMAIL_SMTP_TLS', 'false'))
param emailFrom = readEnvironmentVariable('EMAIL_FROM', 'staging-noreply@example.org')

param deployerPrincipalId = readEnvironmentVariable('AZURE_DEPLOYER_PRINCIPAL_ID', '')

param deployFrontDoor = true
param frontDoorSku = 'Standard_AzureFrontDoor'
param frontDoorCustomDomains = []
