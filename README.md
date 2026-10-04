# Attendance Management System

## Setup

1. Create/import the database with `database/attendance.sql`.
2. Use `.env.example` as a template and set the four database environment variables in your server or hosting control panel. PHP does not load `.env` files automatically; never commit one.
3. Add a department and a first HOD account. Choose a unique, randomly generated password in a password manager, then generate its hash without putting the password in shell history:

```powershell
php -r "echo password_hash(readline('Initial HOD password: '), PASSWORD_DEFAULT), PHP_EOL;"
```

Keep the password in your password manager and insert the generated hash (not the password):

```sql
INSERT INTO departments (name, code) VALUES ('Information Technology', 'IT');
INSERT INTO users (name, username, password, role, department_id)
VALUES ('HOD', 'hod', 'GENERATED_HASH', 'hod', 1);
```

4. For hosting, upload the application files to the domain's public folder. Import `database/attendance.sql` first, then do not upload the `database` folder or `.env` file to the public folder. Configure the database environment variables in the hosting control panel or PHP/Apache environment.

	The application detects whether it is installed at the domain root or under `/attendance_web`, so local XAMPP and hosted URLs work without changing links.

For the PHP development server:

```powershell
cd C:\xampp\htdocs\attendance_web
php -S localhost:8000
```

Before starting the server, set the variables in PowerShell, for example:

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_NAME = 'attendance_system'
$env:DB_USER = 'your_database_user'
$env:DB_PASS = 'your_database_password'
php -S localhost:8000
```

Open `http://localhost:8000/login.php`. When using this server, change `baseUrl()` in `includes/auth.php` from `/attendance_web` to an empty string.

## GitHub safety

Do not commit `.env`, database exports containing application data, uploaded files, or runtime logs. The included `.gitignore` excludes these files, while `.env.example` documents the required configuration without containing secrets.
