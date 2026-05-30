# University Clinic — Setup Guide (XAMPP)

## Folder Structure
```
htdocs/
└── university-clinic/
    ├── index.html          ← rename university-clinic-upgraded.html → index.html
    ├── database.sql
    └── api/
        ├── config.php
        ├── auth.php
        ├── appointments.php
        └── availability.php
```

---

## Step 1 — Copy Files to XAMPP

1. Open your XAMPP folder: `C:\xampp\htdocs\`
2. Create a new folder: `university-clinic`
3. Copy ALL files into it (maintaining the `api/` subfolder)
4. Rename `university-clinic-upgraded.html` → `index.html`

---

## Step 2 — Import the Database

1. Open XAMPP Control Panel → Start **Apache** and **MySQL**
2. Open your browser → go to `http://localhost/phpmyadmin`
3. Click **Import** tab (top menu)
4. Click **Choose File** → select `database.sql`
5. Click **Go** at the bottom
6. You should see: `university_clinic` database created ✓

---

## Step 3 — Run the App

Open your browser and go to:
```
http://localhost/university-clinic/
```

---

## Default Login Accounts

### Staff / Doctors / Nurses
| Name             | Email                      | Password    |
|------------------|----------------------------|-------------|
| Dr. Maria Santos | maria.santos@clinic.edu    | doctor123   |
| Dr. Jose Reyes   | jose.reyes@clinic.edu      | doctor123   |
| Nurse Ana Lim    | ana.lim@clinic.edu         | nurse123    |
| Nurse Carlo Diaz | carlo.diaz@clinic.edu      | nurse123    |
| Admin Staff      | staff@clinic.edu           | staff123    |

### Patient
| Name           | Email                  | Password    |
|----------------|------------------------|-------------|
| Juan dela Cruz | juan@university.edu    | patient123  |

> ⚠️ Change these passwords after first deployment!

---

## How Scheduling Works

1. **Patient logs in** → Dashboard → Appointments tab
2. Clicks **Schedule Appointment**
3. Selects a **Doctor or Nurse** from dropdown
4. Available **dates** appear as chips (loaded from DB)
5. Select a date → available **time slots** appear
6. Select a slot → enter reason → **Confirm**
7. Slot is locked in DB → no double bookings possible

---

## How Staff Manages Availability

Staff must add availability via the API (phpMyAdmin or a future staff UI):

**Option A — phpMyAdmin**
- Open `university_clinic` → `availability` table
- Insert rows: `provider_id`, `avail_date`, `start_time`, `end_time`

**Option B — API call (Postman or browser)**
```
POST http://localhost/university-clinic/api/availability.php?action=generate
Body (JSON):
{
  "provider_id": 1,
  "from_date": "2026-06-01",
  "to_date": "2026-06-30",
  "weekdays": [1,2,3,4,5],
  "time_slots": [
    { "start_time": "08:00:00", "end_time": "08:30:00" },
    { "start_time": "08:30:00", "end_time": "09:00:00" },
    { "start_time": "09:00:00", "end_time": "09:30:00" },
    { "start_time": "10:00:00", "end_time": "10:30:00" },
    { "start_time": "13:00:00", "end_time": "13:30:00" },
    { "start_time": "14:00:00", "end_time": "14:30:00" }
  ]
}
```
This generates 30 days worth of slots for provider #1, Mon–Fri only.

---

## API Endpoints Reference

| Endpoint | Method | Description |
|---|---|---|
| `api/auth.php?action=login` | POST | Patient/staff login |
| `api/auth.php?action=signup` | POST | Patient registration |
| `api/auth.php?action=logout` | POST | Logout |
| `api/auth.php?action=session` | GET | Check login status |
| `api/appointments.php?action=providers` | GET | List all active providers |
| `api/appointments.php?action=dates&provider_id=1` | GET | Available dates for provider |
| `api/appointments.php?action=slots&provider_id=1&date=2026-06-01` | GET | Time slots for date |
| `api/appointments.php?action=book` | POST | Book appointment |
| `api/appointments.php?action=mine` | GET | Patient's own appointments |
| `api/appointments.php?action=cancel` | POST | Cancel appointment |
| `api/appointments.php?action=all` | GET | All appointments (staff) |
| `api/availability.php?action=generate` | POST | Bulk-generate slots (staff) |
| `api/availability.php?action=add` | POST | Add specific slots (staff) |

---

## Troubleshooting

| Problem | Fix |
|---|---|
| "Database connection failed" | Make sure MySQL is running in XAMPP |
| "Could not load providers" | Check that Apache is running & files are in htdocs |
| Blank page / 404 | Rename the HTML file to `index.html` |
| "No available dates" | Add availability rows in the `availability` table |
