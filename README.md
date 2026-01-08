# Residents Plugin for Admidio

A billing and invoicing plugin for Admidio that helps manage resident invoices, payments, recurring charges, and device registrations.

## Features

- **Invoice Management** - Create, edit, preview, and delete invoices for residents
- **Payment Tracking** - Record and manage payments against invoices
- **Recurring Charges** - Define recurring charge templates for automated billing
- **Device Management** - Track and approve resident device registrations
- **Payment Gateway Integration** - CCAvenue payment gateway support for online payment transactions
- **PDF Generation** - Generate PDF invoices and payment receipts
- **Multi-Database Support** - Works with both MySQL and PostgreSQL databases
- **Role-Based Access Control** - Configurable admin roles for billing management
- **Mobile API** - REST API endpoints for mobile app integration

## Requirements

- Admidio 4.3 or higher
- PHP 7.2 or higher
- MySQL 5.7+ or PostgreSQL 9.5+

## Installation

1. **Download the Plugin**
   - Download the plugin from [GitHub](https://github.com/adhi-software/residents)
   - Unzip the downloaded file into your Admidio plugins folder: `adm_plugins/residents/`

2. **Run Installation**
   - Open your browser and navigate to:
     ```
     http://your-domain/adm_plugins/residents/installation.php
     ```
   - Click the **Install** button to create the required database tables

3. **Access the Plugin**
   - Log in to your Admidio installation as an administrator
   - Navigate to `Plugins` → `Residents` in the menu
   - Configure billing admin roles in the `Preferences` tab

4. **Configure Payment Gateway (Optional)**
   - Go to `Preferences` tab
   - Click **Add Payment Gateway** to configure CCAvenue for online payment transactions
   - Enter your CCAvenue merchant credentials (Merchant ID, Access Code, Working Key)

## Uninstallation

1. Navigate to `Preferences` tab in the Residents plugin
2. Click the **Uninstall Residents** button at the bottom of the page
3. Confirm the uninstallation to remove all plugin tables and data

## Configuration

### Admin Roles
Configure which Admidio roles have billing admin access in the Preferences tab:
- **Billing Admin** - Can manage invoices, payments, and charges
- **Payment Admin** - Can manage payment recordings

### Payment Gateway
For CCAvenue online payment integration, you need:
- Merchant ID
- Access Code
- Working Key (Encryption Key)

### v1.0.0
- Invoice creation and management with line items
- Payment recording with invoice allocation
- Recurring charge definitions
- Device registration and approval workflow
- CCAvenue payment gateway integration
- PDF generation for invoices and receipts
- Role-based access control for billing admins
- Support for MySQL and PostgreSQL databases
- Mobile API endpoints for resident app
- Multi-language support via Admidio localization

## Directory Structure

```
residents/
├── api/                    # REST API endpoints
│   ├── auth/              # Authentication APIs
│   ├── invoice/           # Invoice APIs
│   ├── message/           # Message APIs
│   ├── payment/           # Payment gateway callbacks
│   └── photos/            # Photo album APIs
├── charges/               # Recurring charges management
├── classes/               # PHP classes
├── devices/               # Device management
├── invoices/              # Invoice management
├── languages/             # Localization files
├── payment_gateway/       # Payment gateway integration
├── payments/              # Payment management
├── preferences/           # Plugin settings
├── common_function.php    # Shared utility functions
├── installation.php       # Database installation script
├── residents.php          # Main plugin entry point
└── version.php            # Plugin version
```