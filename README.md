# AG Golf League Management
Home built golf league management (repository: ag_golfleaguemanagment)

See [design.md](design.md) for the design.

## Built with
- PHP 8.2 or newer (developed on PHP 8.5)
- CodeIgniter 4.7
- SQLite (the database is the file `writable/golfleague.db`, in WAL mode)
- dompdf (tee time lists as PDF files)

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

## Test on your phone
1. Start the server so other devices can reach it:
   ```bash
   php spark serve --host 0.0.0.0 --port 8080
   ```
2. Find the laptop's IP address on your Wi-Fi:
   ```bash
   ipconfig getifaddr en0
   ```
3. On the phone (same Wi-Fi, not a guest network), open `http://<laptop IP>:8080`.
   If macOS asks whether to allow `php` to accept incoming connections, allow it.

In development mode the site builds its links and redirects from the address
you browsed to, as long as it is a local-network address (see
`app/Config/App.php`), so nothing needs to be configured. Only do this on a
network you trust: development mode shows detailed error pages.

## Install on a Linux server
`install.sh` downloads the software from GitHub and sets everything up on
Debian, Ubuntu, Fedora or RHEL/Rocky/AlmaLinux 9+: PHP 8.2+ and its
extensions, Composer, a web server (it uses nginx or Apache if one is already
installed, otherwise installs nginx), the database, and the first admin
account (it asks for the password). Copy the script to the server and run:
```bash
sudo bash install.sh
```
With a domain name pointing at the server, add free HTTPS:
```bash
sudo bash install.sh --domain golf.example.com --https you@example.com
```
Run it again to update to the newest version; the settings (`.env`) and the
database are kept, and the database is backed up first. See
`bash install.sh --help` for all options.

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
