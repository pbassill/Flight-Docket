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
      }
      form.classList.add('was-validated');
    }, false);
  });
})();

function deleteAircraft(id, typeCode) {
  if (!confirm(`Delete aircraft configuration "${typeCode}"?`)) {
    return;
  }
  
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'delete_aircraft.php';
  
  // Get CSRF token from the main form
  const mainForm = document.getElementById('aircraftForm');
  const csrfToken = mainForm.querySelector('input[name="csrf_token"]').value;
  
  const csrfInput = document.createElement('input');
  csrfInput.type = 'hidden';
  csrfInput.name = 'csrf_token';
  csrfInput.value = csrfToken;
  form.appendChild(csrfInput);
  
  const idInput = document.createElement('input');
  idInput.type = 'hidden';
  idInput.name = 'id';
  idInput.value = id;
  form.appendChild(idInput);
  
  document.body.appendChild(form);
  form.submit();
}
</script>
</body>
</html>
