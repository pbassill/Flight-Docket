# AIP Charts Storage

This directory stores Aeronautical Information Publication (AIP) charts for airports.

## Directory Structure

Charts should be organized by ICAO airport code:

```
storage/aip/
├── EGMA/
│   ├── EGMA_VAC.pdf
│   ├── EGMA_ADC.pdf
│   └── EGMA_AERODROME_DATA.pdf
├── LEGR/
│   ├── LEGR_VAC_RWY01.pdf
│   ├── LEGR_ADC.pdf
│   └── LEGR_PDC.pdf
└── ... (more airports)
```

## Chart Types

The system automatically includes PDFs containing these identifiers:

- **VAC** - Visual Approach Chart
- **ADC** - Aerodrome Chart
- **PDC** - Parking/Docking Chart
- **AERODROME** or **AD** - Aerodrome data documents

## File Naming

Files should be named with clear identifiers. Examples:
- `EGMA_VAC.pdf`
- `EGMA_ADC.pdf`
- `LEGR_VAC_RWY01.pdf`
- `LEGR_AERODROME_DATA.pdf`

## Usage

When creating a flight docket:
1. Enter the departure and destination ICAO codes
2. Charts from the corresponding directories will be automatically included
3. If alternates are specified, their charts will also be included

## Notes

- All files must be in PDF format
- The system merges multiple charts per airport into a single PDF
- If no charts are found for an airport, that section will be empty in the docket
- Chart files are not uploaded with the repository (see `.gitignore`)
