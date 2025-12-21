# Student Organizer

A full-stack student management system built with Laravel (backend) and vanilla HTML/JavaScript (frontend).

## Features

- User authentication and authorization
- Course and semester management
- Task and goal tracking
- Assessment management
- Budget and transaction tracking
- Notes with file attachments
- Habits tracking
- Events and calendar integration
- Admin dashboard
- Google OAuth integration

## Tech Stack

- **Backend**: Laravel 12
- **Frontend**: HTML, JavaScript, Tailwind CSS
- **Database**: MySQL
- **Authentication**: Laravel Sanctum

## Requirements

- PHP >= 8.2
- Composer
- MySQL >= 5.7 or MariaDB >= 10.3
- Node.js >= 18 and npm
- Web server (Apache/Nginx)

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/a20ro/student-organizer.git
cd student-organizer
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Environment setup

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure database

Edit `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=student_organizer
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 5. Run migrations

```bash
php artisan migrate
```

### 6. Build frontend assets

```bash
npm run build
```

### 7. Create storage link

```bash
php artisan storage:link
```

### 8. Set permissions (Linux/Mac)

```bash
chmod -R 775 storage bootstrap/cache
```

## Development

Start the development server:

```bash
php artisan serve
npm run dev
```

Or use the combined command:

```bash
composer run dev
```

## Production Deployment

### 1. Server Requirements

- PHP 8.2+ with extensions: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache with mod_rewrite or Nginx)
- Composer
- Node.js and npm

### 2. Deployment Steps

1. **Clone and install**:
   ```bash
   git clone https://github.com/a20ro/student-organizer.git
   cd student-organizer
   composer install --optimize-autoloader --no-dev
   npm install
   npm run build
   ```

2. **Configure environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   
   Update `.env` with production settings:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   
   DB_CONNECTION=mysql
   DB_HOST=your_db_host
   DB_DATABASE=your_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   MAIL_MAILER=smtp
   MAIL_HOST=your_smtp_host
   MAIL_PORT=587
   MAIL_USERNAME=your_email
   MAIL_PASSWORD=your_password
   MAIL_FROM_ADDRESS=your_email
   ```

3. **Run migrations**:
   ```bash
   php artisan migrate --force
   ```

4. **Optimize for production**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan storage:link
   ```

5. **Set permissions**:
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

6. **Configure web server**:
   
   **Apache** - Point document root to `/public` directory
   
   **Nginx** - Example configuration:
   ```nginx
   server {
       listen 80;
       server_name yourdomain.com;
       root /path/to/student-organizer/public;
       
       add_header X-Frame-Options "SAMEORIGIN";
       add_header X-Content-Type-Options "nosniff";
       
       index index.php;
       
       charset utf-8;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location = /favicon.ico { access_log off; log_not_found off; }
       location = /robots.txt  { access_log off; log_not_found off; }
       
       error_page 404 /index.php;
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }
       
       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```

### 3. Frontend Deployment

The frontend files are in the `frontend/` directory. You can:

- Serve them from the same domain (recommended)
- Deploy to a static hosting service (Netlify, Vercel, etc.)
- Update API endpoints in `frontend/js/api.js` to point to your backend URL

### 4. Queue Workers (Optional)

If using queues, set up a supervisor or systemd service:

```bash
php artisan queue:work --daemon
```

## Environment Variables

Key environment variables to configure:

- `APP_NAME` - Application name
- `APP_ENV` - Environment (production/local)
- `APP_DEBUG` - Debug mode (false in production)
- `APP_URL` - Application URL
- `DB_*` - Database configuration
- `MAIL_*` - Email configuration
- `GOOGLE_CLIENT_ID` - Google OAuth client ID
- `GOOGLE_CLIENT_SECRET` - Google OAuth client secret
- `GOOGLE_REDIRECT_URI` - Google OAuth redirect URI

## Security

- Never commit `.env` file
- Use strong database passwords
- Enable HTTPS in production
- Keep dependencies updated
- Set `APP_DEBUG=false` in production
- Use secure session configuration

## License

MIT

## Support

For issues and questions, please open an issue on GitHub.
