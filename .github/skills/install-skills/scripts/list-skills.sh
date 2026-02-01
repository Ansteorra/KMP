#!/bin/bash
# list-skills.sh - List available skills from a GitHub repository
#
# Usage: ./list-skills.sh [source-repo]
#
# Examples:
#   ./list-skills.sh
#   ./list-skills.sh anthropics/skills
#   ./list-skills.sh github/awesome-copilot

set -e

SOURCE_REPO="${1:-anthropics/skills}"

# Paths to check for skills (in order of priority)
SKILLS_PATHS=("skills" ".github/skills" ".claude/skills")

FOUND=false

for SKILLS_PATH in "${SKILLS_PATHS[@]}"; do
    # Try to get skills listing via GitHub API
    RESPONSE=$(curl -s "https://api.github.com/repos/$SOURCE_REPO/contents/$SKILLS_PATH" 2>/dev/null)

    if echo "$RESPONSE" | grep -q '"type": "dir"'; then
        echo "Available skills from $SOURCE_REPO ($SKILLS_PATH):"
        echo ""
        # Parse and display skill names
        echo "$RESPONSE" | grep '"name":' | sed 's/.*"name": "\([^"]*\)".*/  - \1/'
        FOUND=true
        break
    fi
done

if [ "$FOUND" = false ]; then
    echo "Error: Could not fetch skills from $SOURCE_REPO"
    echo "Checked paths: ${SKILLS_PATHS[*]}"
    echo "Make sure the repository exists and has a skills directory"
    exit 1
fi

echo ""
echo "To install a skill, run:"
echo "  ./install-skill.sh <skill-name> $SOURCE_REPO"
