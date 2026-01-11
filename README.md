# Flight-Docket

A flight docket generation system for OTR Aviation that creates comprehensive PDF documents containing all required flight planning materials.

## Features

- Generate professional flight dockets with branded cover pages
- Upload flight planning documents (flight plans, mass & balance, performance data, charts, etc.)
- **NEW**: Automatic weather data fetching via CheckWX API (METAR, TAF, SIGMET)
- **NEW**: Automatic NOTAM fetching via Notamify API
- **NEW**: Automatic aerodrome chart fetching via AIP España (Spanish airports only)
- Merge multiple PDF documents into a single flight docket
- Organized checklist index
- Secure file handling and storage

## API Integration

The application now supports automatic fetching of weather, NOTAM, and chart data:

- **CheckWX API**: Fetch METAR, TAF, and SIGMET data for departure, destination, and alternate airfields
- **Notamify API**: Fetch NOTAMs for all specified airfields
- **AIP España**: Fetch aerodrome charts (VAC/ADC/PDC) for Spanish airports (ICAO codes starting with LE)

See [API_INTEGRATION.md](API_INTEGRATION.md) for detailed setup instructions.

## Quick Start

1. Clone the repository
2. Install dependencies: `composer install`
3. Configure CheckWX API key (optional, for weather data):
   ```bash
   export CHECKWX_API_KEY="your-api-key-here"
   ```
4. Set up web server to serve files from the `public` directory
5. Ensure storage directories are writable

## Requirements

- PHP 8.1 or higher
- Composer
- Web server (Apache, Nginx, etc.)
- cURL extension enabled (for API integrations)

## Documentation

- [API Integration Guide](API_INTEGRATION.md) - Detailed information about weather and NOTAM API integrations
- [Security Guide](SECURITY_GUIDE.md) - Security best practices and guidelines

## License

See [LICENSE](LICENSE) for details.
