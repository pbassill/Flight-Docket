# Flight-Docket

A flight docket generation system for OTR Aviation that creates comprehensive PDF documents containing all required flight planning materials.

## Features

- Generate professional flight dockets with branded cover pages
- Upload flight planning documents (flight plans, mass & balance, performance data, charts, etc.)
- **NEW**: Automatic weather data fetching via CheckWX API (METAR, TAF, SIGMET)
- **NEW**: Automatic NOTAM fetching via Notamify API
- **NEW**: AIP PDF downloader for airport information by ICAO code
- Merge multiple PDF documents into a single flight docket
- Organized checklist index
- Secure file handling and storage

## API Integration

The application now supports automatic fetching of weather and NOTAM data:

- **CheckWX API**: Fetch METAR, TAF, and SIGMET data for departure, destination, and alternate airfields
- **Notamify API**: Fetch NOTAMs for all specified airfields

See [API_INTEGRATION.md](API_INTEGRATION.md) for detailed setup instructions.

## AIP PDF Downloader

The application includes a script to download Aeronautical Information Publication (AIP) PDFs from the ENAIRE website for any airport by ICAO code.

### Usage

Download AIP PDFs for one or more airports:

```bash
# Single airport
php scripts/download_aip_pdfs.php LEMD

# Multiple airports
php scripts/download_aip_pdfs.php LEMD LEBL LEMG

# Show help
php scripts/download_aip_pdfs.php --help
```

Downloaded PDFs are stored in `storage/aip/ICAO_CODE/` (excluded from git).

Common Spanish airport ICAO codes: LEMD (Madrid), LEBL (Barcelona), LEMG (Málaga), LEVC (Valencia), LEAL (Alicante).

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
