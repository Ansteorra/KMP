#!/bin/bash

# Script to find all files containing "* #" and create a markdown todo list

OUTPUT_FILE="TODO_LIST.md"

# Start the markdown file
cat > "$OUTPUT_FILE" << 'EOF'
# Todo List - Files with `* #`

This is an auto-generated todo list of all files containing `* #` patterns.

---

EOF

# Find all files containing "* #" and append them to the markdown file
echo "## Files to Review" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"

count=0
while IFS= read -r file; do
    # Get the relative path from the workspace root
    relative_path="${file#./}"
    
    # Count occurrences of "* #"
    occurrences=$(grep -o '\* #' "$file" 2>/dev/null | wc -l)
    
    # Add as a todo item in markdown
    echo "- [ ] [$relative_path]($relative_path) ($occurrences occurrences)" >> "$OUTPUT_FILE"
    
    ((count++))
done < <(find . -type f \
    -not -path '*/node_modules/*' \
    -not -path '*/.git/*' \
    -not -path '*/vendor/*' \
    -not -path '*/tmp/*' \
    -not -path '*/.vscode/*' \
    -not -path '*/logs/*' \
    -not -path '*/.env' \
    ! -name '*.phar' \
    ! -name '*.node' \
    -exec grep -l '\* #' {} \; 2>/dev/null | sort)

# Add summary at the top
cat > "${OUTPUT_FILE}.tmp" << EOF
# Todo List - Files with \`* #\`

This is an auto-generated todo list of all files containing \`* #\` patterns.

**Total files found:** $count

---

EOF

tail -n +2 "$OUTPUT_FILE" >> "${OUTPUT_FILE}.tmp"
mv "${OUTPUT_FILE}.tmp" "$OUTPUT_FILE"

echo "✓ Todo list created: $OUTPUT_FILE"
echo "✓ Total files found: $count"
