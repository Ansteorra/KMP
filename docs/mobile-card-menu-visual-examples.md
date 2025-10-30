# Mobile Card Menu System - Visual Examples

## FAB Menu States

### Closed State
```
┌─────────────────────────────┐
│                             │
│  Member Mobile Card         │
│  ┌───────────────────────┐  │
│  │                       │  │
│  │   [Member Info]       │  │
│  │   Name: John Doe      │  │
│  │   Branch: Local       │  │
│  │                       │  │
│  └───────────────────────┘  │
│                             │
│  [Authorization Cards]      │
│                             │
│                             │
│                      (●●●)  │ ← FAB Button
│                             │
└─────────────────────────────┘
```

### Open State
```
┌─────────────────────────────┐
│                             │
│  Member Mobile Card         │
│  ┌───────────────────────┐  │
│  │                       │  │
│  │   [Member Info]       │  │
│  │                       │  │
│  └───────────────────────┘  │
│                             │
│  [Authorization Cards]      │
│                             │
│  ┌─────────────────────────┤
│  │ ✓ Request Authorization │ ← Green
│  ├─────────────────────────┤
│  │ ○ Approve Authorizations│ ← Blue w/ badge
│  ├─────────────────────────┤
│  │ ▢ Submit Waiver         │ ← Light blue
│  └─────────────────────────┘
│                             │
│                      (⋮⋮⋮)  │ ← FAB (rotated)
│                             │
└─────────────────────────────┘
```

## Menu Item Examples

### Standard Menu Item
```html
┌───────────────────────────────────┐
│ 📄 Submit Waiver              →   │
└───────────────────────────────────┘
  ↑      ↑                      ↑
 Icon  Label                 Arrow
```

### Menu Item with Badge
```html
┌───────────────────────────────────┐
│ ✓ Approve Authorizations  (5) →   │
└───────────────────────────────────┘
  ↑      ↑                   ↑   ↑
 Icon  Label              Badge Arrow
```

## Color Coding

Menu items use Bootstrap colors to indicate action type:

- **Green (success)** - Create/Request actions
  - "Request Authorization"
  - "Submit New Waiver"
  
- **Blue (primary)** - Main actions
  - "Approve Authorizations"
  - "View My Profile"
  
- **Light Blue (info)** - Information/Documentation
  - "Submit Waiver"
  - "View Guidelines"
  
- **Orange (warning)** - Attention needed
  - "Pending Reviews (3)"
  - "Expiring Soon"
  
- **Red (danger)** - Urgent actions
  - "Overdue Items (2)"
  - "Expired Authorization"

## Interaction Flow

```
User View:                          System Response:

1. View Mobile Card
   [Member Card]
                  (●●●) ←────────  FAB visible at bottom-right


2. Tap FAB
                  (⋮⋮⋮) ←────────  FAB rotates 90°
                  [Menu slides up]
                  ┌──────────────┐
                  │ Item 1       │
                  │ Item 2       │
                  │ Item 3       │
                  └──────────────┘


3. Tap Menu Item
   Navigate to target ──────────→  Menu closes
                  (●●●)            FAB rotates back
                                   Page navigates
```

## Responsive Behavior

### Mobile Portrait (< 576px)
```
┌──────────────┐
│              │
│   [Card]     │
│              │
│              │
│              │
│      [Menu]  │
│              │
│       (FAB)  │
└──────────────┘
```

### Mobile Landscape (576px - 768px)
```
┌──────────────────────────┐
│                          │
│   [Card]         [Menu]  │
│                          │
│                   (FAB)  │
└──────────────────────────┘
```

### Tablet (> 768px)
```
┌────────────────────────────────┐
│                                │
│   [Card]               [Menu]  │
│                                │
│                         (FAB)  │
└────────────────────────────────┘
```

## Animation Sequence

### Opening
```
Frame 1: (●●●)  Menu hidden
         ↓
Frame 2: (●●⋮)  FAB starts rotating
         ↓
Frame 3: (⋮⋮⋮)  FAB rotated, menu starts sliding
         ↓      [▁▁▁]
         ↓      
Frame 4: (⋮⋮⋮)  Menu fully visible
              ┌──────┐
              │ Item │
              └──────┘
```

### Closing
```
Frame 1: (⋮⋮⋮)  Menu visible
              ┌──────┐
              │ Item │
              └──────┘
         ↓
Frame 2: (⋮●●)  Menu starts sliding down
              [▔▔▔]
         ↓
Frame 3: (●●●)  FAB rotated back, menu hidden
```

## Plugin Configuration Visual

```php
// Activities Plugin adds 2 items
$cells[] = [
    'label' => 'Request Authorization',
    'icon' => 'bi-file-earmark-check',  // ✓ icon
    'color' => 'success',                 // Green
    'order' => 10                         // First
];

$cells[] = [
    'label' => 'Approve Authorizations',
    'icon' => 'bi-check-circle',         // ○ icon
    'color' => 'primary',                 // Blue
    'badge' => 5,                         // Shows (5)
    'order' => 20                         // Second
];

// Waivers Plugin adds 1 item
$cells[] = [
    'label' => 'Submit Waiver',
    'icon' => 'bi-file-earmark-text',    // ▢ icon
    'color' => 'info',                    // Light blue
    'order' => 30                         // Third
];
```

Results in this menu order:
```
┌─────────────────────────────┐
│ ✓ Request Authorization  → │ ← Activities (order 10, green)
├─────────────────────────────┤
│ ○ Approve Authorizations → │ ← Activities (order 20, blue)
├─────────────────────────────┤
│ ▢ Submit Waiver         → │ ← Waivers (order 30, info)
└─────────────────────────────┘
```

## CSS Structure

```
.mobile-menu-fab-container          ← Fixed positioning
    ├─ .mobile-menu-fab             ← Circular FAB button
    │   └─ .bi (icon)               ← Three-dots icon
    └─ .mobile-menu-items           ← Menu panel
        └─ .mobile-menu-item        ← Individual button
            ├─ span                 ← Icon + Label
            │   ├─ .bi (icon)       ← Item icon
            │   └─ span             ← Item label
            └─ .badge               ← Optional notification badge
```

## Bootstrap Icon Examples

Common icons used in mobile menu:

- `bi-file-earmark-check` → ✓ (checkmark on document)
- `bi-check-circle` → ○ (circle with check)
- `bi-file-earmark-text` → ▢ (document with lines)
- `bi-plus-circle` → ⊕ (plus in circle)
- `bi-person-badge` → 👤 (person with badge)
- `bi-calendar-event` → 📅 (calendar)
- `bi-shield-check` → 🛡 (shield with check)
- `bi-three-dots` → ⋮ (vertical dots)

## Example: Full Mobile Card with Menu

```
╔═══════════════════════════════╗
║    Kingdom Mobile Card        ║
╠═══════════════════════════════╣
║                               ║
║  ╭─────────────────────────╮  ║
║  │                         │  ║
║  │  🏰 Kingdom Name        │  ║
║  │  Activity Authorization │  ║
║  │                         │  ║
║  ╰─────────────────────────╯  ║
║                               ║
║  ╭─────────────────────────╮  ║
║  │ Legal Name:  John Doe   │  ║
║  │ SCA Name:    Sir John   │  ║
║  │ Branch:      Local      │  ║
║  │ Membership:  Active     │  ║
║  │ Background:  ✓ Current  │  ║
║  ╰─────────────────────────╯  ║
║                               ║
║  ╭─────────────────────────╮  ║
║  │  Heavy Combat           │  ║
║  │  ✓ Authorized          │  ║
║  │  Expires: 2024-12-31    │  ║
║  ╰─────────────────────────╯  ║
║                               ║
║  ╭─────────────────────────╮  ║
║  │  Archery                │  ║
║  │  ✓ Authorized          │  ║
║  │  Expires: 2025-06-30    │  ║
║  ╰─────────────────────────╯  ║
║                               ║
║                               ║
║                        ╭───╮  ║
║                        │⋮⋮⋮│ ←║─ FAB
║                        ╰───╯  ║
║                               ║
║  © Copyright Footer           ║
╚═══════════════════════════════╝

When FAB is tapped:

╔═══════════════════════════════╗
║                               ║
║  [Card content above...]      ║
║                               ║
║  ╭─────────────────────────╮  ║
║  │  Archery                │  ║
║  │  ✓ Authorized          │  ║
║  ╰─────────────────────────╯  ║
║                               ║
║  ┌─────────────────────────┐  ║
║  │ ✓ Request Authorization │  ║
║  ├─────────────────────────┤  ║
║  │ ○ Approve Auth (5)      │  ║
║  ├─────────────────────────┤  ║
║  │ ▢ Submit Waiver         │  ║
║  └─────────────────────────┘  ║
║                        ╭───╮  ║
║                        │⋮⋮⋮│  ║
║                        ╰───╯  ║
║  © Copyright Footer           ║
╚═══════════════════════════════╝
```

## Accessibility Markup

```html
<button 
  class="mobile-menu-fab"
  aria-label="Open menu"
  aria-expanded="false"
  aria-controls="mobile-menu">
  <i class="bi bi-three-dots" aria-hidden="true"></i>
</button>

<div id="mobile-menu" 
     class="mobile-menu-items" 
     hidden
     role="menu">
  <a href="/action"
     role="menuitem"
     class="mobile-menu-item"
     aria-label="Request Authorization">
    <span>
      <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
      <span>Request Authorization</span>
    </span>
  </a>
</div>
```

This ensures screen readers can properly announce:
- "Button, Open menu"
- When opened: "Menu with 3 items"
- Each item: "Link, Request Authorization"
