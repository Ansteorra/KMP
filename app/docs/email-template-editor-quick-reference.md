# Email Template Editor - Quick Reference

## Overview

The email template editor uses **EasyMDE** (a simple markdown editor) for creating email templates. It provides a user-friendly interface for editing both HTML and plain text email templates.

**Key Feature:** HTML templates are stored as **Markdown** and automatically converted to HTML when emails are sent. This makes editing much easier while still producing professional HTML emails.

## Features

- **Markdown-style toolbar** for basic formatting (bold, italic, headings, lists, links)
- **Variable insertion buttons** - Click to insert template variables like `{{variableName}}`
- **Live preview** with variable highlighting
- **Side-by-side editing** - See markdown and preview simultaneously
- **Fullscreen mode** for distraction-free editing
- **Syntax help** - Displays available variables and their descriptions

## Using the Editor

### 1. Accessing the Editor

Navigate to **Admin > Email Templates** and either:
- Click **Add Email Template** to create a new template
- Click **Edit** on an existing template

### 2. Editor Interface

Each template has two editors:

1. **Text Template** - Plain text version for email clients that don't support HTML
2. **HTML Template (Markdown)** - Write in Markdown, automatically converted to HTML

### 3. Inserting Variables

**Available Variables** are displayed above each editor. Click any variable button to insert it at the cursor position.

**Manual Syntax:**
- Use `{{variableName}}` format
- Example: `{{email}}`, `{{passwordResetUrl}}`, `{{siteAdminSignature}}`

**Variable Button Insertion:**
```
Click: {{email}}  →  Inserts: {{email}}
```

### 4. Toolbar Features

| Icon | Function | Shortcut |
|------|----------|----------|
| **B** | Bold text | Ctrl+B |
| *I* | Italic text | Ctrl+I |
| H | Heading | - |
| " | Quote | - |
| • | Unordered list | - |
| 1. | Ordered list | - |
| 🔗 | Insert link | - |
| 👁 | Toggle preview | - |
| ⇆ | Side-by-side | - |
| ⛶ | Fullscreen | F11 |
| {} | Insert variable | - |
| ? | Guide | - |

### 5. Example Template

**Plain Text Example:**
```
Hello {{memberName}},

Someone has requested a password reset for your account ({{email}}).

If this was you, please click the link below to reset your password:
{{passwordResetUrl}}

If you did not request this, you can safely ignore this email.

{{siteAdminSignature}}
```

**HTML Template (Markdown) Example:**
```markdown
Someone has requested a password reset for the **AMP HTML** account associated with **{{email}}**.

If this was you, please click the link below to reset your password:

[Reset My Password]({{passwordResetUrl}})

If you did not request this, you can safely ignore this email.

---

{{siteAdminSignature}}
```

**Result (HTML output after conversion):**
```html
<p>Someone has requested a password reset for the <strong>AMP HTML</strong> account associated with <strong>admin@amp.ansteorra.org</strong>.</p>

<p>If this was you, please click the link below to reset your password:</p>

<p><a href="http://localhost:8080/members/reset-password/...">Reset My Password</a></p>

<p>If you did not request this, you can safely ignore this email.</p>

<hr>

<p>Thank you<br>Webminister</p>
```

### 6. Markdown Formatting Tips

**Text Formatting:**
- `**bold**` → **bold**
- `*italic*` or `_italic_` → *italic*
- `~~strikethrough~~` → ~~strikethrough~~
- `` `code` `` → `code`

**Headings:**
```markdown
# Heading 1
## Heading 2
### Heading 3
```

**Links:**
```markdown
[Link Text](http://example.com)
[Link with Variable]({{passwordResetUrl}})
```

**Lists:**
```markdown
- Unordered item 1
- Unordered item 2

1. Ordered item 1
2. Ordered item 2
```

**Other:**
```markdown
---                  (horizontal line)
> Quoted text        (blockquote)
```

**Paragraphs:**
Leave a blank line between paragraphs for proper spacing.

### 7. Preview Mode

Click the **eye icon** (👁) to toggle preview mode where you can see:
- Rendered markdown/HTML
- Variables highlighted in **blue badges** (`{{variable}}`)
- Final appearance of the email

### 8. Best Practices

1. **Use Markdown for HTML templates** - It's easier to write and maintain
2. **Keep it simple** - Email clients have varying HTML support
3. **Test both versions** - Some users see plain text only
4. **Verify variables** - Ensure all `{{variableName}}` placeholders are spelled correctly
5. **Preview before saving** - Check how your template looks
6. **Mobile-friendly** - Keep layouts simple and text readable on small screens
7. **Use semantic formatting** - Use headings, lists, and emphasis appropriately
8. **Links work in variables** - You can use `[Click here]({{urlVariable}})`

### 9. Variable Syntax

The system supports two variable syntaxes:
- `{{variableName}}` - Standard template syntax (recommended)
- `${variableName}` - Alternative syntax (also supported)

Both will be replaced with actual values when the email is sent.

### 10. Common Variables

Depending on the email type, you may have access to:

| Variable | Description |
|----------|-------------|
| `{{email}}` | Recipient's email address |
| `{{memberName}}` | Member's full name |
| `{{passwordResetUrl}}` | Link to reset password |
| `{{siteAdminSignature}}` | Standard admin signature |
| `{{userName}}` | User's username |
| `{{gatheringName}}` | Name of a gathering/event |
| `{{activityName}}` | Name of an activity |

**Note:** Available variables vary by email type. Check the "Available Variables" section above the editor.

## Troubleshooting

### Editor Not Loading
- Ensure assets are compiled: `npm run dev`
- Check browser console for errors
- Verify EasyMDE is in package.json dependencies

### Variables Not Working
- Use exact syntax: `{{variableName}}` (case-sensitive)
- Check spelling matches available variables
- Don't add spaces: `{{ variable }}` won't work

### Preview Not Showing
- Click the eye icon (👁) to enable preview
- Try side-by-side mode (⇆) to see both views

### HTML Not Rendering in Email
- Check template is marked as **Active**
- Verify HTML template field has content
- Some email clients may not support certain HTML tags

## Technical Details

### Controller Location
`assets/js/controllers/email-template-editor-controller.js`

### CSS Location
`assets/css/app.css` (imports `easymde/dist/easymde.min.css`)

### Template Location
`templates/EmailTemplates/form.php`

### Stimulus Integration
```html
<div data-controller="email-template-editor"
     data-email-template-editor-variables-value='[{"name":"email","description":"User email"}]'
     data-email-template-editor-placeholder-value="Enter template..."
     data-email-template-editor-min-height-value="400px">
  
  <textarea data-email-template-editor-target="editor"></textarea>
  <div data-email-template-editor-target="variableButtons"></div>
</div>
```

## See Also

- [Email Template Management Documentation](email-template-management.md)
- [EasyMDE GitHub Repository](https://github.com/Ionaru/easy-markdown-editor)
- [Stimulus.js Documentation](https://stimulus.hotwired.dev/)
