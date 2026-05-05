# Residents Plugin

A billing and invoicing plugin for Admidio that helps manage resident invoices, payments, recurring charges, and device registrations.

## Features

- **Invoice Management** - Create, edit, preview, and delete invoices for residents
- **SEPA File Generation** - Generate SEPA XML files for direct debit payments
- **Payment Tracking** - Record and manage payments against invoices
- **Recurring Charges** - Define recurring charge templates for automated billing
- **Device Management** - Track and approve resident device registrations
- **Payment Gateway Integration** - CCAvenue and PAYPAL payment gateway support for online payment transactions
- **PDF Generation** - Generate PDF invoices and payment receipts
- **Multi-Database Support** - Works with both MySQL and PostgreSQL databases
- **Role-Based Access Control** - Configurable admin roles for billing management
- **Mobile API** - REST API endpoints for mobile app integration

## Requirements

- Admidio 5.0 or higher

## Installation

1. **Download the Plugin**
   - Download the plugin from [GitHub](https://github.com/adhi-software/residents/archive/refs/tags/v1.0.zip)
   - Unzip the downloaded file into your Admidio plugins folder: `adm_plugins/residents/`

2. **Run Installation**
   - Open your browser and navigate to:
     ```
     https://your-domain/adm_plugins/residents/installation.php
     ```
   - Click the **Install** button to create the required database tables

3. **Configure CCAvenue Payment Gateway**
   - Go to `Preferences` tab
   - Click **Add Payment Gateway** to configure CCAvenue for online payment transactions
   - Enter your CCAvenue merchant credentials (Merchant ID, Access Code, Working Key)

4. **Configure PayPal Payment Gateway**
   - Go to `Preferences` tab
   - Click **Add Payment Gateway** to configure PayPal for online payment transactions
   - Enter your PayPal merchant credentials (Merchant ID, Access Code, Working Key)

## Uninstallation

1. Navigate to `Preferences` tab in the Residents plugin
2. Click the **Uninstall Residents** button at the bottom of the page
3. Confirm the uninstallation to remove all plugin tables and data

## Compatibility Matrix

| **Admidio** | **Residents** |
|-------------|-------------|
| 5.0.x | 1.0, 1.1 |

## Release Notes v1.1
- PAYPAL payment gateway integration
- SEPA xml file generation

## Customization

For any Customization/Support, please contact us, our consulting team will be happy to help you

Adhi Software Pvt Ltd  
12/B-35, 6th Cross Road  
SIPCOT IT Park, Siruseri  
Kancheepuram Dist  
Tamilnadu - 603103  
India

Website: [https://www.adhisoftware.co.in](https://www.adhisoftware.co.in)  
Email: [info@adhisoftware.co.in](mailto:info@adhisoftware.co.in)  
Phone: +91 44 27470401
