# Quickstart Guide - Gathering Waiver Tracking System

**Feature**: 001-build-out-waiver  
**Branch**: `001-build-out-waiver`  
**Estimated Complexity**: 21 story points

---

## Overview

This feature adds a **Gathering Waiver Tracking System** to KMP, enabling:
- Configuration of gathering types, waiver types, and activities
- Mobile camera capture of waiver images
- Automated image-to-PDF conversion with black and white compression
- Configurable retention policies with automated expiration
- Search and reporting capabilities

**Architecture**: Hybrid (Core entities + Waivers plugin)

---

## Prerequisites

Before starting development:

1. **System Dependencies**:
   ```bash
   # Install ImageMagick (required for PDF conversion)
   sudo apt-get install imagemagick
   sudo apt-get install php-imagick
   
   # Verify installation
   php -m | grep imagick
   ```

2. **PHP Extensions**:
   - `imagick` - Image manipulation and PDF conversion
   - `gd` - Fallback image library
   - `json` - JSON handling for retention policies

3. **Composer Packages**:
   ```bash
   cd /workspaces/KMP/app
   composer require league/flysystem-cakephp
   ```

4. **NPM Packages** (already installed):
   - `@hotwired/turbo` - Turbo Frames/Streams
   - `@hotwired/stimulus` - Interactive controllers
   - `bootstrap` - UI framework

---

## Quick Start

### 1. Set Up Branch

```bash
cd /workspaces/KMP
git checkout -b 001-build-out-waiver
```

### 2. Create Core Database Migrations

```bash
cd app
bin/cake bake migration CreateGatheringTypes
bin/cake bake migration CreateGatherings
bin/cake bake migration CreateGatheringActivities
```

Fill in migration files based on `data-model.md` schemas.

### 3. Create Plugin

```bash
bin/cake bake plugin Waivers
```

This creates `app/plugins/Waivers/` structure.

### 4. Create Plugin Migrations

```bash
bin/cake bake migration CreateWaiverTypes --plugin Waivers
bin/cake bake migration CreateGatheringActivityWaivers --plugin Waivers
bin/cake bake migration CreateGatheringWaivers --plugin Waivers
bin/cake bake migration CreateWaiverConfiguration --plugin Waivers
```

### 5. Run Migrations

```bash
bin/cake migrations migrate
bin/cake migrations migrate --plugin Waivers
```

### 6. Bake Models (CakePHP Code Generation)

**Core Models**:
```bash
bin/cake bake model GatheringTypes
bin/cake bake model Gatherings
bin/cake bake model GatheringActivities
```

**Plugin Models**:
```bash
bin/cake bake model WaiverTypes --plugin Waivers
bin/cake bake model GatheringActivityWaivers --plugin Waivers
bin/cake bake model GatheringWaivers --plugin Waivers
bin/cake bake model WaiverConfiguration --plugin Waivers
```

### 7. Bake Controllers

**Core Controllers**:
```bash
bin/cake bake controller GatheringTypes
bin/cake bake controller Gatherings
bin/cake bake controller GatheringActivities
```

**Plugin Controllers**:
```bash
bin/cake bake controller WaiverTypes --plugin Waivers
bin/cake bake controller GatheringWaivers --plugin Waivers
bin/cake bake controller WaiverConfiguration --plugin Waivers
```

### 8. Bake Templates

```bash
bin/cake bake template GatheringTypes
bin/cake bake template Gatherings
bin/cake bake template WaiverTypes --plugin Waivers
bin/cake bake template GatheringWaivers --plugin Waivers
```

Then customize templates to use Turbo Frames (see patterns below).

---

## Key Development Patterns

### Pattern 1: Turbo Frame Form

**Template** (`templates/GatheringTypes/add.php`):
```php
<turbo-frame id="gathering-type-form">
    <?= $this->Form->create($gatheringType, [
        'data-turbo-frame' => '_top', // Break out of frame on success
        'data-controller' => 'form-validation'
    ]) ?>
    <?= $this->Form->control('name') ?>
    <?= $this->Form->control('description', ['type' => 'textarea']) ?>
    <?= $this->Form->button(__('Save')) ?>
    <?= $this->Form->end() ?>
</turbo-frame>
```

**Controller** (`src/Controller/GatheringTypesController.php`):
```php
public function add()
{
    $gatheringType = $this->GatheringTypes->newEmptyEntity();
    
    if ($this->request->is('post')) {
        $gatheringType = $this->GatheringTypes->patchEntity($gatheringType, $this->request->getData());
        
        if ($this->GatheringTypes->save($gatheringType)) {
            $this->Flash->success(__('Gathering Type saved.'));
            
            if ($this->request->accepts('text/vnd.turbo-stream.html')) {
                return $this->render('turbo_stream_append', [
                    'gatheringType' => $gatheringType
                ]);
            }
            
            return $this->redirect(['action' => 'index']);
        }
        
        $this->Flash->error(__('Could not save Gathering Type.'));
    }
    
    $this->set(compact('gatheringType'));
}
```

### Pattern 2: Stimulus Controller for File Upload

**JavaScript** (`plugins/Waivers/assets/js/controllers/waiver-upload-controller.js`):
```javascript
import { Controller } from "@hotwired/stimulus"

class WaiverUploadController extends Controller {
    static targets = ["fileInput", "preview", "submitButton"]
    static values = { uploadUrl: String }
    
    selectedFiles = []
    
    handleFiles(event) {
        const files = Array.from(event.target.files)
        
        // Validate file types
        const validFiles = files.filter(file => {
            return file.type === 'image/jpeg' || file.type === 'image/png'
        })
        
        if (validFiles.length !== files.length) {
            alert('Only JPEG and PNG images are allowed')
        }
        
        this.selectedFiles = validFiles
        this.showPreviews()
        this.submitButtonTarget.disabled = (validFiles.length === 0)
    }
    
    showPreviews() {
        this.previewTarget.innerHTML = ''
        
        this.selectedFiles.forEach((file, index) => {
            const reader = new FileReader()
            reader.onload = (e) => {
                const img = document.createElement('img')
                img.src = e.target.result
                img.className = 'thumbnail'
                this.previewTarget.appendChild(img)
            }
            reader.readAsDataURL(file)
        })
    }
    
    async submitUpload(event) {
        event.preventDefault()
        
        const formData = new FormData(event.target)
        
        try {
            const response = await fetch(this.uploadUrlValue, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'text/vnd.turbo-stream.html'
                }
            })
            
            if (response.ok) {
                // Turbo will handle the stream response
                this.selectedFiles = []
                this.fileInputTarget.value = ''
                this.previewTarget.innerHTML = ''
            }
        } catch (error) {
            console.error('Upload failed:', error)
            alert('Upload failed. Please try again.')
        }
    }
}

window.Controllers["waiver-upload"] = WaiverUploadController
```

### Pattern 3: Service Layer for Business Logic

**Service** (`plugins/Waivers/src/Services/ImageToPdfConversionService.php`):
```php
<?php
declare(strict_types=1);

namespace Waivers\Services;

use Imagick;

class ImageToPdfConversionService
{
    public function convertToPdf(string $imagePath, string $outputPath): bool
    {
        try {
            $imagick = new Imagick($imagePath);
            
            // Convert to black and white
            $imagick->setImageType(Imagick::IMGTYPE_BILEVEL);
            
            // Set compression
            $imagick->setImageFormat('pdf');
            $imagick->setImageCompression(Imagick::COMPRESSION_GROUP4);
            $imagick->setImageCompressionQuality(85);
            
            // Write PDF
            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();
            
            return true;
        } catch (\Exception $e) {
            \Cake\Log\Log::error("PDF conversion failed: " . $e->getMessage());
            return false;
        }
    }
}
```

**Usage in Controller**:
```php
use Waivers\Services\ImageToPdfConversionService;

$conversionService = new ImageToPdfConversionService();
$success = $conversionService->convertToPdf($imagePath, $pdfPath);
```

### Pattern 4: Policy for Authorization

**Policy** (`plugins/Waivers/src/Policy/GatheringWaiverPolicy.php`):
```php
<?php
declare(strict_types=1);

namespace Waivers\Policy;

use App\Model\Entity\Member;
use Waivers\Model\Entity\GatheringWaiver;
use Authorization\IdentityInterface;

class GatheringWaiverPolicy
{
    public function canUpload(IdentityInterface $member, GatheringWaiver $waiver): bool
    {
        // Kingdom officers can upload
        if ($member->hasRole('Kingdom Officer')) {
            return true;
        }
        
        // Gathering stewards can upload for their gatherings
        return $waiver->gathering->created_by === $member->id;
    }
    
    public function canDelete(IdentityInterface $member, GatheringWaiver $waiver): bool
    {
        // Only compliance officers can delete
        return $member->hasRole('Compliance Officer');
    }
    
    public function canView(IdentityInterface $member, GatheringWaiver $waiver): bool
    {
        // All authorized roles can view
        return $member->hasRole(['Kingdom Officer', 'Compliance Officer', 'Gathering Steward']);
    }
}
```

---

## Testing Strategy

### 1. Unit Tests (PHPUnit)

**Test Models**:
```bash
bin/cake bake test table GatheringTypes
bin/cake bake test entity Gathering
```

**Test Services**:
```php
// tests/TestCase/Service/ImageToPdfConversionServiceTest.php
public function testConvertToPdf(): void
{
    $service = new ImageToPdfConversionService();
    $imagePath = TEST_FILES . 'sample_waiver.jpg';
    $outputPath = TMP . 'test_output.pdf';
    
    $result = $service->convertToPdf($imagePath, $outputPath);
    
    $this->assertTrue($result);
    $this->assertFileExists($outputPath);
    $this->assertLessThan(500000, filesize($outputPath)); // < 500KB
}
```

### 2. Integration Tests

**Test Controller Actions**:
```php
// tests/TestCase/Controller/GatheringWaiversControllerTest.php
public function testUpload(): void
{
    $this->session(['Auth' => $this->authMember]);
    
    $file = new UploadedFile(
        TEST_FILES . 'sample_waiver.jpg',
        'sample_waiver.jpg',
        'image/jpeg',
        UPLOAD_ERR_OK
    );
    
    $this->post('/waivers/gathering-waivers/upload', [
        'gathering_id' => 1,
        'images' => [$file]
    ]);
    
    $this->assertResponseOk();
    $this->assertResponseContains('Waiver uploaded successfully');
}
```

### 3. Run All Tests

```bash
vendor/bin/phpunit
vendor/bin/phpunit --testsuite=plugins/Waivers
```

---

## Common Commands

### Database
```bash
# Reset database (dev only)
bin/cake migrations rollback --target 0
bin/cake migrations migrate

# Seed test data
bin/cake migrations seed --seed GatheringTypesSeed
```

### Code Quality
```bash
# PHP CodeSniffer
vendor/bin/phpcs src/ plugins/Waivers/src/

# PHPStan
vendor/bin/phpstan analyse src/ plugins/Waivers/src/
```

### Assets
```bash
# Compile JavaScript/CSS
npm run dev  # Development
npm run watch  # Watch mode
npm run production  # Minified
```

---

## Key Files Reference

| File | Purpose |
|------|---------|
| `spec.md` | Complete feature specification |
| `plan.md` | Implementation plan (this was auto-generated) |
| `research.md` | Technical research and decisions |
| `data-model.md` | Database schema and entity relationships |
| `contracts/` | API endpoint specifications |
| `src/Model/Entity/Gathering.php` | Core Gathering entity |
| `plugins/Waivers/src/WaiversPlugin.php` | Plugin bootstrap |
| `plugins/Waivers/src/Services/ImageToPdfConversionService.php` | PDF conversion logic |
| `plugins/Waivers/assets/js/controllers/waiver-upload-controller.js` | File upload UI |

---

## Next Steps

1. Review constitution compliance in `plan.md`
2. Create database migrations from `data-model.md`
3. Implement Service classes (start with ImageToPdfConversionService)
4. Bake models and controllers
5. Create Stimulus controllers for mobile UI
6. Write PHPUnit tests
7. Test on mobile devices (iOS Safari, Android Chrome)

**Questions?** Refer to:
- `.specify/memory/constitution.md` - KMP architectural principles
- `.github/copilot-instructions.md` - Coding standards
- `docs/` - Full KMP documentation
