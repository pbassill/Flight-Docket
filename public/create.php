<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

\OTR\Security::startSecureSession();
\OTR\Security::setSecurityHeaders();
$csrfToken = \OTR\Security::generateCsrfToken();

$aircraft = [
    'C140'  => 'Cessna 140',
    'C150L' => 'Cessna FRA150L',
    'C152'  => 'Cessna 152',
];

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($config['app_name']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <span class="navbar-brand"><i class="fa-solid fa-plane-departure me-2"></i>OTR Aviation Flight Docket</span>
    <a class="btn btn-outline-light btn-sm" href="index.php"><i class="fa-solid fa-clock-rotate-left me-1"></i>Recent</a>
  </div>
</nav>

<main class="container py-4">
  <div class="row">
    <div class="col-lg-9">
      <div class="card shadow-sm">
        <div class="card-body">
          <h1 class="h4 mb-3">Create docket</h1>

          <form action="submit.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Aircraft</label>
                <select class="form-select" name="aircraft_type" required>
                  <?php foreach ($aircraft as $k => $v): ?>
                    <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Aircraft selection is required.</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Registration</label>
                <input class="form-control" name="registration" placeholder="G-XXXX" required>
                <div class="invalid-feedback">Registration is required.</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Callsign (optional)</label>
                <input class="form-control" name="callsign" placeholder="OTR123">
              </div>

              <div class="col-md-4">
                <label class="form-label">Departure ICAO</label>
                <input class="form-control text-uppercase" name="departure" placeholder="EGMA" required maxlength="4">
                <div class="invalid-feedback">Departure is required.</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Destination ICAO</label>
                <input class="form-control text-uppercase" name="destination" placeholder="LEGR" required maxlength="4">
                <div class="invalid-feedback">Destination is required.</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">ETD (local, optional)</label>
                <input type="datetime-local" class="form-control" name="etd_local">
              </div>

              <div class="col-12">
                <label class="form-label">Alternates (comma-separated ICAO, optional)</label>
                <div class="input-group">
                  <input class="form-control text-uppercase" id="alternates" name="alternates" placeholder="LEMG, EGKA">
                  <button class="btn btn-outline-secondary" type="button" id="findAlternatesBtn" title="Auto-fill viable alternates within 10-25 miles">
                    <i class="fa-solid fa-location-crosshairs me-1"></i>Find Alternates
                  </button>
                </div>
                <div class="form-text" id="alternatesHelp"></div>
              </div>
            </div>

            <hr class="my-4">

            <div class="mb-3">
              <button class="btn btn-primary" type="button" id="fetch-all-data">
                <i class="fa-solid fa-cloud-arrow-down me-1"></i>Fetch Data via API
              </button>
              <div class="form-text" id="fetch-all-help">Automatically fetch METAR/TAF, SIGMET, and NOTAMs data from CheckWX and Notamify APIs.</div>
            </div>

            <h2 class="h5">Uploads</h2>

            <div class="mb-3">
              <label class="form-label"><i class="fa-solid fa-file-signature me-1"></i>Accepted Flight Plan (mandatory, PDF)</label>
              <input class="form-control" type="file" name="accepted_flight_plan" accept="application/pdf" required>
              <div class="invalid-feedback">Accepted Flight Plan is required.</div>
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="fa-solid fa-file-lines me-1"></i>Operational Flight Plan (optional, PDF)</label>
              <input class="form-control" type="file" name="operational_flight_plan" accept="application/pdf">
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="fa-solid fa-scale-balanced me-1"></i>Mass &amp; Balance Calculation (mandatory, PDF)</label>
              <input class="form-control" type="file" name="mass_balance" accept="application/pdf" required>
              <div class="invalid-feedback">Mass &amp; Balance is required.</div>
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="fa-solid fa-gauge-high me-1"></i>Performance table (mandatory, PDF)</label>
              <div class="form-text">Must include take-off and landing distance, rate of climb, and cruise performance where applicable.</div>
              <input class="form-control" type="file" name="performance" accept="application/pdf" required>
              <div class="invalid-feedback">Performance table is required.</div>
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="fa-solid fa-triangle-exclamation me-1"></i>NOTAMs (mandatory, PDF)</label>
              <div class="form-text">Include EGMA or LEGR and any destinations/alternates as applicable.</div>
              <input class="form-control" type="file" name="notams" accept="application/pdf" required id="notams-file">
              <input type="hidden" name="notams_api_key" id="notams-api-key">
              <div class="invalid-feedback">NOTAMs are required.</div>
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="fa-solid fa-cloud-sun-rain me-1"></i>SIGWX/SIGMET (PDF)</label>
              <input class="form-control" type="file" name="sigwx" accept="application/pdf" id="sigwx-file">
              <input type="hidden" name="sigwx_api_key" id="sigwx-api-key">
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="fa-solid fa-wind me-1"></i>Wind Charts (PDF)</label>
              <input class="form-control" type="file" name="winds" accept="application/pdf">
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="fa-solid fa-cloud me-1"></i>METAR &amp; TAF (PDF)</label>
              <input class="form-control" type="file" name="metar_taf" accept="application/pdf" id="metar-taf-file">
              <input type="hidden" name="metar_taf_api_key" id="metar-taf-api-key">
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="fa-solid fa-map me-1"></i>Charts pack: Departure (VAC/ADC/PDC/Aerodrome data) (mandatory, PDF)</label>
              <div class="input-group">
                <input class="form-control" type="file" name="charts_departure" accept="application/pdf" required id="charts-departure-file">
                <button class="btn btn-outline-primary" type="button" id="fetch-charts-departure">
                  <i class="fa-solid fa-download me-1"></i>Fetch via API
                </button>
              </div>
              <div class="invalid-feedback">Departure charts pack is required.</div>
              <div class="form-text" id="charts-departure-help"></div>
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="fa-solid fa-map-location-dot me-1"></i>Charts pack: Destination (VAC/ADC/PDC/Aerodrome data) (mandatory, PDF)</label>
              <div class="input-group">
                <input class="form-control" type="file" name="charts_destination" accept="application/pdf" required id="charts-destination-file">
                <button class="btn btn-outline-primary" type="button" id="fetch-charts-destination">
                  <i class="fa-solid fa-download me-1"></i>Fetch via API
                </button>
              </div>
              <div class="invalid-feedback">Destination charts pack is required.</div>
              <div class="form-text" id="charts-destination-help"></div>
            </div>

            <div class="mb-3">
              <label class="form-label"><i class="fa-solid fa-map-pin me-1"></i>Charts pack: Alternates (single combined PDF, optional)</label>
              <div class="input-group">
                <input class="form-control" type="file" name="charts_alternates" accept="application/pdf" id="charts-alternates-file">
                <button class="btn btn-outline-primary" type="button" id="fetch-charts-alternates">
                  <i class="fa-solid fa-download me-1"></i>Fetch via API
                </button>
              </div>
              <div class="form-text" id="charts-alternates-help"></div>
            </div>

            <button class="btn btn-primary btn-lg mt-2" type="submit">
              <i class="fa-solid fa-file-pdf me-1"></i>Generate docket PDF
            </button>

          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-3 mt-3 mt-lg-0">
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h6">Output</h2>
          <p class="mb-0 small text-muted">The generator will produce a single merged PDF in the standard order with branded cover pages and a checklist index.</p>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });

  // Auto-fill alternates functionality
  const findAlternatesBtn = document.getElementById('findAlternatesBtn');
  const departureInput = document.querySelector('input[name="departure"]');
  const destinationInput = document.querySelector('input[name="destination"]');
  const alternatesInput = document.getElementById('alternates');
  const alternatesHelp = document.getElementById('alternatesHelp');
  const csrfToken = '<?= htmlspecialchars($csrfToken) ?>';

  if (findAlternatesBtn) {
    findAlternatesBtn.addEventListener('click', async () => {
      const departure = departureInput.value.trim().toUpperCase();
      const destination = destinationInput.value.trim().toUpperCase();

      // Validate inputs
      if (!departure || departure.length !== 4) {
        alternatesHelp.textContent = 'Please enter a valid 4-letter departure ICAO code.';
        alternatesHelp.className = 'form-text text-danger';
        return;
      }

      if (!destination || destination.length !== 4) {
        alternatesHelp.textContent = 'Please enter a valid 4-letter destination ICAO code.';
        alternatesHelp.className = 'form-text text-danger';
        return;
      }

      // Show loading state
      findAlternatesBtn.disabled = true;
      findAlternatesBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Finding...';
      alternatesHelp.textContent = 'Searching for viable alternates...';
      alternatesHelp.className = 'form-text text-muted';

      try {
        const url = `api_alternates.php?departure=${encodeURIComponent(departure)}&destination=${encodeURIComponent(destination)}&csrf_token=${encodeURIComponent(csrfToken)}`;
        const response = await fetch(url);
        const data = await response.json();

        if (data.success && data.alternates) {
          if (data.alternates.length > 0) {
            alternatesInput.value = data.alternates.join(', ');
            alternatesHelp.textContent = `Found ${data.count} alternate(s) within 10-25 miles of departure or destination.`;
            alternatesHelp.className = 'form-text text-success';
          } else {
            alternatesHelp.textContent = 'No alternates found within 25 miles of departure or destination.';
            alternatesHelp.className = 'form-text text-warning';
          }
        } else {
          alternatesHelp.textContent = data.error || 'Failed to find alternates.';
          alternatesHelp.className = 'form-text text-danger';
        }
      } catch (error) {
        alternatesHelp.textContent = 'Error: ' + error.message;
        alternatesHelp.className = 'form-text text-danger';
      } finally {
        // Reset button state
        findAlternatesBtn.disabled = false;
        findAlternatesBtn.innerHTML = '<i class="fa-solid fa-location-crosshairs me-1"></i>Find Alternates';
      }
    });
  }

  // Helper function to fetch charts via API
  async function fetchCharts(chartType, icaoInput, fileInput, helpElement, button) {
    const icao = icaoInput.value.trim().toUpperCase();

    // Validate ICAO code
    if (!icao || icao.length !== 4) {
      helpElement.textContent = 'Please enter a valid 4-letter ICAO code.';
      helpElement.className = 'form-text text-danger';
      return;
    }

    // Check if it's a Spanish airport
    if (!icao.startsWith('LE')) {
      helpElement.textContent = 'AIP España API only supports Spanish airports (ICAO codes starting with LE).';
      helpElement.className = 'form-text text-danger';
      return;
    }

    // Show loading state
    button.disabled = true;
    const originalHTML = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Fetching...';
    helpElement.textContent = `Fetching charts for ${icao}...`;
    helpElement.className = 'form-text text-muted';

    try {
      const formData = new FormData();
      formData.append('csrf_token', csrfToken);
      formData.append('icao', icao);
      formData.append('chart_type', chartType);

      const response = await fetch('fetch_charts.php', {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success && data.file_key) {
        // Create a hidden input to store the file key
        const existingInput = document.querySelector(`input[name="charts_${chartType}_api_key"]`);
        if (existingInput) {
          existingInput.remove();
        }

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = `charts_${chartType}_api_key`;
        hiddenInput.value = data.file_key;
        fileInput.parentElement.appendChild(hiddenInput);

        // Make file input optional since we have API data
        fileInput.removeAttribute('required');
        
        helpElement.textContent = `✓ Fetched ${data.charts_found} chart(s) for ${icao} from AIP España`;
        helpElement.className = 'form-text text-success';
      } else {
        helpElement.textContent = data.error || 'Failed to fetch charts.';
        helpElement.className = 'form-text text-danger';
      }
    } catch (error) {
      helpElement.textContent = 'Error: ' + error.message;
      helpElement.className = 'form-text text-danger';
    } finally {
      // Reset button state
      button.disabled = false;
      button.innerHTML = originalHTML;
    }
  }

  // Fetch charts for departure
  const fetchChartsDepartureBtn = document.getElementById('fetch-charts-departure');
  if (fetchChartsDepartureBtn) {
    fetchChartsDepartureBtn.addEventListener('click', () => {
      fetchCharts(
        'departure',
        departureInput,
        document.getElementById('charts-departure-file'),
        document.getElementById('charts-departure-help'),
        fetchChartsDepartureBtn
      );
    });
  }

  // Fetch charts for destination
  const fetchChartsDestinationBtn = document.getElementById('fetch-charts-destination');
  if (fetchChartsDestinationBtn) {
    fetchChartsDestinationBtn.addEventListener('click', () => {
      fetchCharts(
        'destination',
        destinationInput,
        document.getElementById('charts-destination-file'),
        document.getElementById('charts-destination-help'),
        fetchChartsDestinationBtn
      );
    });
  }

  // Fetch charts for alternates (use first alternate if multiple)
  const fetchChartsAlternatesBtn = document.getElementById('fetch-charts-alternates');
  if (fetchChartsAlternatesBtn) {
    fetchChartsAlternatesBtn.addEventListener('click', () => {
      const alternatesValue = alternatesInput.value.trim().toUpperCase();
      if (!alternatesValue) {
        const helpElement = document.getElementById('charts-alternates-help');
        helpElement.textContent = 'Please enter at least one alternate ICAO code.';
        helpElement.className = 'form-text text-danger';
        return;
      }
      
      // Use the first alternate
      const firstAlternate = alternatesValue.split(',')[0].trim();
      const fakeInput = { value: firstAlternate };
      
      fetchCharts(
        'alternates',
        fakeInput,
        document.getElementById('charts-alternates-file'),
        document.getElementById('charts-alternates-help'),
        fetchChartsAlternatesBtn
      );
    });
  }
})();
</script>
</body>
</html>
