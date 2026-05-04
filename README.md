# 🦆 DuckEggAllocationSystem-BalutOrChick

## 📌 Description

DuckEggAllocationSystem-BalutOrChick is a web-based management system built using PHP and JavaScript that manages duck egg allocation into two production categories:

* 🥚 Balut Production
* 🐣 Chick Hatching

This system helps monitor egg distribution, user roles, and overall production management in a structured and organized way.

---

## 🎯 Project Purpose

This system is designed to:

* Track duck egg allocation and distribution
* Categorize eggs into balut or chick production
* Manage users with role-based access (Admin, Manager, User)
* Handle user records with CRUD operations
* Organize system structure using a controller-based architecture
* Provide a clean and role-specific dashboard interface

---

## 📂 Project Structure

```bash
DuckEggAllocationSystem-BalutOrChick
├── assets
│   ├── admin
│   │   ├── css
│   │   │   └── admin_style.css
│   │   └── js
│   │       └── admin_function.js
│   ├── manager
│   │   ├── css
│   │   │   └── manager_style.css
│   │   └── js
│   │       └── manager_function.js
│   ├── user
│   │   ├── css
│   │   │   └── user_style.css
│   │   └── js
│   │       └── user_function.js
│   └── users
│       ├── css
│       │   ├── index.css
│       │   └── user-management_style.css
│       └── js
│           └── user-management_function.js
├── branch_guide.txt
├── collaborators.txt
├── controller
│   ├── auth
│   │   ├── resend-verification.php
│   │   ├── signout.php
│   │   └── verify-email.php
│   ├── script.js
│   ├── user-create.php
│   ├── user-delete.php
│   ├── user-export.php
│   ├── user-update.php
│   └── user-view.php
├── db
│   ├── db_delete.sql
│   ├── email_verify.sql
│   ├── insert.sql
│   └── schema.sql
├── debug_check.php
├── index.js
├── index.php
├── model
│   ├── config.php
│   ├── email_helper.php
│   └── temp.file
├── package-lock.json
├── package.json
├── README.md
└── view
    ├── admin
    │   └── dashboard.php
    ├── manager
    │   └── dashboard.php
    ├── user
    │   └── dashboard.php
    └── users
        └── user-management.php
```

## 🏗 System Architecture

The project follows the *MVC (Model-View-Controller)* architecture:

- *Model* – Handles data and database logic  
- *View* – Handles user interface and display  
- *Controller* – Handles system logic and connects Model & View  
