# Waiver Secretary Dashboard - Quick Reference

## Access
**URL**: `/waivers/gathering-waivers/dashboard`  
**Menu**: Waivers → Waiver Dashboard  
**Permission**: Must have waiver upload permission for at least one branch

## Dashboard Sections

### 📊 Key Statistics (Top)
5 metric cards showing:
- **Total Waivers** (Blue) - All waivers in system
- **Last 30 Days** (Green) - Recent uploads
- **Missing Waivers** (Red) - Gatherings needing action
- **Declined Waivers** (Yellow) - Rejected uploads
- **Active Branches** (Cyan) - Branches with gatherings

### 🔍 Search Bar
Search by:
- Gathering name
- Branch name
- Member name (SCA or modern)
- Waiver type name

Returns up to 50 most recent matches

### ⚠️ Gatherings Missing Waivers
Shows gatherings in next 30 days that need waivers:
- **Yellow row** = Starts within 7 days (urgent)
- **Red row** = Already started
- Lists specific missing waiver types
- "Upload" button for quick action

### 🏢 Branches with Compliance Issues
Top 10 branches ranked by compliance problems:
- Number of gatherings missing waivers
- Total count of missing waivers
- Click branch name to view details

### 🕐 Recent Waiver Activity
Last 20 waiver uploads showing:
- Upload date
- Gathering and branch
- Waiver type
- Who uploaded it

### 📋 Waiver Types Summary
All active waiver types with counts:
- Shows how many waivers of each type exist
- Helps identify common/rare waiver types

### ⚡ Quick Actions
Fast links to:
- View Gatherings Needing Waivers (detailed view)
- Manage Waiver Types (configuration)
- Upload Waiver (direct upload)
- All Waivers (browse all)

## Color Coding

| Color | Meaning |
|-------|---------|
| 🔴 Red | Critical - event has started or ended |
| 🟡 Yellow | Urgent - event starts within 7 days |
| 🟢 Green | Good - positive metrics |
| 🔵 Blue | Info - general statistics |

## Tips

1. **Daily Check**: Review "Gatherings Missing Waivers" each morning
2. **Weekly Check**: Review "Branches with Compliance Issues" for patterns
3. **Use Search**: Quickly find specific waivers without browsing
4. **Urgent First**: Focus on yellow/red highlighted items
5. **Track Progress**: Monitor "Recent Activity" to see who's uploading

## Common Tasks

### Find a specific waiver
1. Use search bar at top
2. Type gathering name, branch, or member
3. Click "View" in results

### Upload missing waiver
1. Click gathering name in "Missing Waivers" section
2. Click "Upload" button
3. Complete upload form

### Check branch compliance
1. Look at "Branches with Compliance Issues"
2. Click branch name for details
3. Contact branch officers if needed

### Review recent work
1. Check "Recent Waiver Activity"
2. Verify expected uploads appear
3. Follow up on any surprises

## Troubleshooting

**Dashboard shows no data**
- Check your branch permissions
- Verify gatherings exist in date range
- Confirm waiver types are configured

**Can't access dashboard**
- Must be logged in
- Need waiver upload permission
- Contact system administrator

**Search returns no results**
- Try fewer/different keywords
- Check spelling
- Verify data exists in system

**Statistics seem wrong**
- Dashboard shows last 30 days for some metrics
- Only counts non-deleted, non-declined waivers
- Only shows branches you have permission for

## Support

For issues or questions:
1. Check `/docs/` for detailed documentation
2. Review plugin overview at `/plugins/Waivers/OVERVIEW.md`
3. Contact system administrator
4. Submit GitHub issue if bug found
