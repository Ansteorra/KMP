# File Size Validation Architecture Diagram

```
┌───────────────────────────────────────────────────────────────────────────────┐
│                         PHP SERVER (CakePHP)                                  │
├───────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  ┌─────────────────────────────────────────────────────────────────┐         │
│  │  php.ini / .htaccess Configuration                              │         │
│  │  ─────────────────────────────────────────────────────────────  │         │
│  │  upload_max_filesize = 25M                                      │         │
│  │  post_max_size = 30M                                            │         │
│  └────────────────────────────────┬────────────────────────────────┘         │
│                                   │                                           │
│                                   │ PHP reads via ini_get()                   │
│                                   ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────┐         │
│  │  KmpHelper::getUploadLimits()                                   │         │
│  │  (src/View/Helper/KmpHelper.php)                                │         │
│  │  ─────────────────────────────────────────────────────────────  │         │
│  │  • Reads upload_max_filesize                                    │         │
│  │  • Reads post_max_size                                          │         │
│  │  • Parses size notation (M, G, K)                               │         │
│  │  • Returns smaller value (effective limit)                      │         │
│  │  • Formats to human-readable string                             │         │
│  └────────────────────────────────┬────────────────────────────────┘         │
│                                   │                                           │
│                                   │ Returns array                             │
│                                   ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────┐         │
│  │  Template (e.g., WaiverTypes/add.php)                           │         │
│  │  ─────────────────────────────────────────────────────────────  │         │
│  │  <?php                                                           │         │
│  │    $limits = $this->KMP->getUploadLimits();                     │         │
│  │    // Returns:                                                   │         │
│  │    // [                                                          │         │
│  │    //   'maxFileSize' => 26214400,                              │         │
│  │    //   'formatted' => '25MB',                                  │         │
│  │    //   ...                                                      │         │
│  │    // ]                                                          │         │
│  │  ?>                                                              │         │
│  └────────────────────────────────┬────────────────────────────────┘         │
│                                   │                                           │
│                                   │ Rendered in HTML                          │
│                                   ▼                                           │
└───────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ HTML sent to browser
                                    ▼
┌───────────────────────────────────────────────────────────────────────────────┐
│                         CLIENT BROWSER (JavaScript)                           │
├───────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  ┌─────────────────────────────────────────────────────────────────┐         │
│  │  HTML Rendered                                                   │         │
│  │  ─────────────────────────────────────────────────────────────  │         │
│  │  <div data-controller="file-size-validator"                     │         │
│  │       data-file-size-validator-max-size-value="26214400"        │         │
│  │       data-file-size-validator-max-size-formatted-value="25MB"> │         │
│  │                                                                  │         │
│  │    <div data-file-size-validator-target="warning"               │         │
│  │         class="d-none mb-3"></div>                              │         │
│  │                                                                  │         │
│  │    <input type="file"                                            │         │
│  │           data-file-size-validator-target="fileInput"           │         │
│  │           data-action="change->file-size-validator#validate">   │         │
│  │  </div>                                                          │         │
│  └────────────────────────────────┬────────────────────────────────┘         │
│                                   │                                           │
│                                   │ Stimulus.js connects controller           │
│                                   ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────┐         │
│  │  FileSizeValidatorController                                     │         │
│  │  (assets/js/controllers/file-size-validator-controller.js)      │         │
│  │  ─────────────────────────────────────────────────────────────  │         │
│  │  connect() {                                                     │         │
│  │    console.log('Connected', {                                    │         │
│  │      maxSize: 26214400,                                          │         │
│  │      formatted: '25MB'                                           │         │
│  │    })                                                             │         │
│  │  }                                                                │         │
│  └────────────────────────────────┬────────────────────────────────┘         │
│                                   │                                           │
│                                   │ Waits for user interaction                │
│                                   ▼                                           │
└───────────────────────────────────────────────────────────────────────────────┘
                                    │
                        ┌───────────┴───────────┐
                        │  User selects file(s) │
                        └───────────┬───────────┘
                                    │
                                    ▼
┌───────────────────────────────────────────────────────────────────────────────┐
│                         VALIDATION FLOW                                       │
├───────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  1. FILE INPUT CHANGE EVENT                                                  │
│  ────────────────────────────────────────────────────────────────────────── │
│     User: Selects "document.pdf" (30MB)                                      │
│     Browser: Fires 'change' event on input                                   │
│     Stimulus: Routes to validateFiles() action                               │
│                                                                               │
│  2. VALIDATION LOGIC                                                         │
│  ────────────────────────────────────────────────────────────────────────── │
│     Controller reads: file.size = 31457280 bytes                             │
│     Controller checks: 31457280 > 26214400 (maxSize)                         │
│     Result: INVALID ❌                                                        │
│                                                                               │
│  3. USER FEEDBACK                                                            │
│  ────────────────────────────────────────────────────────────────────────── │
│     ┌──────────────────────────────────────────────────────────┐            │
│     │  ⚠ Error Alert (Red)                                     │            │
│     │  ────────────────────────────────────────────────────────│            │
│     │  The file "document.pdf" (30MB) exceeds the maximum      │            │
│     │  upload size of 25MB.                                    │            │
│     └──────────────────────────────────────────────────────────┘            │
│                                                                               │
│     [Upload] button → DISABLED                                               │
│                                                                               │
│  4. EVENT DISPATCHED                                                         │
│  ────────────────────────────────────────────────────────────────────────── │
│     CustomEvent: 'file-size-validator:invalid'                               │
│     Detail: {                                                                │
│       files: [{ name: 'document.pdf', size: 31457280, ... }],                │
│       message: '...'                                                         │
│     }                                                                        │
│                                                                               │
└───────────────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────────────┐
│                     ALTERNATIVE: VALID FILE                                   │
├───────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  User: Selects "small.pdf" (5MB)                                             │
│  Controller: 5242880 < 26214400 ✓ Valid                                      │
│  Result:                                                                      │
│    • No error message                                                         │
│    • Submit button remains enabled                                            │
│    • Event: 'file-size-validator:valid'                                      │
│    • Form can be submitted normally                                           │
│                                                                               │
└───────────────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────────────┐
│                     MULTIPLE FILES SCENARIO                                   │
├───────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  User: Selects 3 files                                                        │
│    • file1.jpg (10MB) ✓                                                       │
│    • file2.jpg (30MB) ❌ EXCEEDS                                              │
│    • file3.jpg (8MB)  ✓                                                       │
│                                                                               │
│  Controller Logic:                                                            │
│    for each file:                                                             │
│      if file.size > maxFileSize:                                              │
│        add to invalidFiles[]                                                  │
│                                                                               │
│  Result:                                                                      │
│    ┌──────────────────────────────────────────────────────────┐              │
│    │  ⚠ Error Alert (Red)                                     │              │
│    │  ────────────────────────────────────────────────────────│              │
│    │  1 file(s) exceed the maximum upload size of 25MB:       │              │
│    │                                                           │              │
│    │  • file2.jpg (30MB)                                       │              │
│    │                                                           │              │
│    │  Please remove or replace these files before uploading.  │              │
│    └──────────────────────────────────────────────────────────┘              │
│                                                                               │
│    [Upload] button → DISABLED                                                │
│                                                                               │
└───────────────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────────────┐
│                     DATA FLOW SUMMARY                                         │
├───────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  PHP Config → KmpHelper → Template → HTML → Stimulus → Validation → User     │
│                                                                               │
│  25M        → 26214400 → data-*  → DOM  → JS Obj → Check   → Alert/Disable   │
│                                                                               │
└───────────────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────────────┐
│                     KEY COMPONENTS                                            │
├───────────────────────────────────────────────────────────────────────────────┤
│                                                                               │
│  Server Side:                                                                 │
│  ├─ PHP Configuration (php.ini)                                               │
│  ├─ KmpHelper::getUploadLimits() (PHP)                                        │
│  └─ Templates with data attributes (PHP)                                      │
│                                                                               │
│  Client Side:                                                                 │
│  ├─ Stimulus.js framework                                                     │
│  ├─ FileSizeValidatorController (JS)                                          │
│  ├─ File API (browser)                                                        │
│  └─ DOM manipulation (show alerts, disable buttons)                           │
│                                                                               │
│  Communication:                                                               │
│  ├─ Server → Client: HTML data attributes                                     │
│  ├─ Client events: file-size-validator:valid/invalid/warning                  │
│  └─ User feedback: Bootstrap alerts + button states                           │
│                                                                               │
└───────────────────────────────────────────────────────────────────────────────┘
```
