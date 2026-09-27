# Barangay SAGIP

**Barangay SAGIP: A Tokenization-based Emergency Assistance Classification and Response Coordination Platform for Smart Barangay Services.**

Barangay SAGIP is a Laravel web application for resident assistance requests, emergency response coordination, personnel management, maps, notifications, dashboards, and reports. It uses a private Python FastAPI service for tokenization-based request classification, urgency classification, and response-assignment scoring.

## Classification approach

Barangay SAGIP's final classification architecture is **tokenization-based and rule-based**. It is an intentional deterministic design: the FastAPI service normalizes resident request text, matches hand-curated keywords and phrases, converts the matches into transparent scores, and escalates ambiguous or low-confidence cases for human review.

Request-type and urgency confidence values are **heuristic match-share scores**, not statistical probabilities. Response assignment uses a separate weighted suitability score based on responder proximity, specialization match, workload, and request urgency. A recommendation is withheld when the configured assignment score or ranking margin is not sufficient.

The complete ruleset, scoring formulas, thresholds, tie handling, and review conditions are versioned in [`docs/tokenization-classification-ruleset.md`](docs/tokenization-classification-ruleset.md). This documentation is the authoritative technical specification for the classifier.

## Architecture

```text
Browser
   |
   v
Herd / Nginx --> Laravel PHP --> MySQL (local) / PostgreSQL (production)
                    |
                    +----------> FastAPI tokenization/classification service
                    |
                    +----------> Database-backed queue --> Queue worker
```

Production uses Docker Compose, PHP-FPM 8.4, Nginx, Python 3.12, and PostgreSQL 17. Normal Windows development uses Laravel Herd, MySQL 8.4, Python 3.12, Vite, and the local FastAPI service.

## Feature map

| # | Feature | Main implementation |
|---|---|---|
| 1 | Resident Registration and Profiling | Authentication, `ResidentProfileController`, `resident_profiles` |
| 2 | Emergency/Assistance Request Submission | `EmergencyRequestController`, request views |
| 3 | Tokenization-Based Request Classification | `TokenizationClassificationService`, FastAPI `/classify/request-type` |
| 4 | Urgency/Priority Classification | FastAPI `/classify/urgency` |
| 5 | Request Validation | Confidence threshold and `needs_review` handling |
| 6 | Response Assignment Rule Scoring | `ResponseAssignmentService`, FastAPI `/assign/response` |
| 7 | Urgent Status Tracking | Status transitions and request status logs |
| 8 | Location Map Generator | `MapController`, Leaflet map |
| 9 | Response Personnel Management | `ResponsePersonnelController` and personnel views |
| 10 | Alerts and Notifications | Laravel notifications and notification controller |
| 11 | Dashboards | Role-specific dashboard views |
| 12 | Report Generator | `ReportController` and CSV reporting |

## Local development on Windows

Laravel Herd is the recommended local Laravel environment. Docker is reserved for production-style builds and deployment validation.

### Prerequisites

Install:

- Laravel Herd with PHP 8.4
- Composer
- Node.js and npm
- MySQL Server 8.4
- Python 3.12
- Git

### 1. Clone the repository

```powershell
git clone https://github.com/solsonaM/Barangay-SAGIP.git
cd Barangay-SAGIP
```

### 2. Configure MySQL

Create the local database and application user. Example:

```sql
CREATE DATABASE barangay_sagip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'barangay_sagip'@'localhost' IDENTIFIED BY 'sagip-local-password';
CREATE USER 'barangay_sagip'@'127.0.0.1' IDENTIFIED BY 'sagip-local-password';
GRANT ALL PRIVILEGES ON barangay_sagip.* TO 'barangay_sagip'@'localhost';
GRANT ALL PRIVILEGES ON barangay_sagip.* TO 'barangay_sagip'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Use your own local password if preferred and put the same value in `.env`. Never commit local secrets.

### 3. Configure Laravel

```powershell
cd barangay-sagip-web
composer install
copy .env.example .env
php artisan key:generate
```

Set the relevant `.env` values:

```env
APP_NAME="Barangay SAGIP"
APP_ENV=local
APP_DEBUG=true
APP_URL=https://barangay-sagip.test
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=barangay_sagip
DB_USERNAME=barangay_sagip
DB_PASSWORD=sagip-local-password

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=log
MAIL_MAILER=log

TOKENIZATION_SERVICE_URL=http://127.0.0.1:8001
TOKENIZATION_SERVICE_TIMEOUT=5
TOKENIZATION_SERVICE_KEY=sagip-local-service-key
```

Run:

```powershell
php artisan migrate --seed
```

The seeder provides these local-only demo accounts:

| Role | Email | Password |
|---|---|---|
| Official | `official@sagip.test` | `password` |
| Personnel | `personnel@sagip.test` | `password` |
| Resident | `resident@sagip.test` | `password` |

### 4. Configure Herd

From `barangay-sagip-web`:

```powershell
herd link barangay-sagip
```

Open:

```text
https://barangay-sagip.test
```

The resident login is `/login`. Barangay staff use `/admin/login`.

**Use the HTTPS Herd URL.** Browser geolocation requires a secure context, so opening the application over plain HTTP will prevent resident GPS detection.

Do not run `php artisan serve` when using Herd.

### 5. Install and run Vite

```powershell
npm install
npm run dev
```

Keep Vite running during development. Use `npm run build` for a production-like frontend build.

### 6. Set up the FastAPI tokenization/classification service

Open another PowerShell terminal:

```powershell
cd tokenization-service
py -3.12 -m venv .venv
.\.venv\Scripts\Activate.ps1
python --version
python -m pip install --upgrade pip
pip install -r requirements.txt
$env:TOKENIZATION_SERVICE_KEY="sagip-local-service-key"
uvicorn main:app --host 127.0.0.1 --port 8001
```

Verify the service at `http://127.0.0.1:8001/health`. API documentation is at `http://127.0.0.1:8001/docs`.

### 7. Start the Laravel queue worker

Open another PowerShell terminal:

```powershell
cd barangay-sagip-web
php artisan queue:work database --sleep=3 --tries=3 --timeout=90
```

Keep the worker running. It processes database-backed Laravel jobs, including queued in-app notifications.

### 8. One-command local startup

The repository includes:

- `Start-SAGIP.ps1` to start the local development environment.
- `Stop-SAGIP.ps1` to stop the local development services.

The startup script expects Herd to be running, MySQL 8.4 to use the Windows service name `MySQL84`, and `tokenization-service\.venv` to exist with Python 3.12.

If PowerShell blocks the scripts on first use:

```powershell
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
Unblock-File .\Start-SAGIP.ps1
Unblock-File .\Stop-SAGIP.ps1
```

From the repository root:

```powershell
.\Start-SAGIP.ps1
```

This checks/starts MySQL, starts Vite, starts the FastAPI service with the local service key, starts the Laravel queue worker, and opens the Laravel application over HTTPS. The three service terminals must remain open while developing.

To stop the development services:

```powershell
.\Stop-SAGIP.ps1
```

The shutdown script stops Vite, FastAPI, and the Laravel queue worker. It intentionally leaves MySQL and Herd running.

### Local stack summary

```text
Herd                    -> Laravel web application
MySQL 8.4               -> Local database
Vite                    -> Frontend development server
FastAPI :8001           -> Tokenization/classification service
Laravel queue worker    -> Queued jobs and notifications

Application             -> https://barangay-sagip.test
Staff login             -> https://barangay-sagip.test/admin/login
FastAPI health          -> http://127.0.0.1:8001/health
```

Redis, WebSockets/Reverb/Pusher, SMTP, and the production Docker stack are not required for this normal local setup. The local configuration uses database-backed sessions/cache/queue, log broadcasting, and the log mail driver.
