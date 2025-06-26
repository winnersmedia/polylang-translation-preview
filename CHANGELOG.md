# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2024-12-26

### Added
- Initial release of Polylang Translation Preview
- Google Translate API integration
- Real-time translation tooltips for selected text
- Polylang language detection and integration
- Support for both TinyMCE visual editor and text areas
- HTML formatting preservation in translations
- Smart language equivalence detection (skips en_GB ↔ en_CA translations)
- Configurable debug logging system
- Comprehensive Google Cloud API setup instructions
- Security best practices for API key configuration
- Escaped character handling fixes
- Dynamic language dropdown using site's Polylang languages

### Features
- Instant translation preview on text selection
- Locale-aware language detection (en_GB vs en_CA)
- HTML content preservation (bold, italic, links, etc.)
- Intelligent caching system
- Responsive tooltip positioning
- Cross-browser compatibility

### Security
- Secure API key storage
- Input sanitization with wp_kses_post()
- XSS protection
- API key restriction guidelines

### Developer Features
- Optional debug logging
- Console error reporting
- Detailed API request/response logging
- Language detection debugging