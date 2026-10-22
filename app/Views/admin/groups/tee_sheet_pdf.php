<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= esc($heading) ?></title>
    <style>
        @page { margin: 0.6in 0.6in; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11pt; color: #000; }
        .tt-heading { font-size: 16pt; margin: 0 0 12pt; }
        .tt-lines { width: 100%; border-collapse: collapse; }
        .tt-lines td { padding: 3pt 0; border-bottom: 0.5pt solid #ccc; vertical-align: top; }
        .tt-time { width: 0.9in; font-weight: bold; white-space: nowrap; }
    </style>
</head>
<body>
<?= view('admin/groups/tee_sheet_doc', ['heading' => $heading, 'rows' => $rows]) ?>
</body>
</html>
