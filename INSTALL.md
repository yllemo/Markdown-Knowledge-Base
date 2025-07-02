# Installation Guide

## Quick Setup for Windows

### Option 1: XAMPP (Recommended)

1. **Download XAMPP**
   - Go to: https://www.apachefriends.org/download.html
   - Download the Windows version (PHP 8.x)

2. **Install XAMPP**
   - Run the installer
   - Choose default installation path: `C:\xampp\`
   - Select Apache and PHP components

3. **Copy Knowledge Base Files**
   - Copy your Knowledge Base folder to: `C:\xampp\htdocs\knowledge-base\`

4. **Start Apache**
   - Open XAMPP Control Panel
   - Click "Start" next to Apache
   - Wait for Apache to start (green status)

5. **Access Your Knowledge Base**
   - Open browser and go to: `http://localhost/knowledge-base/`
   - You should see the setup page

### Option 2: Standalone PHP

1. **Download PHP**
   - Go to: https://windows.php.net/download/
   - Download "Thread Safe" version (ZIP file)

2. **Extract PHP**
   - Extract to: `C:\php\`
   - Copy `php.ini-development` to `php.ini`

3. **Add to PATH**
   - Open System Properties → Environment Variables
   - Add `C:\php\` to PATH variable

4. **Test Installation**
   - Open Command Prompt
   - Run: `php -v`
   - Should show PHP version

5. **Start Server**
   - Navigate to your Knowledge Base folder
   - Run: `php -S localhost:8000`
   - Access: `http://localhost:8000`

## Troubleshooting

### "PHP not recognized" Error
- Make sure PHP is added to your system PATH
- Restart Command Prompt after adding to PATH
- Try using the full path: `C:\php\php.exe -S localhost:8000`

### "Permission Denied" Error
- Run Command Prompt as Administrator
- Check folder permissions
- Make sure `content/` folder is writable

### Blank Page
- Check if PHP is working: visit `http://localhost:8000/test.php`
- Check browser console for errors
- Check server logs for PHP errors

### Redirect Issues
- Visit `http://localhost:8000/debug.php` to see debug information
- Check if all files are in the correct location
- Verify `config.php` exists and is readable

## File Structure After Installation

```
C:\xampp\htdocs\knowledge-base\  (or your chosen location)
├── index.php
├── login.php
├── setup.php
├── config.php
├── api/
├── assets/
├── classes/
├── content/
└── ... (other files)
```

## First Time Setup

1. **Access Setup Page**
   - Go to: `http://localhost/knowledge-base/setup.php`
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