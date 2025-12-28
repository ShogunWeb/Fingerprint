# Fingerprint

Simple PHP page that shows what a server and a browser can see about a visitor.
The page displays HTTP request details from PHP and client-side signals from
JavaScript (WebGL, storage availability, media devices counts, etc.).

## Features
- Server-side snapshot of HTTP headers and connection metadata.
- Client-side collection on demand (no automatic collection).
- FR/EN UI with automatic detection and user toggle.
- Basic security + no-cache headers via `.htaccess` (Apache).

## Usage
1. Deploy the `src/` directory to any PHP-capable web server.
2. Ensure Apache `mod_headers` (and optionally `mod_rewrite`) is enabled if you
   want the `.htaccess` rules to apply.
3. Open the page in a browser and click "Collect browser details".

## Development
- Entry point: `src/index.php`
- Apache config: `src/.htaccess`

## License
GNU Affero General Public License v3.0. See `LICENSE`.
