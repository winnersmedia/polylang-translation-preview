#!/bin/bash
# Package script for Plesk/CentOS 7 environment
# Run this locally after downloading the plugin files

echo "📦 Packaging Polylang Translation Preview for GitHub"
echo "Repository: https://github.com/winnersmedia/polylang-translation-preview"

# Rename main file to match repository convention
if [ -f "translation-helper.php" ]; then
    mv translation-helper.php polylang-translation-preview.php
    echo "✅ Renamed main plugin file"
fi

# Initialize git repository
git init
echo "✅ Initialized git repository"

# Add all files
git add .
echo "✅ Added all files to git"

# Commit with full message
git commit -m "🚀 Initial release v1.0.0 - Polylang Translation Preview

✨ Features:
• Google Translate integration with live preview tooltips
• Smart language detection using Polylang locales
• HTML formatting preservation in translations
• Equivalent language filtering (en_GB ↔ en_CA)
• Configurable debug logging
• Comprehensive setup instructions
• Security best practices for API key management

🏢 Created by Winners Media Limited
🌐 https://www.winnersmedia.co.uk

🚀 Generated with Claude Code
Co-Authored-By: Claude <noreply@anthropic.com>"

echo "✅ Committed files"

# Add remote repository
git remote add origin https://github.com/winnersmedia/polylang-translation-preview.git
echo "✅ Added remote repository"

# Push to GitHub
git branch -M main
git push -u origin main
echo "✅ Pushed to GitHub"

# Create release tag
git tag -a v1.0.0 -m "Polylang Translation Preview v1.0.0"
git push origin v1.0.0
echo "✅ Created release tag"

echo "🎯 Successfully deployed to GitHub!"
echo "📋 Next: Create release at https://github.com/winnersmedia/polylang-translation-preview/releases"