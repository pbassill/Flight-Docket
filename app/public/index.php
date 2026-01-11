<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

$repo = new \OTR\DocketRepository($config);
$recent = $repo->listRecent(25);

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
    <a class="btn btn-primary btn-sm" href="create.php"><i class="fa-solid fa-plus me-1"></i>Create</a>
  </div>
</nav>

<main class="container py-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h4 mb-3">Recent dockets</h1>

      <?php if (!$recent): ?>
        <p class="text-muted mb-0">No dockets found.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>ID</th>
                <th>Created</th>
                <th>Route</th>
                <th>Aircraft</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $d): ?>
              <tr>
                <td><?= htmlspecialchars($d['id'] ?? '') ?></td>
                <td><?= htmlspecialchars($d['created_at'] ?? '') ?></td>
                <td><?= htmlspecialchars(($d['flight']['departure'] ?? '') . ' → ' . ($d['flight']['destination'] ?? '')) ?></td>
                <td><?= htmlspecialchars(($d['flight']['aircraft_type'] ?? '') . ' / ' . ($d['flight']['registration'] ?? '')) ?></td>
                <td class="text-end">
                  <a class="btn btn-outline-primary btn-sm" href="view.php?id=<?= urlencode((string)($d['id'] ?? '')) ?>">
                    <i class="fa-solid fa-eye me-1"></i>View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>
</body>
</html>
