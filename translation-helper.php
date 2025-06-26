<?php
/**
 * Plugin Name: Polylang Translation Preview
 * Plugin URI: https://github.com/winnersmedia/polylang-translation-preview
 * Description: Shows Google Translate previews for selected text in the WordPress admin interface when using Polylang.
 * Version: 1.0.0
 * Author: Winners Media Limited
 * Author URI: https://www.winnersmedia.co.uk
 * Text Domain: polylang-translation-preview
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PTP_VERSION', '1.0.0');
define('PTP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PTP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Debug logging function
function ptp_debug_log($message) {
    if (get_option('ptp_debug_logging', false)) {
        error_log('PTP Debug - ' . $message);
    }
}

// Function to check if debug logging is enabled
function ptp_is_debug_enabled() {
    return get_option('ptp_debug_logging', false);
}

// Encryption functions for API key security
function ptp_get_encryption_key() {
    // Use WordPress AUTH_KEY and SECURE_AUTH_KEY for encryption
    if (defined('AUTH_KEY') && defined('SECURE_AUTH_KEY')) {
        return hash('sha256', AUTH_KEY . SECURE_AUTH_KEY . 'ptp_api_key_salt');
    }
    
    // Fallback if constants not defined
    return hash('sha256', 'ptp_fallback_encryption_key_' . get_option('siteurl'));
}

// Encrypt API key for storage
function ptp_encrypt_api_key($api_key) {
    if (empty($api_key)) {
        return '';
    }
    
    // Check if OpenSSL is available
    if (!extension_loaded('openssl')) {
        // Fallback to base64 encoding if OpenSSL not available
        ptp_debug_log('OpenSSL not available, using base64 encoding fallback');
        return base64_encode($api_key);
    }
    
    $encryption_key = ptp_get_encryption_key();
    $iv = openssl_random_pseudo_bytes(16);
    
    $encrypted = openssl_encrypt($api_key, 'AES-256-CBC', $encryption_key, 0, $iv);
    
    // Return base64 encoded IV + encrypted data
    return base64_encode($iv . $encrypted);
}

// Decrypt API key from storage
function ptp_decrypt_api_key($encrypted_data) {
    if (empty($encrypted_data)) {
        return '';
    }
    
    $data = base64_decode($encrypted_data);
    
    if ($data === false) {
        return '';
    }
    
    // Check if OpenSSL is available
    if (!extension_loaded('openssl')) {
        // If OpenSSL not available, assume it's just base64 encoded
        return $data;
    }
    
    // Check if data is long enough to be encrypted (IV + encrypted data)
    if (strlen($data) < 16) {
        // Assume it's base64 encoded fallback
        return $data;
    }
    
    $encryption_key = ptp_get_encryption_key();
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $encryption_key, 0, $iv);
    
    return $decrypted !== false ? $decrypted : '';
}

// Get API key (with decryption)
function ptp_get_api_key() {
    $encrypted_key = get_option('ptp_google_translate_api_key_encrypted', '');
    
    if (!empty($encrypted_key)) {
        // Use encrypted version
        return ptp_decrypt_api_key($encrypted_key);
    }
    
    // Fallback to plain text for migration
    $plain_key = get_option('ptp_google_translate_api_key', '');
    if (!empty($plain_key)) {
        // Migrate to encrypted storage
        ptp_set_api_key($plain_key);
        delete_option('ptp_google_translate_api_key');
        return $plain_key;
    }
    
    return '';
}

// Set API key (with encryption)
function ptp_set_api_key($api_key) {
    if (empty($api_key)) {
        delete_option('ptp_google_translate_api_key_encrypted');
        return;
    }
    
    $encrypted_key = ptp_encrypt_api_key($api_key);
    update_option('ptp_google_translate_api_key_encrypted', $encrypted_key);
}

// Check if Polylang is active
function ptp_check_polylang() {
    if (!function_exists('pll_current_language')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . 
                 esc_html__('Polylang Translation Preview requires Polylang to be installed and activated.', 'polylang-translation-preview') . 
                 '</p></div>';
        });
        return false;
    }
    return true;
}

// Function to convert Polylang language code to Google Translate language code
function ptp_get_google_language_code($pll_code) {
    ptp_debug_log('Converting language code: ' . $pll_code);
    
    // First try to get the locale from Polylang
    if (function_exists('pll_the_languages')) {
        $pll_languages = pll_the_languages(array('raw' => 1, 'hide_if_empty' => 0));
        
        if (!empty($pll_languages) && isset($pll_languages[$pll_code])) {
            $locale = $pll_languages[$pll_code]['locale'];
            ptp_debug_log('Found Polylang locale for ' . $pll_code . ': ' . $locale);
            
            // Convert locale to Google Translate language code
            $google_code = ptp_convert_locale_to_google_code($locale);
            ptp_debug_log('Converted locale ' . $locale . ' to Google code: ' . $google_code);
            return $google_code;
        }
    }
    
    // Fallback to original mapping if Polylang data not available
    ptp_debug_log('Polylang data not available, using fallback mapping');
    
    // If the code is already a Google language code, return it
    if (strlen($pll_code) === 2) {
        ptp_debug_log('Code is already in Google format: ' . $pll_code);
        return $pll_code;
    }
    
    $language_map = array(
        'fi' => 'fi',  // Finnish
        'sv' => 'sv',  // Swedish
        'no' => 'no',  // Norwegian
        'da' => 'da',  // Danish
        'en' => 'en',  // English
        'de' => 'de',  // German
        'fr' => 'fr',  // French
        'es' => 'es',  // Spanish
        'it' => 'it',  // Italian
        'nl' => 'nl',  // Dutch
        'pt' => 'pt',  // Portuguese
        'ru' => 'ru',  // Russian
        'ja' => 'ja',  // Japanese
        'zh' => 'zh',  // Chinese
        'ko' => 'ko',  // Korean
        // Common Polylang slugs
        'uk' => 'en',  // UK -> English
        'us' => 'en',  // US -> English
        'gb' => 'en',  // GB -> English
        // Add full language names
        'finnish' => 'fi',
        'swedish' => 'sv',
        'norwegian' => 'no',
        'danish' => 'da',
        'english' => 'en',
        'german' => 'de',
        'french' => 'fr',
        'spanish' => 'es',
        'italian' => 'it',
        'dutch' => 'nl',
        'portuguese' => 'pt',
        'russian' => 'ru',
        'japanese' => 'ja',
        'chinese' => 'zh',
        'korean' => 'ko'
    );

    $result = isset($language_map[strtolower($pll_code)]) ? $language_map[strtolower($pll_code)] : 'en';
    ptp_debug_log('Converted ' . $pll_code . ' to Google language code: ' . $result);
    return $result;
}

// Function to convert locale (e.g., en_GB) to Google Translate language code
function ptp_convert_locale_to_google_code($locale) {
    ptp_debug_log('Converting locale to Google code: ' . $locale);
    
    // Handle common locale formats
    $locale_parts = explode('_', $locale);
    $language_code = strtolower($locale_parts[0]);
    $country_code = isset($locale_parts[1]) ? strtoupper($locale_parts[1]) : '';
    
    ptp_debug_log('Language: ' . $language_code . ', Country: ' . $country_code);
    
    // Special cases where country matters for Google Translate
    $special_locales = array(
        'en_GB' => 'en',    // British English -> English
        'en_US' => 'en',    // US English -> English
        'en_AU' => 'en',    // Australian English -> English
        'en_CA' => 'en',    // Canadian English -> English
        'fr_FR' => 'fr',    // French (France) -> French
        'fr_CA' => 'fr',    // French (Canada) -> French
        'pt_BR' => 'pt',    // Portuguese (Brazil) -> Portuguese
        'pt_PT' => 'pt',    // Portuguese (Portugal) -> Portuguese
        'es_ES' => 'es',    // Spanish (Spain) -> Spanish
        'es_MX' => 'es',    // Spanish (Mexico) -> Spanish
        'de_DE' => 'de',    // German (Germany) -> German
        'de_AT' => 'de',    // German (Austria) -> German
        'zh_CN' => 'zh-cn', // Chinese (Simplified)
        'zh_TW' => 'zh-tw', // Chinese (Traditional)
        'zh_HK' => 'zh-tw', // Chinese (Hong Kong) -> Traditional
    );
    
    // Check if we have a specific mapping for this locale
    if (isset($special_locales[$locale])) {
        $result = $special_locales[$locale];
        ptp_debug_log('Found specific mapping for ' . $locale . ': ' . $result);
        return $result;
    }
    
    // For most cases, just use the language part
    ptp_debug_log('Using language part: ' . $language_code);
    return $language_code;
}

// Function to check if two languages are essentially the same (same base language)
function ptp_are_languages_equivalent($lang1, $lang2) {
    ptp_debug_log('Checking if languages are equivalent: ' . $lang1 . ' vs ' . $lang2);
    
    // If they're exactly the same, they're equivalent
    if ($lang1 === $lang2) {
        ptp_debug_log('Languages are identical');
        return true;
    }
    
    // Get base language codes (language part before underscore or dash)
    $base1 = strtolower(preg_split('/[_-]/', $lang1)[0]);
    $base2 = strtolower(preg_split('/[_-]/', $lang2)[0]);
    
    ptp_debug_log('Base language 1: ' . $base1);
    ptp_debug_log('Base language 2: ' . $base2);
    
    // Check if base languages are the same
    $equivalent = $base1 === $base2;
    ptp_debug_log('Languages equivalent: ' . ($equivalent ? 'Yes' : 'No'));
    
    return $equivalent;
}

// Function to convert language ID to language code
function ptp_get_language_code_from_id($language_id) {
    if (empty($language_id)) {
        ptp_debug_log('Empty language ID provided');
        return '';
    }
    
    ptp_debug_log('Getting term for language ID ' . $language_id);
    
    // First try to get the term
    $term = get_term($language_id, 'language');
    if ($term && !is_wp_error($term)) {
        $lang_code = $term->slug;
        ptp_debug_log('Got language code from term: ' . $lang_code);
        return $lang_code;
    }
    
    // If term lookup fails, try to get the language code from Polylang
    if (function_exists('pll_get_term_language')) {
        $lang_code = pll_get_term_language($language_id);
        ptp_debug_log('Got language code from Polylang: ' . $lang_code);
        return $lang_code;
    }
    
    // If all else fails, try to get the post
    $post = get_post($language_id);
    if ($post && $post->post_type === 'language') {
        $lang_code = $post->post_name;
        ptp_debug_log('Got language code from post: ' . $lang_code);
        return $lang_code;
    }
    
    return '';
}

// Function to get post language from meta
function ptp_get_post_language_from_meta($post_id) {
    // First try to get language directly from Polylang
    $pll_lang = pll_get_post_language($post_id);
    if (!empty($pll_lang)) {
        ptp_debug_log('Got language directly from Polylang: ' . $pll_lang);
        return $pll_lang;
    }
    
    // Try to get current language from Polylang
    $current_lang = pll_current_language();
    if (!empty($current_lang)) {
        ptp_debug_log('Got current language from Polylang: ' . $current_lang);
        return $current_lang;
    }
    
    // Try to get language from post content
    $post = get_post($post_id);
    if ($post) {
        // Check if content contains Finnish characters
        if (preg_match('/[äöåÄÖÅ]/', $post->post_content)) {
            ptp_debug_log('Detected Finnish from content');
            return 'fi';
        }
        
        // Check if content contains Swedish characters
        if (preg_match('/[åäöÅÄÖ]/', $post->post_content)) {
            ptp_debug_log('Detected Swedish from content');
            return 'sv';
        }
        
        // Check if content contains German characters
        if (preg_match('/[äöüßÄÖÜ]/', $post->post_content)) {
            ptp_debug_log('Detected German from content');
            return 'de';
        }
        
        // Check if content contains French characters
        if (preg_match('/[éèêëàâçôöîïûüÉÈÊËÀÂÇÔÖÎÏÛÜ]/', $post->post_content)) {
            ptp_debug_log('Detected French from content');
            return 'fr';
        }
    }
    
    // Try to get language from meta
    $language_ids = get_post_meta($post_id, 'language', false);
    ptp_debug_log('Language IDs from meta: ' . print_r($language_ids, true));
    
    if (!empty($language_ids)) {
        // Get the first language ID (usually the primary language)
        $first_lang_id = is_array($language_ids[0]) ? $language_ids[0]['ID'] : $language_ids[0];
        ptp_debug_log('First language ID: ' . $first_lang_id);
        
        // Try to get the language code from the post name
        if (is_array($language_ids[0]) && isset($language_ids[0]['post_name'])) {
            $lang_code = $language_ids[0]['post_name'];
            ptp_debug_log('Got language code from post_name: ' . $lang_code);
            return $lang_code;
        }
        
        // If that fails, try to get it from the term
        $lang_code = ptp_get_language_code_from_id($first_lang_id);
        if (!empty($lang_code)) {
            ptp_debug_log('Got language code from term: ' . $lang_code);
            return $lang_code;
        }
    }
    
    // If all else fails, try to get the post type's default language
    $post_type = get_post_type($post_id);
    if ($post_type) {
        $pll_options = get_option('polylang');
        if (isset($pll_options['post_types']) && in_array($post_type, $pll_options['post_types'])) {
            $default_lang = pll_default_language();
            ptp_debug_log('Using default language for post type: ' . $default_lang);
            return $default_lang;
        }
    }
    
    // Final fallback - check if the post type is registered with Polylang
    if (function_exists('pll_is_translated_post_type') && pll_is_translated_post_type($post_type)) {
        ptp_debug_log('Post type is registered with Polylang, using default language');
        return pll_default_language();
    }
    
    return '';
}

// Enqueue admin scripts and styles
function ptp_enqueue_admin_assets($hook) {
    // Debug logging
    ptp_debug_log('Hook: ' . $hook);
    ptp_debug_log('Current Screen: ' . get_current_screen()->id);

    // Only load on post edit screens
    if (!in_array($hook, array('post.php', 'post-new.php'))) {
        ptp_debug_log('Not a post edit screen, skipping');
        return;
    }

    // Check if Polylang is active
    if (!ptp_check_polylang()) {
        ptp_debug_log('Polylang not active');
        return;
    }

    // Get current post ID
    $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
    ptp_debug_log('Post ID: ' . $post_id);
    
    if (!$post_id) {
        ptp_debug_log('No post ID found');
        return;
    }

    // Try multiple methods to get the post language
    $current_language = '';
    
    // Method 1: Direct pll_get_post_language
    $current_language = pll_get_post_language($post_id);
    ptp_debug_log('Method 1 (pll_get_post_language): ' . $current_language);
    
    // Method 2: Get post language from post object
    if (empty($current_language)) {
        $post = get_post($post_id);
        if ($post) {
            $current_language = pll_get_post_language($post->ID);
            ptp_debug_log('Method 2 (from post object): ' . $current_language);
        }
    }
    
    // Method 3: Get language from post type
    if (empty($current_language)) {
        $post_type = get_post_type($post_id);
        ptp_debug_log('Post Type: ' . $post_type);
        if ($post_type) {
            $current_language = pll_get_post_language($post_id, 'slug');
            ptp_debug_log('Method 3 (with post type): ' . $current_language);
        }
    }
    
    // Method 4: Get language from term
    if (empty($current_language)) {
        $terms = get_the_terms($post_id, 'language');
        if ($terms && !is_wp_error($terms)) {
            $current_language = $terms[0]->slug;
            ptp_debug_log('Method 4 (from term): ' . $current_language);
        }
    }

    // Method 5: Get language from post meta
    if (empty($current_language)) {
        $post_meta = get_post_meta($post_id);
        ptp_debug_log('Post Meta: ' . print_r($post_meta, true));
        
        // Try to get language from language meta field
        $current_language = ptp_get_post_language_from_meta($post_id);
        if (!empty($current_language)) {
            ptp_debug_log('Method 5 (from language meta): ' . $current_language);
        } else {
            // Try common Polylang meta keys
            $possible_meta_keys = array(
                'pll_language',
                '_pll_language',
                'language',
                'post_language'
            );
            
            foreach ($possible_meta_keys as $key) {
                if (isset($post_meta[$key])) {
                    $current_language = $post_meta[$key][0];
                    ptp_debug_log('Method 5 (from meta ' . $key . '): ' . $current_language);
                    break;
                }
            }
        }
    }
    
    // Method 6: Get language from Polylang options
    if (empty($current_language)) {
        $pll_options = get_option('polylang');
        ptp_debug_log('Polylang Options: ' . print_r($pll_options, true));
        
        if (isset($pll_options['post_types']) && in_array($post_type, $pll_options['post_types'])) {
            $current_language = pll_current_language();
            ptp_debug_log('Method 6 (from Polylang options): ' . $current_language);
        }
    }
    
    // Final fallback to current language
    if (empty($current_language)) {
        ptp_debug_log('All methods failed, falling back to current language');
        $current_language = pll_current_language();
        ptp_debug_log('Current language fallback: ' . $current_language);
    }
    
    // If we still don't have a language, try to get it from the URL
    if (empty($current_language)) {
        $current_language = pll_current_language();
        ptp_debug_log('Language from URL: ' . $current_language);
    }
    
    $target_language = get_option('ptp_target_language', pll_default_language());
    ptp_debug_log('Target language from settings: ' . $target_language);

    // Get actual locales for comparison
    $current_locale = '';
    $target_locale = '';
    
    if (function_exists('pll_the_languages')) {
        $pll_languages = pll_the_languages(array('raw' => 1, 'hide_if_empty' => 0));
        if (!empty($pll_languages)) {
            $current_locale = isset($pll_languages[$current_language]['locale']) ? $pll_languages[$current_language]['locale'] : $current_language;
            $target_locale = isset($pll_languages[$target_language]['locale']) ? $pll_languages[$target_language]['locale'] : $target_language;
        }
    }
    
    // Fallback to language codes if locales not available
    if (empty($current_locale)) $current_locale = $current_language;
    if (empty($target_locale)) $target_locale = $target_language;
    
    ptp_debug_log('Current locale: ' . $current_locale);
    ptp_debug_log('Target locale: ' . $target_locale);

    // Don't load if languages are equivalent (same base language)
    if (ptp_are_languages_equivalent($current_locale, $target_locale)) {
        ptp_debug_log('Languages are equivalent, skipping translation');
        return;
    }

    // Convert language codes for Google Translate
    $source_lang = ptp_get_google_language_code($current_language);
    $target_lang = ptp_get_google_language_code($target_language);

    ptp_debug_log('Converted Language Codes:');
    ptp_debug_log('Source Language (Google): ' . $source_lang);
    ptp_debug_log('Target Language (Google): ' . $target_lang);

    // Prepare data for localization
    $localize_data = array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ptp_nonce'),
        'currentLanguage' => $source_lang,
        'targetLanguage' => $target_lang,
        'currentLocale' => $current_locale,
        'targetLocale' => $target_locale,
        'isTargetLanguage' => $current_language === $target_language,
        'debug' => ptp_is_debug_enabled()
    );

    ptp_debug_log('Localizing with data: ' . print_r($localize_data, true));

    // Add cache busting
    $cache_bust = time();
    $version = PTP_VERSION . '.' . $cache_bust;

    // Enqueue CSS
    wp_enqueue_style(
        'polylang-translation-preview-admin',
        PTP_PLUGIN_URL . 'css/admin.css',
        array(),
        $version
    );

    // Enqueue JavaScript
    wp_enqueue_script(
        'polylang-translation-preview-admin',
        PTP_PLUGIN_URL . 'js/admin.js',
        array('jquery'),
        $version,
        true
    );

    // Localize script with translations
    wp_localize_script(
        'polylang-translation-preview-admin',
        'ptpData',
        $localize_data
    );

    // Add inline script to verify loading (only if debug is enabled)
    if (ptp_is_debug_enabled()) {
        wp_add_inline_script('polylang-translation-preview-admin', 'console.log("PTP Debug - Inline script loaded");', 'before');
    }
}
add_action('admin_enqueue_scripts', 'ptp_enqueue_admin_assets', 20);

// AJAX handler for getting translations
function ptp_get_translation() {
    check_ajax_referer('ptp_nonce', 'nonce');

    // Don't use sanitize_text_field as it strips HTML - use wp_kses and stripslashes for safe HTML
    $text = isset($_POST['text']) ? wp_kses_post(stripslashes($_POST['text'])) : '';
    $source_language = isset($_POST['source_language']) ? sanitize_text_field($_POST['source_language']) : '';
    $target_language = isset($_POST['target_language']) ? sanitize_text_field($_POST['target_language']) : '';
    
    ptp_debug_log('Translation Request Details:');
    ptp_debug_log('Text: ' . $text);
    ptp_debug_log('Source Language (from request): ' . $source_language);
    ptp_debug_log('Target Language (from request): ' . $target_language);
    
    if (empty($text) || empty($source_language) || empty($target_language)) {
        ptp_debug_log('Invalid request: Missing required parameters');
        wp_send_json_error('Invalid request: Missing required parameters');
        return;
    }

    // Check if source and target languages are the same or equivalent
    if (ptp_are_languages_equivalent($source_language, $target_language)) {
        ptp_debug_log('Invalid request: Source and target languages are equivalent');
        ptp_debug_log('Source Language: ' . $source_language);
        ptp_debug_log('Target Language: ' . $target_language);
        wp_send_json_error('Cannot translate between equivalent language variants (e.g., en_GB ↔ en_CA)');
        return;
    }

    $api_key = ptp_get_api_key();
    if (empty($api_key)) {
        ptp_debug_log('Invalid request: API key not configured');
        wp_send_json_error('Invalid request: API key not configured');
        return;
    }

    // Convert locales to Google Translate language codes
    $google_source = ptp_convert_locale_to_google_code($source_language);
    $google_target = ptp_convert_locale_to_google_code($target_language);
    
    ptp_debug_log('Google Translate codes:');
    ptp_debug_log('Source: ' . $source_language . ' -> ' . $google_source);
    ptp_debug_log('Target: ' . $target_language . ' -> ' . $google_target);

    // Detect if content contains HTML
    $is_html = strip_tags($text) !== $text;
    ptp_debug_log('Content contains HTML: ' . ($is_html ? 'Yes' : 'No'));

    // Use Google Translate API
    $url = 'https://translation.googleapis.com/language/translate/v2';
    $request_body = array(
        'q' => $text,
        'source' => $google_source,
        'target' => $google_target,
        'key' => $api_key
    );

    // Add format parameter if HTML is detected
    if ($is_html) {
        $request_body['format'] = 'html';
        ptp_debug_log('Using HTML format for translation');
    } else {
        ptp_debug_log('Using text format for translation');
    }

    $args = array(
        'body' => $request_body
    );

    ptp_debug_log('Making API request to: ' . $url);
    ptp_debug_log('Request args: ' . print_r($args, true));

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        ptp_debug_log('API Error: ' . $response->get_error_message());
        wp_send_json_error('Translation API error: ' . $response->get_error_message());
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    ptp_debug_log('API Response: ' . print_r($body, true));

    if (isset($body['data']['translations'][0]['translatedText'])) {
        $translation = $body['data']['translations'][0]['translatedText'];
        
        // Sanitize the translated content if it contains HTML
        if ($is_html) {
            $translation = wp_kses_post($translation);
            ptp_debug_log('Sanitized HTML translation: ' . $translation);
        }
        
        wp_send_json_success(array(
            'translation' => $translation
        ));
    } else {
        ptp_debug_log('Translation failed: ' . print_r($body, true));
        wp_send_json_error('Translation failed: ' . (isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error'));
    }
}
add_action('wp_ajax_ptp_get_translation', 'ptp_get_translation');

// Add settings page for API key
function ptp_add_settings_page() {
    add_options_page(
        'Polylang Translation Preview Settings',
        'Translation Preview',
        'manage_options',
        'polylang-translation-preview',
        'ptp_settings_page'
    );
}
add_action('admin_menu', 'ptp_add_settings_page');

// Settings page callback
function ptp_settings_page() {
    if (isset($_POST['ptp_save_settings'])) {
        check_admin_referer('ptp_settings');
        
        // Use encryption for API key
        $api_key = sanitize_text_field($_POST['ptp_google_translate_api_key']);
        ptp_set_api_key($api_key);
        
        update_option('ptp_target_language', sanitize_text_field($_POST['ptp_target_language']));
        update_option('ptp_debug_logging', isset($_POST['ptp_debug_logging']) ? 1 : 0);
        echo '<div class="notice notice-success"><p>Settings saved securely.</p></div>';
    }

    $api_key = ptp_get_api_key();
    // Get default target language - use Polylang's default language if available
    $default_target = 'en'; // Fallback to English
    if (function_exists('pll_default_language')) {
        $default_target = pll_default_language();
    }
    $target_language = get_option('ptp_target_language', $default_target);
    $debug_logging = get_option('ptp_debug_logging', false);
    
    // Get available languages from Polylang
    $available_languages = array();
    
    if (function_exists('pll_languages_list')) {
        // Get all configured languages from Polylang
        $pll_languages = pll_the_languages(array('raw' => 1, 'hide_if_empty' => 0));
        
        if (!empty($pll_languages)) {
            foreach ($pll_languages as $lang_code => $lang_data) {
                $available_languages[$lang_code] = $lang_data['name'];
            }
        }
    }
    
    // Fallback to hardcoded list if Polylang languages not available
    if (empty($available_languages)) {
        $available_languages = array(
            'fi' => 'Finnish',
            'sv' => 'Swedish',
            'no' => 'Norwegian',
            'da' => 'Danish',
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'nl' => 'Dutch',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'zh' => 'Chinese',
            'ko' => 'Korean'
        );
    }
    ?>
    <div class="wrap">
        <h1>Polylang Translation Preview Settings</h1>
        <form method="post" action="">
            <?php wp_nonce_field('ptp_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ptp_google_translate_api_key">Google Translate API Key</label>
                    </th>
                    <td>
                        <div class="ptp-api-key-wrapper">
                            <input type="password" 
                                   id="ptp_google_translate_api_key" 
                                   name="ptp_google_translate_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>" 
                                   class="regular-text">
                            <button type="button" 
                                    class="ptp-toggle-api-key button button-secondary" 
                                    aria-label="<?php esc_attr_e('Show/Hide API Key', 'polylang-translation-preview'); ?>"
                                    title="<?php esc_attr_e('Toggle API key visibility', 'polylang-translation-preview'); ?>">
                                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                <span class="dashicons dashicons-hidden" aria-hidden="true"></span>
                            </button>
                        </div>
                        <div class="description ptp-setup-instructions">
                            <p><strong>To get your Google Cloud Translation API key:</strong></p>
                            <ol>
                                <li><a href="https://console.cloud.google.com/" target="_blank">Go to Google Cloud Console</a></li>
                                <li>Create a new project or select an existing one</li>
                                <li>Enable the Cloud Translation API:
                                    <ul>
                                        <li>Go to <strong>APIs & Services → Library</strong></li>
                                        <li>Search for "Cloud Translation API"</li>
                                        <li>Click on it and press <strong>Enable</strong></li>
                                    </ul>
                                </li>
                                <li>Create credentials:
                                    <ul>
                                        <li>Go to <strong>APIs & Services → Credentials</strong></li>
                                        <li>Click <strong>Create Credentials → API Key</strong></li>
                                        <li>Copy the generated API key</li>
                                    </ul>
                                </li>
                                <li>Set up billing (required for Translation API)</li>
                                <li><strong>Security (Recommended):</strong> Restrict your API key:
                                    <ul>
                                        <li>In Credentials, click on your API key</li>
                                        <li>Under "API restrictions", select "Restrict key"</li>
                                        <li>Choose "Cloud Translation API" only</li>
                                        <li>Under "Website restrictions", add your domain</li>
                                    </ul>
                                </li>
                                <li>Paste your API key in the field above</li>
                            </ol>
                            <p><strong>💰 Pricing:</strong> Google Cloud Translation API requires a billing account but offers <strong>free usage up to 500,000 characters per month</strong>.</p>
                            <p><strong>🔒 Security:</strong> Restricting your API key prevents unauthorized usage and protects your billing account.</p>
                            <p><strong>🛡️ Encryption:</strong> Your API key is automatically encrypted using AES-256-CBC encryption before being stored in the database.</p>
                            <p><a href="https://cloud.google.com/translate/docs/setup" target="_blank">📚 Full Setup Guide</a> | <a href="https://cloud.google.com/translate/pricing" target="_blank">💰 Pricing Details</a> | <a href="https://cloud.google.com/docs/authentication/api-keys#restricting_an_api_key" target="_blank">🔒 API Key Security</a></p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ptp_target_language">Default Target Language</label>
                    </th>
                    <td>
                        <select id="ptp_target_language" name="ptp_target_language" class="regular-text">
                            <?php foreach ($available_languages as $code => $name) : ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($target_language, $code); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Select the default language to translate to when previewing translations. This list shows the languages configured in your Polylang settings.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ptp_debug_logging">Enable Debug Logging</label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="ptp_debug_logging" 
                               name="ptp_debug_logging" 
                               value="1" 
                               <?php checked($debug_logging, 1); ?>>
                        <p class="description">
                            Enable console logging and debug output for troubleshooting. This will log translation requests, language detection, and other debug information to the browser console and PHP error log.
                        </p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="ptp_save_settings" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // API Key visibility toggle
        $('.ptp-toggle-api-key').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $wrapper = $button.closest('.ptp-api-key-wrapper');
            const $input = $wrapper.find('input');
            const isHidden = $wrapper.hasClass('ptp-api-key-hidden');
            
            if (isHidden) {
                // Show the API key
                $input.attr('type', 'password');
                $wrapper.removeClass('ptp-api-key-hidden');
                $button.attr('title', '<?php esc_attr_e('Hide API key', 'polylang-translation-preview'); ?>');
            } else {
                // Hide the API key
                $input.attr('type', 'text');
                $wrapper.addClass('ptp-api-key-hidden');
                $button.attr('title', '<?php esc_attr_e('Show API key', 'polylang-translation-preview'); ?>');
            }
            
            // Move cursor to end of input
            const input = $input[0];
            if (input.setSelectionRange) {
                const len = input.value.length;
                input.setSelectionRange(len, len);
            }
        });
        
        // Initialize with hidden state if there's a value
        const $apiKeyInput = $('#ptp_google_translate_api_key');
        if ($apiKeyInput.val().length > 0) {
            // Start in password mode (hidden)
            $apiKeyInput.attr('type', 'password');
        }
    });
    </script>
    <?php
}
