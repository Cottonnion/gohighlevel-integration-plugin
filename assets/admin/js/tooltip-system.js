/**
 * Universal Tooltip System - tooltip-system.js
 * A global tooltip system that creates tooltips for any element with data-ghl-tooltip attribute
 * 
 * @package    GHL_CRM_Integration
 * @subpackage Assets/Admin/JS
 * 
 * Usage: <span data-ghl-tooltip="This is a tooltip explanation">?</span>
 */

(function() {
    'use strict';

    // Configuration
    const TOOLTIP_CONFIG = {
        attribute: 'data-ghl-tooltip',
        className: 'ghl-tooltip',
        showDelay: 200,
        hideDelay: 100,
        offset: 8,
        maxWidth: 280,
        zIndex: 999999,
        animations: true,
        smartPositioning: true
    };

    // Tooltip state
    let activeTooltip = null;
    let activeElement = null;
    let showTimeout = null;
    let hideTimeout = null;
    let mouseX = 0;
    let mouseY = 0;
    let suppressClick = false;
    let dismissHandlersBound = false;

    // Track mouse position for dynamic positioning
    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
    });

    /**
     * Create tooltip element
     */
    function createTooltip(content) {
        const tooltip = document.createElement('div');
        tooltip.className = TOOLTIP_CONFIG.className;
        tooltip.innerHTML = content;
        tooltip.style.cssText = `
            position: fixed;
            background: rgba(30, 41, 59, 0.98);
            color: white;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 400;
            line-height: 1.5;
            max-width: ${TOOLTIP_CONFIG.maxWidth}px;
            word-wrap: break-word;
            z-index: ${TOOLTIP_CONFIG.zIndex};
            pointer-events: none;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2), 0 2px 4px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            opacity: 0;
            transform: scale(0.85) translateY(6px);
            transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        `;
        
        // Add arrow
        const arrow = document.createElement('div');
        arrow.className = 'ghl-tooltip-arrow';
        arrow.style.cssText = `
            position: absolute;
            width: 0;
            height: 0;
            border: 6px solid transparent;
            border-top-color: rgba(30, 41, 59, 0.98);
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
        `;
        tooltip.appendChild(arrow);
        
        return tooltip;
    }

    /**
     * Position tooltip relative to element
     */
    function positionTooltip(tooltip, element) {
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        // Position tooltip centered above the element, slightly to the left and higher up
        let x = rect.left + (rect.width / 2) - (tooltipRect.width / 2) - 24;
        let y = rect.top - tooltipRect.height - TOOLTIP_CONFIG.offset - 23;
        
        // Smart positioning - adjust if tooltip goes off screen
        if (TOOLTIP_CONFIG.smartPositioning) {
            // Horizontal adjustments
            if (x + tooltipRect.width > viewportWidth - 10) {
                x = viewportWidth - tooltipRect.width - 10;
            }
            if (x < 10) {
                x = 10;
            }
            
            // Vertical adjustments - show below if not enough space above
            if (y < 10) {
                y = rect.bottom + TOOLTIP_CONFIG.offset;
                // Flip arrow
                const arrow = tooltip.querySelector('.ghl-tooltip-arrow');
                if (arrow) {
                    arrow.style.cssText = `
                        position: absolute;
                        width: 0;
                        height: 0;
                        border: 6px solid transparent;
                        border-bottom-color: rgba(30, 41, 59, 0.98);
                        top: -12px;
                        left: 50%;
                        transform: translateX(-50%);
                    `;
                }
            }
            
            // If still off screen at bottom, force above
            if (y + tooltipRect.height > viewportHeight - 10) {
                y = rect.top - tooltipRect.height - TOOLTIP_CONFIG.offset;
            }
        }
        
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    }

    /**
     * Show tooltip
     */
    function showTooltip(element, content) {
        // Clear any existing timeouts
        clearTimeout(hideTimeout);
        clearTimeout(showTimeout);
        
        showTimeout = setTimeout(() => {
            // Hide any existing tooltip
            hideTooltip();
            
            // Create new tooltip
            activeTooltip = createTooltip(content);
            document.body.appendChild(activeTooltip);

            if ((!Number.isFinite(mouseX) || !Number.isFinite(mouseY)) || (mouseX === 0 && mouseY === 0)) {
                const rect = element.getBoundingClientRect();
                mouseX = rect.left + rect.width / 2;
                mouseY = rect.top + rect.height / 2;
            }

            activeElement = element;
            element.classList.add('has-ghl-tooltip-active');
            
            // Position tooltip
            positionTooltip(activeTooltip, element);
            
            // Show with animation
            requestAnimationFrame(() => {
                if (activeTooltip) {
                    activeTooltip.style.opacity = '1';
                    activeTooltip.style.transform = 'scale(1) translateY(0)';
                }
            });
        }, TOOLTIP_CONFIG.showDelay);
    }

    /**
     * Hide tooltip
     */
    function hideTooltip() {
        clearTimeout(showTimeout);
        
        if (activeTooltip) {
            const tooltip = activeTooltip;
            
            if (TOOLTIP_CONFIG.animations) {
                tooltip.style.opacity = '0';
                tooltip.style.transform = 'scale(0.85) translateY(6px)';
                
                hideTimeout = setTimeout(() => {
                    if (tooltip.parentNode) {
                        tooltip.parentNode.removeChild(tooltip);
                    }
                }, 200);
            } else {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
            }
            
            activeTooltip = null;
        }

        if (activeElement) {
            activeElement.classList.remove('has-ghl-tooltip-active');
            activeElement = null;
        }
    }

    /**
     * Handle mouse enter
     */
    function handleMouseEnter(event) {
        const element = event.currentTarget;
        const content = element.getAttribute(TOOLTIP_CONFIG.attribute);
        
        if (content && content.trim()) {
            showTooltip(element, content.trim());
        }
    }

    /**
     * Handle mouse leave
     */
    function handleMouseLeave(event) {
        const element = event.currentTarget;
        element.classList.remove('has-ghl-tooltip-active');
        
        clearTimeout(showTimeout);
        hideTimeout = setTimeout(hideTooltip, TOOLTIP_CONFIG.hideDelay);
    }

    /**
     * Handle click interactions (desktop and touch fallbacks)
     */
    function handleTooltipClick(event) {
        if (event.type === 'click' && suppressClick) {
            event.preventDefault();
            return;
        }

        const element = event.currentTarget;
        const content = element.getAttribute(TOOLTIP_CONFIG.attribute);

        if (!content || !content.trim()) {
            return;
        }

        event.preventDefault();

        if (typeof event.clientX === 'number' && typeof event.clientY === 'number') {
            mouseX = event.clientX;
            mouseY = event.clientY;
        } else if (event instanceof KeyboardEvent) {
            const rect = element.getBoundingClientRect();
            mouseX = rect.left + rect.width / 2;
            mouseY = rect.top + rect.height / 2;
        }

        if (activeElement === element && activeTooltip) {
            hideTooltip();
            return;
        }

        showTooltip(element, content.trim());
    }

    /**
     * Handle touch interactions to suppress checkbox/label toggles
     */
    function handleTooltipTouch(event) {
        // Allow preventDefault to stop label activation on touch devices
        event.preventDefault();

        suppressClick = true;
        setTimeout(() => {
            suppressClick = false;
        }, 400);

        if (event.touches && event.touches[0]) {
            mouseX = event.touches[0].clientX;
            mouseY = event.touches[0].clientY;
        }

        handleTooltipClick(event);
    }

    /**
     * Handle keyboard activation for accessibility
     */
    function handleTooltipKeyDown(event) {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar' || event.key === 'Space') {
            handleTooltipClick(event);
        }
    }

    /**
     * Handle document clicks/touches to dismiss active tooltip
     */
    function handleDocumentInteraction(event) {
        if (!activeTooltip) {
            return;
        }

        const target = event.target instanceof Element ? event.target : null;

        if (activeElement && target && target.closest(`[${TOOLTIP_CONFIG.attribute}]`) === activeElement) {
            return;
        }

        hideTooltip();
    }

    /**
     * Handle global keyboard interactions (e.g., Escape to close)
     */
    function handleDocumentKeyDown(event) {
        if (!activeTooltip) {
            return;
        }

        if (event.key === 'Escape' || event.key === 'Esc') {
            hideTooltip();
        }
    }

    /**
     * Set up global listeners for dismissing tooltips
     */
    function setupDismissHandlers() {
        if (dismissHandlersBound) {
            return;
        }

        document.addEventListener('click', handleDocumentInteraction, true);
        document.addEventListener('touchstart', handleDocumentInteraction, { passive: true, capture: true });
        document.addEventListener('keydown', handleDocumentKeyDown);
        dismissHandlersBound = true;
    }

    /**
     * Initialize tooltips for existing elements
     */
    function initializeTooltips() {
        const elements = document.querySelectorAll(`[${TOOLTIP_CONFIG.attribute}]`);
        
        elements.forEach(element => {
            // Remove existing listeners to prevent duplicates
            element.removeEventListener('mouseenter', handleMouseEnter);
            element.removeEventListener('mouseleave', handleMouseLeave);
            element.removeEventListener('click', handleTooltipClick);
            element.removeEventListener('touchstart', handleTooltipTouch);
            element.removeEventListener('keydown', handleTooltipKeyDown);
            
            // Add new listeners
            element.addEventListener('mouseenter', handleMouseEnter);
            element.addEventListener('mouseleave', handleMouseLeave);
            element.addEventListener('click', handleTooltipClick);
            element.addEventListener('touchstart', handleTooltipTouch, { passive: false });
            element.addEventListener('keydown', handleTooltipKeyDown);
            
            // Add hover cursor if not already set
            if (!element.style.cursor && !element.classList.contains('ghl-tooltip-icon')) {
                element.style.cursor = 'help';
            }

            if (typeof element.tabIndex === 'number' && element.tabIndex < 0) {
                element.setAttribute('tabindex', '0');
            }
        });
    }

    /**
     * Observer for dynamically added elements
     */
    function setupMutationObserver() {
        const observer = new MutationObserver((mutations) => {
            let shouldReinit = false;
            
            mutations.forEach((mutation) => {
                // Check for added nodes
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            if (node.hasAttribute && node.hasAttribute(TOOLTIP_CONFIG.attribute)) {
                                shouldReinit = true;
                            }
                            // Check children
                            if (node.querySelectorAll) {
                                const children = node.querySelectorAll(`[${TOOLTIP_CONFIG.attribute}]`);
                                if (children.length > 0) {
                                    shouldReinit = true;
                                }
                            }
                        }
                    });
                }
                
                // Check for attribute changes
                if (mutation.type === 'attributes' && 
                    mutation.attributeName === TOOLTIP_CONFIG.attribute) {
                    shouldReinit = true;
                }
            });
            
            if (shouldReinit) {
                // Debounce reinitalization
                clearTimeout(window.ghlTooltipReinitTimeout);
                window.ghlTooltipReinitTimeout = setTimeout(initializeTooltips, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: [TOOLTIP_CONFIG.attribute]
        });
    }

    /**
     * Handle scroll and resize events
     */
    function handleWindowEvents() {
        window.addEventListener('scroll', () => {
            if (activeTooltip) {
                hideTooltip();
            }
        }, { passive: true });
        
        window.addEventListener('resize', () => {
            hideTooltip();
        });
    }

    /**
     * Add global CSS for enhanced styling
     */
    function addGlobalStyles() {
        const styleId = 'ghl-tooltip-system-styles';
        
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.textContent = `
                .has-ghl-tooltip-active {
                    position: relative;
                }
                
                .ghl-tooltip-icon {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 16px;
                    height: 16px;
                    border-radius: 50%;
                    background: #94a3b8;
                    color: white;
                    font-size: 11px;
                    font-weight: 600;
                    cursor: help;
                    margin-left: 6px;
                    transition: all 0.2s ease;
                    flex-shrink: 0;
                }
                
                .ghl-tooltip-icon:hover {
                    background: #3b82f6;
                    transform: scale(1.1);
                }
                
                /* Reduced motion support */
                @media (prefers-reduced-motion: reduce) {
                    .${TOOLTIP_CONFIG.className} {
                        transition: none !important;
                    }
                    
                    .ghl-tooltip-icon {
                        transition: none !important;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    /**
     * Public API
     */
    window.GHLTooltip = {
        init: initializeTooltips,
        hide: hideTooltip,
        config: TOOLTIP_CONFIG,
        
        // Method to manually show tooltip
        show: function(element, content, options = {}) {
            const mergedOptions = { ...TOOLTIP_CONFIG, ...options };
            showTooltip(element, content);
        },
        
        // Method to update configuration
        configure: function(options) {
            Object.assign(TOOLTIP_CONFIG, options);
        }
    };

    /**
     * Initialize when DOM is ready
     */
    function initialize() {
        addGlobalStyles();
        initializeTooltips();
        setupMutationObserver();
        handleWindowEvents();
        setupDismissHandlers();
    }

    // Initialize based on document state
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

    // Also initialize on window load as fallback
    window.addEventListener('load', initialize);

})();
