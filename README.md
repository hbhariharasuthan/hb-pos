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

## Database Structure

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
