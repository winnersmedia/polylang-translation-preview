# Polylang Translation Preview

A WordPress plugin that provides instant Google Translate previews for selected text in the WordPress admin interface when using Polylang.

![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)
![License](https://img.shields.io/badge/License-GPL%20v3-green.svg)

## 🌟 Features

- **Instant Translation Preview**: Select any text in WordPress admin to see live translation tooltips
- **Polylang Integration**: Automatically detects page language and uses your configured Polylang languages
- **Smart Language Detection**: Uses actual locales (e.g., `en_GB`) instead of just language codes
- **HTML Formatting Preservation**: Maintains bold, italic, links, and other formatting in translations
- **Equivalent Language Filtering**: Skips unnecessary translations between similar languages (e.g., `en_GB` ↔ `en_CA`)
- **Visual & Text Editor Support**: Works with both TinyMCE visual editor and text areas
- **Configurable Debug Logging**: Optional console logging for troubleshooting
- **Secure API Key Management**: Built-in security best practices for Google Cloud API

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher  
- Polylang plugin (free or pro)
- Google Cloud Translation API key

## 🚀 Installation

### From GitHub

1. Download the latest release or clone this repository
2. Upload the `polylang-translation-preview` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin
4. Configure your settings at **Settings → Translation Preview**

### Manual Installation

```bash
cd wp-content/plugins/
git clone https://github.com/your-username/polylang-translation-preview.git
```

## ⚙️ Configuration

### 1. Get Google Cloud Translation API Key

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the Cloud Translation API:
   - Navigate to **APIs & Services → Library**
   - Search for "Cloud Translation API"
   - Click and press **Enable**
4. Create credentials:
   - Go to **APIs & Services → Credentials**
   - Click **Create Credentials → API Key**
   - Copy the generated API key
5. **Security (Recommended)**: Restrict your API key:
   - Click on your API key in Credentials
   - Under "API restrictions", select "Restrict key"
   - Choose "Cloud Translation API" only
   - Under "Website restrictions", add your domain
6. Set up billing (required for Translation API)

### 2. Configure Plugin Settings

1. Go to **Settings → Translation Preview** in WordPress admin
2. Enter your Google Cloud API key
3. Select your default target language from your Polylang languages
4. Optionally enable debug logging for troubleshooting

## 💡 Usage

1. **Edit any post, page, or term** in WordPress admin
2. **Select text** in the editor or text fields
3. **View instant translation** in the popup tooltip
4. **Formatted content** (bold, italic, links) is preserved in translations

### Supported Content Areas

- TinyMCE Visual Editor
- Text Editor/HTML mode
- Text input fields
- Textarea fields
- Custom field inputs

## 🎯 Smart Features

### Language Equivalence Detection
The plugin intelligently skips translations between equivalent language variants:
- ✅ `en_GB` → `fr_FR` (English to French) - Translates
- ❌ `en_GB` → `en_CA` (UK English to Canadian English) - Skips with message
- ❌ `de_DE` → `de_CH` (German Germany to German Switzerland) - Skips with message

### HTML Preservation
Maintains formatting in translations:
- **Bold** and *italic* text
- [Links](https://example.com) with URLs
- Lists and paragraphs
- Headings and blockquotes

## 🔧 Developer Features

### Debug Logging
Enable detailed logging in settings to troubleshoot:
- Translation API requests/responses
- Language detection process
- Selection coordinates
- TinyMCE editor events

### Hooks & Filters
Currently no custom hooks available, but pull requests welcome!

## 💰 Pricing

Google Cloud Translation API pricing:
- **Free Tier**: 500,000 characters per month
- **Paid Usage**: $20 per 1M characters
- Billing account required but free tier available

[View Current Pricing](https://cloud.google.com/translate/pricing)

## 🛠️ Troubleshooting

### Common Issues

1. **No translation tooltip appearing**
   - Check API key is correctly entered
   - Verify Google Cloud billing is set up
   - Enable debug logging and check browser console

2. **"Equivalent language variants" message**
   - This is normal for same-language variants (e.g., `en_GB` ↔ `en_CA`)
   - Check your Polylang language configuration

3. **Escaped characters (e.g., "it\'s")**
   - This should be fixed in current version
   - Enable debug logging to troubleshoot

### Debug Mode

Enable debug logging in **Settings → Translation Preview** to see detailed console logs for troubleshooting.

## 🤝 Contributing

We welcome contributions! Please feel free to submit pull requests or open issues.

### Development Setup

1. Clone the repository
2. Install in WordPress development environment
3. Enable debug logging for development
4. Make changes and test thoroughly

## 📝 Changelog

### Version 1.0.0
- Initial release
- Google Translate integration
- Polylang language detection
- HTML formatting preservation
- Smart language equivalence detection
- Debug logging controls
- Comprehensive setup instructions

## 📄 License

This plugin is licensed under the [GPL v3](LICENSE) or later.

## 🏢 About Winners Media Limited

This plugin is created and maintained by **Winners Media Limited**, a digital agency specializing in WordPress development and digital solutions.

- **Website**: [winnersmedia.co.uk](https://www.winnersmedia.co.uk)
- **Contact**: For support or custom development inquiries

## 🔗 Links

- [Plugin Settings](wp-admin/options-general.php?page=polylang-translation-preview)
- [Google Cloud Console](https://console.cloud.google.com/)
- [Google Translation API Docs](https://cloud.google.com/translate/docs)
- [Polylang Plugin](https://wordpress.org/plugins/polylang/)

## ⭐ Support

If you find this plugin helpful, please consider:
- ⭐ Starring this repository
- 🐛 Reporting issues
- 💡 Suggesting new features
- 🤝 Contributing code improvements

---

**Made with ❤️ by [Winners Media Limited](https://www.winnersmedia.co.uk)**