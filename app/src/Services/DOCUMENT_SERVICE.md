# DocumentService contract

`App\Services\DocumentService` owns document persistence and storage-adapter access. It does
not own authorization, retention policy, or the parent entity's transaction. Controllers and
domain services must authorize the owning record before asking it to read, update, or delete a
document.

For the broader model and deployment configuration, see
[`../../../docs/4.7-document-management-system.md`](../../../docs/4.7-document-management-system.md)
and [`../../../docs/deployment/configuration.md`](../../../docs/deployment/configuration.md).

## Data and tenant boundary

A tenant document consists of a tenant-database `documents` row plus an object in local,
Azure, or legacy S3 storage. The row records `entity_type`, `entity_id`, uploader, original and
stored names, relative `file_path`, MIME type, size, SHA-256 checksum, metadata, and the
`storage_adapter` used at write time. Reads use the row's adapter, allowing older objects to
remain readable after an adapter change.

The table is resolved from the active `default` connection. Construct the service only after
`TenantConnectionManager` has entered the intended tenant. Document IDs and owning entity IDs
are tenant-local and must never be used to select a tenant.

For managed Azure storage, `TenantDocumentStorageConfigResolver` selects:

1. the tenant's explicit `tenant_config.documents.blob_container`/`container`, if present;
2. otherwise `<AZURE_STORAGE_CONTAINER_PREFIX>-<tenant-slug>`; and
3. an optional tenant `blob_prefix`/`prefix` inside that container.

Container names are normalized and validated. This is logical object separation in a shared
storage account, not proof of per-tenant Azure RBAC. The database boundary, resolver, controller
policy, and storage configuration all remain part of the isolation model. Local and legacy S3
adapters use their configured shared root/bucket and rely on tenant-local rows plus application
authorization; managed deployments should use the Azure tenant resolver.

## Configuration behavior

`Documents.storage.adapter` selects `local`, `azure`, or `s3` in `config/app.php`.

- Local storage uses `Documents.storage.local.path`.
- Azure supports managed identity (preferred in Container Apps) or a connection string.
- S3 is a compatibility path and requires the optional Flysystem S3 dependencies.
- The adapter stored on each document controls later retrieval/deletion.

Azure reads never create a container. A write calls the write-readiness path and may create the
resolved container. This keeps an authorized read from provisioning infrastructure. If Azure
initialization fails, the current service logs the error and falls back to local storage;
operators of a managed environment should alert on that condition because container-local
storage is not a durable substitute.

## Public methods

| Method | Contract |
| --- | --- |
| `createDocument(...)` | Validate upload status, extension allowlist and configured size; compute checksum; store the object; save the tenant row; optionally copy a PDF preview |
| `getDocumentDownloadResponse(...)` | Return an attachment response using the row's adapter, or `null` when unavailable |
| `getDocumentInlineResponse(...)` | Return inline content with a sanitized filename, or `null` |
| `documentPreviewExists(...)` / `getDocumentPreviewResponse(...)` | Check/read the optional `_preview.jpg` beside a PDF |
| `getImageThumbnailInlineResponse(...)` | Read or lazily generate a bounded JPEG thumbnail after caller authorization |
| `getImageThumbnailPath(...)` / `getImageThumbnailEtag(...)` | Produce deterministic, versioned derivative identifiers |
| `updateDocumentEntityId(...)` | Attach a previously created row to its persisted parent |
| `deleteDocument(...)` | Delete derivatives and original object, then remove the row, returning `ServiceResult` |

Remote reads currently load the object into memory; do not describe them as streaming or use
this service for unbounded files. `Documents.maxFileSize` bounds normal uploads. Image
thumbnailing also caps source bytes and pixels and returns the original image when safe
thumbnail generation is unavailable.

## Upload pattern

Validate the domain-specific MIME/content first, authorize the parent action, and pass the
server-verified MIME type when available:

```php
$result = $documentService->createDocument(
    file: $upload,
    entityType: 'Waivers.WaiverTypes',
    entityId: (int)$waiverType->id,
    uploadedBy: (int)$identity->id,
    metadata: ['type' => 'waiver_template'],
    subDirectory: 'waiver-templates',
    allowedExtensions: ['pdf'],
    previewTempPath: $previewPath,
    verifiedMimeType: 'application/pdf',
);

if (!$result->success) {
    return $result;
}

$documentId = (int)$result->data;
```

The extension check inside `DocumentService` is not content verification. File-owning services
such as Waivers and member profile/registration flows must keep their MIME, image/PDF, size,
and domain validation before this call.

If the parent ID is unavailable, `entityId: 0` plus `updateDocumentEntityId()` is supported.
The caller must compensate by deleting the document if the parent save or attachment fails;
`DocumentService` cannot make a remote object write and an unrelated parent transaction
atomic.

## Read and delete pattern

1. Resolve the owner through a tenant-scoped query.
2. Authorize the owner/action with the relevant policy.
3. Verify the associated document belongs to that owner and expected `entity_type`.
4. Pass the loaded `Document` entity to the response method.
5. Return a controlled not-found/error response when the service returns `null`; do not expose
   storage paths or exception text.

For deletion, authorize and lock/transaction-wrap the domain mutation as needed before calling
`deleteDocument()`. The service logs storage failures; callers should not report a successful
domain deletion when required object cleanup failed.

## Security invariants

- Never accept `entity_type`, `entity_id`, adapter, container, prefix, or path as proof of
  authorization.
- Keep stored paths relative and let the service sanitize them. Local reads also verify the
  resolved path remains under the configured base directory.
- Strip control characters from response filenames and use stored/verified MIME deliberately.
- Do not log file contents, credentials, signed URLs, sensitive metadata, or raw storage errors
  to the user.
- Do not create remote containers or other infrastructure during a read.
- Derived previews and thumbnails are private objects reached only through authorized app
  endpoints, not public blob URLs.
- Cache keys and URLs for document variants must retain tenant context.

## Verification

At minimum, cover:

- accepted and rejected extension/MIME/size cases and upload error codes;
- object cleanup when the document row cannot be saved;
- authorized and denied owner reads at the controller/policy layer;
- adapter recorded at write and honored after configuration changes;
- tenant-specific Azure container/prefix resolution, invalid names, and two-tenant separation;
- read paths never provisioning a container and write paths using the intended tenant;
- traversal and response-filename handling;
- preview and thumbnail fallback, size/pixel bounds, deterministic path/ETag, and cleanup;
- unavailable storage and delete failure behavior.

Primary tests live in `tests/TestCase/Services/DocumentServiceTest.php`,
`tests/TestCase/Services/Storage/TenantDocumentStorageConfigResolverTest.php`, member controller
and profile/registration service tests, and Waivers plugin upload tests.
