# HestiaCP Site Stager & Cloner (hcpp-sitestager)

This is an advanced plugin for the [Hestia Control Panel](https://hestiacp.com) that adds a powerful "one-click" site staging capability. It is built upon the `hestiacp-pluginable` framework.

This tool allows users to create a complete, independent copy of a live website—including all files and a full database clone—on a new subdomain. Its key feature is the ability to **automatically update the configuration file** (`wp-config.php` or `.env`) of the new staging site with the correct staging database credentials, making the cloned site work out-of-the-box.

> [!WARNING]
> ## ADVANCED PLUGIN: USE WITH CAUTION
> This plugin performs major, automated operations on your server, including creating subdomains, copying large amounts of files, and manipulating databases. It is **NOT recommended for beginners** and should be tested on a **non-production server** first.
>
> **Always ensure you have recent, working backups before using this tool.** You assume all risk for its use.

---

## Features

-   **One-Click Staging:** Adds a "Create Staging" button directly to the actions menu for each web domain.
-   **Automatic Configuration Updates:** Intelligently updates database credentials in the staging site's configuration file for:
    -   **WordPress** (`wp-config.php`)
    -   **.env-based applications** (Laravel, etc.)
-   **Complete File Cloning:** Uses `rsync` to efficiently create an exact copy of the entire `public_html` directory.
-   **Full Database Duplication:** Dumps the selected source database, creates a new database with a unique user/password, and imports the data.
-   **Automatic URL Replacement:** Performs a search-and-replace on the SQL dump to automatically update the site URL from the production domain to the new staging domain.
-   **Background Processing:** The entire staging process runs as a background task to prevent web server timeouts on large sites.
-   **UI Notifications:** The user receives a notification in the Hestia panel when the staging process starts and when it completes successfully.

## How It Works

The staging process is fully automated by a backend script:

1.  The user clicks the "Create Staging" button for a domain and is taken to a configuration page.
2.  They choose a prefix for the staging subdomain, select the database to clone, and **select their Application Type** (e.g., WordPress, .env, or Manual).
3.  Upon submission, a background shell script is launched with `root` privileges. This script performs the following steps:
    a. Creates the new staging web domain (e.g., `staging.example.com`).
    b. Creates a new, empty database with a secure, randomly generated password.
    c. Copies all files from the source to the staging directory.
    d. Dumps the source database and replaces all instances of the production domain with the new staging domain.
    e. Imports the modified database into the new staging database.
    f. **Updates Configuration:** Based on the user's selection, it modifies the `wp-config.php` or `.env` file in the staging directory, replacing the production database credentials with the new staging ones.
    g. Sends a final success notification to the user in the Hestia UI.

## Requirements

-   Hestia Control Panel v1.9.X or greater.
-   **[hestiacp-pluginable](https://github.com/virtuosoft-dev/hestiacp-pluginable)** must be installed first.
-   Ubuntu or Debian Linux OS.
-   The `rsync` utility (the installer will attempt to add this).
-   `root` or `sudo` access to the server.

## Installation

**Please back up your server before proceeding!**

1.  SSH into your HestiaCP server.
2.  Navigate to the Hestia plugins directory:
    ```bash
    cd /usr/local/hestia/plugins
    ```
3.  Clone this repository:
    ```bash
    sudo git clone https://github.com/iniznet/hcpp-sitestager.git sitestager
    ```
4.  **Set Permissions:** This is a critical step. Ensure all scripts are executable.
    ```bash
    sudo chmod +x sitestager/install sitestager/uninstall sitestager/pages/staging_script.sh
    ```
5.  **Run the Installer:** This will ensure the `rsync` dependency is met.
    ```bash
    sudo /usr/local/hestia/plugins/sitestager/install
    ```
6.  Log in to HestiaCP. The new "Create Staging" option will be available in the Web tab.

## Important Considerations & Limitations

-   **WordPress & Serialized Data:** The current database search-and-replace method using `sed` is effective for simple sites but **may break WordPress sites** or other applications that store URLs in serialized PHP arrays. For 100% reliable WordPress staging, a tool like `wp-cli search-replace` is recommended.
-   **Configuration File Parsing:** The automatic configuration update relies on `sed` and common patterns (e.g., `define('DB_NAME', ...);` or `DB_DATABASE=...`). If your configuration file is formatted in a non-standard way, the update might fail. In this case, you would need to update the credentials manually.
-   **One-Way Staging:** This plugin only handles creating a staging site from production. It does **not** have a "push to live" feature to move changes from staging back to production.

## Uninstallation

1.  Run the uninstallation script:
    ```bash
    sudo /usr/local/hestia/plugins/sitestager/uninstall
    ```
2.  Remove the plugin directory:
    ```bash
    sudo rm -rf /usr/local/hestia/plugins/sitestager
    ```