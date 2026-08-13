# Blood Donor Management System

A lightweight web-based Blood Donor Management System built with **PHP and MySQL**, designed to run on standard shared hosting environments.

The system helps blood donation organizations manage donor information, blood donation camps, donor eligibility, and communication through WhatsApp and SMS.

## 🚀 Project Goals

The primary goal is to maintain a centralized donor database and make it easy to notify eligible donors about upcoming blood donation camps.

The system is particularly useful when:

* A blood donation camp changes location
* Donors miss a scheduled donation
* A new blood donation camp is organized
* Specific blood groups are urgently required
* Donors need to be contacted after their eligibility period

---

## 🛠 Technology Stack

### Backend

* PHP 8.2+
* MySQL
* PDO
* PHP Sessions

### Frontend

* HTML5
* CSS3
* Bootstrap 5
* JavaScript
* jQuery
* DataTables
* Font Awesome

### Integrations

* WhatsApp Business / WhatsApp Cloud API
* SMS Gateway
* PhpSpreadsheet

### Hosting

Designed for:

* cPanel
* Shared Hosting
* Apache
* MySQL / MariaDB
* PHP 8.2+

Laravel is intentionally not used so the application can be deployed easily on standard shared hosting.

---

## 📋 Main Features

### 🔐 Authentication

* Admin login
* Secure password hashing
* Session-based authentication
* Logout
* Protected admin pages

### 👥 Donor Management

* Add donors
* Edit donors
* Delete donors
* Search donors
* Filter by blood group
* Activate/deactivate donors
* Track last donation date
* Import donors from Excel
* Export donor data

### 🩸 Blood Group Management

Supported blood groups:

* A+
* A-
* B+
* B-
* AB+
* AB-
* O+
* O-

### 📅 Blood Camp Management

* Create blood camps
* Edit blood camps
* Delete blood camps
* View upcoming camps
* View previous camps
* Store camp date and time
* Store camp location
* Store camp description

### 📱 Messaging

Support for:

* WhatsApp
* SMS

Messages can be sent to:

* All active donors
* Selected donors
* Donors belonging to a specific blood group

### 📊 Reports

* Total donors
* Donors by blood group
* Eligible donors
* Recently contacted donors
* Message history
* Camp history

---

## 🗄 Database

### `admins`

Stores administrator accounts.

### `donors`

Stores donor information including:

* Name
* Mobile
* WhatsApp number
* Email
* Address
* Blood group
* Gender
* Date of birth
* Last donation date
* Status

### `blood_camps`

Stores blood donation camp information.

### `message_logs`

Stores WhatsApp and SMS communication history.

---

## 📁 Project Structure

```text
blood-donor-system/

├── admin/
│   ├── dashboard.php
│   ├── donors.php
│   ├── donor-add.php
│   ├── donor-edit.php
│   ├── camps.php
│   ├── messages.php
│   └── reports.php
│
├── ajax/
│   ├── donor-save.php
│   ├── donor-delete.php
│   ├── send-whatsapp.php
│   ├── send-sms.php
│   └── import-donors.php
│
├── includes/
│   ├── db.php
│   ├── auth.php
│   ├── header.php
│   └── footer.php
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── uploads/
│   └── excel/
│
├── config/
│   └── config.php
│
├── login.php
├── logout.php
├── index.php
│
├── .gitignore
└── README.md
```

---

## 📥 Excel Import

Donors can be imported from Excel using PhpSpreadsheet.

Expected columns:

```text
Name
Mobile
WhatsApp
Email
Address
Blood Group
Gender
Date Of Birth
Last Donation Date
```

The import system should:

* Validate required fields
* Validate blood groups
* Validate mobile numbers
* Detect duplicate mobile numbers
* Skip invalid records
* Display import results
* Record successful and failed imports

---

## ⏱ Donor Eligibility

The initial business rule is that a donor becomes eligible approximately **4 months after their previous donation**.

Example:

```sql
SELECT *
FROM donors
WHERE last_donation_date <= DATE_SUB(CURDATE(), INTERVAL 4 MONTH)
AND status = 'Active';
```

The eligibility period should eventually be configurable from the admin settings rather than hard-coded.

---

## 📢 Blood Camp Notification Workflow

Example scenario:

A regular blood donation camp normally takes place at Location A.

The location changes to Location B.

Some existing donors therefore miss the camp.

The administrator can:

1. Create the new blood camp
2. Select the affected donors
3. Filter donors by blood group if required
4. Preview the notification
5. Send the notification through WhatsApp or SMS
6. Record the communication in `message_logs`

Example message:

```text
Hello {NAME},

Our blood donation camp location has changed.

New Location:
{LOCATION}

Date:
{DATE}

We would be very grateful for your participation.

Thank you for supporting blood donation.
```

---

## 📱 WhatsApp Integration

The preferred implementation is the official WhatsApp Business / Cloud API.

The application should support:

* Configurable API credentials
* WhatsApp Phone Number ID
* Access Token
* Approved message templates
* Variable replacement
* API response handling
* Message logging
* Error handling

Sensitive API credentials must never be committed to GitHub.

Use configuration outside the repository or environment variables where supported.

---

## 📲 SMS Integration

The system should use an abstraction layer for SMS providers so that the provider can be changed without rewriting the messaging system.

Potential providers include:

* Dialog
* SLT-MOBITEL
* Twilio
* Other compatible SMS gateways

---

## 🔒 Security Requirements

Because the system contains personal donor information, security is a major requirement.

The application must:

* Use prepared SQL statements
* Use PDO
* Hash passwords using `password_hash()`
* Verify passwords using `password_verify()`
* Protect admin pages with authentication
* Implement CSRF protection for forms
* Validate and sanitize input
* Escape output using appropriate HTML escaping
* Prevent SQL injection
* Prevent XSS
* Restrict uploaded files
* Validate Excel uploads
* Never expose API credentials
* Never commit passwords or API tokens
* Use HTTPS in production

---

## ⚙️ Installation

### Requirements

```text
PHP 8.2+
MySQL 5.7+ / MariaDB
Apache
PDO MySQL
cURL
PHP Zip extension
PHP XML extension
PHP MBString extension
```

### Installation Steps

1. Create a MySQL database.
2. Import the database schema.
3. Upload the application to the hosting account.
4. Configure database credentials.
5. Configure application settings.
6. Install Composer dependencies if required.
7. Configure HTTPS.
8. Create the first administrator account.
9. Log into the admin dashboard.

---

## 🔑 Configuration

Never commit production credentials.

Example configuration:

```php
DB_HOST
DB_NAME
DB_USER
DB_PASSWORD

WHATSAPP_API_TOKEN
WHATSAPP_PHONE_NUMBER_ID

SMS_API_KEY
SMS_API_SECRET
```

A local configuration file should be excluded using `.gitignore`.

Example:

```text
config/config.php
.env
```

---

## 🧪 Development

Recommended development process:

```text
1. Database
2. Authentication
3. Donor CRUD
4. Dashboard
5. Blood Camps
6. Excel Import
7. Excel Export
8. Messaging
9. Reports
10. Security Review
11. Production Deployment
```

Each major feature should be committed separately to Git.

---


## 🤝 Contribution

Development should follow a feature-based Git workflow.

Recommended branch structure:

```text
main
develop
feature/authentication
feature/donor-management
feature/blood-camps
feature/whatsapp
feature/sms
feature/reports
```

---

## 🔐 Privacy

This application handles personal donor information such as:

* Names
* Telephone numbers
* Addresses
* Blood groups
* Donation history

Production deployments must follow applicable privacy and data-protection requirements.

Only authorized personnel should have access to donor information.

---

## 📄 License

License to be determined by the project owner.

---

## ❤️ Purpose

This project is intended to help blood donation organizations maintain their donor network and communicate with donors efficiently, especially when changes to blood donation camps result in donors missing scheduled donations.

Every notification should be used responsibly and only for legitimate blood donation communication.
