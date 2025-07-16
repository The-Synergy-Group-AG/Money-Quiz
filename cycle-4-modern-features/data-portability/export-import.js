/**
 * Money Quiz Export/Import Handler
 * 
 * Manages data export/import functionality with progress tracking
 */
(function($) {
    'use strict';
    
    const MoneyQuizExportImport = {
        
        // Configuration
        config: {
            ajaxUrl: ajaxurl,
            nonce: moneyQuizExportImport.nonce,
            maxFileSize: 50 * 1024 * 1024, // 50MB
            allowedFormats: ['csv', 'json', 'xml', 'xlsx', 'sql'],
            chunkSize: 1000 // Records per chunk for large exports
        },
        
        // State
        state: {
            currentOperation: null,
            progress: 0,
            isProcessing: false,
            abortController: null
        },
        
        /**
         * Initialize
         */
        init() {
            this.bindEvents();
            this.initializeTabs();
            this.loadExportHistory();
            
            // Initialize file upload areas
            this.initializeFileUpload();
        },
        
        /**
         * Bind events
         */
        bindEvents() {
            // Tab navigation
            $('.nav-tab').on('click', this.handleTabClick.bind(this));
            
            // Export events
            $('#preview-export').on('click', this.previewExport.bind(this));
            $('#export-form').on('submit', this.handleExport.bind(this));
            $('#export-format').on('change', this.updateExportOptions.bind(this));
            
            // Import events
            $('#import-file').on('change', this.handleFileSelect.bind(this));
            $('#validate-import').on('click', this.validateImport.bind(this));
            $('#import-form').on('submit', this.handleImport.bind(this));
            
            // Backup events
            $('#create-backup').on('click', this.createBackup.bind(this));
            $('#restore-backup').on('click', this.restoreBackup.bind(this));
            
            // Migration events
            $('#check-migrations').on('click', this.checkMigrations.bind(this));
            $('#run-migrations').on('click', this.runMigrations.bind(this));
            
            // Field mapping
            $(document).on('change', '.field-mapping select', this.updateFieldMapping.bind(this));
            
            // Cancel operation
            $(document).on('click', '.cancel-operation', this.cancelOperation.bind(this));
        },
        
        /**
         * Initialize tabs
         */
        initializeTabs() {
            const hash = window.location.hash.substring(1) || 'export';
            this.switchTab(hash);
        },
        
        /**
         * Handle tab click
         */
        handleTabClick(e) {
            e.preventDefault();
            const tab = $(e.currentTarget).attr('href').substring(1);
            this.switchTab(tab);
        },
        
        /**
         * Switch tab
         */
        switchTab(tab) {
            // Update nav
            $('.nav-tab').removeClass('nav-tab-active');
            $(`.nav-tab[href="#${tab}"]`).addClass('nav-tab-active');
            
            // Update content
            $('.tab-content').hide();
            $(`#${tab}-tab`).show();
            
            // Update URL
            window.location.hash = tab;
        },
        
        /**
         * Preview export
         */
        async previewExport(e) {
            e.preventDefault();
            
            const formData = new FormData($('#export-form')[0]);
            formData.append('action', 'money_quiz_preview_export');
            formData.append('nonce', this.config.nonce);
            
            try {
                this.showLoading('#export-preview');
                
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Preview failed');
                }
                
                const data = await response.json();
                this.displayExportPreview(data);
                
            } catch (error) {
                this.showError('Failed to generate preview: ' + error.message);
            } finally {
                this.hideLoading();
            }
        },
        
        /**
         * Display export preview
         */
        displayExportPreview(data) {
            const $preview = $('#export-preview');
            
            let html = `
                <h3>Export Preview</h3>
                <div class="export-summary">
                    <p><strong>Total Records:</strong> ${data.total_records}</p>
                    <p><strong>Estimated File Size:</strong> ${this.formatFileSize(data.estimated_size)}</p>
                    <p><strong>Data Types:</strong> ${data.data_types.join(', ')}</p>
                </div>
            `;
            
            if (data.sample_data) {
                html += `
                    <h4>Sample Data</h4>
                    <div class="sample-data-container">
                        ${this.renderSampleData(data.sample_data, data.format)}
                    </div>
                `;
            }
            
            if (data.warnings && data.warnings.length > 0) {
                html += `
                    <div class="notice notice-warning">
                        <p><strong>Warnings:</strong></p>
                        <ul>
                            ${data.warnings.map(w => `<li>${w}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }
            
            $preview.html(html).slideDown();
        },
        
        /**
         * Render sample data
         */
        renderSampleData(data, format) {
            if (format === 'csv' || Array.isArray(data)) {
                // Render as table
                if (!data.length) return '<p>No data available</p>';
                
                const headers = Object.keys(data[0]);
                let html = '<table class="widefat striped"><thead><tr>';
                
                headers.forEach(header => {
                    html += `<th>${this.escapeHtml(header)}</th>`;
                });
                
                html += '</tr></thead><tbody>';
                
                data.slice(0, 5).forEach(row => {
                    html += '<tr>';
                    headers.forEach(header => {
                        html += `<td>${this.escapeHtml(String(row[header] || ''))}</td>`;
                    });
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                
                if (data.length > 5) {
                    html += `<p class="description">Showing 5 of ${data.length} records</p>`;
                }
                
                return html;
            } else {
                // Render as JSON
                return `<pre>${JSON.stringify(data, null, 2)}</pre>`;
            }
        },
        
        /**
         * Handle export
         */
        async handleExport(e) {
            e.preventDefault();
            
            if (this.state.isProcessing) {
                return;
            }
            
            const formData = new FormData(e.target);
            const dataTypes = formData.getAll('data_types[]');
            
            if (dataTypes.length === 0) {
                this.showError('Please select at least one data type to export');
                return;
            }
            
            this.state.isProcessing = true;
            this.state.currentOperation = 'export';
            this.showProgressModal('Exporting Data');
            
            try {
                // Check if large export
                const estimatedSize = await this.estimateExportSize(formData);
                
                if (estimatedSize > 10 * 1024 * 1024) { // 10MB
                    // Use chunked export for large data
                    await this.chunkedExport(formData);
                } else {
                    // Direct export for small data
                    await this.directExport(formData);
                }
                
            } catch (error) {
                this.showError('Export failed: ' + error.message);
            } finally {
                this.state.isProcessing = false;
                this.hideProgressModal();
            }
        },
        
        /**
         * Direct export
         */
        async directExport(formData) {
            // Create hidden form and submit
            const $form = $('<form>', {
                method: 'POST',
                action: ajaxurl
            });
            
            formData.forEach((value, key) => {
                $form.append($('<input>', {
                    type: 'hidden',
                    name: key,
                    value: value
                }));
            });
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'money_quiz_export'
            }));
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: this.config.nonce
            }));
            
            $form.appendTo('body').submit().remove();
            
            this.updateProgress(100);
            this.showSuccess('Export completed successfully');
        },
        
        /**
         * Chunked export for large datasets
         */
        async chunkedExport(formData) {
            const chunks = await this.getExportChunks(formData);
            const totalChunks = chunks.length;
            let processedChunks = 0;
            
            const exportId = await this.initializeChunkedExport(formData);
            
            for (const chunk of chunks) {
                if (this.state.abortController?.signal.aborted) {
                    break;
                }
                
                await this.exportChunk(exportId, chunk);
                processedChunks++;
                this.updateProgress((processedChunks / totalChunks) * 100);
            }
            
            if (!this.state.abortController?.signal.aborted) {
                const downloadUrl = await this.finalizeChunkedExport(exportId);
                this.downloadFile(downloadUrl);
            }
        },
        
        /**
         * Initialize file upload
         */
        initializeFileUpload() {
            const $dropZone = $('#import-drop-zone');
            
            // Drag and drop
            $dropZone.on('dragover', (e) => {
                e.preventDefault();
                $dropZone.addClass('drag-over');
            });
            
            $dropZone.on('dragleave', () => {
                $dropZone.removeClass('drag-over');
            });
            
            $dropZone.on('drop', (e) => {
                e.preventDefault();
                $dropZone.removeClass('drag-over');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    this.handleFileSelect({ target: { files: files } });
                }
            });
        },
        
        /**
         * Handle file select
         */
        handleFileSelect(e) {
            const file = e.target.files[0];
            
            if (!file) {
                return;
            }
            
            // Validate file
            const validation = this.validateFile(file);
            if (!validation.valid) {
                this.showError(validation.error);
                return;
            }
            
            // Display file info
            this.displayFileInfo(file);
            
            // Auto-detect format
            const format = this.detectFileFormat(file);
            $('#import-format').val(format);
            
            // Enable validation button
            $('#validate-import').prop('disabled', false);
        },
        
        /**
         * Validate file
         */
        validateFile(file) {
            // Check size
            if (file.size > this.config.maxFileSize) {
                return {
                    valid: false,
                    error: `File size exceeds maximum allowed size of ${this.formatFileSize(this.config.maxFileSize)}`
                };
            }
            
            // Check format
            const extension = file.name.split('.').pop().toLowerCase();
            if (!this.config.allowedFormats.includes(extension)) {
                return {
                    valid: false,
                    error: `Invalid file format. Allowed formats: ${this.config.allowedFormats.join(', ')}`
                };
            }
            
            return { valid: true };
        },
        
        /**
         * Display file info
         */
        displayFileInfo(file) {
            const $info = $('#file-info');
            
            $info.html(`
                <div class="file-details">
                    <p><strong>File:</strong> ${this.escapeHtml(file.name)}</p>
                    <p><strong>Size:</strong> ${this.formatFileSize(file.size)}</p>
                    <p><strong>Type:</strong> ${file.type || 'Unknown'}</p>
                    <p><strong>Modified:</strong> ${new Date(file.lastModified).toLocaleString()}</p>
                </div>
            `).show();
        },
        
        /**
         * Validate import
         */
        async validateImport(e) {
            e.preventDefault();
            
            const file = $('#import-file')[0].files[0];
            if (!file) {
                this.showError('Please select a file to validate');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'money_quiz_validate_import');
            formData.append('nonce', this.config.nonce);
            formData.append('import_file', file);
            formData.append('format', $('#import-format').val());
            formData.append('data_type', $('#import-data-type').val());
            
            try {
                this.showLoading('#validation-results');
                
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Validation failed');
                }
                
                const result = await response.json();
                this.displayValidationResults(result);
                
            } catch (error) {
                this.showError('Validation failed: ' + error.message);
            } finally {
                this.hideLoading();
            }
        },
        
        /**
         * Display validation results
         */
        displayValidationResults(result) {
            const $results = $('#validation-results');
            
            let html = '<h3>Validation Results</h3>';
            
            if (result.valid) {
                html += `
                    <div class="notice notice-success">
                        <p>File is valid and ready for import</p>
                    </div>
                `;
            } else {
                html += `
                    <div class="notice notice-error">
                        <p>File validation failed</p>
                    </div>
                `;
            }
            
            // Summary
            html += `
                <div class="validation-summary">
                    <p><strong>Total Records:</strong> ${result.total_records}</p>
                    <p><strong>Valid Records:</strong> ${result.valid_records}</p>
                    <p><strong>Invalid Records:</strong> ${result.invalid_records}</p>
                </div>
            `;
            
            // Field mapping
            if (result.fields) {
                html += this.renderFieldMapping(result.fields);
            }
            
            // Errors
            if (result.errors && result.errors.length > 0) {
                html += `
                    <div class="validation-errors">
                        <h4>Errors Found</h4>
                        <ul>
                            ${result.errors.slice(0, 10).map(error => `
                                <li>
                                    <strong>Row ${error.row}:</strong> ${error.message}
                                </li>
                            `).join('')}
                        </ul>
                        ${result.errors.length > 10 ? `<p>And ${result.errors.length - 10} more errors...</p>` : ''}
                    </div>
                `;
            }
            
            // Enable import if valid
            if (result.valid) {
                $('#start-import').prop('disabled', false);
            }
            
            $results.html(html).show();
        },
        
        /**
         * Render field mapping
         */
        renderFieldMapping(fields) {
            const systemFields = moneyQuizExportImport.systemFields || {};
            
            let html = `
                <div class="field-mapping-container">
                    <h4>Field Mapping</h4>
                    <p class="description">Map import fields to system fields</p>
                    <table class="field-mapping widefat">
                        <thead>
                            <tr>
                                <th>Import Field</th>
                                <th>System Field</th>
                                <th>Sample Value</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            fields.forEach(field => {
                html += `
                    <tr>
                        <td>${this.escapeHtml(field.name)}</td>
                        <td>
                            <select name="mapping[${field.name}]" class="field-mapping">
                                <option value="">-- Skip --</option>
                                ${Object.entries(systemFields).map(([key, label]) => `
                                    <option value="${key}" ${field.suggested_mapping === key ? 'selected' : ''}>
                                        ${label}
                                    </option>
                                `).join('')}
                            </select>
                        </td>
                        <td class="sample-value">${this.escapeHtml(field.sample || '')}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            return html;
        },
        
        /**
         * Handle import
         */
        async handleImport(e) {
            e.preventDefault();
            
            if (this.state.isProcessing) {
                return;
            }
            
            const file = $('#import-file')[0].files[0];
            if (!file) {
                this.showError('Please select a file to import');
                return;
            }
            
            // Confirm import
            if (!confirm('Are you sure you want to import this data? This action cannot be undone.')) {
                return;
            }
            
            this.state.isProcessing = true;
            this.state.currentOperation = 'import';
            this.showProgressModal('Importing Data');
            
            const formData = new FormData(e.target);
            formData.append('action', 'money_quiz_import');
            formData.append('nonce', this.config.nonce);
            
            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Import failed');
                }
                
                const result = await response.json();
                this.displayImportResults(result);
                
            } catch (error) {
                this.showError('Import failed: ' + error.message);
            } finally {
                this.state.isProcessing = false;
                this.hideProgressModal();
            }
        },
        
        /**
         * Display import results
         */
        displayImportResults(result) {
            let message = `
                Import completed:
                - Imported: ${result.imported}
                - Updated: ${result.updated}
                - Skipped: ${result.skipped}
                - Errors: ${result.errors.length}
            `;
            
            if (result.errors.length > 0) {
                message += '\n\nErrors:\n' + result.errors.slice(0, 5).map(e => 
                    `Row ${e.row}: ${e.error}`
                ).join('\n');
                
                if (result.errors.length > 5) {
                    message += `\n... and ${result.errors.length - 5} more errors`;
                }
            }
            
            if (result.success) {
                this.showSuccess(message);
            } else {
                this.showError(message);
            }
            
            // Refresh data displays
            this.refreshDataDisplays();
        },
        
        /**
         * Create backup
         */
        async createBackup(e) {
            e.preventDefault();
            
            if (this.state.isProcessing) {
                return;
            }
            
            this.state.isProcessing = true;
            this.showProgressModal('Creating Backup');
            
            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'money_quiz_create_backup',
                        nonce: this.config.nonce,
                        include_uploads: $('#include-uploads').is(':checked') ? 1 : 0,
                        include_settings: $('#include-settings').is(':checked') ? 1 : 0,
                        compress: 1
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Backup creation failed');
                }
                
                const result = await response.json();
                
                if (result.success) {
                    this.showSuccess('Backup created successfully');
                    this.downloadFile(result.download_url);
                    this.loadBackupHistory();
                } else {
                    throw new Error(result.message || 'Unknown error');
                }
                
            } catch (error) {
                this.showError('Backup failed: ' + error.message);
            } finally {
                this.state.isProcessing = false;
                this.hideProgressModal();
            }
        },
        
        /**
         * Show progress modal
         */
        showProgressModal(title) {
            const $modal = $('#progress-modal');
            
            if (!$modal.length) {
                $('body').append(`
                    <div id="progress-modal" class="money-quiz-modal">
                        <div class="modal-content">
                            <h3 class="modal-title">${title}</h3>
                            <div class="progress-bar-container">
                                <div class="progress-bar">
                                    <div class="progress-bar-fill" style="width: 0%"></div>
                                </div>
                                <div class="progress-text">0%</div>
                            </div>
                            <div class="progress-status"></div>
                            <button class="button cancel-operation">Cancel</button>
                        </div>
                    </div>
                `);
            } else {
                $modal.find('.modal-title').text(title);
                this.updateProgress(0);
            }
            
            $modal.show();
            this.state.abortController = new AbortController();
        },
        
        /**
         * Update progress
         */
        updateProgress(percentage, status = '') {
            const $modal = $('#progress-modal');
            $modal.find('.progress-bar-fill').css('width', percentage + '%');
            $modal.find('.progress-text').text(Math.round(percentage) + '%');
            
            if (status) {
                $modal.find('.progress-status').text(status);
            }
        },
        
        /**
         * Hide progress modal
         */
        hideProgressModal() {
            $('#progress-modal').hide();
            this.state.abortController = null;
        },
        
        /**
         * Cancel operation
         */
        cancelOperation() {
            if (this.state.abortController) {
                this.state.abortController.abort();
            }
            
            this.state.isProcessing = false;
            this.hideProgressModal();
            this.showWarning('Operation cancelled');
        },
        
        /**
         * Helper methods
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, m => map[m]);
        },
        
        showLoading(selector) {
            $(selector).html('<div class="spinner is-active"></div>').show();
        },
        
        hideLoading() {
            $('.spinner').remove();
        },
        
        showSuccess(message) {
            this.showNotice(message, 'success');
        },
        
        showError(message) {
            this.showNotice(message, 'error');
        },
        
        showWarning(message) {
            this.showNotice(message, 'warning');
        },
        
        showNotice(message, type = 'info') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.wrap h1').after($notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        },
        
        downloadFile(url) {
            const link = document.createElement('a');
            link.href = url;
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },
        
        /**
         * Load export history
         */
        async loadExportHistory() {
            // Implementation for loading export history
        },
        
        /**
         * Load backup history
         */
        async loadBackupHistory() {
            // Implementation for loading backup history
        },
        
        /**
         * Refresh data displays
         */
        refreshDataDisplays() {
            // Trigger refresh of any data displays on the page
            $(document).trigger('moneyQuiz:dataUpdated');
        }
    };
    
    // Initialize when ready
    $(document).ready(() => {
        if ($('.money-quiz-export-import').length) {
            MoneyQuizExportImport.init();
        }
    });
    
    // Export for external use
    window.MoneyQuizExportImport = MoneyQuizExportImport;
    
})(jQuery);