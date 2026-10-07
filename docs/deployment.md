# Free deployment: Render + Neon

The application is configured to run Laravel 13 / PHP 8.4, Vue, and the API at one HTTPS origin. The Docker image builds the frontend and contains no local environment files or database records. Render handles HTTPS and deploys changes pushed to `main`. Neon persists data outside Render's ephemeral filesystem.

## Deploy

1. In [Neon](https://console.neon.tech), create a **Free** project named `delivery`, preferably in Frankfurt. Open **Connect** and copy the PostgreSQL connection URL, including `sslmode=require`. Use it only in Render's secret environment settings, not Git or this chat.
2. Open [Deploy to Render](https://render.com/deploy?repo=https://github.com/blackfrankwhite/delivery), authorize access to this GitHub repository if prompted, and create the Blueprint.
3. Render prompts for these settings:

   | Setting | Value |
   | --- | --- |
   | `DB_URL` | Neon's PostgreSQL connection URL |
   | `ADMIN_EMAIL` | Your administrator login email |
   | `ADMIN_PASSWORD` | A unique password of at least 12 characters |

   Leave the service on the **Free** plan. `APP_KEY` is generated automatically and adapted to Laravel's key format by the startup script. Keep the same key across redeployments.
4. Deploy and wait until Render reports **Live**. Open the service URL and sign in with `ADMIN_EMAIL` / `ADMIN_PASSWORD`.

Startup runs migrations and creates the administrator only if it does not exist. Subsequent deployments do not reset its password or remove records. The frontend uses the same origin as the API, with secure session cookies. `/up` is the health-check endpoint. SPA paths such as `/orders` and `/rides/1` work on direct page refresh.

## Data and sample records

The deployed database starts fresh; your local MySQL data is not uploaded. The administrator creates clients, drivers, storage locations, and orders through the app. Demo seeders are intentionally not run on a public deployment because their passwords are published in the repository. Existing local records are preserved.

## Free hosting limits

Render Free web services sleep after 15 idle minutes; the next request can take roughly a minute to wake the service. The filesystem is ephemeral, so the deployment uses external PostgreSQL and database-backed sessions/cache. Render's own free PostgreSQL expires after 30 days, which is why this setup uses Neon. Both providers apply free-plan usage limits; select free plans and keep paid upgrades disabled unless desired. See [Render's limits](https://render.com/docs/free) and [Neon's pricing](https://neon.com/pricing).

## Local deployment build

```sh
docker build -t delivery-dispatch:deploy .
```

Production configuration is passed at runtime, not baked into the image. Local development still uses `.env`, MySQL, Laravel on port 8000, and Vite on port 5173.
