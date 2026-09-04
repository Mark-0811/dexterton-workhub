# Dexterton WorkHub

Dexterton WorkHub is a Dexterton-only, on-premise Rework-parity replacement built as a Laravel modular monolith with Vue 3, Inertia, TypeScript, Bootstrap 5, PostgreSQL, Redis, MinIO, Meilisearch, and Docker Compose.

The UI uses an original Mazer-inspired operational shell. The implementation must not copy Rework source code, proprietary assets, or visual design; parity means matching observable workflows, permissions, record behavior, and data contracts from authorized read-only inspection.

## Current Slice

- Local authentication with seeded system owner account.
- Organization, offices, users, groups, roles, permissions, audit events, and outbox tables.
- Core primitives for UUID records, soft deletes, comments, attachments, reminders, migration mappings, and versioned workflows.
- Inertia/Vue pages for dashboard, requests/approvals, workflows, projects, todos, and administration.
- Versioned `/api/v1` catalog endpoints.
- Docker services for Nginx, PHP-FPM, queue, scheduler, PostgreSQL, Redis, MinIO, Meilisearch, and Mailpit.

## Local Credentials

- Email: `john.llavanes@dexterton.com`
- Password: `WorkHub2026!`

## Clone + Docker Setup

Fresh machine requirements:

- Git
- Docker Desktop / Docker Compose
- Node.js 20+ and npm for building the Vue/Inertia assets

Clone the private repository:

```bash
git clone https://github.com/Mark-0811/dexterton-workhub.git
cd dexterton-workhub
```

Create the environment file and install dependencies:

```bash
cp .env.example .env
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate:fresh --seed
npm install
npm run build
docker compose up -d
```

Open from the Docker host:

- WorkHub: `http://localhost:8080`
- Mailpit: `http://localhost:8025`
- MinIO: `http://localhost:9001`
- Meilisearch: `http://localhost:7700`

Open from another computer on the same network:

1. Find the Docker host IP address, for example `10.10.111.227`.
2. Set `APP_URL` in `.env` to that address:

```dotenv
APP_URL=http://10.10.111.227:8080
```

3. Restart the containers:

```bash
docker compose down
docker compose up -d
```

4. Open `http://10.10.111.227:8080` from another device on the LAN.

Useful service consoles:

- Mailpit: `http://localhost:8025`
- MinIO: `http://localhost:9001`
- Meilisearch: `http://localhost:7700`

The Nginx service is already published as `0.0.0.0:8080`, so WorkHub can be reached from other LAN devices if the Windows firewall allows inbound traffic on port `8080`.

## Default Accounts

- System owner: `john.llavanes@dexterton.com` / `WorkHub2026!`
- Dexterton administrator: `admin@dexterton.com` / `DextertonAdmin2026!`
- Restricted project user: `normal.project@dexterton.com` / `WorkHub2026!`

## Development Without Full Compose

If PHP is only available through Docker:

```bash
docker run --rm -v "$PWD:/app" -w /app composer:2 composer install
docker run --rm -v "$PWD:/app" -w /app composer:2 php artisan test
npm run build
```

## Acceptance Notes

This is the foundation and workflow-core pilot slice, not the final full Rework replacement. Before production, complete the parity catalog with Dexterton product-owner review, harden 2FA/session/device screens, implement import adapters, add malware scanning hooks, run restore drills, and close all critical parity gaps.
