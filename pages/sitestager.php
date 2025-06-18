<?php
// Get the effective user (handles user impersonation in Hestia CP)
$effective_user = (isset($_SESSION['look']) && !empty($_SESSION['look']))
    ? $_SESSION['look']
    : $_SESSION['user'];

// Redirect to web list if no domain specified
if (empty($_GET['domain'])) {
    header("Location: /list/web/");
    exit();
}
$source_domain = $_GET['domain'];

// Process form submission
if (isset($_POST['action']) && $_POST['action'] === 'create_staging') {
    // Sanitize and validate inputs
    $staging_prefix = preg_replace('/[^a-zA-Z0-9-]/', '', $_POST['staging_prefix']);
    $source_db = $_POST['source_db'];
    $config_type = $_POST['config_type'];
    $config_path = $_POST['config_path'];
    $env_db_name = $_POST['env_db_name'] ?? '';
    $env_db_user = $_POST['env_db_user'] ?? '';
    $env_db_pass = $_POST['env_db_pass'] ?? '';

    // Validate required fields
    if (empty($staging_prefix) || empty($source_db)) {
        $error = "Staging prefix and database cannot be empty.";
    } else {
        // Invoke plugin action to create staging site
        $hcpp->run("v-invoke-plugin sitestager_create "
            . escapeshellarg($effective_user) . " "
            . escapeshellarg($source_domain) . " "
            . escapeshellarg($staging_prefix) . " "
            . escapeshellarg($source_db) . " "
            . escapeshellarg($config_type) . " "
            . escapeshellarg($config_path) . " "
            . escapeshellarg($env_db_name) . " "
            . escapeshellarg($env_db_user) . " "
            . escapeshellarg($env_db_pass)
        );
        
        header("Location: /list/web/?staging_started=true");
        exit();
    }
}

// Fetch available databases for this user
$databases = $hcpp->run("v-list-databases {$effective_user} json");
if (!is_array($databases)) $databases = [];
$db_list = array_keys($databases);
?>

<!-- Toolbar -->
<div class="toolbar">
    <div class="toolbar-inner">
        <div class="toolbar-buttons">
            <a class="button button-secondary button-back" href="/list/web/">
                <i class="fas fa-arrow-left icon-blue"></i>Back to Web
            </a>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="container">
    <h1 class="u-mb20">Create Staging Site</h1>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger u-mb20"><?= $error ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="create_staging">
        
        <div class="form-group u-mb10">
            <label class="form-label">Source Domain</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($source_domain) ?>" readonly>
        </div>
        
        <div class="form-group u-mb10">
            <label for="staging_prefix" class="form-label">Staging Subdomain Prefix</label>
            <div class="u-input-group">
                <input type="text" class="form-control" name="staging_prefix" id="staging_prefix" value="staging">
                <span class="u-input-group-text">.<?= htmlspecialchars($source_domain) ?></span>
            </div>
        </div>
        
        <div class="form-group u-mb20">
            <label for="source_db" class="form-label">Database to Clone</label>
            <select name="source_db" id="source_db" class="form-select">
                <option value="">-- Select a Database --</option>
                <?php foreach ($db_list as $db): ?>
                    <option value="<?= $db ?>"><?= $db ?></option>
                <?php endforeach; ?>
            </select>
            <p class="form-text">This database will be duplicated for the staging site.</p>
        </div>

        <hr>
        <h2 class="u-mb10 u-mt20">Automatic Configuration</h2>
        <p class="u-mb10">The plugin can attempt to automatically update your staging site's database connection details.</p>

        <div class="form-group u-mb10">
            <label for="config_type" class="form-label">Application Type</label>
            <select name="config_type" id="config_type" class="form-select">
                <option value="manual">Manual Update (Do Nothing)</option>
                <option value="wordpress">WordPress (wp-config.php)</option>
                <option value="env">.env File (Laravel, etc.)</option>
            </select>
        </div>

        <div id="config-path-group" class="form-group u-mb10" style="display: none;">
            <label for="config_path" class="form-label">Configuration File Path</label>
            <input type="text" class="form-control" name="config_path" id="config_path" value="">
            <p class="form-text">Relative to the domain root (e.g., `public_html/wp-config.php`).</p>
        </div>

        <div id="env-keys-group" style="display: none; border-left: 2px solid #58a6ff; padding-left: 15px;">
            <p>Please provide the exact variable names from your `.env` file.</p>
            <div class="form-group u-mb10">
                <label for="env_db_name" class="form-label">Database Name Key</label>
                <input type="text" class="form-control" name="env_db_name" id="env_db_name" value="DB_DATABASE">
            </div>
            <div class="form-group u-mb10">
                <label for="env_db_user" class="form-label">Database User Key</label>
                <input type="text" class="form-control" name="env_db_user" id="env_db_user" value="DB_USERNAME">
            </div>
            <div class="form-group u-mb10">
                <label for="env_db_pass" class="form-label">Database Password Key</label>
                <input type="text" class="form-control" name="env_db_pass" id="env_db_pass" value="DB_PASSWORD">
            </div>
        </div>

        <div class="alert alert-warning u-mt20 u-mb20">
            <strong>Warning:</strong> This process may take several minutes for large sites. 
            You will be redirected and notified when the process begins.
        </div>
        
        <button type="submit" class="button">Create Staging Site</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const configType = document.getElementById('config_type');
    const pathGroup = document.getElementById('config-path-group');
    const pathInput = document.getElementById('config_path');
    const envGroup = document.getElementById('env-keys-group');

    // Toggle form fields based on configuration type selection
    function toggleConfigFields() {
        const selected = configType.value;
        if (selected === 'wordpress') {
            pathInput.value = 'public_html/wp-config.php';
            pathGroup.style.display = 'block';
            envGroup.style.display = 'none';
        } else if (selected === 'env') {
            pathInput.value = 'public_html/.env';
            pathGroup.style.display = 'block';
            envGroup.style.display = 'block';
        } else {
            pathGroup.style.display = 'none';
            envGroup.style.display = 'none';
        }
    }
    
    configType.addEventListener('change', toggleConfigFields);
});
</script>