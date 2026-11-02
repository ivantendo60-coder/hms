# MediCare HMS — Hospital Management System (PHP + Bootstrap 5 + Arduino RFID)

## Quick Install (XAMPP)
1. Copy the `hms` folder into `C:\xampp\htdocs\` (so the URL becomes `http://localhost/hms`).
2. Start Apache + MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin → import `sql/hms.sql`. This creates the `hms_db` database with sample users and one demo patient (RFID `A1B2C3D4`).
   For an existing installation, import `sql/upgrade.sql` to add per-user themes, login throttling, inpatient/outpatient tracking, service charges, payment receipts, and pharmacy clearance. It requires all existing accounts to change their password at next sign-in.
4. Visit `http://localhost/hms`.
   PHP pages can be opened without the `.php` suffix (for example, `/hms/pages/auth/login`). GET requests using the suffix redirect to the clean URL; POST submissions keep their original URL.

For deployment, configure `HMS_DB_HOST`, `HMS_DB_NAME`, `HMS_DB_USER`, and `HMS_DB_PASS` in the Apache/PHP environment and use a dedicated MySQL account with only the permissions the application needs. The built-in `root`/empty-password defaults are for local XAMPP development only.

## Default Logins
| Role          | Email                  | Temporary password |
|---------------|------------------------|-----------|
| Doctor        | doctor@hms.local       | password |
| Receptionist  | reception@hms.local    | password |
| Lab           | lab@hms.local          | password |
| Pharmacy      | pharmacy@hms.local     | password |
| Admin         | admin@hms.local        | password |

> Seeded accounts must change the shared temporary password on first sign-in before accessing the system. Replace demo accounts and credentials before deployment.

## Role Permissions
- **Receptionist** — register/update patients, set department service prices, and record outpatient payments. Reception can view visit dates, inpatient/outpatient classification, and pharmacy clearance status, but clinical records (diagnoses, lab results, prescriptions) remain restricted to medical staff.
- **Doctor / Lab / Pharmacy** — full access to all patient data; each adds records in their own domain; everyone can read everything and print PDFs.
- **Doctors** — classify each open visit as an outpatient or inpatient. The admin dashboard shows the current counts of patients in each category; closed visits remain classified in visit history.
- **Patient billing** — reception maintains prices by department; doctors, lab staff, and pharmacy add the services they provide to the open visit at the current price. Reception records outpatient payments; pharmacy records inpatient payments and any remaining balance, then gives final clearance for either patient type. Visits can only be closed by a doctor after pharmacy clears the bill.
- **Admin financials** — admins can review all patient payment receipts and outstanding balances, including patient type, amount paid, collection point, receipt reference, and pharmacy clearance.
- The whole system is **doctor/staff-only**. Patients never log in — they just present the RFID card.
- Every staff member can choose and save a color theme from the sidebar. Admins can review patient activity by staff member and department from **Staff Patient Activity**.
- The responsive interface uses a Material-inspired visual system with soft surfaces, accessible controls, compact navigation on mobile, and per-user accent colors.

## Three Patient Pages
Every patient profile has three tabs:
1. **Details** – demographics, contact, etc.
2. **Current Visit** – live data being added today by Doctor/Lab/Pharmacy.
3. **History** – every previous visit, linked by date. Click a date to load that visit's full report. The history page auto-refreshes (older visits move here when the current one is closed).

## Arduino RFID Setup
Folder `arduino/`:
- `rfid_reader.ino` — Arduino UNO + MFRC522 sketch. Prints `UID:xxxx` on Serial.
- `serial_bridge.py` — Python bridge that reads from serial and POSTs to `api/rfid_ingest.php`.
- `rfid_reader.ino` also supports ESP8266/ESP32 direct Wi-Fi POST mode.

### Serial mode (Arduino UNO / PC bridge)
Wiring (UNO ↔ MFRC522): SDA=D10, SCK=D13, MOSI=D11, MISO=D12, RST=D9, 3.3V, GND.

### Wi-Fi mode (ESP32 / ESP8266)
1. Use an ESP32 or ESP8266 board.
2. In `arduino/rfid_reader.ino`, uncomment `#define WIFI_MODE`.
3. Set `ssid`, `password`, and `serverUrl` to your network and PHP host.
4. Flash the sketch to the board.

The ESP board will connect to Wi-Fi and POST directly to:
`http://<host>/hms/api/rfid_ingest.php`
with body `uid=XXXX&station=reception`.

The sketch can also verify connectivity automatically by calling:
`http://<host>/hms/api/rfid_health.php`
before sending scanned UID data.

API endpoint: `POST /hms/api/rfid_ingest.php` with body `uid=XXXX&station=reception`.

The **Scan RFID** page (any role) polls `/hms/api/rfid_last.php` every 1.5 s and auto-opens the patient profile when a card is detected. If the UID is unknown the receptionist gets a one-click "Register this card" button.

## Security (.htaccess)
- `config/` and `includes/` are blocked from direct browser access.
- `.sql`, `.md`, `.env`, `.bak` files are denied.
- Security headers include frame/content-type protections, Referrer-Policy, Permissions-Policy, and HTTPS-only HSTS.
- Session cookies use the app-specific `HMSSESSID` name, are scoped to `/hms/`, and use HttpOnly, SameSite=Lax, and Secure over HTTPS; sessions expire after 30 minutes idle. Each browser profile/device has an independent login, and the session lock is released before page rendering.
- Login throttling allows up to five failed attempts per account/IP pair in 15 minutes.
- Password changes require at least 12 characters with mixed character types; existing accounts are prompted to update their password after the security migration.
- CSRF tokens are checked using constant-time comparison; passwords require at least 12 characters with mixed character types.
- Passwords hashed (PHP `password_hash`).

## Printing PDF Reports
On any patient profile click **Print PDF** → opens a print-ready page → use the browser's "Save as PDF". Works on every modern browser, no external lib required.

## Folder Layout
```
hms/
├── api/                 # RFID ingest + last-scan endpoints
├── arduino/             # .ino sketch + python serial bridge
├── assets/              # css, js, generated images
├── config/db.php        # DB credentials (edit if needed)
├── includes/            # header, sidebar, footer, auth helpers
├── pages/
│   ├── auth/            # login, logout
│   ├── doctor/          # doctor dashboard + today's visits
│   ├── receptionist/    # add/edit/list patients, reception view
│   ├── lab/             # lab dashboard + queue
│   ├── pharmacy/        # pharmacy dashboard + queue
│   ├── patient/         # shared search, full clinical view, visit report, print
│   └── scan.php         # RFID listener
├── sql/hms.sql          # database schema + seed data
├── uploads/             # patient photos (writable)
├── .htaccess            # security & rewrites
└── index.php            # landing page
```

Built with PHP 8, Bootstrap 5.3, Bootstrap Icons, vanilla JS, MySQL.

## New Features
- **Unknown-card confirmation flow** — receptionists land on a review screen (UID + station + reviewer) before registering a card. On confirm, the patient is created and a first visit is auto-opened. Existing installs: import `sql/upgrade.sql`.
- **Fast patient search with department filters** — name, phone, RFID UID, and visit-date range. Receptionists get demographics-only filters; clinical roles also get visit-date filters and a visit count column.
- **Patient audit log** — every create / update / clinical entry / photo upload writes a row (who, role, field, old → new, IP, timestamp). Visible on a new "Audit Log" tab on every patient profile.
- **Patient photos** — uploadable from every dashboard's patient page (JPG/PNG/WebP ≤ 4 MB). Files are stored under `uploads/patients/` which is hard-blocked by an `.htaccess Require all denied`; the only way to view a photo is `pages/patient/photo.php?id=N`, which enforces login + role.
