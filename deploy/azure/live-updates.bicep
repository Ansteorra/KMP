@description('Live gateway name, deployed independently from PHP workers.')
param name string
param location string
param managedEnvironmentId string
param registryServer string
param registryName string
param redisVaultName string
param redisSecretName string
@minLength(1)
param image string
@minLength(1)
param publicKey string
@minLength(1)
param allowedOrigins array
param redisSecretUrl string
@allowed([true])
param sharedRedisEnabled bool
@allowed([true])
param driveEnabled bool

resource identity 'Microsoft.ManagedIdentity/userAssignedIdentities@2023-01-31' = {
  name: '${name}-identity'
  location: location
}

resource registry 'Microsoft.ContainerRegistry/registries@2023-11-01-preview' existing = {
  name: registryName
}
resource vault 'Microsoft.KeyVault/vaults@2023-07-01' existing = {
  name: redisVaultName
}
resource redisSecret 'Microsoft.KeyVault/vaults/secrets@2023-07-01' existing = {
  parent: vault
  name: redisSecretName
}
resource pull 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  name: guid(registry.id, identity.id, 'live-acr-pull')
  scope: registry
  properties: {
    principalId: identity.properties.principalId
    principalType: 'ServicePrincipal'
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', '7f951dda-4ed3-4680-a7ca-43fe172d538d')
  }
}
resource readRedis 'Microsoft.Authorization/roleAssignments@2022-04-01' = {
  name: guid(redisSecret.id, identity.id, 'live-redis-reader')
  scope: redisSecret
  properties: {
    principalId: identity.properties.principalId
    principalType: 'ServicePrincipal'
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', '4633458b-17de-408a-b874-0445c86b69e6')
  }
}

resource gateway 'Microsoft.App/containerApps@2024-03-01' = {
  name: name
  location: location
  identity: { type: 'UserAssigned', userAssignedIdentities: { '${identity.id}': {} } }
  dependsOn: [pull, readRedis]
  properties: {
    managedEnvironmentId: managedEnvironmentId
    configuration: {
      activeRevisionsMode: 'Single'
      ingress: {
        external: true
        targetPort: 8080
        transport: 'http'
        allowInsecure: false
        traffic: [{ latestRevision: true, weight: 100 }]
      }
      registries: [{ server: registryServer, identity: identity.id }]
      secrets: [{ name: 'redis-url', keyVaultUrl: redisSecretUrl, identity: identity.id }]
    }
    template: {
      containers: [{
        name: 'live'
        image: image
        resources: { cpu: json('0.5'), memory: '1Gi' }
        env: [
          { name: 'REDIS_URL', secretRef: 'redis-url' }
          { name: 'KMP_LIVE_PUBLIC_KEY', value: publicKey }
          { name: 'KMP_LIVE_ALLOWED_ORIGINS', value: join(allowedOrigins, ',') }
        ]
        probes: [
          { type: 'Readiness', httpGet: { path: '/health', port: 8080 }, periodSeconds: 10 }
          { type: 'Liveness', httpGet: { path: '/livez', port: 8080 }, periodSeconds: 30 }
        ]
      }]
      scale: {
        minReplicas: 2
        maxReplicas: 4
        rules: [{ name: 'connections', http: { metadata: { concurrentRequests: '150' } } }]
      }
    }
  }
}

output url string = 'https://${gateway.properties.configuration.ingress.fqdn}/events'
output runtimeSwitchesValidated bool = sharedRedisEnabled && driveEnabled
