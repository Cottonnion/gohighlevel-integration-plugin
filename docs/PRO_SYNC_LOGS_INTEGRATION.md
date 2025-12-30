# PRO Sync Logs Integration Guide

This document explains how the PRO plugin should implement the detailed sync logs functionality.

## Overview

The free version now shows basic sync logs (date, type, item ID, action, status) but the detailed view functionality has been moved to PRO plugin only.

## PRO Plugin Implementation

### 1. Filter Hook for Details Button

The PRO plugin should hook into the `ghl_crm_sync_log_details_button` filter to render the detailed view button:

```php
/**
 * Render detailed view button for sync logs
 */
add_filter( 'ghl_crm_sync_log_details_button', function( $button_html, $log, $details_json ) {
    // Check if PRO is active and user has permission
    if ( ! current_user_can( 'manage_options' ) ) {
        return $button_html;
    }
    
    return '<button type="button" class="ghl-button ghl-button-small ghl-button-secondary ghl-view-details" data-details="' . esc_attr( $details_json ) . '">
        <span class="dashicons dashicons-visibility"></span>
        ' . esc_html__( 'View Details', 'ghl-crm-pro' ) . '
    </button>';
}, 10, 3 );
```

### 2. Action Hook for Modal HTML

The PRO plugin should hook into the `ghl_crm_sync_logs_after_content` action to add the modal HTML:

```php
/**
 * Add detailed view modal to sync logs page
 */
add_action( 'ghl_crm_sync_logs_after_content', function() {
    ?>
    <!-- Details Modal -->
    <div id="ghl-details-modal">
        <div class="ghl-modal-content">
            <div class="ghl-modal-header">
                <h2 class="ghl-modal-title">
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e( 'Sync Log Details', 'ghl-crm-pro' ); ?>
                </h2>
                <button type="button" id="ghl-close-modal" class="ghl-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ghl-crm-pro' ); ?>">
                    &times;
                </button>
            </div>
            <div class="ghl-modal-body">
                <pre id="ghl-details-content" class="ghl-modal-details"></pre>
            </div>
        </div>
    </div>
    <?php
});
```

### 3. JavaScript Integration

The PRO plugin should provide JavaScript functionality by creating a global object:

**File: `assets/admin/js/sync-logs-pro.js`**

```javascript
/**
 * PRO Sync Logs functionality
 */
window.ghlCrmSyncLogsPro = {
    modal: null,
    closeBtn: null,
    content: null,
    
    /**
     * Cache PRO-specific DOM elements
     */
    cacheElements: function(syncLogsInstance) {
        this.modal = $('#ghl-details-modal');
        this.closeBtn = $('#ghl-close-modal');
        this.content = $('#ghl-details-content');
        
        // Store references in main instance for compatibility
        syncLogsInstance.modal = this.modal;
        syncLogsInstance.closeBtn = this.closeBtn;
        syncLogsInstance.content = this.content;
    },
    
    /**
     * Bind PRO-specific events
     */
    bindEvents: function(syncLogsInstance) {
        const self = this;
        
        // View details button
        $(document)
            .off('click.ghlSyncLogsPro', '.ghl-view-details')
            .on('click.ghlSyncLogsPro', '.ghl-view-details', function(e) {
                e.preventDefault();
                const details = $(this).attr('data-details');
                self.showDetailsModal(details);
            });
        
        // Close modal button
        if (this.closeBtn && this.closeBtn.length) {
            this.closeBtn.off('click.ghlSyncLogsPro').on('click.ghlSyncLogsPro', function() {
                self.hideModal();
            });
        }
        
        // Click outside to close
        if (this.modal && this.modal.length) {
            this.modal.off('click.ghlSyncLogsPro').on('click.ghlSyncLogsPro', function(e) {
                if (e.target === self.modal[0]) {
                    self.hideModal();
                }
            });
        }
        
        // ESC key to close
        $(document)
            .off('keydown.ghlSyncLogsPro')
            .on('keydown.ghlSyncLogsPro', function(e) {
                if (e.key === 'Escape' && self.modal && self.modal.is(':visible')) {
                    self.hideModal();
                }
            });
        
        // Override free version methods with PRO functionality
        syncLogsInstance.showDetailsModal = function(details) {
            self.showDetailsModal(details);
        };
        
        syncLogsInstance.hideModal = function() {
            self.hideModal();
        };
    },
    
    /**
     * Show details modal with enhanced PRO data
     */
    showDetailsModal: function(details) {
        try {
            const parsed = JSON.parse(details);
            
            // PRO version can add additional formatting/processing here
            const formatted = this.formatLogDetails(parsed);
            this.content.html(formatted);
        } catch (err) {
            this.content.text(details);
        }
        this.modal.fadeIn(200);
    },
    
    /**
     * Hide modal
     */
    hideModal: function() {
        this.modal.fadeOut(200);
    },
    
    /**
     * Format log details with PRO enhancements
     */
    formatLogDetails: function(data) {
        let formatted = '<div class="ghl-log-details-pro">';
        
        // Basic info
        formatted += '<div class="ghl-detail-section">';
        formatted += '<h4>Basic Information</h4>';
        formatted += '<table class="ghl-detail-table">';
        formatted += '<tr><td><strong>Type:</strong></td><td>' + (data.sync_type || 'N/A') + '</td></tr>';
        formatted += '<tr><td><strong>Item ID:</strong></td><td>' + (data.item_id || 'N/A') + '</td></tr>';
        formatted += '<tr><td><strong>Action:</strong></td><td>' + (data.action || 'N/A') + '</td></tr>';
        formatted += '<tr><td><strong>Status:</strong></td><td>' + (data.status || 'N/A') + '</td></tr>';
        formatted += '<tr><td><strong>Date:</strong></td><td>' + (data.created_at || 'N/A') + '</td></tr>';
        if (data.ghl_id) {
            formatted += '<tr><td><strong>GHL ID:</strong></td><td><code>' + data.ghl_id + '</code></td></tr>';
        }
        formatted += '</table>';
        formatted += '</div>';
        
        // Message
        if (data.message) {
            formatted += '<div class="ghl-detail-section">';
            formatted += '<h4>Message</h4>';
            formatted += '<div class="ghl-detail-message">' + data.message + '</div>';
            formatted += '</div>';
        }
        
        // Metadata (PRO feature - detailed breakdown)
        if (data.metadata && Object.keys(data.metadata).length > 0) {
            formatted += '<div class="ghl-detail-section">';
            formatted += '<h4>Metadata (PRO)</h4>';
            formatted += '<pre class="ghl-detail-metadata">' + JSON.stringify(data.metadata, null, 2) + '</pre>';
            formatted += '</div>';
        }
        
        formatted += '</div>';
        
        return formatted;
    }
};
```

### 4. CSS Styles for PRO Features

**File: `assets/admin/css/sync-logs-pro.css`**

```css
/* PRO Sync Logs Styles */
.ghl-log-details-pro {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.ghl-detail-section {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e2e8f0;
}

.ghl-detail-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.ghl-detail-section h4 {
    margin: 0 0 10px 0;
    color: #1e293b;
    font-size: 14px;
    font-weight: 600;
}

.ghl-detail-table {
    width: 100%;
    border-collapse: collapse;
}

.ghl-detail-table td {
    padding: 6px 0;
    vertical-align: top;
    border: none;
}

.ghl-detail-table td:first-child {
    width: 120px;
    padding-right: 15px;
}

.ghl-detail-message {
    background: #f8fafc;
    padding: 12px;
    border-radius: 6px;
    border-left: 3px solid #3b82f6;
    font-family: ui-monospace, 'Cascadia Code', monospace;
    font-size: 13px;
    line-height: 1.5;
}

.ghl-detail-metadata {
    background: #1e293b;
    color: #e2e8f0;
    padding: 15px;
    border-radius: 6px;
    font-size: 12px;
    line-height: 1.4;
    max-height: 300px;
    overflow-y: auto;
}

/* PRO button styling */
.ghl-pro-feature {
    opacity: 0.6;
    cursor: not-allowed;
}

.ghl-pro-feature .dashicons-lock {
    color: #f59e0b;
}
```

### 5. Asset Loading

The PRO plugin should load these assets on the sync logs page:

```php
/**
 * Load PRO sync logs assets
 */
add_action( 'admin_enqueue_scripts', function( $hook_suffix ) {
    // Only load on sync logs page
    if ( 'toplevel_page_ghl-crm-admin' === $hook_suffix ) {
        wp_enqueue_style(
            'ghl-crm-sync-logs-pro',
            GHL_CRM_PRO_URL . 'assets/admin/css/sync-logs-pro.css',
            [ 'ghl-crm-sync-logs-css' ],
            GHL_CRM_PRO_VERSION
        );
        
        wp_enqueue_script(
            'ghl-crm-sync-logs-pro',
            GHL_CRM_PRO_URL . 'assets/admin/js/sync-logs-pro.js',
            [ 'ghl-crm-sync-logs-js' ],
            GHL_CRM_PRO_VERSION,
            true
        );
    }
});
```

## Benefits

1. **Clean Separation**: Free version shows basic logs, PRO adds detailed functionality
2. **Extensible**: PRO plugin can enhance the detailed view with additional features
3. **Backward Compatible**: Existing PRO installations will work seamlessly
4. **Upgrade Path**: Free users see clear indication of PRO features

## Free Version Behavior

- Shows basic sync log table with all essential information
- "View Details" column shows locked PRO button with tooltip
- Clicking locked button shows upgrade notice via SweetAlert
- No modal or detailed view functionality

## PRO Version Behavior

- All free version features plus:
- Functional "View Details" buttons
- Detailed modal with formatted log information
- Enhanced metadata display
- Better error information for troubleshooting