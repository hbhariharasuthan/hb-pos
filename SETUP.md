# Database Setup Guide for MAMP

## Step 1: Create the Database

You need to create the `hb_erp` database first. You can do this in two ways:

### Option A: Using phpMyAdmin (Recommended)
1. Open MAMP and start the servers
2. Go to http://localhost:8888/phpMyAdmin
3. Click on "New" in the left sidebar
4. Enter database name: `hb_erp`
5. Select collation: `utf8mb4_unicode_ci`
6. Click "Create"

### Option B: Using MySQL Command Line
```bash
mysql -u root -proot -h 127.0.0.1 -P 8889
CREATE DATABASE hb_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

## Step 2: Verify MAMP MySQL Settings

Check your MAMP MySQL settings:
- **Port**: Usually `8889` (default MAMP MySQL port)
- **Username**: Usually `root`
- **Password**: Usually `root` (or empty)

If your MAMP uses different settings, update the `.env` file accordingly.

## Step 3: Update .env File

Make sure your `.env` file has the correct settings:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=hb_erp
DB_USERNAME=root
DB_PASSWORD=root
```

**Note**: If your MAMP MySQL password is empty, set `DB_PASSWORD=` (empty)

## Step 4: Test Database Connection

```bash
php artisan migrate:status
```

## Step 5: Run Migrations and Seeders

Once the database is created and connection works:

```bash
php artisan migrate --seed
```

This will:
- Create all database tables
- Seed initial data (admin user, sample products, categories, customers)

## Troubleshooting

### If you get "Operation not permitted" error:
1. Make sure MAMP MySQL is running
2. Check if the port is correct (8889 for MAMP)
3. Try using `localhost` instead of `127.0.0.1`:
   ```env
   DB_HOST=localhost
   ```

### If socket error occurs:
Remove the DB_SOCKET line from .env or update it to your MAMP socket path:
```env
DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock
```

### Check MAMP MySQL Status:
- Open MAMP application
- Check if MySQL shows green (running)
- If not, click "Start Servers"

## Default Login Credentials (after seeding)

- **Admin**: admin@pos.com / password
- **Cashier**: cashier@pos.com / password
