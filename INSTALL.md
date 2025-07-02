# Installation Guide

# Markdown Knowledge Base (MDKB)
A modern, self-hosted web application for organizing and managing your documentation and notes. Built with PHP and featuring a clean, responsive interface, it allows you to create, edit, and organize Markdown files with powerful tagging and search capabilities.

## Key Features:


![Screenshot](MDKB2.jpg)

### 1. Files:

Create, edit, and delete Markdown files through a web interface
Automatic file management with modification timestamps and file size tracking
Secure file storage in a dedicated content directory with proper access controls

### 2. Tags:

Organize content with flexible tagging system for easy categorization
Browse and filter files by tags for quick navigation
Tag-based search functionality to find related content across your knowledge base

### 3. Markdown Editor:

Built-in Markdown editor with syntax highlighting and auto-save functionality
Customizable editor settings including font size and theme preferences
Split-pane view with real-time editing and synchronized scrolling

### 4. Markdown Preview:

Live preview of rendered Markdown content as you type
Side-by-side editor and preview panes for seamless writing experience
Fullscreen mode for focused writing or reading

### 5. Download:

Export individual Markdown files for backup or sharing
Secure download API with authentication and file validation
Maintains original file names and formatting during download
The application features secure cookie-based authentication, responsive design for desktop and mobile,

## First Time Setup

1. **Access Setup Page**
   - Go to: `http://(webserver)/knowledge-base/setup.php`
   - Or just visit the main URL and it will redirect

2. **Configure Settings**
   - Enter your desired site title
   - Set a secure password (minimum 6 characters)
   - Click "Complete Setup"

3. **Login**
   - You'll be redirected to login page
   - Enter your password
   - Start using your Knowledge Base!

## Security Notes

- Change the default password after first login
- Use HTTPS in production environments
- Regularly backup your `content/` folder
- Keep PHP and your web server updated 