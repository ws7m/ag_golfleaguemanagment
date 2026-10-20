# AG Golf League Management
Home built golf league management (repository: ag_golfleaguemanagment)

See [design.md](design.md) for the design.

## Built with
- PHP 8.2 or newer (developed on PHP 8.5)
- CodeIgniter 4.7
- SQLite (the database is the file `writable/golfleague.db`, in WAL mode)

## First-time setup on a Mac
1. Install PHP and Composer:
   ```bash
   brew install php composer
   ```
2. Install the project's PHP packages (creates the `vendor/` folder):
   ```bash
   composer install
   ```
3. Create your local settings file and an encryption key:
   ```bash
   cp env .env
   php spark key:generate
   ```
   Then edit `.env` and set:
   ```
   CI_ENVIRONMENT = development
   app.baseURL = 'http://localhost:8080/'
   app.appTimezone = 'America/Denver'
   ```
4. Create the database (runs all migrations):
   ```bash
   php spark migrate
   ```
5. Create the initial admin account (login `admin1`, admin-only, not a league
   player). The password is given only on the command line, so it is never
   saved in the project. Running it again resets the password.
   ```bash
   ADMIN_LOGIN='admin1' ADMIN_PASSWORD='your-password' php spark db:seed AdminSeeder
   ```

## Run it
```bash
php spark serve
```
Then open http://localhost:8080/ in a browser. Press Ctrl+C to stop the server.

## Run the tests
```bash
vendor/bin/phpunit
```

## Useful commands
| Command | What it does |
|---|---|
| `php spark migrate` | Apply new database changes |
| `php spark migrate:status` | List which migrations have run |
| `php spark db:table --show` | List the database tables |
| `php spark routes` | List the site's URLs |

## Not in git
- `.env` — local settings and the encryption key
- `vendor/` — recreated by `composer install`
- `writable/golfleague.db` — the database; back it up separately
