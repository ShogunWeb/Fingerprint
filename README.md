# Basic Fingerprinting

Simple PHP page that shows what a server and a browser can see about a visitor.
It combines HTTP request details from PHP with client-side signals from JavaScript.

## Features
- Server-side snapshot of key HTTP fingerprinting signals with JSON access.
- Client-side summary for languages, GPU, CPU cores, and screen metrics.
- Device class inference based on screen size/DPR presets.
- GeoIP (ipapi.co) on-demand with OpenStreetMap preview.
- Tor exit DNS check + proxy/VPN heuristic from forwarding headers.
- FR/EN UI with automatic detection and user toggle.
- Access logging to daily `.log` files in `src/logs/`.
- Security headers + no-cache via `.htaccess`.

## Usage
1. Deploy the `src/` directory to any PHP-capable web server.
2. Ensure Apache `mod_headers` (and optionally `mod_rewrite`) is enabled if you
   want the `.htaccess` rules to apply.
3. Open the page in a browser. GeoIP runs only after clicking the button.

## Development
- Entry point: `src/index.php`
- Apache config: `src/.htaccess`
- Styles: `src/styles.css`
- JS: `src/app.js` (helpers in `src/app-utils.js`, i18n in `src/i18n.js`)

## License
GNU Affero General Public License v3.0. See `LICENSE`.
