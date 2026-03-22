# Memento Vitae Docker Guide

This guide explains how to run the project locally using Docker.

## Requirements

- Docker Desktop installed
- Docker Desktop running
- On Windows: WSL must be installed and working

## Project URL

When the containers are running, open:

```text
http://localhost:8080
```

## Setup

Open a terminal inside the project folder:

```powershell
cd C:\xampp\htdocs\DWEB\Memento_Vitae
```

Create the environment file:

```powershell
Copy-Item .env.example .env
```

Open `.env` and set the mail values if you want real email sending:

```env
MAIL_USERNAME=your_gmail@gmail.com
MAIL_PASSWORD=your_google_app_password
MAIL_FROM_EMAIL=your_gmail@gmail.com
```

If real email is not needed during testing, the app can still run, but email-related actions may fail.

## Start The Project

Run:

```powershell
docker compose up --build
```

Then open:

```text
http://localhost:8080
```

## Default Admin Login

- Email: `adminmemento@gmail.com`
- Password: `deathnotes1`

## Stop The Project

In the same terminal, press:

```text
Ctrl + C
```

Or run:

```powershell
docker compose down
```

## Run In Background

To run the project without keeping the terminal open:

```powershell
docker compose up -d --build
```

To stop it later:

```powershell
docker compose down
```

## Reset The Docker Database

If you want Docker to rebuild the database from `mementovitae.sql`:

```powershell
docker compose down -v
docker compose up --build
```

## Notes

- The app container serves PHP/Apache on port `8080`
- The MariaDB container uses the `mementovitae` database
- Database seed/import comes from `mementovitae.sql`
- Google login may require adding `http://localhost:8080` to the authorized JavaScript origins in Google Cloud Console

## Troubleshooting

If Docker shows errors like:

```text
Docker Desktop is unable to start
```

or

```text
dockerDesktopLinuxEngine
```

make sure:

- Docker Desktop is running
- WSL is installed
- virtualization is enabled

You can test Docker with:

```powershell
docker version
docker compose ps
```
