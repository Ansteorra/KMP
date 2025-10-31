# Azure Blob Storage Configuration

This document explains how to configure the KMP application to use Azure Blob Storage for document storage.

## Overview

The `DocumentService` uses Flysystem to abstract storage operations and supports multiple storage backends:
- **Local Filesystem** (default) - Files stored on the server's local filesystem
- **Azure Blob Storage** - Files stored in Azure cloud storage

## Configuration

Storage configuration is done in `config/app_local.php`. If this file doesn't exist, create it by copying `config/app.php` and customizing it.

### Local Filesystem Configuration (Default)

```php
'Documents' => [
    'storage' => [
        'adapter' => 'local',
        'local' => [
            'path' => ROOT . DS . 'images' . DS . 'uploaded',
        ],
    ],
],
```

### Azure Blob Storage Configuration

```php
'Documents' => [
    'storage' => [
        'adapter' => 'azure',
        'azure' => [
            'connectionString' => env('AZURE_STORAGE_CONNECTION_STRING'),
            'container' => 'documents',
            'prefix' => '', // Optional: prefix all paths (e.g., 'kmp/documents/')
        ],
    ],
],
```

## Setting up Azure Blob Storage

### 1. Create Azure Storage Account

1. Log into [Azure Portal](https://portal.azure.com)
2. Navigate to **Storage Accounts**
3. Click **+ Create**
4. Fill in the required information:
   - **Subscription**: Your Azure subscription
   - **Resource Group**: Create new or use existing
   - **Storage account name**: Must be globally unique (lowercase, numbers only)
   - **Region**: Choose closest to your application
   - **Performance**: Standard (or Premium if needed)
   - **Redundancy**: Choose based on your needs (LRS, GRS, etc.)
5. Click **Review + Create** and then **Create**

### 2. Create Blob Container

1. Open your newly created Storage Account
2. In the left menu, under **Data storage**, click **Containers**
3. Click **+ Container**
4. Enter a name (e.g., `documents`)
5. Set **Public access level** to **Private (no anonymous access)**
6. Click **Create**

### 3. Get Connection String

1. In your Storage Account, go to **Security + networking** > **Access keys**
2. Click **Show** next to one of the connection strings
3. Copy the entire connection string

### 4. Configure Application

Add the connection string to your environment variables:

**Option A: Using `.env` file (Development)**

1. Copy `config/.env.example` to `config/.env`
2. Add the connection string:
   ```bash
   export AZURE_STORAGE_CONNECTION_STRING="DefaultEndpointsProtocol=https;AccountName=myaccount;AccountKey=mykey;EndpointSuffix=core.windows.net"
   ```

**Option B: Using System Environment Variables (Production)**

Set the environment variable in your hosting environment:
```bash
AZURE_STORAGE_CONNECTION_STRING="DefaultEndpointsProtocol=https;AccountName=myaccount;AccountKey=mykey;EndpointSuffix=core.windows.net"
```

**Option C: Direct Configuration (Not Recommended)**

You can set it directly in `config/app_local.php`, but this is less secure:
```php
'Documents' => [
    'storage' => [
        'adapter' => 'azure',
        'azure' => [
            'connectionString' => 'DefaultEndpointsProtocol=https;AccountName=...',
            'container' => 'documents',
        ],
    ],
],
```

### 5. Update Configuration

Edit `config/app_local.php` and add/update the Documents configuration:

```php
return [
    // ... other configuration ...
    
    'Documents' => [
        'storage' => [
            'adapter' => 'azure',
            'azure' => [
                'connectionString' => env('AZURE_STORAGE_CONNECTION_STRING'),
                'container' => 'documents',
                'prefix' => '', // Optional prefix
            ],
        ],
    ],
];
```

## Testing the Configuration

After configuring Azure Blob Storage, test it by:

1. Uploading a waiver document through the application
2. Verifying the file appears in your Azure Blob Storage container
3. Downloading the document to ensure retrieval works

You can view uploaded files in Azure Portal:
- Navigate to your Storage Account
- Click **Containers**
- Click your container name
- Files should appear here

## Troubleshooting

### Connection Errors

**Issue**: "Failed to initialize Azure Blob Storage"

**Solutions**:
- Verify your connection string is correct
- Ensure the storage account exists and is accessible
- Check that the account key hasn't been regenerated
- Verify network connectivity to Azure

### Container Not Found

**Issue**: "Container does not exist"

**Solutions**:
- Ensure the container name in configuration matches the actual container name
- Verify the container exists in the Storage Account
- Check that the connection string has access to the container

### Permission Errors

**Issue**: "Authorization failed"

**Solutions**:
- Ensure you're using a valid access key
- Check that the storage account hasn't been deleted or restricted
- Verify firewall rules on the storage account allow your application's IP

### Performance Considerations

**For Production**:
- Choose a region close to your application for lower latency
- Consider using Azure CDN for frequently accessed documents
- Monitor storage costs and optimize as needed
- Consider using lifecycle management to archive old documents

## Migration from Local to Azure

To migrate existing documents from local filesystem to Azure:

1. Configure Azure Blob Storage as documented above
2. Keep local configuration temporarily
3. Upload new documents (they will go to Azure)
4. Manually copy existing files to Azure using Azure Storage Explorer or CLI
5. Update document records in the database to reflect new storage_adapter
6. Once migration is complete, remove local configuration

## Security Best Practices

1. **Never commit connection strings** to version control
2. **Use environment variables** for sensitive configuration
3. **Rotate access keys** regularly
4. **Use private containers** (no anonymous access)
5. **Enable Azure Storage encryption** at rest (enabled by default)
6. **Monitor access logs** for unusual activity
7. **Consider using Azure Managed Identity** in production instead of connection strings

## Cost Optimization

- Monitor storage usage in Azure Portal
- Clean up unused/expired documents regularly
- Consider implementing retention policies
- Use appropriate redundancy level for your needs
- Archive old documents to cool/archive tier

## Additional Resources

- [Azure Blob Storage Documentation](https://docs.microsoft.com/en-us/azure/storage/blobs/)
- [Flysystem Azure Adapter Documentation](https://azure-oss.github.io/storage/flysystem/)
- [Azure Storage Pricing](https://azure.microsoft.com/en-us/pricing/details/storage/)
