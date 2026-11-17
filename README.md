# ChandraKala Jewellers (CJ)

Family jewellery store site for **ChandraKala Jewellers**, Civil Road, Khedbrahma.

This repository contains:

1. **The live PHP application** at the repo root (source of truth for UI and business rules).
2. **A migration in progress** to Flutter Web + Node.js + PostgreSQL.

PHP files are **not** moved into `legacy-php/` because that would break XAMPP (`C:\xampp\htdocs\CJ`) and the InfinityFree deploy layout.

## Documentation

- [MIGRATION_ANALYSIS.md](./MIGRATION_ANALYSIS.md) — architecture, pages, schema, risks
- [MIGRATION_CHECKLIST.md](./MIGRATION_CHECKLIST.md) — page / feature / API status
- [docs/DATABASE_MIGRATION.md](./docs/DATABASE_MIGRATION.md) — MySQL → Postgres

## Layout

| Path | Role |
|------|------|
| `*.php`, `admin/`, `includes/`, `css/`, `js/` | Production PHP site |
| `frontend/` | Flutter Web customer app |
| `admin_app/` | Flutter admin (separate until `/admin` is merged) |
| `backend/` | Node APIs (Vercel serverless TypeScript today) |
| `database/` | PostgreSQL schema, seeds, migrations |

## Local PHP (current production stack)

1. Start XAMPP Apache + MySQL.
2. Import `if0_38635607_cjdb.sql` into database `if0_38635607_cjdb` if needed.
3. Copy `config/.env.example` → `config/.env`.
4. Open `http://localhost/CJ/`.

## Migration apps (preview only — PHP remains live)

Do **not** deploy the repository root. Use three Vercel projects:

| Vercel project | Folder | Purpose |
|----------------|--------|---------|
| Customer | `frontend/` | Flutter Web shop (preview) |
| API | `backend/` | Node serverless + Postgres |
| Admin | `admin_app/` | Flutter Web admin (second project) |

Until cutover, the public shop is still `https://chandrakalajewellers.in`. Details: [docs/CUTOVER.md](docs/CUTOVER.md).

```bash
# API
cd backend
cp .env.example .env   # set POSTGRES_URL and JWT_SECRET
npm install
npm run dev            # http://localhost:3000

# Customer Flutter Web
cd frontend
flutter pub get
flutter run -d chrome --dart-define=API_URL=http://localhost:3000/api

# Admin Flutter Web (separate app)
cd admin_app
flutter pub get
flutter run -d chrome --dart-define=API_URL=http://localhost:3000/api
```

Do not point production Flutter at localhost. Product images currently load from `https://chandrakalajewellers.in/uploads/`.

## Rules

- Do not delete or overwrite the PHP site during migration.
- Do not invent UI or dummy jewellery copy.
- Do not commit `.env` files or SQL password dumps to a public remote.
