// assets/js/app.js - Main Application JavaScript

class KnowledgeBase {
    constructor() {
        this.currentFile = '';
        this.unsavedChanges = false;
        this.searchTimeout = null;
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
        
        // Buttons
        this.newFileBtn = document.getElementById('newFileBtn');
        this.saveBtn = document.getElementById('saveBtn');
        this.deleteBtn = document.getElementById('deleteBtn');
        this.downloadBtn = document.getElementById('downloadBtn');
        this.closeBtn = document.getElementById('closeBtn');
        
        // Initialize mobile functionality
        this.setupMobileView();
    }

    bindEvents() {
        console.log('bindEvents called');
        // File operations
        this.newFileBtn.addEventListener('click', () => this.createNewFile());
        this.saveBtn.addEventListener('click', () => this.saveFile());
        this.deleteBtn.addEventListener('click', () => this.deleteFile());
        // Remove all previous listeners from downloadBtn
        this.downloadBtn.replaceWith(this.downloadBtn.cloneNode(true));
        this.downloadBtn = document.getElementById('downloadBtn');
        this.downloadBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.downloadFile();
        });
        this.closeBtn.addEventListener('click', () => this.closeEditor());
        
        // Settings operations
        this.settingsBtn.addEventListener('click', () => this.openSettingsModal());
        this.saveSettingsBtn.addEventListener('click', () => this.saveSettings());
        this.resetSettingsBtn.addEventListener('click', () => this.resetSettings());
        this.changePasswordBtn.addEventListener('click', () => this.changePassword());
        
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
        
        let html = filesToShow.map(file => `
            <div class="file-item" data-file="${file.name}">
                <span class="file-name" title="${file.display_name}">${file.display_name}</span>
                <span class="file-date">${new Date(file.modified * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</span>
            </div>
        `).join('');
        
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
            buttons[3].classList.add('active');
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
            return `
                <div class="browse-item" data-file="${file.name}">
                    <div class="browse-item-title">${file.display_name}</div>
                    <div class="browse-item-meta">
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
            document.getElementById('sessionTimeout').value = Math.floor((settings.session_timeout || 31536000) / 60);
            document.getElementById('sidebarWidth').value = settings.sidebar_width || 300;
            document.getElementById('editorFontSize').value = settings.editor_font_size || 14;
            document.getElementById('autoSaveInterval').value = Math.floor((settings.auto_save_interval || 30000) / 1000);
            document.getElementById('passwordProtected').checked = settings.password_protected || false;
            document.getElementById('backupEnabled').checked = settings.backup_enabled || false;
            document.getElementById('backupInterval').value = Math.floor((settings.backup_interval || 86400) / 3600);
            document.getElementById('maxBackups').value = settings.max_backups || 10;
            
        } catch (error) {
            console.error('Error loading settings:', error);
            this.showNotification('Error loading settings', 'error');
        }
    }

    async saveSettings() {
        try {
            const settings = {
                site_title: document.getElementById('siteTitle').value,
                session_timeout: parseInt(document.getElementById('sessionTimeout').value) * 60,
                sidebar_width: parseInt(document.getElementById('sidebarWidth').value),
                editor_font_size: parseInt(document.getElementById('editorFontSize').value),
                auto_save_interval: parseInt(document.getElementById('autoSaveInterval').value) * 1000,
                password_protected: document.getElementById('passwordProtected').checked,
                backup_enabled: document.getElementById('backupEnabled').checked,
                backup_interval: parseInt(document.getElementById('backupInterval').value) * 3600,
                max_backups: parseInt(document.getElementById('maxBackups').value)
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
                this.showNotification(result.error || 'Error saving settings', 'error');
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

        if (pane.classList.contains('fullscreen')) {
            // Exit fullscreen
            pane.classList.remove('fullscreen');
            if (otherPane) otherPane.style.display = 'flex';
            editorContainer.classList.remove('single-pane');
            if (header) header.style.display = '';
            body.classList.remove('fullscreen-mode');
        } else {
            // Enter fullscreen
            pane.classList.add('fullscreen');
            if (otherPane) otherPane.style.display = 'none';
            editorContainer.classList.add('single-pane');
            if (header) header.style.display = 'none';
            body.classList.add('fullscreen-mode');
        }
        // Always restore sidebar/header if exiting fullscreen
        if (!document.querySelector('.editor-pane.fullscreen') && !document.querySelector('.preview-pane.fullscreen')) {
            if (header) header.style.display = '';
            body.classList.remove('fullscreen-mode');
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

            this.currentFile = fileName;
            this.currentFileInput.value = fileName;
            
            // Parse frontmatter
            const { content, metadata } = this.parseFrontmatter(fileData.content);
            
            // Update UI
            this.fileTitle.value = metadata.title || this.getDisplayName(fileName);
            this.fileTags.value = (metadata.tags || []).join(', ');
            this.markdownEditor.value = content;
            
            // Update preview
            this.updatePreview();
            
            // Show editor
            this.showEditor();
            
            // Update active file
            this.updateActiveFile(fileName);
            
            this.unsavedChanges = false;
            this.updateSaveButton();
            
        } catch (error) {
            console.error('Error opening file:', error);
            alert('Error opening file');
        }
    }

    createNewFile() {
        if (this.unsavedChanges) {
            if (!confirm('You have unsaved changes. Do you want to continue?')) {
                return;
            }
        }

        const fileName = `note-${Date.now()}.md`;
        this.currentFile = fileName;
        this.currentFileInput.value = fileName;
        
        this.fileTitle.value = 'New Note';
        this.fileTags.value = '';
        this.markdownEditor.value = '# New Note\n\nStart writing here...';
        
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

        try {
            const response = await fetch('api/files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'save',
                    file: this.currentFile,
                    content: fullContent,
                    title: title
                })
            });

            const result = await response.json();
            
            if (result.error) {
                alert('Error saving file: ' + result.error);
                return;
            }

            this.unsavedChanges = false;
            this.updateSaveButton();
            
            // Refresh lists
            await this.refreshFileList();
            await this.refreshTagList();
            
            // Update active file
            this.updateActiveFile(this.currentFile);
            
            // Show success
            this.showNotification('File saved successfully!', 'success');
            
        } catch (error) {
            console.error('Error saving file:', error);
            alert('Error saving file');
        }
    }

    async deleteFile() {
        if (!this.currentFile) return;

        if (!confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch('api/files.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    file: this.currentFile
                })
            });

            const result = await response.json();
            
            if (result.error) {
                alert('Error deleting file: ' + result.error);
                return;
            }

            // Close editor and refresh
            this.closeEditor();
            await this.refreshFileList();
            await this.refreshTagList();
            
            this.showNotification('File deleted successfully!', 'success');
            
        } catch (error) {
            console.error('Error deleting file:', error);
            alert('Error deleting file');
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
        link.href = `api/download.php?file=${encodeURIComponent(this.currentFile)}`;
        link.download = this.currentFile; // Force the filename
        link.style.display = 'none';
        
        // Add to DOM, click, and remove
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        this.showNotification('Download started!', 'success');
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
        this.currentFileInput.value = '';
        this.unsavedChanges = false;
        this.updateActiveFile('');
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
                await this.refreshFileList();
                return;
            }

            try {
                const response = await fetch(`api/search.php?q=${encodeURIComponent(query)}`);
                const results = await response.json();
                this.renderFileList(results);
            } catch (error) {
                console.error('Search error:', error);
            }
        }, 300);
    }

    async filterByTag(tagName) {
        try {
            const response = await fetch(`api/files.php?tag=${encodeURIComponent(tagName)}`);
            const files = await response.json();
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
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.kb = new KnowledgeBase();
});