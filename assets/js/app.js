// assets/js/app.js - Main Application JavaScript

class KnowledgeBase {
    constructor() {
        this.currentFile = '';
        this.currentFileRelativePath = ''; // Track the full relative path for saving
        this.currentFileKnowledgebase = ''; // Track which knowledgebase the file belongs to
        this.originalFileTitle = ''; // Track original title to detect changes
        this.unsavedChanges = false;
        this.searchTimeout = null;
        this.isSearchActive = false;
        this.isScrollSyncing = false;
        this.fileDisplayLimit = 15;
        this.tagDisplayLimit = 10;
        this.allFiles = [];
        this.allTags = {};
        this.currentBrowseType = '';
        
        this.initializeElements();
        this.bindEvents();
        this.loadInitialData();
    }

    initializeElements() {
        // Main elements
        this.searchInput = document.getElementById('searchInput');
        this.fileList = document.getElementById('fileList');
        this.tagList = document.getElementById('tagList');
        this.editorContainer = document.getElementById('editorContainer');
        this.welcomeScreen = document.getElementById('welcomeScreen');
        
        // Mobile elements
        this.mobileMenuBtn = document.getElementById('mobileMenuBtn');
        this.mobileOverlay = document.getElementById('mobileOverlay');
        this.mobileToggleBtn = document.getElementById('mobileToggleBtn');
        this.sidebar = document.querySelector('.sidebar');
        
        // Browse modal elements
        this.browseModal = document.getElementById('browseModal');
        this.browseTitle = document.getElementById('browseTitle');
        this.browseSearch = document.getElementById('browseSearch');
        this.browseList = document.getElementById('browseList');
        
        // Settings modal elements
        this.settingsModal = document.getElementById('settingsModal');
        this.settingsBtn = document.getElementById('settingsBtn');
        this.saveSettingsBtn = document.getElementById('saveSettingsBtn');
        this.resetSettingsBtn = document.getElementById('resetSettingsBtn');
        this.changePasswordBtn = document.getElementById('changePasswordBtn');
        
        // Editor elements
        this.fileTitle = document.getElementById('fileTitle');
        this.fileTags = document.getElementById('fileTags');
        this.markdownEditor = document.getElementById('markdownEditor');
        this.markdownPreview = document.getElementById('markdownPreview');
        this.currentFileInput = document.getElementById('currentFile');
        // Removed syntax highlighting elements
        
        // Buttons
        this.newFileBtn = document.getElementById('newFileBtn');
        this.newFileDropdownBtn = document.getElementById('newFileDropdownBtn');
        this.newFileDropdown = document.getElementById('newFileDropdown');
        this.newFileDropdownContainer = document.querySelector('.new-file-dropdown');
        this.loadFileBtn = document.getElementById('loadFileBtn');
        this.loadFileInput = document.getElementById('loadFileInput');
        this.saveBtn = document.getElementById('saveBtn');
        this.deleteBtn = document.getElementById('deleteBtn');
        this.downloadBtn = document.getElementById('downloadBtn');
        this.viewBtn = document.getElementById('viewBtn');
        this.closeBtn = document.getElementById('closeBtn');
        
        // Export/Import elements
        this.exportBtn = document.getElementById('exportBtn');
        this.importBtn = document.getElementById('importBtn');
        this.importFileInput = document.getElementById('importFileInput');
        this.importModal = document.getElementById('importModal');
        
        // Initialize mobile functionality
        this.setupMobileView();
    }

    bindEvents() {
        console.log('bindEvents called');
        // File operations
        this.newFileBtn.addEventListener('click', () => this.createNewFile());
        this.newFileDropdownBtn.addEventListener('click', (e) => this.toggleTemplateDropdown(e));
        this.newFileDropdown.addEventListener('click', (e) => this.handleTemplateSelection(e));
        this.loadFileBtn.addEventListener('click', () => this.loadFileInput.click());
        this.loadFileInput.addEventListener('change', (e) => this.handleLoadFile(e));
        this.saveBtn.addEventListener('click', () => this.saveFile());
        this.deleteBtn.addEventListener('click', () => this.deleteFile());
        // Remove all previous listeners from downloadBtn
        this.downloadBtn.replaceWith(this.downloadBtn.cloneNode(true));
        this.downloadBtn = document.getElementById('downloadBtn');
        this.downloadBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.downloadFile();
        });
        this.viewBtn.addEventListener('click', () => this.viewFile());
        this.closeBtn.addEventListener('click', () => this.closeEditor());
        
        // Settings operations
        this.settingsBtn.addEventListener('click', () => this.openSettingsModal());
        this.saveSettingsBtn.addEventListener('click', () => this.saveSettings());
        this.resetSettingsBtn.addEventListener('click', () => this.resetSettings());
        this.changePasswordBtn.addEventListener('click', () => this.changePassword());
        
        // Export/Import operations
        this.exportBtn.addEventListener('click', () => this.exportContent());
        this.importBtn.addEventListener('click', () => this.openImportModal());
        this.importFileInput.addEventListener('change', (e) => this.handleImportFile(e));
        
        // File upload operations will be bound when settings modal opens
        
        // Mobile toggle button
        if (this.mobileToggleBtn) {
            this.mobileToggleBtn.addEventListener('click', () => this.toggleMobilePreview());
        }
        
        // Header title click to go to welcome screen
        const headerTitle = document.getElementById('headerTitle');
        if (headerTitle) {
            headerTitle.addEventListener('click', () => this.goToWelcomeScreen());
        }
        
        // Welcome screen stats click to browse
        const welcomeFilesStat = document.getElementById('welcomeFilesStat');
        const welcomeTagsStat = document.getElementById('welcomeTagsStat');
        
        if (welcomeFilesStat) {
            welcomeFilesStat.addEventListener('click', () => this.browseAll('files'));
        }
        
        if (welcomeTagsStat) {
            welcomeTagsStat.addEventListener('click', () => this.browseAll('tags'));
        }
        
        // Mobile functionality
        if (this.mobileMenuBtn) {
            const toggleMobileMenu = () => {
                console.log('Mobile menu toggle called');
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.getElementById('mobileOverlay');
                console.log('Sidebar found:', !!sidebar);
                console.log('Overlay found:', !!overlay);
                
                if (sidebar && overlay) {
                    const isOpen = sidebar.classList.contains('open');
                    console.log('Current state - isOpen:', isOpen);
                    
                    if (isOpen) {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('show');
                        if (this.isMobile()) {
                            sidebar.style.transform = 'translateX(-100%)';
                        }
                        console.log('Closing mobile menu');
                    } else {
                        sidebar.classList.add('open');
                        overlay.classList.add('show');
                        if (this.isMobile()) {
                            sidebar.style.transform = 'translateX(0)';
                        }
                        console.log('Opening mobile menu');
                    }
                    
                    // Force a reflow to ensure the transition works
                    sidebar.offsetHeight;
                } else {
                    console.error('Sidebar or overlay not found');
                }
            };
            
            // Add event listeners for better iOS compatibility
            this.mobileMenuBtn.addEventListener('click', toggleMobileMenu);
            this.mobileMenuBtn.addEventListener('touchstart', (e) => {
                e.preventDefault(); // Prevent double-firing on iOS
                toggleMobileMenu();
            }, { passive: false });
        }
        if (this.mobileOverlay) {
            const closeMobileMenu = () => {
                console.log('Overlay clicked - closing mobile menu');
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.getElementById('mobileOverlay');
                if (sidebar && overlay) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('show');
                    sidebar.style.transform = 'translateX(-100%)';
                }
            };
            
            // Add event listeners for better iOS compatibility
            this.mobileOverlay.addEventListener('click', closeMobileMenu);
            this.mobileOverlay.addEventListener('touchstart', (e) => {
                e.preventDefault(); // Prevent double-firing on iOS
                closeMobileMenu();
            }, { passive: false });
        }
        // Close menu when a file or tag is selected on mobile
        this.fileList.addEventListener('click', (e) => {
            if (this.isMobile()) {
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.getElementById('mobileOverlay');
                if (sidebar && overlay) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('show');
                }
            }
            this.handleFileClick(e);
        });
        this.tagList.addEventListener('click', (e) => {
            if (this.isMobile()) {
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.getElementById('mobileOverlay');
                if (sidebar && overlay) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('show');
                }
            }
            this.handleTagClick(e);
        });
        
        // Editor events
        this.markdownEditor.addEventListener('input', () => this.onEditorChange());
        this.markdownEditor.addEventListener('keydown', (e) => this.handleMarkdownKeydown(e));
        this.fileTitle.addEventListener('input', () => this.markUnsaved());
        this.fileTags.addEventListener('input', () => this.handleTagsInput());
        this.fileTags.addEventListener('paste', (e) => this.handleTagsPaste(e));
        
        // Synchronized scrolling (only on desktop)
        if (!this.isMobile()) {
            this.markdownEditor.addEventListener('scroll', () => this.syncScroll('editor'));
            this.markdownPreview.addEventListener('scroll', () => this.syncScroll('preview'));
        }
        
        // Search
        this.searchInput.addEventListener('input', (e) => this.handleSearch(e.target.value));
        
        // Browse modal search
        if (this.browseSearch) {
            this.browseSearch.addEventListener('input', (e) => this.filterBrowseItems(e.target.value));
        }
        
        // File and tag selection
        this.fileList.addEventListener('click', (e) => this.handleFileClick(e));
        this.tagList.addEventListener('click', (e) => this.handleTagClick(e));
        
        if (this.browseList) {
            this.browseList.addEventListener('click', (e) => this.handleBrowseClick(e));
        }
        
        // Modal close on background click
        if (this.browseModal) {
            this.browseModal.addEventListener('click', (e) => {
                if (e.target === this.browseModal) {
                    this.closeBrowseModal();
                }
            });
        }
        
        if (this.settingsModal) {
            this.settingsModal.addEventListener('click', (e) => {
                if (e.target === this.settingsModal) {
                    this.closeSettingsModal();
                }
            });
        }
        
        // Window resize handler
        window.addEventListener('resize', () => this.handleResize());
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => this.handleDocumentClick(e));
        
        // Prevent accidental close
        window.addEventListener('beforeunload', (e) => {
            if (this.unsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        // Double-click handlers for section headers
        this.setupDoubleClickHandlers();
    }

    async loadInitialData() {
        try {
            await this.refreshFileList();
            await this.refreshTagList();
            // Setup click handlers after content is loaded
            this.setupDoubleClickHandlers();
        } catch (error) {
            console.error('Error loading initial data:', error);
        }
    }

    async refreshFileList() {
        try {
            const response = await fetch('api/files.php');
            const files = await response.json();
            this.allFiles = files;
            this.renderFileList(files);
        } catch (error) {
            console.error('Error refreshing file list:', error);
        }
    }

    async refreshTagList() {
        try {
            const response = await fetch('api/tags.php');
            const tags = await response.json();
            this.allTags = tags;
            this.renderTagList(tags);
        } catch (error) {
            console.error('Error refreshing tag list:', error);
        }
    }

    renderFileList(files) {
        const filesToShow = files.slice(0, this.fileDisplayLimit);
        
        let html = filesToShow.map(file => {
            const kbIndicator = file.knowledgebase && file.knowledgebase !== 'root' 
                ? `<span class="kb-indicator" title="Knowledge Base: ${file.knowledgebase}">${file.knowledgebase}</span>` 
                : '';
            
            return `
                <div class="file-item" data-file="${file.relative_path || file.name}">
                    <span class="file-name" title="${file.display_name}">${file.display_name}</span>
                    <div class="file-meta">
                        ${kbIndicator}
                        <span class="file-date">${new Date(file.modified * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</span>
                    </div>
                </div>
            `;
        }).join('');
        
        // Update the header with count
        const filesHeader = document.querySelector('.sidebar-section h3');
        if (filesHeader) {
            filesHeader.innerHTML = `📁 Files <span class="section-count">${files.length}</span>`;
        }
        
        this.fileList.innerHTML = html;
    }

    renderTagList(tags) {
        const tagEntries = Object.entries(tags);
        const tagsToShow = tagEntries.slice(0, this.tagDisplayLimit);
        
        let html = tagsToShow.map(([tag, count]) => `
            <div class="tag-item" data-tag="${tag}">
                <span class="tag-name" title="${tag}">${tag}</span>
                <span class="tag-count">${count}</span>
            </div>
        `).join('');
        
        // Update the header with count
        const tagsHeader = document.querySelectorAll('.sidebar-section h3')[1];
        if (tagsHeader) {
            tagsHeader.innerHTML = `🏷️ Tags <span class="section-count">${tagEntries.length}</span>`;
        }
        
        this.tagList.innerHTML = html;
    }

    // Section view switchers
    showRecent(type) {
        // Don't override search results
        if (this.isSearchActive && type === 'files') {
            return;
        }
        
        const buttons = document.querySelectorAll(`.sidebar-section .section-btn`);
        buttons.forEach(btn => btn.classList.remove('active'));
        
        if (type === 'files') {
            buttons[0].classList.add('active');
            this.renderFileList(this.allFiles);
        }
    }

    showPopular(type) {
        const buttons = document.querySelectorAll(`.sidebar-section .section-btn`);
        buttons.forEach(btn => btn.classList.remove('active'));
        
        if (type === 'tags') {
            // Find the tags section and activate its button
            const sidebarSections = document.querySelectorAll('.sidebar-section');
            sidebarSections.forEach(section => {
                const header = section.querySelector('h3');
                if (header && header.textContent.includes('Tags')) {
                    const tagBtn = section.querySelector('.section-btn');
                    if (tagBtn) tagBtn.classList.add('active');
                }
            });
            this.renderTagList(this.allTags);
        }
    }

    // Browse modal functionality
    browseAll(type) {
        if (!this.browseModal) return;
        
        this.currentBrowseType = type;
        
        if (type === 'files') {
            this.browseTitle.textContent = 'Browse All Files';
            this.browseSearch.placeholder = 'Search files...';
            this.renderBrowseFiles(this.allFiles);
        } else if (type === 'tags') {
            this.browseTitle.textContent = 'Browse All Tags';
            this.browseSearch.placeholder = 'Search tags...';
            this.renderBrowseTags(this.allTags);
        }
        
        this.browseModal.classList.add('show');
        this.browseSearch.focus();
    }

    renderBrowseFiles(files) {
        if (!this.browseList) return;
        
        const html = files.map(file => {
            const metadata = this.getFileMetadata(file);
            const kbIndicator = file.knowledgebase && file.knowledgebase !== 'root' 
                ? `<span class="kb-indicator">${file.knowledgebase}</span>` 
                : '';
            
            return `
                <div class="browse-item" data-file="${file.relative_path || file.name}">
                    <div class="browse-item-title">${file.display_name}</div>
                    <div class="browse-item-meta">
                        ${kbIndicator}
                        <span>${new Date(file.modified * 1000).toLocaleDateString()}</span>
                        <span>${this.formatFileSize(file.size)}</span>
                    </div>
                    ${metadata.tags ? `
                        <div class="browse-item-tags">
                            ${metadata.tags.map(tag => `<span class="browse-tag">${tag}</span>`).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
        
        this.browseList.innerHTML = html;
    }

    renderBrowseTags(tags) {
        if (!this.browseList) return;
        
        const html = Object.entries(tags).map(([tag, count]) => `
            <div class="browse-item" data-tag="${tag}">
                <div class="browse-item-title">${tag}</div>
                <div class="browse-item-meta">
                    <span>Used in ${count} file${count !== 1 ? 's' : ''}</span>
                </div>
            </div>
        `).join('');
        
        this.browseList.innerHTML = html;
    }

    filterBrowseItems(query) {
        if (!this.browseList) return;
        
        const items = this.browseList.querySelectorAll('.browse-item');
        const queryLower = query.toLowerCase();
        
        items.forEach(item => {
            const title = item.querySelector('.browse-item-title').textContent.toLowerCase();
            const visible = title.includes(queryLower);
            item.style.display = visible ? 'block' : 'none';
        });
    }

    handleBrowseClick(e) {
        const browseItem = e.target.closest('.browse-item');
        if (browseItem) {
            if (this.currentBrowseType === 'files') {
                const fileName = browseItem.dataset.file;
                this.closeBrowseModal();
                this.openFile(fileName);
            } else if (this.currentBrowseType === 'tags') {
                const tagName = browseItem.dataset.tag;
                this.closeBrowseModal();
                this.filterByTag(tagName);
            }
        }
    }

    closeBrowseModal() {
        if (!this.browseModal) return;
        
        this.browseModal.classList.remove('show');
        if (this.browseSearch) {
            this.browseSearch.value = '';
        }
    }

    openSettingsModal() {
        if (this.settingsModal) {
            this.settingsModal.classList.add('show');
            this.loadSettings();
            // Re-bind file upload events when modal opens
            this.bindFileUploadEvents();
        }
    }

    closeSettingsModal() {
        if (this.settingsModal) {
            this.settingsModal.classList.remove('show');
        }
    }

    async loadSettings() {
        try {
            const response = await fetch('api/settings.php');
            const settings = await response.json();
            
            // Populate form fields
            document.getElementById('siteTitle').value = settings.site_title || '';
            document.getElementById('currentKnowledgebase').value = settings.current_knowledgebase || 'root';
            document.getElementById('sessionTimeout').value = Math.floor((settings.session_timeout || 31536000) / 60);
            document.getElementById('sidebarWidth').value = settings.sidebar_width || 300;
            document.getElementById('editorFontSize').value = settings.editor_font_size || 14;
            document.getElementById('autoSaveInterval').value = Math.floor((settings.auto_save_interval || 30000) / 1000);
            document.getElementById('passwordProtected').checked = settings.password_protected || false;
            document.getElementById('backupEnabled').checked = settings.backup_enabled || false;
            document.getElementById('backupInterval').value = Math.floor((settings.backup_interval || 86400) / 3600);
            document.getElementById('maxBackups').value = settings.max_backups || 10;
            
            // Load favicon and header icon
            this.updateFileDisplay('favicon', settings.favicon_path);
            this.updateFileDisplay('header_icon', settings.header_icon_path);
            
        } catch (error) {
            console.error('Error loading settings:', error);
            this.showNotification('Error loading settings', 'error');
        }
    }

    async saveSettings() {
        try {
            // Helper function to parse integer with fallback
            const parseIntSafe = (value, fallback) => {
                const parsed = parseInt(value);
                return isNaN(parsed) ? fallback : parsed;
            };
            
            const settings = {
                site_title: document.getElementById('siteTitle').value || 'Knowledge Base',
                current_knowledgebase: document.getElementById('currentKnowledgebase').value || '',
                session_timeout: parseIntSafe(document.getElementById('sessionTimeout').value, 60) * 60,
                sidebar_width: parseIntSafe(document.getElementById('sidebarWidth').value, 300),
                editor_font_size: parseIntSafe(document.getElementById('editorFontSize').value, 14),
                auto_save_interval: parseIntSafe(document.getElementById('autoSaveInterval').value, 30) * 1000,
                password_protected: document.getElementById('passwordProtected').checked,
                backup_enabled: document.getElementById('backupEnabled').checked,
                backup_interval: parseIntSafe(document.getElementById('backupInterval').value, 24) * 3600,
                max_backups: parseIntSafe(document.getElementById('maxBackups').value, 10)
            };

            const response = await fetch('api/settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update',
                    settings: settings
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Settings saved successfully!', 'success');
                this.closeSettingsModal();
                // Reload page to apply changes
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                // Show detailed error message if available
                let errorMsg = result.error || 'Error saving settings';
                if (result.details && result.details.length > 0) {
                    errorMsg += ':\n• ' + result.details.join('\n• ');
                }
                this.showNotification(errorMsg, 'error');
                console.error('Settings errors:', result.details);
            }
            
        } catch (error) {
            console.error('Error saving settings:', error);
            this.showNotification('Error saving settings', 'error');
        }
    }

    async resetSettings() {
        if (confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
            try {
                // Clear all form fields to defaults
                document.getElementById('siteTitle').value = 'Knowledge Base';
                document.getElementById('sessionTimeout').value = 525600;
                document.getElementById('sidebarWidth').value = 300;
                document.getElementById('editorFontSize').value = 14;
                document.getElementById('autoSaveInterval').value = 30;
                document.getElementById('passwordProtected').checked = false;
                document.getElementById('backupEnabled').checked = true;
                document.getElementById('backupInterval').value = 24;
                document.getElementById('maxBackups').value = 10;
                
                this.showNotification('Settings reset to defaults', 'info');
                
            } catch (error) {
                console.error('Error resetting settings:', error);
                this.showNotification('Error resetting settings', 'error');
            }
        }
    }

    async changePassword() {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (!newPassword) {
            this.showNotification('New password cannot be empty', 'error');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            this.showNotification('New passwords do not match', 'error');
            return;
        }
        
        if (newPassword.length < 6) {
            this.showNotification('Password must be at least 6 characters long', 'error');
            return;
        }
        
        try {
            const response = await fetch('api/settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'change_password',
                    current_password: currentPassword,
                    new_password: newPassword,
                    confirm_password: confirmPassword
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Password changed successfully!', 'success');
                // Clear password fields
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
            } else {
                this.showNotification(result.error || 'Error changing password', 'error');
            }
            
        } catch (error) {
            console.error('Error changing password:', error);
            this.showNotification('Error changing password', 'error');
        }
    }

    bindFileUploadEvents() {
        console.log('Binding file upload events...');
        
        // Get file upload elements
        this.faviconUpload = document.getElementById('faviconUpload');
        this.headerIconUpload = document.getElementById('headerIconUpload');
        this.removeFaviconBtn = document.getElementById('removeFaviconBtn');
        this.removeHeaderIconBtn = document.getElementById('removeHeaderIconBtn');
        
        console.log('File upload elements:', {
            faviconUpload: !!this.faviconUpload,
            headerIconUpload: !!this.headerIconUpload,
            removeFaviconBtn: !!this.removeFaviconBtn,
            removeHeaderIconBtn: !!this.removeHeaderIconBtn
        });
        
        // Remove existing event listeners if any
        if (this.faviconUpload) {
            this.faviconUpload.removeEventListener('change', this.faviconUploadHandler);
            this.faviconUploadHandler = (e) => this.handleFileUpload(e, 'favicon');
            this.faviconUpload.addEventListener('change', this.faviconUploadHandler);
        }
        
        if (this.headerIconUpload) {
            this.headerIconUpload.removeEventListener('change', this.headerIconUploadHandler);
            this.headerIconUploadHandler = (e) => this.handleFileUpload(e, 'header_icon');
            this.headerIconUpload.addEventListener('change', this.headerIconUploadHandler);
        }
        
        if (this.removeFaviconBtn) {
            this.removeFaviconBtn.removeEventListener('click', this.removeFaviconHandler);
            this.removeFaviconHandler = () => this.removeFile('favicon');
            this.removeFaviconBtn.addEventListener('click', this.removeFaviconHandler);
        }
        
        if (this.removeHeaderIconBtn) {
            this.removeHeaderIconBtn.removeEventListener('click', this.removeHeaderIconHandler);
            this.removeHeaderIconHandler = () => this.removeFile('header_icon');
            this.removeHeaderIconBtn.addEventListener('click', this.removeHeaderIconHandler);
        }
    }

    // Mobile functionality
    setupMobileView() {
        this.handleResize();
    }

    setupDoubleClickHandlers() {
        // Sidebar section headers (click to browse all)
        const sidebarHeaders = document.querySelectorAll('.sidebar-section h3');
        console.log('Found sidebar headers:', sidebarHeaders.length);
        sidebarHeaders.forEach(header => {
            console.log('Adding click listener to header:', header.textContent);
            header.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('Sidebar header clicked:', header.textContent);
                
                // Determine if it's Files or Tags section
                if (header.textContent.includes('Files')) {
                    this.browseAll('files');
                } else if (header.textContent.includes('Tags')) {
                    this.browseAll('tags');
                }
            });
        });

        // Editor pane headers (single click for fullscreen)
        const editorPaneHeaders = document.querySelectorAll('.editor-pane h4, .preview-pane h4');
        editorPaneHeaders.forEach(header => {
            header.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('Header clicked', header);
                this.togglePaneFullscreen(header.closest('.editor-pane, .preview-pane'));
            });
        });
    }

    togglePaneFullscreen(pane) {
        const editorContainer = this.editorContainer;
        const otherPane = pane.classList.contains('editor-pane') ? 
            editorContainer.querySelector('.preview-pane') : 
            editorContainer.querySelector('.editor-pane');
        const sidebar = document.querySelector('.sidebar');
        const header = document.querySelector('.app-header');
        const body = document.body;
        const previewPaneHeader = document.getElementById('previewPaneHeader');

        if (pane.classList.contains('fullscreen')) {
            // Exit fullscreen
            pane.classList.remove('fullscreen');
            if (otherPane) otherPane.style.display = 'flex';
            editorContainer.classList.remove('single-pane');
            if (header) header.style.display = '';
            body.classList.remove('fullscreen-mode');
            // Restore preview header text
            if (pane.classList.contains('preview-pane') && previewPaneHeader) {
                previewPaneHeader.textContent = '👁️ Preview';
            }
            // Remove content wrapper for preview
            this.unwrapPreviewContent();
        } else {
            // Enter fullscreen
            pane.classList.add('fullscreen');
            if (otherPane) otherPane.style.display = 'none';
            editorContainer.classList.add('single-pane');
            if (header) header.style.display = 'none';
            body.classList.add('fullscreen-mode');
            // Set preview header to file title
            if (pane.classList.contains('preview-pane') && previewPaneHeader) {
                const title = this.fileTitle && this.fileTitle.value ? this.fileTitle.value.trim() : '';
                previewPaneHeader.textContent = title || '👁️ Preview';
            }
            // Wrap content for centered layout with edge scrollbar
            if (pane.classList.contains('preview-pane')) {
                this.wrapPreviewContent();
            }
        }
        // Always restore sidebar/header if exiting fullscreen
        if (!document.querySelector('.editor-pane.fullscreen') && !document.querySelector('.preview-pane.fullscreen')) {
            if (header) header.style.display = '';
            body.classList.remove('fullscreen-mode');
        }
    }
    
    wrapPreviewContent() {
        const preview = this.markdownPreview;
        if (preview && !preview.querySelector('.markdown-preview-content')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'markdown-preview-content';
            wrapper.innerHTML = preview.innerHTML;
            preview.innerHTML = '';
            preview.appendChild(wrapper);
        }
    }
    
    unwrapPreviewContent() {
        const preview = this.markdownPreview;
        const wrapper = preview.querySelector('.markdown-preview-content');
        if (wrapper) {
            preview.innerHTML = wrapper.innerHTML;
        }
    }

    isMobile() {
        return window.innerWidth <= 768;
    }

    handleResize() {
        const isMobile = this.isMobile();
        
        if (this.mobileMenuBtn) {
            this.mobileMenuBtn.style.display = isMobile ? 'block' : 'none';
        }
        
        if (this.mobileToggleBtn) {
            this.mobileToggleBtn.style.display = isMobile ? 'block' : 'none';
        }
        
        if (!isMobile) {
            this.closeMobileMenu();
            this.editorContainer.classList.remove('preview-mode');
        }
    }

    closeMobileMenu() {
        console.log('Closing mobile menu');
        const sidebar = document.querySelector('.sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');
        if (sidebar && mobileOverlay) {
            sidebar.classList.remove('open');
            mobileOverlay.classList.remove('show');
            // Only apply transform on mobile
            if (this.isMobile()) {
                sidebar.style.transform = 'translateX(-100%)';
            } else {
                // On desktop, remove any inline transform to let CSS handle it
                sidebar.style.transform = '';
            }
        }
    }

    toggleMobilePreview() {
        const isPreviewMode = this.editorContainer.classList.contains('preview-mode');
        
        if (isPreviewMode) {
            this.editorContainer.classList.remove('preview-mode');
            this.mobileToggleBtn.textContent = '👁️ Preview';
        } else {
            this.editorContainer.classList.add('preview-mode');
            this.mobileToggleBtn.textContent = '📝 Edit';
            // Update preview when switching to preview mode
            this.updatePreview();
        }
    }

    handleFileClick(e) {
        e.stopPropagation();
        const fileItem = e.target.closest('.file-item');
        if (fileItem) {
            const fileName = fileItem.dataset.file;
            console.log('File clicked:', fileName, 'Mobile:', this.isMobile());
            this.openFile(fileName);
            // Close mobile menu when file is selected
            if (this.isMobile()) {
                this.closeMobileMenu();
            }
        }
    }

    handleTagClick(e) {
        const tagItem = e.target.closest('.tag-item');
        if (tagItem) {
            const tagName = tagItem.dataset.tag;
            this.filterByTag(tagName);
            // Close mobile menu when tag is selected
            if (this.isMobile()) {
                this.closeMobileMenu();
            }
        }
    }

    async openFile(fileName) {
        if (this.unsavedChanges) {
            if (!confirm('You have unsaved changes. Do you want to continue?')) {
                return;
            }
        }

        try {
            const response = await fetch(`api/files.php?action=get&file=${encodeURIComponent(fileName)}`);
            const fileData = await response.json();
            
            if (fileData.error) {
                alert('Error loading file: ' + fileData.error);
                return;
            }

            // Store file information for saving
            this.currentFile = fileData.name || fileName; // Just the filename
            this.currentFileRelativePath = fileData.relative_path || fileName; // Full relative path
            this.currentFileInput.value = this.currentFileRelativePath;
            
            // Determine knowledgebase from the relative path
            if (this.currentFileRelativePath.includes('/')) {
                this.currentFileKnowledgebase = this.currentFileRelativePath.split('/')[0];
            } else {
                this.currentFileKnowledgebase = 'root';
            }
            
            // Parse frontmatter
            const { content, metadata } = this.parseFrontmatter(fileData.content);
            
            // Update UI
            this.fileTitle.value = metadata.title || this.getDisplayName(this.currentFile);
            this.originalFileTitle = this.fileTitle.value; // Store original title
            this.fileTags.value = (metadata.tags || []).join(', ');
            this.markdownEditor.value = content;
            
            // Update preview
            this.updatePreview();
            
            // Show editor
            this.showEditor();
            
            // Update active file
            this.updateActiveFile(this.currentFileRelativePath);
            
            this.unsavedChanges = false;
            this.updateSaveButton();
            
        } catch (error) {
            console.error('Error opening file:', error);
            alert('Error opening file');
        }
    }

    createNewFile(template = 'blank') {
        if (this.unsavedChanges) {
            if (!confirm('You have unsaved changes. Do you want to continue?')) {
                return;
            }
        }

        const templateData = this.getTemplate(template);
        const fileName = `${templateData.filename}.md`;
        
        this.currentFile = fileName;
        this.currentFileRelativePath = fileName; // New files start in current context
        this.currentFileKnowledgebase = 'current'; // Will use current selected KB
        this.currentFileInput.value = fileName;
        
        this.fileTitle.value = templateData.title;
        this.originalFileTitle = templateData.title; // Store original title for new files
        this.fileTags.value = templateData.tags;
        this.markdownEditor.value = templateData.content;
        
        this.updatePreview();
        this.showEditor();
        this.updateActiveFile('');
        
        this.unsavedChanges = true;
        this.updateSaveButton();
        
        // Close mobile menu when new file is created
        if (this.isMobile()) {
            this.closeMobileMenu();
        }
        
        // Focus on title
        this.fileTitle.focus();
        this.fileTitle.select();
    }

    getTemplate(templateType) {
        const today = new Date().toISOString().split('T')[0]; // yyyy-mm-dd format
        
        const templates = {
            blank: {
                title: 'New Note',
                filename: 'note',
                tags: '',
                content: '# New Note\n\nStart writing here...'
            },
            todo: {
                title: 'Todo List',
                filename: 'todo',
                tags: 'tasks, todo',
                content: `# Todo List

## Today's Tasks

- [ ] Task 1
- [ ] Task 2
- [ ] Task 3

## Completed
- [x] Example completed task

## Notes
Add any additional notes or context here.`
            },
            explainer: {
                title: 'Concept Explanation',
                filename: 'explainer',
                tags: 'explanation, concept',
                content: `# Concept Explanation

## Overview
Brief overview of the concept or topic.

## Key Points
- **Point 1**: Explanation
- **Point 2**: Explanation
- **Point 3**: Explanation

## Details
Detailed explanation with examples.

## Examples
\`\`\`
// Code example or other examples
\`\`\`

## Related Topics
- Related topic 1
- Related topic 2

## References
- [Source 1](https://example.com)
- [Source 2](https://example.com)`
            },
            instructions: {
                title: 'Step-by-Step Instructions',
                filename: 'instructions',
                tags: 'guide, instructions, how-to',
                content: `# Step-by-Step Instructions

## Overview
Brief description of what these instructions will help accomplish.

## Prerequisites
- Requirement 1
- Requirement 2
- Requirement 3

## Steps

### Step 1: Preparation
Detailed explanation of the first step.

### Step 2: Main Action
Detailed explanation of the main steps.

### Step 3: Verification
How to verify the process was successful.

## Troubleshooting
Common issues and solutions:

- **Problem**: Solution
- **Problem**: Solution

## Additional Notes
Any additional tips or considerations.`
            },
            diary: {
                title: today,
                filename: today,
                tags: 'diary, journal, personal',
                content: `# ${today}

## Today's Focus
What did I want to accomplish today?

## What Happened
Key events, thoughts, and activities from today.

## Reflections
- What went well?
- What could be improved?
- What did I learn?

## Tomorrow's Goals
- Goal 1
- Goal 2
- Goal 3

## Mood: ⭐⭐⭐⭐⭐
Rate your day (1-5 stars)

## Additional Notes
Any other thoughts or observations.`
            },
            'ai-prompt': {
                title: 'AI Prompt Template',
                filename: 'ai-prompt',
                tags: 'ai, prompt, llm, chatgpt, claude',
                content: `# AI Prompt Template

## Prompt Title
Brief descriptive title for this prompt

## Purpose
What this prompt is designed to accomplish or solve.

## Context/Background
Any necessary background information or context the AI needs to understand.

## The Prompt

\`\`\`
[Your AI prompt goes here]

Be specific about:
- The role or persona the AI should adopt
- The task or objective
- The format of the desired output
- Any constraints or guidelines
- Examples if helpful
\`\`\`

## Expected Output
Description of what kind of response you expect from the AI.

## Variations
Alternative versions or modifications of the prompt:

### Version 1 (Basic)
\`\`\`
Simplified version of the prompt
\`\`\`

### Version 2 (Detailed)
\`\`\`
More detailed or specific version
\`\`\`

## Test Results
Record how well the prompt works:

- **AI Model**: Which AI was used (GPT-4, Claude, etc.)
- **Quality**: Rate the output quality (1-5)
- **Consistency**: How consistent are the results
- **Notes**: Any observations or improvements needed

## Related Prompts
- Link to similar prompts
- Variations for different use cases

## Tags
#ai #prompt #${today.replace(/-/g, '')}`
            }
        };
        
        return templates[templateType] || templates.blank;
    }

    toggleTemplateDropdown(e) {
        e.preventDefault();
        e.stopPropagation();
        this.newFileDropdownContainer.classList.toggle('open');
    }

    handleTemplateSelection(e) {
        e.stopPropagation();
        const templateItem = e.target.closest('.template-item');
        if (!templateItem) return;
        
        const template = templateItem.dataset.template;
        this.newFileDropdownContainer.classList.remove('open');
        this.createNewFile(template);
    }

    handleDocumentClick(e) {
        // Close template dropdown if clicking outside
        if (!this.newFileDropdownContainer.contains(e.target)) {
            this.newFileDropdownContainer.classList.remove('open');
        }
    }

    async handleLoadFile(event) {
        const file = event.target.files[0];
        if (!file) return;

        // Check if file is a markdown file
        if (!file.name.toLowerCase().endsWith('.md') && !file.name.toLowerCase().endsWith('.markdown')) {
            this.showNotification('Please select a .md or .markdown file', 'error');
            return;
        }

        // Check for unsaved changes
        if (this.unsavedChanges) {
            if (!confirm('You have unsaved changes. Do you want to continue?')) {
                event.target.value = ''; // Reset file input
                return;
            }
        }

        try {
            const content = await this.readFileAsText(file);
            const fileName = file.name;
            
            // Parse the content to extract frontmatter and markdown
            const parsed = this.parseFrontmatter(content);
            const metadata = parsed.metadata;
            const markdownContent = parsed.content;
            
            // Set the current file
            this.currentFile = fileName;
            this.currentFileInput.value = fileName;
            
            // Set the title (use frontmatter title or filename without extension)
            const title = metadata.title || fileName.replace(/\.(md|markdown)$/i, '');
            this.fileTitle.value = title;
            
            // Set the tags
            const tags = metadata.tags ? metadata.tags.join(', ') : '';
            this.fileTags.value = tags;
            
            // Set the markdown content
            this.markdownEditor.value = markdownContent;
            
            // Update preview and show editor
            this.updatePreview();
            this.showEditor();
            this.updateActiveFile('');
            
            // Mark as unsaved so user can save it
            this.unsavedChanges = true;
            this.updateSaveButton();
            
            // Close mobile menu when file is loaded
            if (this.isMobile()) {
                this.closeMobileMenu();
            }
            
            // Ask user if they want to save the file to the server
            if (confirm(`File "${fileName}" loaded successfully! Would you like to save it to your knowledge base?`)) {
                await this.saveLoadedFile(fileName, content, title);
            } else {
                this.showNotification(`File "${fileName}" loaded successfully!`, 'success');
            }
            
        } catch (error) {
            console.error('Error loading file:', error);
            this.showNotification('Error loading file: ' + error.message, 'error');
        }
        
        // Reset file input
        event.target.value = '';
    }

    readFileAsText(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = (e) => reject(new Error('Failed to read file'));
            reader.readAsText(file);
        });
    }

    async saveLoadedFile(fileName, content, title) {
        try {
            const response = await fetch('api/load.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    fileName: fileName,
                    content: content,
                    title: title
                })
            });

            const result = await response.json();
            
            if (result.error) {
                this.showNotification('Error saving file: ' + result.error, 'error');
                return;
            }

            // Refresh lists
            await this.refreshFileList();
            await this.refreshTagList();
            
            // Update current file to the saved file
            this.currentFile = fileName;
            this.currentFileInput.value = fileName;
            
            // Mark as saved
            this.unsavedChanges = false;
            this.updateSaveButton();
            
            // Update active file
            this.updateActiveFile(fileName);
            
            this.showNotification(`File "${fileName}" saved to knowledge base successfully!`, 'success');
            
        } catch (error) {
            console.error('Error saving loaded file:', error);
            this.showNotification('Error saving file: ' + error.message, 'error');
        }
    }

    async saveFile() {
        if (!this.currentFile) return;

        const title = this.fileTitle.value.trim();
        const tags = this.fileTags.value.split(',').map(tag => tag.trim()).filter(tag => tag);
        const content = this.markdownEditor.value;

        // Create frontmatter
        const frontmatter = {
            title: title,
            tags: tags,
            created: new Date().toISOString(),
            modified: new Date().toISOString()
        };

        const fullContent = this.createFrontmatter(frontmatter) + '\n' + content;

        // Determine the file path for saving
        let fileToSave = this.currentFileRelativePath || this.currentFile;
        
        // For new files or when knowledgebase is 'current', determine the correct path
        if (this.currentFileKnowledgebase === 'current' || !this.currentFileRelativePath.includes('/')) {
            // This is a new file or existing root file - save to current knowledgebase context
            // We'll let the backend determine the correct location based on current settings
            fileToSave = this.currentFile;
        }

        const requestBody = {
            action: 'save',
            file: fileToSave,
            content: fullContent,
            knowledgebase_context: this.currentFileKnowledgebase
        };

        // Only include title if it has changed from the original
        if (title !== this.originalFileTitle) {
            requestBody.title = title;
        }

        try {
            const response = await fetch('api/files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestBody)
            });

            const result = await response.json();
            
            if (result.error) {
                alert('Error saving file: ' + result.error);
                return;
            }

            // Update file tracking information after save
            if (result.file && result.file.name) {
                this.currentFile = result.file.name;
                this.currentFileRelativePath = result.file.relative_path || result.file.name;
                this.currentFileInput.value = this.currentFileRelativePath;
            }

            this.unsavedChanges = false;
            this.updateSaveButton();
            
            // Update the original title to the current title
            this.originalFileTitle = title;
            
            // Refresh lists
            await this.refreshFileList();
            await this.refreshTagList();
            
            // Update active file
            this.updateActiveFile(this.currentFileRelativePath);
            
            // Show success
            this.showNotification('File saved successfully!', 'success');
            
        } catch (error) {
            console.error('Error saving file:', error);
            alert('Error saving file');
        }
    }

    async deleteFile() {
        console.log('Delete file called');
        console.log('Current file:', this.currentFile);
        console.log('Current file relative path:', this.currentFileRelativePath);
        
        if (!this.currentFile) {
            console.log('No current file set, returning');
            this.showNotification('No file selected for deletion', 'error');
            return;
        }

        if (!confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
            return;
        }

        const fileToDelete = this.currentFileRelativePath || this.currentFile;
        console.log('Attempting to delete file:', fileToDelete);

        try {
            const response = await fetch('api/files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    file: fileToDelete
                })
            });

            console.log('Delete response status:', response.status);
            console.log('Delete response ok:', response.ok);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            console.log('Delete API result:', result);
            
            if (result.error) {
                console.error('Delete API error:', result.error);
                this.showNotification('Error deleting file: ' + result.error, 'error');
                return;
            }

            // Close editor and refresh
            this.closeEditor();
            await this.refreshFileList();
            await this.refreshTagList();
            
            this.showNotification('File deleted successfully!', 'success');
            
        } catch (error) {
            console.error('Error deleting file:', error);
            this.showNotification('Error deleting file: ' + error.message, 'error');
        }
    }

    downloadFile() {
        console.log('Download triggered');
        if (!this.currentFile) {
            this.showNotification('No file selected', 'error');
            return;
        }

        // Create a temporary link element to trigger the download
        const link = document.createElement('a');
        const fileToDownload = this.currentFileRelativePath || this.currentFile;
        link.href = `api/download.php?file=${encodeURIComponent(fileToDownload)}`;
        link.download = this.currentFile; // Force the filename (just the name, not path)
        link.style.display = 'none';
        
        // Add to DOM, click, and remove
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.showNotification('Download started!', 'success');
    }

    viewFile() {
        if (!this.currentFile) {
            this.showNotification('No file selected', 'error');
            return;
        }

        // Use the relative path for the view URL, similar to filemanager implementation
        const fileToView = this.currentFileRelativePath || this.currentFile;
        const viewUrl = `view/?file=${encodeURIComponent(fileToView)}&style=dark`;
        
        // Open in new tab
        window.open(viewUrl, '_blank');
    }

    closeEditor() {
        if (this.unsavedChanges) {
            if (!confirm('You have unsaved changes. Do you want to continue?')) {
                return;
            }
        }

        this.editorContainer.style.display = 'none';
        this.welcomeScreen.style.display = 'flex';
        this.currentFile = '';
        this.currentFileRelativePath = '';
        this.currentFileKnowledgebase = '';
        this.currentFileInput.value = '';
        this.unsavedChanges = false;
        this.updateActiveFile('');
        
        // Reset search box and refresh file list
        if (this.searchInput) {
            this.searchInput.value = '';
            this.isSearchActive = false;
            this.clearSearchUI();
            this.refreshFileList();
        }
    }
    
    goToWelcomeScreen() {
        console.log('Going to welcome screen');
        // Close any open modals
        this.closeBrowseModal();
        this.closeSettingsModal();
        
        // Close editor if open
        if (this.editorContainer.style.display !== 'none') {
            this.closeEditor();
        }
        
        // Show welcome screen
        this.welcomeScreen.style.display = 'flex';
        
        // Clear any active file selection
        this.updateActiveFile('');
    }

    showEditor() {
        this.welcomeScreen.style.display = 'none';
        this.editorContainer.style.display = 'flex';
        this.editorContainer.classList.add('fade-in');
        // Always attach single-click handlers for fullscreen
        const editorPaneHeaders = document.querySelectorAll('.editor-pane h4, .preview-pane h4');
        editorPaneHeaders.forEach(header => {
            // Remove any previous click event
            header.replaceWith(header.cloneNode(true));
        });
        // Re-select after cloning
        const newHeaders = document.querySelectorAll('.editor-pane h4, .preview-pane h4');
        newHeaders.forEach(header => {
            header.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('Header clicked for fullscreen', header);
                this.togglePaneFullscreen(header.closest('.editor-pane, .preview-pane'));
            });
        });
    }

    onEditorChange() {
        this.updatePreview();
        this.markUnsaved();
    }

    handleMarkdownKeydown(e) {
        const textarea = this.markdownEditor;
        const value = textarea.value;
        const cursorPosition = textarea.selectionStart;
        
        // Handle Enter key for markdown list autocomplete
        if (e.key === 'Enter') {
            const beforeCursor = value.substring(0, cursorPosition);
            const currentLineStart = beforeCursor.lastIndexOf('\n') + 1;
            const currentLine = beforeCursor.substring(currentLineStart);
            
            // Check for bullet lists (-, *, +)
            const bulletListRegex = /^(\s*)([-*+] \[ \] |[-*+] \[x\] |[-*+] )/;
            const bulletMatch = currentLine.match(bulletListRegex);
            
            // Check for numbered lists (1. 2. 3.)
            const numberedListRegex = /^(\s*)(\d+)\. /;
            const numberedMatch = currentLine.match(numberedListRegex);
            
            if (bulletMatch) {
                this.handleBulletListEnter(e, currentLine, currentLineStart, bulletMatch);
            } else if (numberedMatch) {
                this.handleNumberedListEnter(e, currentLine, currentLineStart, numberedMatch);
            }
        }
        
        // Handle auto-closing for bold/italic/code
        else if (e.key === '*') {
            this.handleAsteriskAutoClose(e);
        } else if (e.key === '`') {
            this.handleBacktickAutoClose(e);
        } else if (e.key === '"' || e.key === "'" || e.key === '(' || e.key === '[' || e.key === '{') {
            this.handlePairingChars(e);
        }
    }

    handleBulletListEnter(e, currentLine, currentLineStart, match) {
        const textarea = this.markdownEditor;
        const value = textarea.value;
        const cursorPosition = textarea.selectionStart;
        const beforeCursor = value.substring(0, cursorPosition);
        
        const indent = match[1];
        const listPrefix = match[2];
        
        // If empty list item, exit list
        if (currentLine.trim() === listPrefix.trim()) {
            e.preventDefault();
            const newValue = value.substring(0, currentLineStart) + '\n' + value.substring(cursorPosition);
            textarea.value = newValue;
            textarea.setSelectionRange(currentLineStart + 1, currentLineStart + 1);
            this.onEditorChange();
        } else {
            // Continue list
            e.preventDefault();
            let newPrefix = listPrefix;
            if (listPrefix.includes('[x]')) {
                newPrefix = listPrefix.replace('[x]', '[ ]');
            }
            
            const newLine = '\n' + indent + newPrefix;
            const afterCursor = value.substring(cursorPosition);
            const newValue = beforeCursor + newLine + afterCursor;
            
            textarea.value = newValue;
            textarea.setSelectionRange(cursorPosition + newLine.length, cursorPosition + newLine.length);
            this.onEditorChange();
        }
    }

    handleNumberedListEnter(e, currentLine, currentLineStart, match) {
        const textarea = this.markdownEditor;
        const value = textarea.value;
        const cursorPosition = textarea.selectionStart;
        const beforeCursor = value.substring(0, cursorPosition);
        
        const indent = match[1];
        const currentNumber = parseInt(match[2]);
        
        // If empty numbered item, exit list
        if (currentLine.trim() === `${currentNumber}. `) {
            e.preventDefault();
            const newValue = value.substring(0, currentLineStart) + '\n' + value.substring(cursorPosition);
            textarea.value = newValue;
            textarea.setSelectionRange(currentLineStart + 1, currentLineStart + 1);
            this.onEditorChange();
        } else {
            // Continue with next number
            e.preventDefault();
            const nextNumber = currentNumber + 1;
            const newLine = '\n' + indent + nextNumber + '. ';
            const afterCursor = value.substring(cursorPosition);
            const newValue = beforeCursor + newLine + afterCursor;
            
            textarea.value = newValue;
            textarea.setSelectionRange(cursorPosition + newLine.length, cursorPosition + newLine.length);
            this.onEditorChange();
        }
    }

    handleAsteriskAutoClose(e) {
        const textarea = this.markdownEditor;
        const value = textarea.value;
        const cursorPosition = textarea.selectionStart;
        const selectedText = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd);
        
        // If text is selected, wrap it
        if (selectedText) {
            e.preventDefault();
            const beforeSelection = value.substring(0, textarea.selectionStart);
            const afterSelection = value.substring(textarea.selectionEnd);
            const newValue = beforeSelection + '*' + selectedText + '*' + afterSelection;
            textarea.value = newValue;
            textarea.setSelectionRange(cursorPosition + 1, cursorPosition + 1 + selectedText.length);
            this.onEditorChange();
        }
        // If at start of word or after space, create pair
        else {
            const charBefore = cursorPosition > 0 ? value[cursorPosition - 1] : '';
            const charAfter = cursorPosition < value.length ? value[cursorPosition] : '';
            
            if ((charBefore === '' || charBefore === ' ' || charBefore === '\n') && 
                (charAfter === '' || charAfter === ' ' || charAfter === '\n' || charAfter === '.')) {
                e.preventDefault();
                const newValue = value.substring(0, cursorPosition) + '**' + value.substring(cursorPosition);
                textarea.value = newValue;
                textarea.setSelectionRange(cursorPosition + 1, cursorPosition + 1);
                this.onEditorChange();
            }
        }
    }

    handleBacktickAutoClose(e) {
        const textarea = this.markdownEditor;
        const value = textarea.value;
        const cursorPosition = textarea.selectionStart;
        const selectedText = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd);
        
        // Check for triple backtick (code block)
        const beforeCursor = value.substring(0, cursorPosition);
        const lineStart = beforeCursor.lastIndexOf('\n') + 1;
        const currentLine = beforeCursor.substring(lineStart);
        
        if (currentLine === '``') {
            // Create code block
            e.preventDefault();
            const newValue = value.substring(0, cursorPosition) + '`\n\n```' + value.substring(cursorPosition);
            textarea.value = newValue;
            textarea.setSelectionRange(cursorPosition + 2, cursorPosition + 2);
            this.onEditorChange();
        }
        // If text is selected, wrap it in inline code
        else if (selectedText) {
            e.preventDefault();
            const beforeSelection = value.substring(0, textarea.selectionStart);
            const afterSelection = value.substring(textarea.selectionEnd);
            const newValue = beforeSelection + '`' + selectedText + '`' + afterSelection;
            textarea.value = newValue;
            textarea.setSelectionRange(cursorPosition + 1, cursorPosition + 1 + selectedText.length);
            this.onEditorChange();
        }
    }

    handlePairingChars(e) {
        const textarea = this.markdownEditor;
        const value = textarea.value;
        const cursorPosition = textarea.selectionStart;
        const selectedText = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd);
        
        const pairs = {
            '"': '"',
            "'": "'",
            '(': ')',
            '[': ']',
            '{': '}'
        };
        
        const closingChar = pairs[e.key];
        if (!closingChar) return;
        
        // If text is selected, wrap it
        if (selectedText) {
            e.preventDefault();
            const beforeSelection = value.substring(0, textarea.selectionStart);
            const afterSelection = value.substring(textarea.selectionEnd);
            const newValue = beforeSelection + e.key + selectedText + closingChar + afterSelection;
            textarea.value = newValue;
            textarea.setSelectionRange(cursorPosition + 1, cursorPosition + 1 + selectedText.length);
            this.onEditorChange();
        }
        // Auto-close if appropriate
        else {
            const charAfter = cursorPosition < value.length ? value[cursorPosition] : '';
            if (charAfter === '' || charAfter === ' ' || charAfter === '\n') {
                e.preventDefault();
                const newValue = value.substring(0, cursorPosition) + e.key + closingChar + value.substring(cursorPosition);
                textarea.value = newValue;
                textarea.setSelectionRange(cursorPosition + 1, cursorPosition + 1);
                this.onEditorChange();
            }
        }
    }

    handleTagsInput() {
        const input = this.fileTags;
        const value = input.value;
        const cursorPosition = input.selectionStart;
        
        // Check if the last character entered was a space
        if (value.length > 0 && value[cursorPosition - 1] === ' ') {
            const beforeCursor = value.slice(0, cursorPosition - 1);
            const afterCursor = value.slice(cursorPosition);

            // Only convert if the character before the space is not a comma (with or without a space)
            if (!beforeCursor.match(/[,\s]$/)) {
                // Replace the space with a comma and space
                const newValue = beforeCursor + ', ' + afterCursor;
                input.value = newValue;
                // Set cursor position after the comma and space
                input.setSelectionRange(cursorPosition + 1, cursorPosition + 1);
            }
        }
        this.markUnsaved();
    }

    handleTagsPaste(e) {
        // Prevent the default paste behavior
        e.preventDefault();
        
        // Get the pasted text
        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
        
        // Convert spaces to commas in the pasted text
        const processedText = pastedText
            .split(/\s+/)
            .filter(tag => tag.trim() !== '')
            .join(', ');
        
        // Insert the processed text at the cursor position
        const input = this.fileTags;
        const start = input.selectionStart;
        const end = input.selectionEnd;
        const value = input.value;
        
        const newValue = value.slice(0, start) + processedText + value.slice(end);
        input.value = newValue;
        
        // Set cursor position after the pasted text
        const newCursorPosition = start + processedText.length;
        input.setSelectionRange(newCursorPosition, newCursorPosition);
        
        this.markUnsaved();
    }

    updatePreview() {
        const content = this.markdownEditor.value;
        const html = marked.parse(content);
        this.markdownPreview.innerHTML = html;
        
        // Apply syntax highlighting
        if (typeof Prism !== 'undefined') {
            Prism.highlightAllUnder(this.markdownPreview);
        }
    }


    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }


    syncScroll(source) {
        if (this.isScrollSyncing) return;
        
        this.isScrollSyncing = true;
        
        if (source === 'editor') {
            const editorScrollPercent = this.markdownEditor.scrollTop / 
                (this.markdownEditor.scrollHeight - this.markdownEditor.clientHeight);
            
            const previewMaxScroll = this.markdownPreview.scrollHeight - this.markdownPreview.clientHeight;
            this.markdownPreview.scrollTop = editorScrollPercent * previewMaxScroll;
        } else if (source === 'preview') {
            const previewScrollPercent = this.markdownPreview.scrollTop / 
                (this.markdownPreview.scrollHeight - this.markdownPreview.clientHeight);
            
            const editorMaxScroll = this.markdownEditor.scrollHeight - this.markdownEditor.clientHeight;
            this.markdownEditor.scrollTop = previewScrollPercent * editorMaxScroll;
        }
        
        // Reset the flag after a short delay to allow the scroll to complete
        setTimeout(() => {
            this.isScrollSyncing = false;
        }, 50);
    }

    markUnsaved() {
        this.unsavedChanges = true;
        this.updateSaveButton();
    }

    updateSaveButton() {
        if (this.unsavedChanges) {
            this.saveBtn.textContent = '💾 Save*';
            this.saveBtn.classList.add('btn-warning');
        } else {
            this.saveBtn.textContent = '💾 Save';
            this.saveBtn.classList.remove('btn-warning');
        }
    }

    updateActiveFile(fileName) {
        // Remove active class from all file items
        document.querySelectorAll('.file-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Add active class to current file
        if (fileName) {
            const activeItem = document.querySelector(`[data-file="${fileName}"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
        }
    }

    async handleSearch(query) {
        // Debounce search
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(async () => {
            if (query.trim() === '') {
                // Clear search - reset to normal view
                this.isSearchActive = false;
                this.clearSearchUI();
                await this.refreshFileList();
                return;
            }

            try {
                const response = await fetch(`api/search.php?q=${encodeURIComponent(query)}`);
                const results = await response.json();
                
                // Set search state and update UI
                this.isSearchActive = true;
                this.updateSearchUI();
                this.renderFileList(results);
            } catch (error) {
                console.error('Search error:', error);
            }
        }, 300);
    }

    updateSearchUI() {
        // Deactivate all section buttons when search is active
        const buttons = document.querySelectorAll('.sidebar-section .section-btn');
        buttons.forEach(btn => btn.classList.remove('active'));
    }

    clearSearchUI() {
        // Reactivate the Recent button for files
        const buttons = document.querySelectorAll('.sidebar-section .section-btn');
        if (buttons.length > 0) {
            buttons[0].classList.add('active'); // First button is usually "Recent" for files
        }
    }

    async filterByTag(tagName) {
        try {
            const response = await fetch(`api/files.php?tag=${encodeURIComponent(tagName)}`);
            const files = await response.json();
            
            // Set search state and update UI
            this.isSearchActive = true;
            this.updateSearchUI();
            this.renderFileList(files);
            
            // Update search input to show filter
            this.searchInput.value = `tag:${tagName}`;
        } catch (error) {
            console.error('Tag filter error:', error);
        }
    }

    handleKeyboard(e) {
        // Close modal with Escape
        if (e.key === 'Escape') {
            if (this.browseModal && this.browseModal.classList.contains('show')) {
                this.closeBrowseModal();
                return;
            }
            if (this.editorContainer.style.display !== 'none') {
                this.closeEditor();
            }
        }
        // App-specific shortcuts only
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            this.saveFile();
        }
        // Ctrl/Cmd + N to create new file
        else if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            this.createNewFile();
        }
        // Ctrl/Cmd + F to focus search
        else if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            if (this.browseModal && this.browseModal.classList.contains('show')) {
                this.browseSearch.focus();
            } else {
                this.searchInput.focus();
            }
        }
        // Ctrl/Cmd + D to download file
        else if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            e.preventDefault();
            this.downloadFile();
        }
        // F11 to toggle editor fullscreen (do not block browser default)
        else if (e.key === 'F11') {
            // Do not preventDefault, let browser handle F11
        }
        // F12 to toggle preview fullscreen (do not block browser default)
        else if (e.key === 'F12') {
            // Do not preventDefault, let browser handle F12
        }
    }

    parseFrontmatter(content) {
        const frontmatterRegex = /^---\s*\n([\s\S]*?)\n---\s*\n([\s\S]*)$/;
        const match = content.match(frontmatterRegex);
        
        if (match) {
            try {
                const frontmatter = match[1];
                const body = match[2];
                const metadata = this.parseYaml(frontmatter);
                return { content: body, metadata };
            } catch (error) {
                console.error('Error parsing frontmatter:', error);
            }
        }
        
        return { content, metadata: {} };
    }

    createFrontmatter(metadata) {
        const yaml = Object.entries(metadata)
            .map(([key, value]) => {
                if (Array.isArray(value)) {
                    return `${key}: [${value.map(v => `"${v}"`).join(', ')}]`;
                }
                return `${key}: "${value}"`;
            })
            .join('\n');
        
        return `---\n${yaml}\n---`;
    }

    parseYaml(yamlString) {
        const result = {};
        const lines = yamlString.split('\n');
        
        lines.forEach(line => {
            const colonIndex = line.indexOf(':');
            if (colonIndex === -1) return;
            
            const key = line.substring(0, colonIndex).trim();
            let value = line.substring(colonIndex + 1).trim();
            
            // Remove quotes
            if ((value.startsWith('"') && value.endsWith('"')) || 
                (value.startsWith("'") && value.endsWith("'"))) {
                value = value.slice(1, -1);
            }
            
            // Parse arrays
            if (value.startsWith('[') && value.endsWith(']')) {
                value = value.slice(1, -1)
                    .split(',')
                    .map(item => item.trim().replace(/^["']|["']$/g, ''))
                    .filter(item => item);
            }
            
            result[key] = value;
        });
        
        return result;
    }

    getDisplayName(fileName) {
        return fileName.replace(/\.md$/, '').replace(/[-_]/g, ' ');
    }

    getFileMetadata(file) {
        // This is a simplified version - in a real app you'd fetch this from the file content
        // For now, return empty metadata
        return { tags: [] };
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        // Style the notification
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '12px 24px',
            borderRadius: '6px',
            color: 'white',
            fontWeight: '500',
            zIndex: '1000',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease'
        });
        
        // Set background color based on type
        const colors = {
            success: '#00b894',
            error: '#e74c3c',
            warning: '#fdcb6e',
            info: '#4a9eff'
        };
        notification.style.backgroundColor = colors[type] || colors.info;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Remove after delay
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    async handleFileUpload(event, type) {
        console.log(`handleFileUpload called for ${type}`, event);
        
        const file = event.target.files[0];
        if (!file) {
            console.log('No file selected');
            return;
        }

        console.log(`File selected: ${file.name}, Size: ${file.size}, Type: ${file.type}`);

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml', 'image/x-icon'];
        if (!allowedTypes.includes(file.type)) {
            console.error('Invalid file type:', file.type);
            this.showNotification('Invalid file type. Only images are allowed.', 'error');
            return;
        }

        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            console.error('File too large:', file.size);
            this.showNotification('File size too large. Maximum 2MB allowed.', 'error');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', type);

            console.log('Sending upload request...');
            const response = await fetch('api/upload.php', {
                method: 'POST',
                body: formData
            });

            console.log('Upload response status:', response.status);
            const result = await response.json();
            console.log('Upload response:', result);

            if (result.success) {
                console.log('Upload successful!');
                this.showNotification(result.message, 'success');
                this.updateFileDisplay(type, result.file_path);
                // Reload page to apply changes
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                console.error('Upload failed:', result.error);
                this.showNotification(result.error || 'Upload failed', 'error');
            }
        } catch (error) {
            console.error('Upload error:', error);
            this.showNotification('Upload failed', 'error');
        }
    }

    updateFileDisplay(type, filePath) {
        console.log(`updateFileDisplay called for ${type} with path: ${filePath}`);
        
        const containerId = type === 'favicon' ? 'currentFavicon' : 'currentHeaderIcon';
        const removeBtnId = type === 'favicon' ? 'removeFaviconBtn' : 'removeHeaderIconBtn';
        const container = document.getElementById(containerId);
        const removeBtn = document.getElementById(removeBtnId);

        console.log('Elements found:', {
            containerId,
            removeBtnId,
            container: !!container,
            removeBtn: !!removeBtn
        });

        if (!container) {
            console.error(`Container not found: ${containerId}`);
            return;
        }

        if (filePath && filePath !== 'null') {
            // Show the uploaded file
            container.innerHTML = `
                <div class="file-info">
                    <img src="${filePath}" alt="${type.replace('_', ' ')}" onerror="this.style.display='none'">
                    <div class="file-name">${filePath.split('/').pop()}</div>
                </div>
            `;
            if (removeBtn) removeBtn.style.display = 'inline-block';
            console.log(`Updated display for ${type} with file: ${filePath}`);
        } else {
            // Show no file message
            container.innerHTML = '<span class="no-file">No ' + type.replace('_', ' ') + ' uploaded</span>';
            if (removeBtn) removeBtn.style.display = 'none';
            console.log(`Cleared display for ${type}`);
        }
    }

    async removeFile(type) {
        if (!confirm(`Are you sure you want to remove the ${type.replace('_', ' ')}?`)) {
            return;
        }

        try {
            // Clear the file path from config
            const response = await fetch('api/settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'update',
                    settings: {
                        [type + '_path']: null
                    }
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification(`${type.replace('_', ' ')} removed successfully`, 'success');
                this.updateFileDisplay(type, null);
                // Reload page to apply changes
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                this.showNotification(result.error || 'Failed to remove file', 'error');
            }
        } catch (error) {
            console.error('Remove file error:', error);
            this.showNotification('Failed to remove file', 'error');
        }
    }

    async exportContent() {
        try {
            this.showNotification('Preparing export...', 'info');
            
            const response = await fetch('api/export.php');
            
            // Check if response is JSON (error) or ZIP (success)
            const contentType = response.headers.get('content-type');
            
            if (!response.ok || contentType?.includes('application/json')) {
                const text = await response.text();
                if (text.trim() === '') {
                    throw new Error('Export failed with empty response (status: ' + response.status + ')');
                }
                try {
                    const errorData = JSON.parse(text);
                    throw new Error(errorData.error || 'Export failed');
                } catch (jsonError) {
                    // If JSON parsing fails, show the raw response
                    throw new Error('Export failed with invalid response: ' + text.substring(0, 200));
                }
            }
            
            // Create download
            const blob = await response.blob();
            
            // Verify we got a valid blob
            if (blob.size === 0) {
                throw new Error('Export file is empty');
            }
            
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            
            // Get filename from response headers or create default
            const contentDisposition = response.headers.get('content-disposition');
            let filename = 'knowledge-base-export.zip';
            if (contentDisposition) {
                const filenameMatch = contentDisposition.match(/filename="([^"]+)"/);
                if (filenameMatch) {
                    filename = filenameMatch[1];
                }
            }
            
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            this.showNotification('Export completed successfully', 'success');
            
        } catch (error) {
            console.error('Export error:', error);
            this.showNotification(error.message || 'Export failed', 'error');
        }
    }

    openImportModal() {
        if (!this.importModal) return;
        
        // Reset modal state
        this.resetImportModal();
        this.importModal.classList.add('show');
        
        // Bind modal events
        this.bindImportModalEvents();
    }

    resetImportModal() {
        // Show step 1, hide others
        document.getElementById('importStep1').style.display = 'block';
        document.getElementById('importStep2').style.display = 'none';
        document.getElementById('importStep3').style.display = 'none';
        
        // Reset buttons
        document.getElementById('confirmImport').style.display = 'none';
        document.getElementById('finishImport').style.display = 'none';
        
        // Clear file input
        if (this.importFileInput) {
            this.importFileInput.value = '';
        }
        
        // Clear knowledgebase name input
        const knowledgebaseNameInput = document.getElementById('knowledgebaseName');
        if (knowledgebaseNameInput) {
            knowledgebaseNameInput.value = '';
        }
        
        // Reset stored session
        this.importSession = null;
    }

    bindImportModalEvents() {
        const selectFileBtn = document.getElementById('selectImportFile');
        const closeModalBtn = document.getElementById('closeImportModal');
        const cancelBtn = document.getElementById('cancelImport');
        const confirmBtn = document.getElementById('confirmImport');
        const finishBtn = document.getElementById('finishImport');
        const overwriteAllCheck = document.getElementById('overwriteAll');
        const removeAllFilesCheck = document.getElementById('removeAllFiles');
        
        // Remove existing listeners and add new ones
        selectFileBtn.replaceWith(selectFileBtn.cloneNode(true));
        document.getElementById('selectImportFile').addEventListener('click', () => {
            this.importFileInput.click();
        });
        
        closeModalBtn.addEventListener('click', () => this.closeImportModal());
        cancelBtn.addEventListener('click', () => this.closeImportModal());
        
        confirmBtn.addEventListener('click', () => this.confirmImport());
        finishBtn.addEventListener('click', () => this.closeImportModal());
        
        overwriteAllCheck.addEventListener('change', () => this.toggleConflictSelection());
        removeAllFilesCheck.addEventListener('change', () => this.toggleConflictVisibility());
    }

    toggleConflictVisibility() {
        const removeAllFiles = document.getElementById('removeAllFiles').checked;
        const conflictList = document.getElementById('conflictList');
        
        if (removeAllFiles) {
            // If removing all files, hide conflict options since they become irrelevant
            conflictList.style.display = 'none';
        } else {
            // Show conflict options if there are conflicts and we have import session data
            if (this.importSession && this.importSession.conflicts && this.importSession.conflicts.length > 0) {
                conflictList.style.display = 'block';
            }
        }
    }

    closeImportModal() {
        if (this.importModal) {
            this.importModal.classList.remove('show');
        }
    }

    async handleImportFile(event) {
        const file = event.target.files[0];
        if (!file) return;
        
        try {
            this.showNotification('Analyzing import file...', 'info');
            
            const knowledgebaseName = document.getElementById('knowledgebaseName')?.value || '';
            
            // Try regular session-based import first
            const formData = new FormData();
            formData.append('zipFile', file);
            formData.append('action', 'upload');
            formData.append('knowledgebase_name', knowledgebaseName);
            
            const response = await fetch('api/import.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (!result.success) {
                // If session-based import fails, try direct import for web servers
                console.log('Session-based import failed, trying direct import...');
                await this.tryDirectImport(file, knowledgebaseName);
                return;
            }
            
            this.importSession = result;
            this.showImportStep2(result);
            
        } catch (error) {
            console.error('Import analysis error:', error);
            // Try direct import as fallback
            try {
                const knowledgebaseName = document.getElementById('knowledgebaseName')?.value || '';
                await this.tryDirectImport(file, knowledgebaseName);
            } catch (fallbackError) {
                this.showNotification(error.message || 'Import analysis failed', 'error');
            }
        }
    }

    showImportStep2(analysisResult) {
        // Hide step 1, show step 2
        document.getElementById('importStep1').style.display = 'none';
        document.getElementById('importStep2').style.display = 'block';
        
        // Reset checkbox states
        document.getElementById('removeAllFiles').checked = false;
        document.getElementById('overwriteAll').checked = false;
        
        // Show summary
        const summary = document.getElementById('importSummary');
        summary.innerHTML = `
            <div class="import-summary">
                <p><strong>Import Analysis:</strong></p>
                <ul>
                    <li>Knowledge Base: <strong>${analysisResult.knowledgebase_name}</strong></li>
                    <li>Total files found: ${analysisResult.total_files}</li>
                    <li>New files: ${analysisResult.new_count}</li>
                    <li>Conflicting files: ${analysisResult.conflict_count}</li>
                </ul>
                <p><small>Files will be imported to: <code>/content/${analysisResult.knowledgebase_name}/</code></small></p>
            </div>
        `;
        
        // Show conflicts if any
        if (analysisResult.conflicts.length > 0) {
            this.showConflicts(analysisResult.conflicts);
        } else {
            // Hide conflict list if no conflicts
            document.getElementById('conflictList').style.display = 'none';
        }
        
        // Show confirm button
        document.getElementById('confirmImport').style.display = 'inline-block';
    }

    showConflicts(conflicts) {
        const conflictList = document.getElementById('conflictList');
        const conflictFiles = document.getElementById('conflictFiles');
        
        conflictList.style.display = 'block';
        
        const html = conflicts.map(conflict => `
            <div class="conflict-item">
                <label>
                    <input type="checkbox" class="conflict-checkbox" data-filename="${conflict.filename}">
                    <strong>${conflict.filename}</strong>
                </label>
                <div class="conflict-details">
                    <small>
                        Existing: ${this.formatFileSize(conflict.existing_size)} (${conflict.existing_modified})<br>
                        New: ${this.formatFileSize(conflict.new_size)} (${conflict.new_modified})
                    </small>
                </div>
            </div>
        `).join('');
        
        conflictFiles.innerHTML = html;
    }

    toggleConflictSelection() {
        const overwriteAll = document.getElementById('overwriteAll').checked;
        const checkboxes = document.querySelectorAll('.conflict-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = overwriteAll;
            checkbox.disabled = overwriteAll;
        });
    }

    async confirmImport() {
        try {
            this.showNotification('Importing files...', 'info');
            
            const overwriteAll = document.getElementById('overwriteAll').checked;
            const removeAllFiles = document.getElementById('removeAllFiles').checked;
            const selectedFiles = [];
            
            if (!overwriteAll && !removeAllFiles) {
                document.querySelectorAll('.conflict-checkbox:checked').forEach(checkbox => {
                    selectedFiles.push(checkbox.dataset.filename);
                });
            }
            
            console.log('Import debug - Session ID:', this.importSession.session_id);
            console.log('Import debug - Remove all files:', removeAllFiles);
            console.log('Import debug - Import session data:', this.importSession);
            
            if (!this.importSession || !this.importSession.session_id) {
                throw new Error('No valid import session. Please try uploading the file again.');
            }
            
            const formData = new FormData();
            formData.append('action', 'confirm');
            formData.append('session_id', this.importSession.session_id);
            formData.append('overwrite_all', overwriteAll ? 'true' : 'false');
            formData.append('remove_all_files', removeAllFiles ? 'true' : 'false');
            formData.append('selected_files', JSON.stringify(selectedFiles));
            
            const response = await fetch('api/import.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (!result.success) {
                // If session error, provide more helpful message
                if (result.error && result.error.includes('session')) {
                    throw new Error('Import session expired. Please start over by selecting your ZIP file again.');
                }
                throw new Error(result.error || 'Import failed');
            }
            
            this.showImportStep3(result);
            this.loadInitialData(); // Refresh file list
            
        } catch (error) {
            console.error('Import error:', error);
            this.showNotification(error.message || 'Import failed', 'error');
        }
    }

    showImportStep3(importResult) {
        // Hide step 2, show step 3
        document.getElementById('importStep2').style.display = 'none';
        document.getElementById('importStep3').style.display = 'block';
        
        // Show results
        const results = document.getElementById('importResults');
        results.innerHTML = `
            <div class="import-results">
                <p><strong>Import Complete!</strong></p>
                <ul>
                    ${importResult.removed_count > 0 ? `<li>🗑️ Removed: ${importResult.removed_count} existing files</li>` : ''}
                    <li>✅ Imported: ${importResult.imported_count} files</li>
                    ${importResult.skipped_count > 0 ? `<li>⏭️ Skipped: ${importResult.skipped_count} files</li>` : ''}
                    ${importResult.error_count > 0 ? `<li>❌ Errors: ${importResult.error_count} files</li>` : ''}
                </ul>
                ${importResult.removed_count > 0 ? `
                    <details>
                        <summary>Removed Files</summary>
                        <ul>${importResult.removed.map(file => `<li>${file}</li>`).join('')}</ul>
                    </details>
                ` : ''}
                ${importResult.imported.length > 0 ? `
                    <details>
                        <summary>Imported Files</summary>
                        <ul>${importResult.imported.map(file => `<li>${file}</li>`).join('')}</ul>
                    </details>
                ` : ''}
            </div>
        `;
        
        // Hide confirm, show finish
        document.getElementById('confirmImport').style.display = 'none';
        document.getElementById('finishImport').style.display = 'inline-block';
        
        this.showNotification('Import completed successfully', 'success');
    }

    async tryDirectImport(file, knowledgebaseName) {
        console.log('Attempting direct import for web server compatibility...');
        this.showNotification('Using web server compatible import mode...', 'info');
        
        // Skip to direct import confirmation with simple options
        this.showDirectImportDialog(file, knowledgebaseName);
    }

    showDirectImportDialog(file, knowledgebaseName) {
        // Show simplified import dialog for direct import
        document.getElementById('importStep1').style.display = 'none';
        document.getElementById('importStep2').style.display = 'block';
        
        const summary = document.getElementById('importSummary');
        summary.innerHTML = `
            <div class="import-summary">
                <p><strong>Direct Import Mode:</strong></p>
                <p>Your web server has short session timeouts. Using direct import mode.</p>
                <ul>
                    <li>File: <strong>${file.name}</strong></li>
                    <li>Knowledge Base: <strong>${knowledgebaseName || file.name.replace('.zip', '')}</strong></li>
                    <li>Size: ${this.formatFileSize(file.size)}</li>
                </ul>
                <p><small>Files will be imported to: <code>/content/${knowledgebaseName || file.name.replace('.zip', '')}/</code></small></p>
            </div>
        `;
        
        // Hide conflict list for direct import
        document.getElementById('conflictList').style.display = 'none';
        
        // Store file for direct import
        this.directImportFile = file;
        this.directImportKnowledgebase = knowledgebaseName;
        
        // Show confirm button
        document.getElementById('confirmImport').style.display = 'inline-block';
        
        // Update confirm button to handle direct import
        const confirmBtn = document.getElementById('confirmImport');
        confirmBtn.onclick = () => this.confirmDirectImport();
    }

    async confirmDirectImport() {
        try {
            this.showNotification('Importing files directly...', 'info');
            
            const overwriteAll = document.getElementById('overwriteAll').checked;
            const removeAllFiles = document.getElementById('removeAllFiles').checked;
            
            const formData = new FormData();
            formData.append('zipFile', this.directImportFile);
            formData.append('action', 'direct_import');
            formData.append('knowledgebase_name', this.directImportKnowledgebase || '');
            formData.append('overwrite_all', overwriteAll ? 'true' : 'false');
            formData.append('remove_all_files', removeAllFiles ? 'true' : 'false');
            
            const response = await fetch('api/import.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'Direct import failed');
            }
            
            this.showImportStep3(result);
            this.loadInitialData(); // Refresh file list
            
        } catch (error) {
            console.error('Direct import error:', error);
            this.showNotification(error.message || 'Direct import failed', 'error');
        }
    }

}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.kb = new KnowledgeBase();
});