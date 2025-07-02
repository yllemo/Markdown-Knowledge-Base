# Markdown Knowledge Base (MDKB)

A modern, open source, self-hosted Markdown knowledge base. Organize, search, and tag your notes and documentation with a beautiful, responsive web interface.

![Screenshot](screenshot.png)

## Features
- Secure login with cookie-based authentication
- Create, edit, and delete Markdown files
- Tagging and search for fast organization
- Responsive design (desktop & mobile)
- File uploads with type/size restrictions
- Auto-save and backup support
- Browse and filter by tags or files
- Dark and light themes

## Installation
1. **Clone the repo:**
   ```sh
   git clone https://github.com/yourusername/markdown-knowledge-base.git
   cd markdown-knowledge-base
   ```
2. **Configure:**
   - Copy `config.php` to `config.custom.php` and edit as needed (password, session timeout, etc).
   - (Optional) Set up a web server (Apache, Nginx, or PHP built-in server).
3. **Access:**
   - Open `index.php` in your browser.

## Usage
- Log in with your configured password.
- Create, edit, and tag Markdown notes.
- Use the sidebar or search to find notes.
- Click the Files/Tags headers or stats to browse all.

## Configuration
- All settings are in `config.php` (or override in `config.custom.php`).
- Change password, session timeout, file size/type, and more.

## Contributing
Pull requests and issues are welcome! Please:
- Open an issue for bugs or feature requests
- Fork and submit a pull request for improvements

## License
MIT License. See [LICENSE](LICENSE).