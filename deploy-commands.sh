#!/bin/bash
# Polylang Translation Preview - GitHub Deployment Commands
# Run these commands on your local machine with git access

echo "🚀 Deploying Polylang Translation Preview to GitHub"
echo "Repository: https://github.com/winnersmedia/polylang-translation-preview"

# Clone the repository
git clone https://github.com/winnersmedia/polylang-translation-preview.git
cd polylang-translation-preview

# Copy your plugin files here, then run:
git add .

# Commit with detailed message
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

📋 Requirements:
• WordPress 5.0+
• PHP 7.4+
• Polylang plugin
• Google Cloud Translation API key

🔧 Installation:
1. Download and extract to /wp-content/plugins/
2. Activate plugin in WordPress admin
3. Configure Google Cloud API key in Settings → Translation Preview
4. Select default target language from Polylang languages

Perfect for multilingual WordPress sites! 🌍"

# Push to GitHub
git push origin main

# Create a release tag
git tag -a v1.0.0 -m "Polylang Translation Preview v1.0.0"
git push origin v1.0.0

echo "✅ Successfully deployed to GitHub!"
echo "🎯 Next: Create a release at https://github.com/winnersmedia/polylang-translation-preview/releases"