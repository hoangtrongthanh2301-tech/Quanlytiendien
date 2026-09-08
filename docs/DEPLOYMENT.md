# Cloud deployment

The application is packaged as a PHP/Apache Docker image. This keeps local XAMPP development compatible while allowing deployment to a container platform such as Railway or Render.

## Run locally with Docker

1. Copy `.env.example` to `.env`.
2. Replace the demo passwords in `.env`.
3. Start the application:

```bash
docker compose up -d --build
```

4. Open `http://localhost:8081`.
5. Stop the services with:

```bash
docker compose down
```

The application container connects to the MySQL/XAMPP instance on the host; Compose does not import SQL automatically. Import `sql/schema.sql` once, then import `sql/normalize-demo-data.sql` to normalize old roles and create the demo accounts. The database remains persistent; use `docker compose down -v` only when intentionally resetting container storage.

Demo accounts after importing the normalization script:

- Admin: `ADMIN001` / `Admin@123`
- Customer: `KHDEMO01` / `Pass@123`

Generate blockchain keys after creating the `storage/keys` directory:

```bash
php scripts/generate_blockchain_keys.php
```

## Deploy to Railway

1. Create a new Railway project from this GitHub repository.
2. Add a MySQL service.
3. Add a service for this repository. Railway detects the `Dockerfile` automatically.
4. Set the application variables in the service secret store:
   - `DB_HOST`: MySQL private host
   - `DB_NAME`: `qltiendien`
   - `DB_USER`: MySQL user
   - `DB_PASSWORD`: MySQL password
   - `DB_CONNECT_TIMEOUT`: `5`
   - `DB_READ_TIMEOUT`: `10`
5. Import `sql/schema.sql`, then `sql/normalize-demo-data.sql`, into the managed MySQL service.
6. Generate a public domain for the application service.
7. Enable automatic deploys from the `main` branch.

Do not commit `.env`, private blockchain keys, production database exports, or real customer data.

## Deploy to Render

Render can build this repository from the `Dockerfile`. Use an external managed MySQL provider because Render does not provide a native MySQL service. Add the same `DB_*` variables in the Render environment settings, import `sql/schema.sql`, and configure the service health check to use `/health.php`.

## CI/CD

`.github/workflows/ci.yml` builds the Docker image and checks PHP syntax for every pull request and push to `main` or `develop`. The cloud provider then deploys the image from the connected repository after a successful build.
