# essense-game-night

A full-stack game voting app built for the Essense of Australia code challenge. Employees can suggest games for the office library and vote on the ones they want most.

## Architecture

| Layer | Stack | Directory |
|-------|-------|-----------|
| **Frontend** | React 19, TypeScript, Vite | `ui/` |
| **Backend** | Laravel 12 (PHP 8.4) | `api/` |
| **Persistence** | Essense Code Challenge REST API | external |

The Laravel backend acts as a proxy to the external Essense API and enforces business rules (duplicate detection, daily action limit). The React frontend talks only to our backend.

## Key decisions

- **User identification**: cookie-based UUID (`voter_id`). No auth flow required — a persistent cookie is set on first visit.
- **Daily action rule**: one action (vote OR add) per calendar day per user, enforced server-side with Laravel's cache. If a user undoes their action (unvotes or removes a game they added today), their daily action is refunded.
- **Duplicate detection**: case-insensitive title comparison happens on the backend before calling the external API.
- **Error handling**: every API error surfaces as a user-friendly toast or inline message — no silent failures.

## Running locally with Docker

### 1. Set up environment

```bash
cp api/.env.example api/.env
```

Open `api/.env` and fill in the two required values:

```
ESSENSE_API_KEY=   # your key from https://codechallenge.essensedesigns.info/docs
APP_KEY=           # leave blank — auto-generated on first start
```

### 2 (Optional). Install frontend dependencies locally (for IDE type-checking)

The Docker setup stores `node_modules` inside a named volume, so the IDE TypeScript server won't find types unless you also install them on the host:

```bash
cd ui && npm install && cd ..
```

This is a one-time step — you don't need to re-run it unless `package.json` changes and it is not needed to start the project.

### 3. Start everything

```bash
docker-compose up --build
```

- **Frontend**: http://localhost:3000
- **API**: http://localhost:8000

> On first startup the API container installs PHP dependencies (including dev tools), which takes a moment.

### Running tests

With the containers running:

```bash
docker-compose exec api php artisan test
```

To run a single test class:

```bash
docker-compose exec api php artisan test --filter GameControllerTest
```

## Running without Docker

Requires PHP 8.4+ and Composer for the backend, and Node 22+ for the frontend.

### Backend

```bash
cd api
composer install
cp .env.example .env
# Set ESSENSE_API_KEY in .env (APP_KEY is auto-generated on first artisan command)
php artisan serve
```

Run tests:

```bash
cd api
php artisan test
```

### Frontend

```bash
cd ui
npm install
cp .env.example .env   # optional — defaults point to localhost:8000
npm run dev
```

Frontend dev server: http://localhost:5173

## Deploying to production

Use the production Compose file (nginx serves the compiled frontend; the API is not exposed to the host):

```bash
docker-compose -f docker-compose.prod.yml up --build
```

Required environment variables in `api/.env.production`:

| Variable | Description |
|----------|-------------|
| `APP_KEY` | Generate with `php artisan key:generate --show` |
| `ESSENSE_API_KEY` | Your challenge API key |
| `FRONTEND_URL` | Your deployed frontend URL (required for CORS) |
| `APP_DEBUG` | Must be `false` in production |

### Deploying the frontend to Vercel

1. Import the `ui/` directory as a new Vercel project.
2. Set `VITE_API_URL` to your deployed API URL.
3. Vercel auto-detects Vite and builds correctly.

## API endpoints

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/games` | List all games (sorted by votes desc) |
| `POST` | `/api/games` | Add a new game |
| `POST` | `/api/games/{id}/vote` | Vote for a game |
| `DELETE` | `/api/games/{id}/vote` | Remove your vote |
| `DELETE` | `/api/games/{id}` | Remove a game |
| `GET` | `/api/me` | Current user status + daily action |
