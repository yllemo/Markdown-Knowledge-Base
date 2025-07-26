<?php
// index.php - Main interface for the Knowledge Base System

// Start output buffering to prevent header issues
ob_start();

require_once 'config.php';

// Check authentication
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Include required classes
require_once 'classes/FileManager.php';
require_once 'classes/SearchEngine.php';
require_once 'classes/TagManager.php';

$fileManager = new FileManager();
$searchEngine = new SearchEngine();
$tagManager = new TagManager();

// Get all files and tags for initial load
$files = $fileManager->getAllFiles();
$allTags = $tagManager->getAllTags();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(getConfig('site_title', 'Knowledge Base')) ?></title>
    <?php 
    $favicon_path = getConfig('favicon_path');
    if ($favicon_path && file_exists($favicon_path)): ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($favicon_path) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-dark.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button id="mobileMenuBtn" class="mobile-menu-btn" style="display: none;">☰</button>
                <h1 id="headerTitle" style="cursor: pointer;">
                    <?php 
                    $header_icon_path = getConfig('header_icon_path');
                    if ($header_icon_path && file_exists($header_icon_path)): ?>
                        <img src="<?= htmlspecialchars($header_icon_path) ?>" alt="Header Icon" style="width: 24px; height: 24px; margin-right: 8px; vertical-align: middle;">
                    <?php else: ?>
                        📚
                    <?php endif; ?>
                    <?= htmlspecialchars(getConfig('site_title', 'Knowledge Base')) ?>
                </h1>
            </div>
            <div class="header-actions">
                <input type="text" id="searchInput" placeholder="Search files..." class="search-input">
                <button id="newFileBtn" class="btn btn-primary">+<span class="btn-text"> New File</span></button>
                <button id="loadFileBtn" class="btn btn-secondary" title="Load .md file">📁<span class="btn-text"> Load</span></button>
                <button id="exportBtn" class="btn btn-secondary" title="Export all files as ZIP">📤<span class="btn-text"> Export</span></button>
                <button id="importBtn" class="btn btn-secondary" title="Import files from ZIP">📥<span class="btn-text"> Import</span></button>
                <button id="settingsBtn" class="btn btn-secondary" title="Settings">⚙️</button>
                <a href="logout.php" class="btn btn-logout" title="Logout">🚪</a>
            </div>
        </header>

        <!-- Mobile Overlay -->
        <div id="mobileOverlay" class="mobile-overlay"></div>

        <!-- Main Content -->
        <main class="app-main">
            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-section">
                    <h3>📁 Files <span class="section-count"><?= count($files) ?></span></h3>
                    <div class="section-actions">
                        <button class="section-btn active" onclick="window.kb.showRecent('files')">Recent</button>
                        <button class="section-btn" onclick="window.kb.browseAll('files')">Browse All</button>
                    </div>
                    <div id="fileList" class="file-list">
                        <?php 
                        $displayFiles = array_slice($files, 0, 15);
                        foreach ($displayFiles as $file): ?>
                            <div class="file-item" data-file="<?= htmlspecialchars($file['name']) ?>">
                                <span class="file-name" title="<?= htmlspecialchars($file['display_name']) ?>"><?= htmlspecialchars($file['display_name']) ?></span>
                                <span class="file-date"><?= date('M j', $file['modified']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sidebar-section">
                    <h3>🏷️ Tags <span class="section-count"><?= count($allTags) ?></span></h3>
                    <div class="section-actions">
                        <button class="section-btn active" onclick="window.kb.showPopular('tags')">Popular</button>
                        <button class="section-btn" onclick="window.kb.browseAll('tags')">Browse All</button>
                    </div>
                    <div id="tagList" class="tag-list">
                        <?php 
                        $displayTags = array_slice($allTags, 0, 10, true);
                        foreach ($displayTags as $tag => $count): ?>
                            <div class="tag-item" data-tag="<?= htmlspecialchars($tag) ?>">
                                <span class="tag-name" title="<?= htmlspecialchars($tag) ?>"><?= htmlspecialchars($tag) ?></span>
                                <span class="tag-count"><?= $count ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </aside>

            <!-- Editor Area -->
            <div class="editor-container" id="editorContainer" style="display: none;">
                <form autocomplete="off" onsubmit="return false;">
                    <!-- Hidden input to prevent password manager triggers -->
                    <input type="text" style="display:none;" autocomplete="off">
                    <input type="password" style="display:none;" autocomplete="off">
                </form>
                <div class="editor-header">
                    <input type="text" id="fileTitle" placeholder="File title..." class="title-input" autocomplete="off" data-form-type="other">
                    <div class="editor-actions">
                        <button id="mobileToggleBtn" class="mobile-toggle" style="display: none;">👁️ Preview</button>
                        <button id="saveBtn" class="btn btn-success">💾 Save</button>
                        <button id="deleteBtn" class="btn btn-danger">🗑️ Delete</button>
                        <button id="downloadBtn" class="btn btn-primary">⬇️ Download</button>
                        <button id="closeBtn" class="btn btn-secondary">✕ Close</button>
                    </div>
                </div>
                
                <div class="editor-meta">
                    <div class="tags-input-container">
                        <input type="text" id="fileTags" placeholder="Tags (space or comma-separated)..." class="tags-input" autocomplete="off" data-form-type="other">
                        <span class="tags-help" title="Type tags with spaces and they'll automatically be converted to comma-separated format">ⓘ</span>
                    </div>
                </div>

                <div class="editor-content">
                    <div class="editor-pane">
                        <h4>📝 Editor</h4>
                        <textarea id="markdownEditor" placeholder="Start writing your markdown here..." spellcheck="false"></textarea>
                    </div>
                    <div class="preview-pane">
                        <h4 id="previewPaneHeader">👁️ Preview</h4>
                        <div id="markdownPreview" class="markdown-preview"></div>
                    </div>
                </div>
            </div>

            <!-- Welcome Screen -->
            <div class="welcome-screen" id="welcomeScreen">
                <div class="welcome-content">
                    <h2>Welcome to Your Knowledge Base</h2>
                    <p>Select a file from the sidebar to start editing, or create a new file to begin.</p>
                    <div class="welcome-stats">
                        <div class="stat" id="welcomeFilesStat" style="cursor: pointer;">
                            <span class="stat-number"><?= count($files) ?></span>
                            <span class="stat-label">Files</span>
                        </div>
                        <div class="stat" id="welcomeTagsStat" style="cursor: pointer;">
                            <span class="stat-number"><?= count($allTags) ?></span>
                            <span class="stat-label">Tags</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Browse Modal -->
    <div id="browseModal" class="browse-modal">
        <div class="browse-content">
            <div class="browse-header">
                <h2 id="browseTitle">Browse All Files</h2>
                <button class="close-modal" onclick="window.kb.closeBrowseModal()">✕</button>
            </div>
            <input type="text" id="browseSearch" class="browse-search" placeholder="Search...">
            <div id="browseList" class="browse-list">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="settings-modal">
        <div class="settings-content">
            <div class="settings-header">
                <h2>⚙️ Settings</h2>
                <button class="close-modal" onclick="window.kb.closeSettingsModal()">✕</button>
            </div>
            <div class="settings-body">
                <div class="settings-section">
                    <h3>General Settings</h3>
                    <div class="setting-group">
                        <label for="siteTitle">Site Title</label>
                        <input type="text" id="siteTitle" placeholder="Enter site title...">
                    </div>
                    <div class="setting-group">
                        <label for="sessionTimeout">Session Timeout (minutes)</label>
                        <input type="number" id="sessionTimeout" min="5" max="1440" step="5">
                    </div>
                    <div class="setting-group">
                        <label for="sidebarWidth">Sidebar Width (pixels)</label>
                        <input type="number" id="sidebarWidth" min="200" max="500" step="10">
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3>Appearance Settings</h3>
                    <div class="setting-group">
                        <label for="faviconUpload">Favicon</label>
                        <div class="upload-container">
                            <div class="current-file" id="currentFavicon">
                                <span class="no-file">No favicon uploaded</span>
                            </div>
                            <input type="file" id="faviconUpload" accept="image/*" style="display: none;">
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('faviconUpload').click()">Choose File</button>
                            <button type="button" class="btn btn-danger" id="removeFaviconBtn" style="display: none;">Remove</button>
                        </div>
                        <small>Upload an image file (JPG, PNG, GIF, SVG, ICO). Max 2MB.</small>
                    </div>
                    <div class="setting-group">
                        <label for="headerIconUpload">Header Icon</label>
                        <div class="upload-container">
                            <div class="current-file" id="currentHeaderIcon">
                                <span class="no-file">No header icon uploaded</span>
                            </div>
                            <input type="file" id="headerIconUpload" accept="image/*" style="display: none;">
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('headerIconUpload').click()">Choose File</button>
                            <button type="button" class="btn btn-danger" id="removeHeaderIconBtn" style="display: none;">Remove</button>
                        </div>
                        <small>Upload an image file (JPG, PNG, GIF, SVG, ICO). Max 2MB.</small>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3>Editor Settings</h3>
                    <div class="setting-group">
                        <label for="editorFontSize">Editor Font Size (pixels)</label>
                        <input type="number" id="editorFontSize" min="10" max="24" step="1">
                    </div>
                    <div class="setting-group">
                        <label for="autoSaveInterval">Auto-save Interval (seconds)</label>
                        <input type="number" id="autoSaveInterval" min="5" max="300" step="5">
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3>Security Settings</h3>
                    <div class="setting-group checkbox-group">
                        <label>
                            <input type="checkbox" id="passwordProtected">
                            Enable password protection
                        </label>
                    </div>
                    <div class="setting-group">
                        <label for="currentPassword">Current Password</label>
                        <input type="password" id="currentPassword" placeholder="Enter current password...">
                    </div>
                    <div class="setting-group">
                        <label for="newPassword">New Password</label>
                        <input type="password" id="newPassword" placeholder="Enter new password...">
                    </div>
                    <div class="setting-group">
                        <label for="confirmPassword">Confirm New Password</label>
                        <input type="password" id="confirmPassword" placeholder="Confirm new password...">
                    </div>
                    <button id="changePasswordBtn" class="btn btn-primary">Change Password</button>
                </div>
                
                <div class="settings-section">
                    <h3>Backup Settings</h3>
                    <div class="setting-group checkbox-group">
                        <label>
                            <input type="checkbox" id="backupEnabled">
                            Enable automatic backups
                        </label>
                    </div>
                    <div class="setting-group">
                        <label for="backupInterval">Backup Interval (hours)</label>
                        <input type="number" id="backupInterval" min="1" max="168" step="1">
                    </div>
                    <div class="setting-group">
                        <label for="maxBackups">Maximum Backups</label>
                        <input type="number" id="maxBackups" min="1" max="100" step="1">
                    </div>
                </div>
            </div>
            <div class="settings-footer">
                <button id="saveSettingsBtn" class="btn btn-success">💾 Save Settings</button>
                <button id="resetSettingsBtn" class="btn btn-secondary">🔄 Reset to Defaults</button>
            </div>
        </div>
    </div>

    <!-- Hidden input for file operations -->
    <input type="hidden" id="currentFile" value="">
    
    <!-- Hidden file input for loading .md files -->
    <input type="file" id="loadFileInput" accept=".md,.markdown" style="display: none;">
    
    <!-- Hidden file input for importing ZIP files -->
    <input type="file" id="importFileInput" accept=".zip" style="display: none;">

    <!-- Import Modal -->
    <div id="importModal" class="settings-modal">
        <div class="settings-content">
            <div class="settings-header">
                <h2>Import Content</h2>
                <button id="closeImportModal" class="close-modal">✕</button>
            </div>
            <div class="settings-body" id="importModalBody">
                <div id="importStep1" class="import-step">
                    <h3>Select ZIP File</h3>
                    <p>Choose a ZIP file containing .md files to import into your knowledge base.</p>
                    <button id="selectImportFile" class="btn btn-primary">📁 Select ZIP File</button>
                </div>
                
                <div id="importStep2" class="import-step" style="display: none;">
                    <h3>Review Import</h3>
                    <div id="importSummary"></div>
                    
                    <div style="margin: 1rem 0; padding: 1rem; background-color: var(--bg-secondary); border-radius: 8px; border-left: 4px solid var(--warning);">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" id="removeAllFiles" style="margin: 0;">
                            <strong>Remove all existing files before import</strong>
                        </label>
                        <p style="margin: 0.5rem 0 0 1.5rem; font-size: 0.9rem; color: var(--text-secondary);">
                            ⚠️ This will permanently delete ALL files in your content folder before importing the new ones.
                        </p>
                    </div>
                    
                    <div id="conflictList" style="display: none;">
                        <h4>File Conflicts</h4>
                        <p>The following files already exist. Select which ones to overwrite:</p>
                        <div id="conflictFiles"></div>
                        <label style="margin-top: 1rem;">
                            <input type="checkbox" id="overwriteAll"> Overwrite all existing files
                        </label>
                    </div>
                </div>
                
                <div id="importStep3" class="import-step" style="display: none;">
                    <h3>Import Complete</h3>
                    <div id="importResults"></div>
                </div>
            </div>
            <div class="settings-footer">
                <button id="cancelImport" class="btn btn-secondary">Cancel</button>
                <button id="confirmImport" class="btn btn-success" style="display: none;">Import Files</button>
                <button id="finishImport" class="btn btn-primary" style="display: none;">Finish</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/5.1.1/marked.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>