<section class="panel">
    <h2>Import from a CSV file</h2>
    <p class="muted">Reads the course details and holes from a CSV file in the same layout as
        <a href="<?= site_url("admin/courses/{$id}/export") ?>">Export CSV</a>: course fields first (field name, value),
        then a row of hole column names, then holes 1 to 18. Fields and holes in the file replace what is saved now;
        anything not in the file is left as it is. If anything in the file is wrong, nothing is changed.</p>
    <form method="post" action="<?= site_url("admin/courses/{$id}/import") ?>" enctype="multipart/form-data" class="form inline"
          onsubmit="return confirm('Replace this course\'s details and holes with the ones in the file?')">
        <?= csrf_field() ?>
        <input type="file" name="csv" accept=".csv,text/csv" required>
        <button type="submit">Import</button>
    </form>
</section>
