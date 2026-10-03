<?php
// index.php - Main interface for the Knowledge Base System

// Start output buffering to prevent header issues
ob_start();

require_once 'config/config.php';

// Revalidate the page so updated asset URLs reach returning browsers.
header('Cache-Control: no-cache');

// Check authentication
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Include required classes
require_once 'classes/FileManager.php';
require_once 'classes/SearchEngine.php';
require_once 'classes/TagManager.php';

$contentPath = getCurrentContentPath();
$fileManager = new FileManager($contentPath);
$searchEngine = new SearchEngine($contentPath);
$tagManager = new TagManager($contentPath);

// Get all files and tags for initial load
$files = $fileManager->getAllFiles();
$allTags = $tagManager->getAllTags();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
    <title><?= htmlspecialchars(getConfig('site_title', 'Knowledge Base')) ?></title>
    <?php 
    $favicon_path = getConfig('favicon_path');
    if ($favicon_path && file_exists($favicon_path)): ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($favicon_path) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= substr(hash_file('sha256', __DIR__ . '/assets/css/styles.css'), 0, 12) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-dark.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button id="mobileMenuBtn" class="mobile-menu-btn" style="display: none;" aria-label="Open file menu">☰</button>
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
            <button id="mobileToolsBtn" class="btn btn-secondary mobile-only" type="button" aria-expanded="false" aria-controls="headerActions">Tools</button>
            <div class="header-actions" id="headerActions">
                <div class="header-search-cluster">
                <?php
                $quickTags = getConfig('quick_filter_tags', ['top', 'prio', 'signal']);
                if (!is_array($quickTags)) {
                    $quickTags = [];
                }
                $quickTags = array_values(array_filter($quickTags, function ($t) {
                    return is_string($t) && $t !== '';
                }));
                if (!empty($quickTags)):
                ?>
                <div class="quick-tag-filters" role="toolbar" aria-label="Quick tag filters">
                    <?php foreach ($quickTags as $qt): ?>
                    <button type="button" class="quick-tag-btn" data-tag="<?= htmlspecialchars($qt, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars('Files with tag: ' . $qt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($qt, ENT_QUOTES, 'UTF-8') ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <input type="search" id="searchInput" placeholder="Search files..." class="search-input"
                       autocomplete="off"
                       autocorrect="off"
                       autocapitalize="none"
                       spellcheck="false"
                       data-form-type="search"
                       data-lpignore="true"
                       data-1p-ignore="true"
                       data-bwignore="true"
                       data-dashlane-ignore="true"
                       role="searchbox"
                       name="kb_q">
                </div>
                <div class="new-file-dropdown">
                    <button id="newFileBtn" class="btn btn-primary btn-split-left">+<span class="btn-text"> New File</span></button>
                    <button id="newFileDropdownBtn" class="btn btn-primary btn-split-right" title="Choose template">
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div id="newFileDropdown" class="template-dropdown">
                        <div class="template-item" data-template="blank">
                            <span class="template-icon">📄</span>
                            <div class="template-info">
                                <div class="template-name">Blank Document</div>
                                <div class="template-desc">Empty markdown file</div>
                            </div>
                        </div>
                        <div class="template-item" data-template="todo">
                            <span class="template-icon">✅</span>
                            <div class="template-info">
                                <div class="template-name">Todo List</div>
                                <div class="template-desc">Task checklist template</div>
                            </div>
                        </div>
                        <div class="template-item" data-template="explainer">
                            <span class="template-icon">💡</span>
                            <div class="template-info">
                                <div class="template-name">Explainer</div>
                                <div class="template-desc">Concept explanation template</div>
                            </div>
                        </div>
                        <div class="template-item" data-template="instructions">
                            <span class="template-icon">📋</span>
                            <div class="template-info">
                                <div class="template-name">Instructions</div>
                                <div class="template-desc">Step-by-step guide template</div>
                            </div>
                        </div>
                        <div class="template-item" data-template="diary">
                            <span class="template-icon">📅</span>
                            <div class="template-info">
                                <div class="template-name">Diary Entry</div>
                                <div class="template-desc">Daily journal with today's date</div>
                            </div>
                        </div>
                        <div class="template-item" data-template="ai-prompt">
                            <span class="template-icon">🤖</span>
                            <div class="template-info">
                                <div class="template-name">AI Prompt</div>
                                <div class="template-desc">Template for AI prompts and instructions</div>
                            </div>
                        </div>
                    </div>
                </div>
                <button id="loadFileBtn" class="btn btn-secondary" title="Load .md file">📁<span class="btn-text"> Load</span></button>
                <a href="filemanager/" class="btn btn-secondary" title="File Manager">📂<span class="btn-text"> File Manager</span></a>
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
                <div class="editor-header">
                    <input type="text" id="fileTitle" placeholder="File title..." class="title-input" 
                           autocomplete="off" 
                           data-form-type="other" 
                           data-lpignore="true"
                           data-1p-ignore="true"
                           name="file_title_field">
                    <div class="editor-actions">
                        <button id="mobileToggleBtn" class="mobile-toggle" style="display: none;" aria-pressed="false">👁️ Preview</button>
                        <button id="saveBtn" class="btn btn-success">💾 Save</button>
                        <button id="editorToolsBtn" class="btn btn-secondary mobile-only" type="button" aria-expanded="false">More ▾</button>
                        <button id="clearCompletedTasksBtn" class="btn btn-secondary" type="button" title="Ta bort alla rader med färdiga uppgifter">✓ Rensa färdiga</button>
                        <button id="deleteBtn" class="btn btn-danger">🗑️ Delete</button>
                        <button id="downloadBtn" class="btn btn-primary">⬇️ Download</button>
                        <div class="view-dropdown" id="viewDropdownContainer">
                            <button id="viewBtn" class="btn btn-primary btn-split-left" title="Open viewer (dark)">👁️ View</button>
                            <button id="viewDropdownBtn" class="btn btn-primary btn-split-right" title="Choose presentation" type="button" aria-haspopup="true" aria-expanded="false">
                                <span class="dropdown-arrow">▼</span>
                            </button>
                            <div id="viewDropdown" class="view-menu" role="menu">
                                <button type="button" class="view-menu-item" data-view="dark" role="menuitem">
                                    <span class="view-menu-icon">🌙</span>
                                    <span class="view-menu-text">
                                        <span class="view-menu-name">Dark</span>
                                        <span class="view-menu-desc">Viewer · dark theme</span>
                                    </span>
                                </button>
                                <button type="button" class="view-menu-item" data-view="light" role="menuitem">
                                    <span class="view-menu-icon">☀️</span>
                                    <span class="view-menu-text">
                                        <span class="view-menu-name">Light</span>
                                        <span class="view-menu-desc">Viewer · light theme</span>
                                    </span>
                                </button>
                                <button type="button" class="view-menu-item" data-view="colab" role="menuitem">
                                    <span class="view-menu-icon">💬</span>
                                    <span class="view-menu-text">
                                        <span class="view-menu-name">Colab</span>
                                        <span class="view-menu-desc">Collaboration view</span>
                                    </span>
                                </button>
                                <button type="button" class="view-menu-item" data-view="reader" role="menuitem">
                                    <span class="view-menu-icon">📖</span>
                                    <span class="view-menu-text">
                                        <span class="view-menu-name">Läsfokus</span>
                                        <span class="view-menu-desc">Läs i din egen takt</span>
                                    </span>
                                </button>
                                <button type="button" class="view-menu-item" data-view="print" role="menuitem">
                                    <span class="view-menu-icon">🖨️</span>
                                    <span class="view-menu-text">
                                        <span class="view-menu-name">Print</span>
                                        <span class="view-menu-desc">Print &amp; export</span>
                                    </span>
                                </button>
                            </div>
                        </div>
                        <button id="closeBtn" class="btn btn-secondary">✕ Close</button>
                    </div>
                </div>
                
                <div class="editor-meta">
                    <div class="tags-input-container">
                        <input type="text" id="fileTags" placeholder="Tags (space or comma-separated)..." class="tags-input" 
                               autocomplete="off" 
                               data-form-type="other" 
                               data-lpignore="true"
                               data-1p-ignore="true"
                               name="file_tags_field">
                        <span class="tags-help" title="Type tags with spaces and they'll automatically be converted to comma-separated format">ⓘ</span>
                    </div>
                </div>

                <div class="editor-content">
                    <div class="editor-pane">
                        <h4>📝 Editor</h4>
                        <div id="markdownEditor" class="monaco-host"></div>
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
            <input type="search" id="browseSearch" class="browse-search" placeholder="Search..."
                   autocomplete="off"
                   autocorrect="off"
                   autocapitalize="none"
                   spellcheck="false"
                   data-form-type="search"
                   data-lpignore="true"
                   data-1p-ignore="true"
                   data-bwignore="true"
                   data-dashlane-ignore="true"
                   role="searchbox"
                   name="kb_browse_q">
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
                        <input type="text" id="siteTitle" placeholder="Enter site title..."
                               autocomplete="off"
                               data-lpignore="true"
                               data-1p-ignore="true"
                               name="site_title_field">
                    </div>
                    <div class="setting-group">
                        <label for="currentKnowledgebase">Content root</label>
                        <select id="currentKnowledgebase" class="form-select">
                            <?php 
                            $knowledgebases = getAvailableKnowledgebases();
                            $currentKb = getConfig('current_knowledgebase', '');
                            foreach ($knowledgebases as $value => $label): 
                                $selected = ($currentKb === $value || (empty($currentKb) && $value === 'root')) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= $selected ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Choose the starting folder for files, search and tags. New files are saved here. Save settings to apply; existing files are not moved.</small>
                    </div>
                    <div class="setting-group">
                        <label for="newContentRoot">Create a content root</label>
                        <input type="text" id="newContentRoot" placeholder="e.g. 2026" maxlength="64" autocomplete="off">
                        <button type="button" id="createContentRootBtn" class="btn btn-secondary">Create and select</button>
                    </div>
                    <div class="setting-group">
                        <label for="sessionTimeout">Session Timeout (minutes)</label>
                        <input type="number" id="sessionTimeout" min="5" max="525600" step="5">
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
                            <input type="checkbox" id="passwordProtected" checked disabled>
                            Password protection is always enabled
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
                    <h3>MCP – anslut en AI-klient</h3>
                    <p>Ger läsåtkomst till sparad innehållsroot: sök i innehåll och taggar, lista och läs dokument. Kräver MCP 2026-07-28 och en Bearer-nyckel via HTTPS.</p>
                    <div class="setting-group">
                        <label for="mcpEndpoint">Serveradress</label>
                        <input id="mcpEndpoint" type="url" readonly>
                    </div>
                    <p id="mcpKeyStatus" role="status"></p>
                    <button type="button" id="generateMcpKey" class="btn btn-primary">Generera ny nyckel</button>
                    <button type="button" id="revokeMcpKey" class="btn btn-secondary">Återkalla nyckel</button>
                    <p>En ny nyckel ersätter den tidigare direkt. Ändringen sparas direkt, oberoende av Save Settings.</p>
                    <div id="mcpNewKey" class="setting-group" hidden>
                        <label for="mcpKeyValue">Kopiera nyckeln nu – den visas bara denna gång</label>
                        <input id="mcpKeyValue" type="text" readonly autocomplete="off" spellcheck="false">
                        <button type="button" id="copyMcpKey" class="btn btn-secondary">Kopiera nyckel</button>
                    </div>
                </div>
                <div class="settings-section">
                    <h3>File recovery</h3>
                    <p>The main editor keeps recovery copies before changing or deleting files (up to 10 per filename). Scheduled backups are not implemented. Use Export to download a ZIP backup.</p>
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
                    <div class="setting-group">
                        <label for="knowledgebaseName">Knowledge Base Name</label>
                        <input type="text" id="knowledgebaseName" placeholder="Enter knowledge base name (optional)..." 
                               class="form-input"
                               autocomplete="off"
                               data-lpignore="true"
                               data-1p-ignore="true"
                               name="knowledgebase_name_field">
                        <small>If empty, the ZIP filename will be used. This will create a subfolder under /content/[name]</small>
                    </div>
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
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/loader.js"></script>
    <script src="assets/js/monaco-markdown.js?v=<?= substr(hash_file('sha256', __DIR__ . '/assets/js/monaco-markdown.js'), 0, 12) ?>"></script>
    <script src="assets/js/markdown-tasks.js?v=<?= substr(hash_file('sha256', __DIR__ . '/assets/js/markdown-tasks.js'), 0, 12) ?>"></script>
    <script src="assets/js/app.js?v=<?= substr(hash_file('sha256', __DIR__ . '/assets/js/app.js'), 0, 12) ?>"></script>
</body>
</html>
