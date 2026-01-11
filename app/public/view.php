<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

\OTR\Security::setSecurityHeaders();

$repo = new \OTR\DocketRepository($config);

$id = (string)($_GET['id'] ?? '');
$docket = $id ? $repo->loadById($id) : null;

if (!$docket) {
    http_response_code(404);
    echo "Docket not found.";
    exit;
}

$pdfPath = (string)($docket['generated_pdf'] ?? '');
$pdfExists = $pdfPath && is_file($pdfPath);

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
    <a class="navbar-brand" href="index.php"><i class="fa-solid fa-plane-departure me-2"></i>OTR Aviation Flight Docket</a>
    <a class="btn btn-primary btn-sm" href="create.php"><i class="fa-solid fa-plus me-1"></i>Create</a>
  </div>
</nav>

<main class="container py-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <h1 class="h4 mb-3">Docket <?= htmlspecialchars($docket['id'] ?? '') ?></h1>

      <p class="mb-2"><strong>Route:</strong> <?= htmlspecialchars(($docket['flight']['departure'] ?? '') . ' → ' . ($docket['flight']['destination'] ?? '')) ?></p>
      <p class="mb-2"><strong>Aircraft:</strong> <?= htmlspecialchars(($docket['flight']['aircraft_type'] ?? '') . ' / ' . ($docket['flight']['registration'] ?? '')) ?></p>
      <p class="mb-4"><strong>Created:</strong> <?= htmlspecialchars($docket['created_at'] ?? '') ?></p>

      <?php if ($pdfExists): ?>
        <a class="btn btn-danger" href="download.php?id=<?= urlencode((string)($docket['id'] ?? '')) ?>">
          <i class="fa-solid fa-file-pdf me-1"></i>Download PDF
        </a>
      <?php else: ?>
        <div class="alert alert-warning mb-0">Generated PDF not found on disk.</div>
      <?php endif; ?>

      <hr class="my-4">

      <h2 class="h6">Stored record (JSON)</h2>
      <pre class="bg-body-secondary p-3 rounded small mb-0"><?= htmlspecialchars(json_encode($docket, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '') ?></pre>
    </div>
  </div>
</main>
</body>
</html>
