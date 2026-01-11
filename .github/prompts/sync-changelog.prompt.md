````prompt
---
description: Sync the user-facing CHANGELOG.md with meaningful changes from git history since the last sync
---

## Purpose

This prompt reviews git history since the last changelog sync and creates user-friendly changelog entries for meaningful changes, ignoring minor commits, bug fixes, and merge commits that don't add user value.

## Files Updated

This prompt updates the `app/CHANGELOG.md` file. The changelog is displayed to users via the `/pages/changelog` route in the application.

## Output Format

The changelog uses a user-centric format with dated entries. Each entry has:
- **Date** - When the change was released (YYYY-MM-DD or Month YYYY for grouped changes)
- **Tag** - One of: `New Feature`, `Improvement`, `Security`, `Announcement`
- **Title** - A short, user-friendly title (not technical)
- **Description** - 1-2 sentences explaining the value to users
- **Bullet points** - Specific capabilities or changes included

Example entry format:
```markdown
### Member Privacy Controls

Enhanced privacy settings give members more control over who can see their personal information.

- Configure visibility for email, phone, and address fields
- Set default privacy levels for new members
- Administrators can override for organizational needs
- Audit logging for privacy setting changes

📅 December 6, 2025 · `Security`
```

## Workflow

### Step 1: Read Current Sync State

Read the `app/CHANGELOG.md` file and extract:
- `LAST_SYNCED_COMMIT` - the commit hash from the last sync (or "none" if never synced)
- `LAST_SYNCED_DATE` - when the last sync occurred

Look for these HTML comment markers near the top of the file:
```
<!-- LAST_SYNCED_COMMIT: abc123 -->
<!-- LAST_SYNCED_DATE: 2025-01-01 -->
```

### Step 2: Get Git History

Run git commands to get the commit history:

**If LAST_SYNCED_COMMIT is "none" (first sync):**
```bash
git log --oneline --no-merges --format="%ad|%s" --date=format:"%Y-%m-%d" -500
```

**If LAST_SYNCED_COMMIT has a value:**
```bash
git log --oneline --no-merges --format="%ad|%s" --date=format:"%Y-%m-%d" {LAST_SYNCED_COMMIT}..HEAD
```

Also get the current HEAD commit hash:
```bash
git rev-parse HEAD
```

### Step 3: Analyze Commits

For each commit, determine if it represents a meaningful user-facing change.

**INCLUDE these types of changes:**
- New features or capabilities users can use
- Significant UI/UX improvements
- New plugins or modules
- Performance improvements users would notice
- New configuration options administrators can use
- Security enhancements
- Major workflow improvements

**EXCLUDE these types of changes:**
- Merge commits (already filtered by --no-merges)
- Version bumps without other changes
- Dependency updates (unless they add new capabilities)
- Code style/formatting changes
- Internal refactoring with no user impact
- Test additions/fixes
- Typo fixes in code
- Build/CI configuration changes
- Developer tooling changes
- Documentation-only changes (unless user-facing docs)
- Commits with messages like "fix", "wip", "temp", "cleanup"

### Step 4: Group and Categorize Changes

Group related commits into logical features. Multiple commits for the same feature should become one entry.

Assign each entry a tag:
- **New Feature** - Brand new capabilities
- **Improvement** - Enhancements to existing features
- **Security** - Security-related changes
- **Announcement** - Major milestones, launches, breaking changes

### Step 5: Write Changelog Entries

Create clear, user-friendly descriptions. Write from the user's perspective.

**Good titles:**
- "Gathering Calendar Views"
- "Member Privacy Controls"
- "Waiver Upload Wizard"
- "Youth to Adult Transitions"

**Bad titles (too technical):**
- "Refactored MembersController"
- "Added DataverseGridTrait"
- "Updated authorization service"

### Step 6: Update CHANGELOG.md

1. Update the sync markers with the new commit hash and today's date

2. Add new entries in reverse chronological order (newest first)

3. Format each entry as:
```markdown
---

### [Title]

[1-2 sentence description of the value to users]

- [Specific capability or change]
- [Another capability]
- [Another capability]

📅 [Month Day, Year] · `[Tag]`
```

### Step 7: Present Summary

After updating, present a summary:
- Number of commits reviewed
- Number of meaningful entries created
- List of entry titles added

## User Input

```text
$ARGUMENTS
```

**Optional arguments:**
- `--dry-run` - Show what would be added without modifying files
- `--since COMMIT` - Override the last synced commit to start from a specific point
- `--since-date YYYY-MM-DD` - Start from a specific date

## Important Notes

1. **Be conservative** - When in doubt about whether a change is user-facing, leave it out
2. **Consolidate related commits** - Multiple commits for the same feature should become one entry
3. **User perspective** - Write entries from the user's perspective, not the developer's
4. **No duplicates** - Don't add entries that already exist in the changelog
5. **Preserve existing content** - Only add new entries, never modify or remove existing ones
6. **Group by time period** - For large backlogs, group entries by month or quarter

````
