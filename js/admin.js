jQuery(document).ready(function($) {
    // Function to log debug messages only when debug is enabled
    function debugLog(message, data = null) {
        if (typeof ptpData !== 'undefined' && ptpData.debug) {
            if (data !== null) {
                console.log('PTP Debug - ' + message, data);
            } else {
                console.log('PTP Debug - ' + message);
            }
        }
    }
    
    // Debug logging
    debugLog('Script loaded');
    debugLog('ptpData available: ' + (typeof ptpData !== 'undefined'));
    if (typeof ptpData !== 'undefined') {
        debugLog('ptpData:', ptpData);
    }

    // Check if required data is available
    if (typeof ptpData === 'undefined') {
        console.error('Polylang Translation Preview: Required data not loaded');
        return;
    }

    let translationCache = {};
    let selectionTimeout = null;
    let lastSelectedText = '';
    let isSelecting = false;
    const $tooltip = $('<div class="ptp-translation-tooltip"></div>').appendTo('body');
    
    // Function to check if two languages are equivalent (same base language)
    function areLanguagesEquivalent(lang1, lang2) {
        if (lang1 === lang2) return true;
        
        // Get base language codes (language part before underscore or dash)
        const base1 = lang1.toLowerCase().split(/[_-]/)[0];
        const base2 = lang2.toLowerCase().split(/[_-]/)[0];
        
        return base1 === base2;
    }

    // Function to get selected text from TinyMCE or regular selection
    function getSelectedText(e) {
        // First check if we're in TinyMCE Visual editor
        if (typeof window.tinyMCE !== 'undefined' && window.tinyMCE.activeEditor) {
            const editor = window.tinyMCE.activeEditor;
            // Get HTML content to preserve formatting
            const content = editor.selection.getContent({format: 'html'});
            debugLog('TinyMCE HTML content:', content);
            return content.trim();
        }
        
        // Fallback to regular selection - try to get HTML if possible
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
            const range = selection.getRangeAt(0);
            const fragment = range.cloneContents();
            const div = document.createElement('div');
            div.appendChild(fragment);
            const htmlContent = div.innerHTML;
            
            // If we have HTML content, use it; otherwise fall back to text
            if (htmlContent && htmlContent !== selection.toString()) {
                debugLog('Regular selection HTML:', htmlContent);
                return htmlContent.trim();
            }
        }
        
        // Final fallback to plain text
        const textContent = selection.toString();
        debugLog('Regular selection text:', textContent);
        return textContent.trim();
    }

    // Function to get element offset considering scroll position
    function getElementOffset(element) {
        const rect = element.getBoundingClientRect();
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        return {
            top: rect.top + scrollTop,
            left: rect.left + scrollLeft,
            width: rect.width,
            height: rect.height,
            bottom: rect.bottom + scrollTop
        };
    }

    // Function to get selection coordinates
    function getSelectionCoordinates(e) {
        // First check if we're in TinyMCE Visual editor
        if (typeof window.tinyMCE !== 'undefined' && window.tinyMCE.activeEditor) {
            const editor = window.tinyMCE.activeEditor;
            const selection = editor.selection;
            const bookmark = selection.getBookmark();
            const range = selection.getRng();
            const rects = range.getClientRects();
            
            if (rects.length === 0) {
                return null;
            }
            
            // Get the first rectangle of the selection
            const rect = rects[0];
            
            // Get iframe position
            const $iframe = $('#' + editor.id + '_ifr');
            const iframeOffset = $iframe.offset();
            
            // Calculate position relative to the page
            return {
                left: iframeOffset.left + rect.left,
                top: iframeOffset.top + rect.top,
                width: rect.width,
                height: rect.height,
                bottom: iframeOffset.top + rect.bottom
            };
        }
        
        // Fallback to regular selection
        const selection = window.getSelection();
        if (!selection.rangeCount) return null;

        const range = selection.getRangeAt(0);
        const rect = range.getBoundingClientRect();
        
        if (rect.width === 0 && rect.height === 0) {
            const node = range.startContainer;
            const element = node.nodeType === 3 ? node.parentElement : node;
            const elementRect = getElementOffset(element);
            
            const textarea = element.tagName === 'TEXTAREA' ? element : null;
            if (textarea) {
                const textBeforeCursor = textarea.value.substring(0, textarea.selectionStart);
                const lines = textBeforeCursor.split('\n');
                const lineHeight = parseInt(window.getComputedStyle(textarea).lineHeight);
                const currentLine = lines.length;
                
                return {
                    left: elementRect.left + 10,
                    top: elementRect.top + (currentLine * lineHeight),
                    width: 100,
                    height: lineHeight,
                    bottom: elementRect.top + (currentLine * lineHeight) + lineHeight
                };
            }
            
            return elementRect;
        }
        
        return {
            left: rect.left + window.pageXOffset,
            top: rect.top + window.pageYOffset,
            width: rect.width,
            height: rect.height,
            bottom: rect.bottom + window.pageYOffset
        };
    }

    // Function to get translation
    function getTranslation(text) {
        debugLog('Getting translation for:', text);
        const cacheKey = text;
        
        if (translationCache[cacheKey]) {
            debugLog('Found in cache:', translationCache[cacheKey]);
            return Promise.resolve(translationCache[cacheKey]);
        }

        // Check if source and target languages are the same or equivalent
        if (ptpData.currentLanguage === ptpData.targetLanguage) {
            debugLog('Source and target languages are the same, skipping translation');
            return Promise.resolve('⚠️ Cannot translate to the same language');
        }
        
        // Check if source and target locales are equivalent (same base language)
        if (ptpData.currentLocale && ptpData.targetLocale && 
            areLanguagesEquivalent(ptpData.currentLocale, ptpData.targetLocale)) {
            debugLog('Source and target locales are equivalent, skipping translation');
            return Promise.resolve('⚠️ Cannot translate between equivalent language variants (e.g., en_GB ↔ en_CA)');
        }

        const requestData = {
            action: 'ptp_get_translation',
            nonce: ptpData.nonce,
            text: text,
            source_language: ptpData.currentLocale || ptpData.currentLanguage,
            target_language: ptpData.targetLocale || ptpData.targetLanguage
        };
        debugLog('Making API request with:', requestData);

        return $.ajax({
            url: ptpData.ajaxurl,
            type: 'POST',
            data: requestData,
            dataType: 'json'
        }).then(function(response) {
            debugLog('API Response:', response);
            if (response.success && response.data && response.data.translation) {
                translationCache[cacheKey] = response.data.translation;
                return response.data.translation;
            } else {
                throw new Error(response.data || 'Translation failed');
            }
        }).catch(function(error) {
            console.error('Translation error:', error); // Keep error logging always visible
            return '⚠️ ' + (error.message || 'Error loading translation. Please try again.');
        });
    }

    // Function to handle text selection
    function handleTextSelection(e) {
        debugLog('Handling text selection event: ' + e.type);
        
        const selectedText = getSelectedText(e);
        debugLog('Selected text:', selectedText);

        if (!selectedText) {
            debugLog('No text selected, hiding tooltip');
            $tooltip.hide();
            return;
        }

        // Get selection coordinates
        const rect = getSelectionCoordinates(e);
        if (!rect) {
            debugLog('Could not get selection coordinates');
            return;
        }
        debugLog('Selection coordinates:', rect);

        // Position tooltip
        const position = positionTooltip(rect);
        debugLog('Tooltip position:', position);
        
        // Show loading state
        $tooltip
            .html('<span class="ptp-loading">🔄 Translating...</span>')
            .css({
                top: position.top,
                left: position.left,
                display: 'block'
            })
            .addClass('force-show');

        // Get translation
        getTranslation(selectedText).then(function(translation) {
            if ($tooltip.is(':visible')) {
                debugLog('Translation received:', translation);
                // Use html() to render HTML content properly
                $tooltip.html(translation);
                
                // Reposition after content is loaded
                const newPosition = positionTooltip(rect);
                $tooltip.css({
                    top: newPosition.top,
                    left: newPosition.left
                });
            }
        });
    }

    // Function to position tooltip within viewport
    function positionTooltip(rect) {
        const viewportWidth = $(window).width();
        const viewportHeight = $(window).height();
        const tooltipWidth = $tooltip.outerWidth();
        const tooltipHeight = $tooltip.outerHeight();

        // Calculate initial position
        let left = rect.left + (rect.width / 2);
        let top = rect.bottom + 10;

        // Adjust horizontal position if tooltip would go off screen
        if (left + (tooltipWidth / 2) > viewportWidth) {
            left = viewportWidth - tooltipWidth - 10;
        }
        if (left < 10) {
            left = 10;
        }

        // Adjust vertical position if tooltip would go off screen
        if (top + tooltipHeight > window.scrollY + viewportHeight) {
            top = rect.top - tooltipHeight - 10;
        }

        return { top, left };
    }

    // Function to debounce selection handling
    function debouncedHandleSelection(e) {
        clearTimeout(selectionTimeout);
        
        // Don't log during active selection
        if (!isSelecting) {
            debugLog('Selection ended, preparing translation');
            isSelecting = true;
        }
        
        selectionTimeout = setTimeout(() => {
            isSelecting = false;
            const currentText = getSelectedText(e);
            
            // Only handle if text has changed
            if (currentText !== lastSelectedText) {
                lastSelectedText = currentText;
                handleTextSelection(e);
            }
        }, 300); // Reduced to 300ms for better responsiveness
    }

    // Function to initialize TinyMCE events
    function initTinyMCE() {
        if (typeof window.tinyMCE !== 'undefined' && window.tinyMCE.activeEditor) {
            const editor = window.tinyMCE.activeEditor;
            
            // Remove existing event listeners if any
            editor.off('mouseup');
            editor.off('keyup');
            editor.off('SelectionChange');
            
            let mouseDown = false;
            
            // Track mouse state
            editor.on('mousedown', function() {
                mouseDown = true;
                isSelecting = true;
            });
            
            // Add event listeners
            editor.on('mouseup', function(e) {
                mouseDown = false;
                isSelecting = false;
                debugLog('TinyMCE selection complete');
                handleTextSelection(e);
            });
            
            editor.on('keyup', function(e) {
                if (e.key === 'Shift' || e.key === 'Control' || e.key === 'Alt' || e.key === 'Meta') {
                    return;
                }
                debouncedHandleSelection(e);
            });
            
            editor.on('SelectionChange', function(e) {
                // Only handle selection changes if we're not actively selecting with mouse
                if (!mouseDown) {
                    debouncedHandleSelection(e);
                }
            });
            
            debugLog('TinyMCE events initialized for editor: ' + editor.id);
        }
    }

    // Initialize TinyMCE when it's ready
    if (typeof window.tinyMCE !== 'undefined') {
        // Handle existing editor
        if (window.tinyMCE.activeEditor) {
            initTinyMCE();
        }
        
        // Watch for new editors
        window.tinyMCE.on('AddEditor', function(e) {
            debugLog('TinyMCE editor added: ' + e.editor.id);
            // Wait for editor to be fully initialized
            e.editor.on('init', function() {
                debugLog('TinyMCE editor initialized: ' + e.editor.id);
                initTinyMCE();
            });
        });
    }

    // Handle regular text selection
    $(document).on('mouseup', function(e) {
        // Only handle if not in TinyMCE
        if (!$(e.target).closest('.mce-content-body, #content_ifr').length) {
            handleTextSelection(e);
        }
    });

    // Hide tooltip and clear selection timeout when clicking outside or on escape key
    $(document).on('mousedown keyup', function(e) {
        if (e.type === 'keyup' && e.key === 'Escape' || 
            e.type === 'mousedown' && !$(e.target).closest('.ptp-translation-tooltip').length) {
            clearTimeout(selectionTimeout);
            $tooltip.hide().removeClass('force-show');
        }
    });

    // Clear cache when post is saved
    $('#post').on('submit', function() {
        debugLog('Post form submitted, clearing translation cache');
        translationCache = {};
    });

    // Log initialization complete
    debugLog('Initialization complete');
}); 