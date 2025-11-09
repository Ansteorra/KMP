#!/bin/bash
# Script to remove fixture declarations from all test files

# Find all test files and process them
find /workspaces/KMP/app/tests -name "*Test.php" -o -name "*Trait.php" | while read file; do
    # Use sed to remove the fixtures property declaration
    # This handles multiline fixture arrays
    sed -i '/^[[:space:]]*\/\*\*$/,/^[[:space:]]*\*\/$/{ 
        /Fixtures/,/^[[:space:]]*\*\/$/{
            /^[[:space:]]*\*\/$/!{
                /^[[:space:]]*\/\*\*$/!d
            }
        }
    }' "$file"
    
    sed -i '/protected array \$fixtures/,/];/d' "$file"
    sed -i '/protected \$fixtures/,/];/d' "$file"
    sed -i '/public array \$fixtures/,/];/d' "$file"
    sed -i '/public \$fixtures/,/];/d' "$file"
done

# Do the same for plugin tests
find /workspaces/KMP/app/plugins -path "*/tests/*" -name "*Test.php" -o -name "*Trait.php" | while read file; do
    sed -i '/^[[:space:]]*\/\*\*$/,/^[[:space:]]*\*\/$/{ 
        /Fixtures/,/^[[:space:]]*\*\/$/{
            /^[[:space:]]*\*\/$/!{
                /^[[:space:]]*\/\*\*$/!d
            }
        }
    }' "$file"
    
    sed -i '/protected array \$fixtures/,/];/d' "$file"
    sed -i '/protected \$fixtures/,/];/d' "$file"
    sed -i '/public array \$fixtures/,/];/d' "$file"
    sed -i '/public \$fixtures/,/];/d' "$file"
done

echo "Fixture declarations removed from all test files"
