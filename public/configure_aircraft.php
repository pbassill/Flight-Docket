<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

\OTR\Security::startSecureSession();
\OTR\Security::setSecurityHeaders();
$csrfToken = \OTR\Security::generateCsrfToken();

$aircraftRepo = new \OTR\AircraftRepository($config);
$aircraftList = $aircraftRepo->listAll();

// Check if editing existing aircraft
$editAircraft = null;
if (isset($_GET['edit'])) {
    $editId = (string)$_GET['edit'];
    $editAircraft = $aircraftRepo->load($editId);
}

// Helper function to safely get string value from array
function getValue(?array $data, string $key, string $subkey = ''): string {
    if ($data === null) {
        return '';
    }
    if ($subkey !== '') {
        $value = $data[$key][$subkey] ?? null;
    } else {
        $value = $data[$key] ?? null;
    }
    if ($value === null) {
        return '';
    }
    return (string)$value;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Configure Aircraft - <?= htmlspecialchars($config['app_name']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <span class="navbar-brand"><i class="fa-solid fa-plane-departure me-2"></i>OTR Aviation Flight Docket</span>
    <div>
      <a class="btn btn-outline-light btn-sm me-2" href="index.php"><i class="fa-solid fa-clock-rotate-left me-1"></i>Recent</a>
      <a class="btn btn-primary btn-sm" href="create.php"><i class="fa-solid fa-plus me-1"></i>Create</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="row">
    <div class="col-lg-8">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h1 class="h4 mb-3"><?= $editAircraft ? 'Edit Aircraft' : 'Add Aircraft Configuration' ?></h1>

          <form id="aircraftForm" action="save_aircraft.php" method="post" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <?php if ($editAircraft): ?>
              <input type="hidden" name="id" value="<?= htmlspecialchars($editAircraft['id'] ?? '') ?>">
            <?php endif; ?>

            <h5 class="border-bottom pb-2 mb-3">Basic Information</h5>
            
            <div class="mb-3">
              <label class="form-label">Unit System</label>
              <div class="btn-group w-100" role="group" aria-label="Unit system toggle">
                <input type="radio" class="btn-check" name="unit_system" id="unit_metric" value="metric" checked autocomplete="off">
                <label class="btn btn-outline-primary" for="unit_metric">
                  <i class="fa-solid fa-globe me-1"></i>Metric
                </label>
                <input type="radio" class="btn-check" name="unit_system" id="unit_imperial" value="imperial" autocomplete="off">
                <label class="btn btn-outline-primary" for="unit_imperial">
                  <i class="fa-solid fa-flag-usa me-1"></i>Imperial
                </label>
              </div>
              <div class="form-text">Toggle to switch between metric and imperial measurements. Data is stored in metric units.</div>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Training Configuration</label>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="training_mode" autocomplete="off">
                <label class="form-check-label" for="training_mode">
                  <i class="fa-solid fa-graduation-cap me-1"></i>Configure for Training
                </label>
              </div>
              <div class="form-text">Enable to generate training configuration (Pilot L: 105kg, Pilot R: 90kg, Baggage: 4kg, Full fuel)</div>
            </div>
            
            <?php if ($editAircraft): ?>
            <div class="mb-3">
              <button type="button" class="btn btn-outline-success w-100" id="generateTrainingPdf">
                <i class="fa-solid fa-file-pdf me-1"></i>Generate Training PDF (Mass & Balance + Performance)
              </button>
              <div class="form-text">Creates a PDF with Mass & Balance sheet and Performance data for training configuration</div>
            </div>
            <?php endif; ?>
            
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label">Aircraft Type Code <span class="text-danger">*</span></label>
                <input class="form-control" name="type_code" placeholder="C152" required 
                  value="<?= htmlspecialchars(getValue($editAircraft, 'type_code')) ?>" maxlength="20">
                <div class="form-text">Short code used in dropdowns (e.g., C152, C172)</div>
                <div class="invalid-feedback">Aircraft type code is required.</div>
              </div>

              <div class="col-md-6">
                <label class="form-label">Aircraft Name <span class="text-danger">*</span></label>
                <input class="form-control" name="name" placeholder="Cessna 152" required
                  value="<?= htmlspecialchars(getValue($editAircraft, 'name')) ?>" maxlength="100">
                <div class="form-text">Full aircraft name (e.g., Cessna 152)</div>
                <div class="invalid-feedback">Aircraft name is required.</div>
              </div>
            </div>

            <h5 class="border-bottom pb-2 mb-3"><i class="fa-solid fa-scale-balanced me-2"></i>Mass & Balance Data</h5>
            
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <label class="form-label">Empty Weight (kg)</label>
                <input type="number" step="0.1" class="form-control" name="empty_weight" placeholder="500.0"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'mass_balance', 'empty_weight')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Empty Weight Moment Arm (m)</label>
                <input type="number" step="0.01" class="form-control" name="empty_moment_arm" placeholder="2.50"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'mass_balance', 'empty_moment_arm')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Max Takeoff Weight (kg)</label>
                <input type="number" step="0.1" class="form-control" name="max_takeoff_weight" placeholder="750.0"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'mass_balance', 'max_takeoff_weight')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Max Landing Weight (kg)</label>
                <input type="number" step="0.1" class="form-control" name="max_landing_weight" placeholder="750.0"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'mass_balance', 'max_landing_weight')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Pilot Seat Moment Arm (m)</label>
                <input type="number" step="0.01" class="form-control" name="pilot_moment_arm" placeholder="2.20"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'mass_balance', 'pilot_moment_arm')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Passenger Seat Moment Arm (m)</label>
                <input type="number" step="0.01" class="form-control" name="passenger_moment_arm" placeholder="2.80"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'mass_balance', 'passenger_moment_arm')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Baggage Moment Arm (m)</label>
                <input type="number" step="0.01" class="form-control" name="baggage_moment_arm" placeholder="3.50"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'mass_balance', 'baggage_moment_arm')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Fuel Moment Arm (m)</label>
                <input type="number" step="0.01" class="form-control" name="fuel_moment_arm" placeholder="2.90"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'mass_balance', 'fuel_moment_arm')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Max Fuel Capacity (L)</label>
                <input type="number" step="0.1" class="form-control" name="max_fuel_capacity" placeholder="120.0"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'mass_balance', 'max_fuel_capacity')) ?>">
              </div>

              <div class="col-12">
                <label class="form-label">CG Limits (JSON array)</label>
                <textarea class="form-control font-monospace" name="cg_limits" rows="3" placeholder='[{"weight": 500, "cg_forward": 2.0, "cg_aft": 2.8}]'><?= htmlspecialchars(getValue($editAircraft, 'mass_balance', 'cg_limits')) ?></textarea>
                <div class="form-text">Define weight-dependent CG envelope as JSON array</div>
              </div>
            </div>

            <h5 class="border-bottom pb-2 mb-3"><i class="fa-solid fa-gauge-high me-2"></i>Performance Data</h5>
            
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <label class="form-label">Cruise Speed (kts)</label>
                <input type="number" step="1" class="form-control" name="cruise_speed" placeholder="110"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'performance', 'cruise_speed')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Stall Speed (kts)</label>
                <input type="number" step="1" class="form-control" name="stall_speed" placeholder="45"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'performance', 'stall_speed')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Climb Rate (ft/min)</label>
                <input type="number" step="10" class="form-control" name="climb_rate" placeholder="700"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'performance', 'climb_rate')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Takeoff Distance (m)</label>
                <input type="number" step="10" class="form-control" name="takeoff_distance" placeholder="450"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'performance', 'takeoff_distance')) ?>">
                <div class="form-text">Ground roll at sea level, ISA</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Landing Distance (m)</label>
                <input type="number" step="10" class="form-control" name="landing_distance" placeholder="380"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'performance', 'landing_distance')) ?>">
                <div class="form-text">Ground roll at sea level, ISA</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Service Ceiling (ft)</label>
                <input type="number" step="100" class="form-control" name="service_ceiling" placeholder="14700"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'performance', 'service_ceiling')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label">Fuel Consumption (L/hr)</label>
                <input type="number" step="0.1" class="form-control" name="fuel_consumption" placeholder="22.5"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'performance', 'fuel_consumption')) ?>">
                <div class="form-text">At cruise power setting</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Range (nm)</label>
                <input type="number" step="10" class="form-control" name="range" placeholder="415"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'performance', 'range')) ?>">
                <div class="form-text">With standard fuel reserves</div>
              </div>

              <div class="col-md-4">
                <label class="form-label">Endurance (hours)</label>
                <input type="number" step="0.1" class="form-control" name="endurance" placeholder="4.5"
                  value="<?= htmlspecialchars(getValue($editAircraft, 'performance', 'endurance')) ?>">
              </div>

              <div class="col-12">
                <label class="form-label">Performance Notes</label>
                <textarea class="form-control" name="performance_notes" rows="3" placeholder="Additional performance information, corrections for weight/altitude/temperature"><?= htmlspecialchars(getValue($editAircraft, 'performance', 'notes')) ?></textarea>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-save me-1"></i><?= $editAircraft ? 'Update Aircraft' : 'Save Aircraft' ?>
              </button>
              <?php if ($editAircraft): ?>
                <a class="btn btn-outline-secondary" href="configure_aircraft.php">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h6 mb-3">Configured Aircraft</h2>
          
          <?php if (empty($aircraftList)): ?>
            <p class="text-muted small mb-0">No aircraft configured yet.</p>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($aircraftList as $aircraft): ?>
                <div class="list-group-item px-0">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="fw-bold"><?= htmlspecialchars($aircraft['type_code'] ?? '') ?></div>
                      <small class="text-muted"><?= htmlspecialchars($aircraft['name'] ?? '') ?></small>
                    </div>
                    <div class="btn-group btn-group-sm">
                      <a class="btn btn-outline-primary" href="configure_aircraft.php?edit=<?= urlencode($aircraft['id'] ?? '') ?>">
                        <i class="fa-solid fa-edit"></i>
                      </a>
                      <button class="btn btn-outline-danger" type="button" onclick="deleteAircraft('<?= htmlspecialchars($aircraft['id'] ?? '') ?>', '<?= htmlspecialchars($aircraft['type_code'] ?? '') ?>')">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm mt-3">
        <div class="card-body">
          <h2 class="h6 mb-2">About Aircraft Configuration</h2>
          <p class="small text-muted mb-0">
            Aircraft configurations store all necessary data for Mass & Balance calculations and Performance tables.
            This data can be referenced when creating flight dockets.
          </p>
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
        form.classList.add('was-validated');
      } else {
        // Convert all values back to metric before submitting
        // Only do this right before actual submission
        if (currentUnitSystem === 'imperial') {
          convertFormValues('imperial', 'metric');
        }
      }
    }, false);
  });
})();

// Unit conversion and toggle functionality
const unitConfig = {
  empty_weight: {
    metric: { unit: 'kg', label: 'Empty Weight (kg)', factor: 1 },
    imperial: { unit: 'lbs', label: 'Empty Weight (lbs)', factor: 2.20462 }
  },
  max_takeoff_weight: {
    metric: { unit: 'kg', label: 'Max Takeoff Weight (kg)', factor: 1 },
    imperial: { unit: 'lbs', label: 'Max Takeoff Weight (lbs)', factor: 2.20462 }
  },
  max_landing_weight: {
    metric: { unit: 'kg', label: 'Max Landing Weight (kg)', factor: 1 },
    imperial: { unit: 'lbs', label: 'Max Landing Weight (lbs)', factor: 2.20462 }
  },
  empty_moment_arm: {
    metric: { unit: 'm', label: 'Empty Weight Moment Arm (m)', factor: 1 },
    imperial: { unit: 'in', label: 'Empty Weight Moment Arm (in)', factor: 39.3701 }
  },
  pilot_moment_arm: {
    metric: { unit: 'm', label: 'Pilot Seat Moment Arm (m)', factor: 1 },
    imperial: { unit: 'in', label: 'Pilot Seat Moment Arm (in)', factor: 39.3701 }
  },
  passenger_moment_arm: {
    metric: { unit: 'm', label: 'Passenger Seat Moment Arm (m)', factor: 1 },
    imperial: { unit: 'in', label: 'Passenger Seat Moment Arm (in)', factor: 39.3701 }
  },
  baggage_moment_arm: {
    metric: { unit: 'm', label: 'Baggage Moment Arm (m)', factor: 1 },
    imperial: { unit: 'in', label: 'Baggage Moment Arm (in)', factor: 39.3701 }
  },
  fuel_moment_arm: {
    metric: { unit: 'm', label: 'Fuel Moment Arm (m)', factor: 1 },
    imperial: { unit: 'in', label: 'Fuel Moment Arm (in)', factor: 39.3701 }
  },
  max_fuel_capacity: {
    metric: { unit: 'L', label: 'Max Fuel Capacity (L)', factor: 1 },
    imperial: { unit: 'gal', label: 'Max Fuel Capacity (gal)', factor: 0.264172 }
  },
  takeoff_distance: {
    metric: { unit: 'm', label: 'Takeoff Distance (m)', factor: 1 },
    imperial: { unit: 'ft', label: 'Takeoff Distance (ft)', factor: 3.28084 }
  },
  landing_distance: {
    metric: { unit: 'm', label: 'Landing Distance (m)', factor: 1 },
    imperial: { unit: 'ft', label: 'Landing Distance (ft)', factor: 3.28084 }
  },
  fuel_consumption: {
    metric: { unit: 'L/hr', label: 'Fuel Consumption (L/hr)', factor: 1 },
    imperial: { unit: 'gal/hr', label: 'Fuel Consumption (gal/hr)', factor: 0.264172 }
  }
};

let currentUnitSystem = 'metric';

function convertValue(value, fieldName, fromSystem, toSystem) {
  if (!value || value === '' || !unitConfig[fieldName]) {
    return value;
  }
  
  const numValue = parseFloat(value);
  if (isNaN(numValue)) {
    return value;
  }
  
  // Convert to metric first (base unit)
  let metricValue = numValue;
  if (fromSystem === 'imperial') {
    metricValue = numValue / unitConfig[fieldName].imperial.factor;
  }
  
  // Then convert to target system
  if (toSystem === 'imperial') {
    return (metricValue * unitConfig[fieldName].imperial.factor).toFixed(2);
  }
  
  return metricValue.toFixed(2);
}

function updateUnitLabels(unitSystem) {
  Object.keys(unitConfig).forEach(fieldName => {
    const input = document.querySelector(`input[name="${fieldName}"]`);
    if (input) {
      const label = input.closest('.col-md-4, .col-12')?.querySelector('label');
      if (label) {
        const config = unitConfig[fieldName][unitSystem];
        // Use textContent instead of innerHTML to prevent XSS
        const requiredStar = label.querySelector('.text-danger');
        label.textContent = config.label;
        // Re-add required star if it existed
        if (requiredStar) {
          label.appendChild(requiredStar);
        }
      }
    }
  });
}

function convertFormValues(fromSystem, toSystem) {
  Object.keys(unitConfig).forEach(fieldName => {
    const input = document.querySelector(`input[name="${fieldName}"]`);
    if (input && input.value) {
      const convertedValue = convertValue(input.value, fieldName, fromSystem, toSystem);
      input.value = convertedValue;
    }
  });
}

// Initialize unit toggle listeners
document.addEventListener('DOMContentLoaded', function() {
  const metricRadio = document.getElementById('unit_metric');
  const imperialRadio = document.getElementById('unit_imperial');
  
  if (metricRadio && imperialRadio) {
    metricRadio.addEventListener('change', function() {
      if (this.checked) {
        const oldSystem = currentUnitSystem;
        currentUnitSystem = 'metric';
        updateUnitLabels('metric');
        convertFormValues(oldSystem, 'metric');
      }
    });
    
    imperialRadio.addEventListener('change', function() {
      if (this.checked) {
        const oldSystem = currentUnitSystem;
        currentUnitSystem = 'imperial';
        updateUnitLabels('imperial');
        convertFormValues(oldSystem, 'imperial');
      }
    });
  }
  
  // Initialize with metric system
  updateUnitLabels('metric');
});

function deleteAircraft(id, typeCode) {
  if (!confirm(`Delete aircraft configuration "${typeCode}"?`)) {
    return;
  }
  
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'delete_aircraft.php';
  
  // Get CSRF token from the main form
  const mainForm = document.getElementById('aircraftForm');
  if (!mainForm) {
    alert('Error: Form not found');
    return;
  }
  
  const csrfTokenInput = mainForm.querySelector('input[name="csrf_token"]');
  if (!csrfTokenInput) {
    alert('Error: CSRF token not found');
    return;
  }
  
  const csrfInput = document.createElement('input');
  csrfInput.type = 'hidden';
  csrfInput.name = 'csrf_token';
  csrfInput.value = csrfTokenInput.value;
  form.appendChild(csrfInput);
  
  const idInput = document.createElement('input');
  idInput.type = 'hidden';
  idInput.name = 'id';
  idInput.value = id;
  form.appendChild(idInput);
  
  document.body.appendChild(form);
  form.submit();
}

// Training mode functionality
document.addEventListener('DOMContentLoaded', function() {
  const trainingModeCheckbox = document.getElementById('training_mode');
  const generatePdfBtn = document.getElementById('generateTrainingPdf');
  
  // Training mode values are just for display/calculation purposes
  // They don't need to be saved to the aircraft configuration
  if (trainingModeCheckbox) {
    trainingModeCheckbox.addEventListener('change', function() {
      if (this.checked) {
        alert('Training mode enabled. This will be used when generating the training PDF with:\n' +
              '• Left seat pilot: 105 kg\n' +
              '• Right seat pilot: 90 kg\n' +
              '• Baggage: 4 kg\n' +
              '• Full fuel and full oil');
      }
    });
  }
  
  // Generate training PDF
  if (generatePdfBtn) {
    generatePdfBtn.addEventListener('click', function() {
      const aircraftId = document.querySelector('input[name="id"]')?.value;
      if (!aircraftId) {
        alert('Please save the aircraft configuration first.');
        return;
      }
      
      // Open PDF generation endpoint in new tab
      window.open('generate_training_pdf.php?aircraft_id=' + encodeURIComponent(aircraftId), '_blank');
    });
  }
});
</script>
</body>
</html>
