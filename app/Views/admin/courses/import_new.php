<section class="panel" id="import-course">
    <h2>Import a course from a CSV file</h2>
    <p class="muted">Adds a new course with its holes from a CSV file made with Export CSV (for example from another copy
        of this site). The file must give the course's name, and no course here may already have that name; to update
        an existing course, use Import on its edit page instead. If anything in the file is wrong, nothing is added.</p>
    <form method="post" action="<?= site_url('admin/courses/import') ?>" enctype="multipart/form-data" class="form inline">
        <?= csrf_field() ?>
        <input type="file" name="csv" accept=".csv,text/csv" required aria-label="CSV file">
        <button type="submit">Import course</button>
    </form>
</section>
