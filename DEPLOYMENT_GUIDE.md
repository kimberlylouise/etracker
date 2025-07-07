# 🚀 ETRACKER WEBSITE DEPLOYMENT GUIDE

## 🔄 AUTOMATIC DEPLOYMENT FROM GITHUB (RECOMMENDED)

### ⭐ BEST OPTIONS FOR PHP + DATABASE:

#### 1. **RAILWAY** (Best for PHP + Database)
- ✅ Supports PHP natively
- ✅ Free tier available
- ✅ Auto-deploys from GitHub
- ✅ Built-in database support
- 🔗 https://railway.app

**Steps:**
1. Push your code to GitHub
2. Connect Railway to your GitHub repo
3. Railway auto-detects PHP and deploys
4. Connect to your existing AWS RDS database

#### 2. **HEROKU** (Popular Choice)
- ✅ Free tier (with limitations)
- ✅ GitHub integration
- ✅ Add-ons for databases
- 🔗 https://heroku.com

#### 3. **VERCEL** (Frontend + Serverless)
- ✅ Perfect for frontend
- ✅ Serverless functions for PHP-like functionality
- ✅ Instant GitHub deployment
- 🔗 https://vercel.com

#### 4. **NETLIFY** (Frontend + Functions)
- ✅ Great for static sites
- ✅ Serverless functions
- ✅ GitHub auto-deploy
- 🔗 https://netlify.com

### 🚀 QUICK SETUP FOR RAILWAY (RECOMMENDED)

1. **Prepare your repository:**
   ```bash
   # If you haven't already, initialize git in your project
   cd "C:\Users\luis ravalo\Desktop\Extension"
   git init
   git add .
   git commit -m "Initial commit"
   ```

2. **Push to GitHub:**
   - Create new repository on GitHub
   - Push your code there

3. **Deploy on Railway:**
   - Go to railway.app
   - Sign up with GitHub
   - Click "Deploy from GitHub repo"
   - Select your eTracker repository
   - Railway will auto-deploy!

### 🔧 CONFIGURATION FOR AUTO-DEPLOYMENT

Create these files in your project root:

#### `railway.json` (for Railway)
```json
{
  "build": {
    "builder": "heroku/php"
  },
  "deploy": {
    "startCommand": "php -S 0.0.0.0:$PORT -t ."
  }
}
```

#### `composer.json` (PHP dependencies)
```json
{
  "require": {
    "php": "^7.4 || ^8.0"
  }
}
```

#### `Procfile` (for Heroku)
```
web: php -S 0.0.0.0:$PORT -t .
```

## ✅ PRE-DEPLOYMENT CHECKLIST
- [x] Database is online (AWS RDS configured)
- [x] All db.php files point to online database
- [x] Main index.php created
- [ ] Files uploaded to hosting provider
- [ ] Domain/subdomain configured
- [ ] SSL certificate (if available)

## 📋 DEPLOYMENT STEPS

### STEP 1: PREPARE FILES FOR UPLOAD
1. **Create a ZIP file** of your entire Extension folder
2. **Exclude these files/folders** from upload:
   - .git folder
   - Learning Journal (OJT 2025).docx
   - *.md files (documentation)
   - *.txt files (unless needed)
   - *.sql files (database scripts - not needed online)

### STEP 2: CHOOSE HOSTING PROVIDER

#### OPTION A: FREE HOSTING (For Testing)
**Recommended: InfinityFree**
1. Go to https://infinityfree.net
2. Sign up for free account
3. Create new hosting account
4. Note your:
   - Control Panel URL
   - FTP credentials
   - Your subdomain (e.g., yoursite.epizy.com)

#### OPTION B: PAID HOSTING (For Production)
**Recommended providers:**
- Hostinger ($1.99/month)
- SiteGround ($2.99/month)
- Bluehost ($2.95/month)

### STEP 3: UPLOAD FILES

#### Method 1: File Manager (Easier)
1. Login to your hosting control panel
2. Open File Manager
3. Navigate to public_html or htdocs folder
4. Upload your ZIP file
5. Extract the ZIP file
6. Move all contents from Extension folder to root

#### Method 2: FTP (Advanced)
1. Download FileZilla FTP client
2. Connect using your FTP credentials
3. Upload all files to public_html folder

### STEP 4: CONFIGURE YOUR WEBSITE

#### Update File Paths (if needed)
Some hosting providers may require path adjustments:

```php
// Instead of:
require 'db.php';

// Use:
require __DIR__ . '/db.php';
```

#### Test Database Connection
1. Visit: yoursite.com/test_db_quick.php
2. Should show "Database connected successfully"

### STEP 5: TESTING YOUR LIVE WEBSITE

#### Test URLs:
- Main page: https://yoursite.com
- Login: https://yoursite.com/register/
- Admin: https://yoursite.com/ADMIN/
- Student: https://yoursite.com/STUDENT/
- Faculty: https://yoursite.com/FACULTY/

#### Test Functions:
1. User registration
2. Login functionality
3. Dashboard access
4. Database operations

### STEP 6: SECURITY CONFIGURATIONS

#### .htaccess Security (Create in root folder)
```apache
# Disable directory browsing
Options -Indexes

# Protect sensitive files
<Files "*.sql">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

# Force HTTPS (if SSL available)
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## 🔧 TROUBLESHOOTING

### Common Issues:

1. **Database Connection Errors**
   - Check AWS RDS security groups
   - Ensure hosting provider's IP is whitelisted

2. **File Permission Errors**
   - Set folder permissions to 755
   - Set file permissions to 644

3. **Missing Extensions**
   - Ensure hosting supports PHP 7.4+
   - Verify mysqli extension is enabled

### AWS RDS Security Group Settings:
- Add inbound rule for port 3306
- Source: 0.0.0.0/0 (for testing) or hosting provider's IP range

## 📞 NEXT STEPS AFTER DEPLOYMENT

1. **Test all functionality**
2. **Set up regular backups**
3. **Monitor error logs**
4. **Configure email settings** (if needed)
5. **Set up domain name** (if using custom domain)

## 🎯 QUICK START FOR INFINITYFREE

1. Sign up at infinityfree.net
2. Create hosting account
3. Upload files via File Manager
4. Visit your subdomain
5. Test login/registration

Your database is already configured and online, so once you upload the files, your website should work immediately!
