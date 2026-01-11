# API Integration Guide

This document explains how to set up and use the CheckWX and Notamify API integrations for fetching METAR, TAF, SIGMET, and NOTAM data.

## Overview

The Flight-Docket application now supports automatic fetching of weather and NOTAM data through two external APIs:

- **CheckWX API**: Provides METAR, TAF, and SIGMET data
- **Notamify API**: Provides NOTAM data

These APIs eliminate the need to manually upload weather and NOTAM PDFs, streamlining the docket creation process.

## Setup

### 1. CheckWX API Configuration

To use the CheckWX API, you need an API key:

1. Register for a free account at [https://www.checkwxapi.com/](https://www.checkwxapi.com/)
2. Obtain your API key from the dashboard
3. Set the API key as an environment variable:

   ```bash
   export CHECKWX_API_KEY="your-api-key-here"
   ```

   Or add it to your `.env` file or server configuration.

The API configuration is located in `config.php` and will automatically use the environment variable:

```php
'apis' => [
    'checkwx' => [
        'enabled' => true,
        'api_key' => getenv('CHECKWX_API_KEY') ?: '',
        'base_url' => 'https://api.checkwx.com',
    ],
]
```

### 2. Notamify API Configuration

The Notamify API does not require an API key. It is enabled by default:

```php
'notamify' => [
    'enabled' => true,
    'base_url' => 'https://api.notamify.com/v1',
],
```

## Usage

### Creating a Docket with API Data

1. Navigate to the "Create docket" page
2. Fill in the required flight information:
   - Departure ICAO code
   - Destination ICAO code
   - Alternates (optional)
3. Instead of uploading PDF files, click the "Fetch via API" buttons for:
   - **METAR & TAF**: Fetches current weather observations and forecasts
   - **SIGMET**: Fetches significant meteorological information
   - **NOTAMs**: Fetches notices to airmen

The system will:
- Fetch data for all specified airfields (departure, destination, and alternates)
- Generate a properly formatted PDF document
- Include the PDF in the final docket

You can still manually upload PDFs if you prefer, or if the API is unavailable.

## API Details

### CheckWX API

**Endpoints used:**
- `GET /metar/{icao}/decoded` - Fetches METAR data
- `GET /taf/{icao}/decoded` - Fetches TAF data
- `GET /sigmet/{icao}/decoded` - Fetches SIGMET data

**Authentication:**
- API key passed via `X-API-Key` header

**Rate limits:**
- Free tier: 100 requests per hour
- Paid tiers available for higher limits

**Documentation:** [https://www.checkwxapi.com/documentation](https://www.checkwxapi.com/documentation)

### Notamify API

**Endpoints used:**
- `GET /notams/{icao}` - Fetches NOTAM data

**Authentication:**
- No API key required (public API)

**Rate limits:**
- Check Notamify documentation for current limits

**Documentation:** [https://notamify.com/notam-api](https://notamify.com/notam-api)

## Architecture

### API Client Classes

- **`OTR\Api\WeatherApiClient`**: Handles all CheckWX API requests
  - `getMetar(string $icao): ?array`
  - `getTaf(string $icao): ?array`
  - `getSigmet(string $icao): ?array`

- **`OTR\Api\NotamApiClient`**: Handles all Notamify API requests
  - `getNotams(string $icao): ?array`

### PDF Generation

- **`OTR\Api\PdfGenerator`**: Converts API responses to PDF documents
  - `generateMetarTafPdf(array $airfields, string $outputPath): void`
  - `generateSigmetPdf(array $airfields, string $outputPath): void`
  - `generateNotamPdf(array $airfields, string $outputPath): void`

### Data Flow

1. User enters flight information and clicks "Fetch via API"
2. JavaScript sends AJAX request to `fetch_weather.php`
3. Backend fetches data from appropriate API for all airfields
4. Backend generates PDF from API response
5. PDF is stored in session and file key returned to frontend
6. User submits form with file key
7. Backend retrieves PDF from session and includes in docket

## Security Considerations

- API keys are stored as environment variables, not in code
- CSRF tokens protect all API fetch requests
- All ICAO codes are validated before API calls
- API responses are sanitized before PDF generation
- Temporary files are cleaned up after use
- Session-based file storage prevents unauthorized access

## Troubleshooting

### API Key Not Working

- Verify the environment variable is set: `echo $CHECKWX_API_KEY`
- Check if the key is valid in your CheckWX dashboard
- Ensure the key has not expired

### No Data Returned

- Verify the ICAO code is correct
- Some smaller airports may not have TAF or SIGMET data
- Check API rate limits haven't been exceeded

### File Not Found Errors

- Ensure PHP session is configured correctly
- Check that `/tmp` or system temp directory is writable
- Verify session storage is not being cleared prematurely

## Future Enhancements

Potential improvements for future versions:

- Cache API responses to reduce API calls
- Add support for custom date ranges for NOTAMs
- Include more detailed SIGMET information
- Add real-time weather radar images
- Support additional weather APIs as fallbacks
