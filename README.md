# POS & Inventory Management System

A comprehensive Point of Sale (POS) and Inventory Management System built with Laravel backend and Vue.js frontend.

## Features

### Core Functionality
- **Point of Sale (POS) Interface** - Modern, intuitive POS interface for processing sales
- **Product Management** - Complete CRUD operations for products with categories
- **Customer Management** - Manage customer database with contact information
- **Sales Management** - View and manage sales history
- **Invoice Printing** - Generate and print professional invoices
- **Stock Management** - Track inventory levels and stock movements
- **Returns Management** - Process product returns and refunds
- **Role-Based Access Control** - User roles and permissions system

### Technical Features
- RESTful API architecture
- Real-time inventory updates
- Form validation (client-side and server-side)
- Error handling and user feedback
- Token-based authentication using Laravel Sanctum
- Responsive design

## Requirements

- PHP >= 8.1
- Composer
- Node.js >= 16.x and npm
- MySQL database (Database name: `hb_erp`)

## Installation

1. **Install PHP dependencies:**
   ```bash
   composer install
   ```

2. **Install Node.js dependencies:**
   ```bash
   npm install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Update `.env` file with your database credentials:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=hb_erp
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

5. **Run migrations:**
   ```bash
   php artisan migrate
   ```

6. **Seed database (optional - creates sample data):**
   ```bash
   php artisan db:seed
   ```

   This will create:
   - Admin user: `admin@pos.com` / `password`
   - Cashier user: `cashier@pos.com` / `password`
   - Sample categories and products
   - Sample customers

7. **Build assets:**
   ```bash
   npm run build
   ```

   Or for development with hot reload:
   ```bash
   npm run dev
   ```

8. **Start the Laravel server:**
   ```bash
   php artisan serve
   ```

## Usage

### Login
- Navigate to `/login`
- Use the seeded credentials or register a new account

### Dashboard
- Access all modules from the main dashboard
- Navigate between POS, Products, Customers, and Sales

### Point of Sale
- Search products by name, SKU, or barcode
- Add items to cart
- Select customer (optional)
- Apply discounts and tax
- Process payment
- Generate invoice

### Products Management
- Add, edit, and delete products
- Set stock levels and minimum stock alerts
- Organize by categories
- Track cost and selling prices

### Customers Management
- Manage customer database
- Track customer balances
- Set credit limits

### Sales History
- View all sales transactions
- Filter by date range
- View detailed invoices
- Print invoices

## API Endpoints

### Authentication
- `POST /api/register` - Register a new user
- `POST /api/login` - Login user
- `POST /api/logout` - Logout user (requires authentication)
- `GET /api/user` - Get authenticated user (requires authentication)

### Products
- `GET /api/products` - List all products
- `POST /api/products` - Create a product
- `GET /api/products/{id}` - Get product details
- `PUT /api/products/{id}` - Update product
- `DELETE /api/products/{id}` - Delete product

### Categories
- `GET /api/categories` - List all categories
- `POST /api/categories` - Create a category
- `PUT /api/categories/{id}` - Update category
- `DELETE /api/categories/{id}` - Delete category

### Customers
- `GET /api/customers` - List all customers
- `POST /api/customers` - Create a customer
- `GET /api/customers/{id}` - Get customer details
- `PUT /api/customers/{id}` - Update customer
- `DELETE /api/customers/{id}` - Delete customer

### POS
- `GET /api/pos/products` - Get products for POS (active products with stock)
- `POST /api/pos/sale` - Process a sale transaction

### Sales
- `GET /api/sales` - List all sales
- `GET /api/sales/{id}` - Get sale details
- `GET /api/sales/{id}/invoice` - Get invoice data

### Stock
- `GET /api/stock/movements` - List stock movements
- `POST /api/stock/adjust` - Adjust stock levels
- `GET /api/stock/low-stock` - Get low stock products

### Returns
- `GET /api/returns` - List all returns
- `POST /api/returns` - Create a return
- `GET /api/returns/{id}` - Get return details

### Expense categories
- `GET /api/expense-categories/all` - List all expense categories (for dropdowns)
- `GET /api/expense-categories` - Paginated list with search and is_active filter
- `POST /api/expense-categories` - Create category
- `GET /api/expense-categories/{id}` - Get category
- `PUT /api/expense-categories/{id}` - Update category
- `DELETE /api/expense-categories/{id}` - Delete category

### Expenses
- `GET /api/expenses` - List expenses (optional: date_from, date_to, expense_category_id, status, search, per_page)
- `POST /api/expenses` - Create expense
- `GET /api/expenses/{id}` - Get expense
- `PUT /api/expenses/{id}` - Update expense
- `DELETE /api/expenses/{id}` - Delete expense

### Day book entries (Phase 2)
- `GET /api/day-book-entries` - List all day book entries (optional: date_from, date_to, entry_type, search, per_page). Includes system-generated (sale, purchase, return, expense) and manual (journal, opening_balance, payment, receipt).
- `POST /api/day-book-entries` - Create manual entry. Body: `entry_type` (payment, receipt, journal, opening_balance), `entry_date`, `amount`, optional `voucher_number`, `narration`. Voucher number auto-generated if omitted (JV-, OB-, PAY-, RCP- prefix by type).
- `GET /api/day-book-entries/{id}` - Get entry (with user and reference when present).
- `PUT /api/day-book-entries/{id}` - Update manual entry only (not system-generated).
- `DELETE /api/day-book-entries/{id}` - Delete manual entry only.
- `POST /api/day-book-entries/{id}/reconcile` - Toggle reconciled_at (bank reconciliation).

### Chart of accounts (Phase 3)
- `GET /api/accounts` - List accounts (optional: type, is_active, search, per_page)
- `POST /api/accounts` - Create account (code, name, type: asset|liability|equity|income|expense, parent_id, opening_balance)
- `GET /api/accounts/{id}` - Get account
- `PUT /api/accounts/{id}` - Update account
- `DELETE /api/accounts/{id}` - Delete account (only if no journal lines)

### Reports
- `GET /api/reports/day-book` - Day Book report (chronological sales, purchases, returns, expenses). Query params: date_from, date_to, page, per_page
- `GET /api/reports/day-book/export` - Day Book export (CSV or Excel). Query params: format=csv|xlsx, date_from, date_to. Streams in chunks to support large records.
- `GET /api/reports/ledger` - Ledger by account (Phase 3). Query params: account_id (required), date_from, date_to, page, per_page
- `GET /api/reports/trial-balance` - Trial balance (Phase 3). Query params: date_from, date_to
- `GET /api/reports/profit-loss` - Profit & Loss (Phase 3). Query params: date_from, date_to
- `GET /api/reports/balance-sheet` - Balance sheet as on date (Phase 3). Query params: date_to or as_on_date
- `GET /api/reports/gst-outward` - GST outward/sales report (Phase 3). Query params: date_from, date_to
- `GET /api/reports/gst-purchase-register` - GST purchase register for ITC (Phase 3). Query params: date_from, date_to

## Database Structure

### Migration notes
- **Legacy naming:** Migration `2024_01_01_000013_add_role_id_to_users_table.php` adds the **phone** column to `users` only. The filename is legacy; do not rename if already deployed.
- **Duplicate timestamps fixed:** Returns and return_items were previously named `2024_01_01_000011` and `2024_01_01_000012` (duplicates of purchases/purchase_items). They are now `2024_01_01_000014_create_returns_table.php` and `2024_01_01_000015_create_return_items_table.php`. If you had already run the old migrations, ensure your `migrations` table reflects that so these are not run again.

### Main Tables
- `users` - System users
- `roles` - User roles (admin, cashier, etc.)
- `permissions` - System permissions
- `role_user` - User-role relationships
- `permission_role` - Role-permission relationships
- `categories` - Product categories
- `products` - Product inventory
- `customers` - Customer database
- `sales` - Sales transactions
- `sale_items` - Individual sale line items
- `stock_movements` - Inventory movement history
- `returns` - Return transactions
- `return_items` - Return line items
- `expense_categories` - Expense categories (for reporting)
- `expenses` - Expense vouchers (date, amount, voucher_number, status, etc.)
- `day_book_entries` - Day book / journal entries (Phase 2). One row per voucher: system-generated from sales, purchases, returns, expenses (via observers), or manual (journal, opening_balance, payment, receipt). Columns: user_id, entry_date, voucher_number, entry_type, amount, narration, reference_type/reference_id (polymorphic), reconciled_at.
- `accounts` - Chart of accounts (Phase 3). code, name, type (asset|liability|equity|income|expense), parent_id, opening_balance, is_active.
- `journal_entries` - Double-entry journal headers (Phase 3). user_id, entry_date, voucher_number, narration, reference_type/reference_id (polymorphic). Created automatically when sales, purchases, returns, expenses are saved (via observers).
- `journal_entry_lines` - Journal entry lines (Phase 3). journal_entry_id, account_id, debit, credit. Each entry balances (sum debit = sum credit).

## User Roles & Permissions

### Administrator
- Full system access
- Manage products, customers, users
- View all reports
- Process sales

### Cashier
- Process sales
- Manage customers
- View sales history

## Project Structure

```
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── API/
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── CategoryController.php
│   │   │   │   ├── CustomerController.php
│   │   │   │   ├── POSController.php
│   │   │   │   ├── SaleController.php
│   │   │   │   ├── StockController.php
│   │   │   │   └── ReturnController.php
│   │   │   └── AuthController.php
│   │   └── Middleware/
│   │       └── CheckPermission.php
│   └── Models/
│       ├── User.php
│       ├── Role.php
│       ├── Permission.php
│       ├── Product.php
│       ├── Category.php
│       ├── Customer.php
│       ├── Sale.php
│       ├── SaleItem.php
│       ├── StockMovement.php
│       ├── ReturnModel.php
│       └── ReturnItem.php
├── database/
│   ├── migrations/
│   └── seeders/
│       └── DatabaseSeeder.php
├── resources/
│   ├── js/
│   │   ├── components/
│   │   │   ├── App.vue
│   │   │   ├── Login.vue
│   │   │   ├── Register.vue
│   │   │   ├── Dashboard.vue
│   │   │   ├── POS.vue
│   │   │   ├── Products.vue
│   │   │   ├── Customers.vue
│   │   │   ├── Sales.vue
│   │   │   └── Invoice.vue
│   │   ├── stores/
│   │   │   └── auth.js
│   │   ├── app.js
│   │   └── bootstrap.js
│   └── views/
│       └── app.blade.php
└── routes/
    ├── api.php
    └── web.php
```

## Development Notes

- The system uses Laravel Sanctum for API authentication
- Vue.js 3 with Composition API is used for the frontend
- All API endpoints require authentication except login/register
- Stock is automatically updated when sales are processed
- Invoice numbers are auto-generated in format: INV-YYYYMMDD-XXXX

## Future Enhancements

- Barcode scanner integration
- Receipt printer support
- Advanced reporting and analytics
- Multi-warehouse support
- Supplier management
- Purchase orders
- NativePHP desktop app integration

## License

MIT License
