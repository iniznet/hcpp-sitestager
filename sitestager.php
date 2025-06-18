<?php
if (!class_exists('SiteStager')) {
    class SiteStager extends HCPP_Hooks
    {
        public function __construct()
        {
            global $hcpp;
            $hcpp->add_custom_page('sitestager', __DIR__ . '/pages/sitestager.php');
            $hcpp->add_action('hcpp_list_web_xpath', [$this, 'inject_ui_button']);
            $hcpp->add_action('hcpp_invoke_plugin', [$this, 'handle_invocations']);
        }

        /**
         * Injects staging buttons into the web domains list
         * Adds a "Create Staging" button to each domain's action menu
         */
        public function inject_ui_button($xpath)
        {
            $rows = $xpath->query("//div[contains(@class, 'js-unit')]");
            
            foreach ($rows as $row) {
                $domain_link = $xpath->query(".//a[contains(@href, '/edit/web/?domain=')]", $row)->item(0);
                $actions_list = $xpath->query(".//ul[contains(@class, 'units-table-row-actions')]", $row)->item(0);
                
                // Prevent duplicate buttons
                $existing_button = $xpath->query(".//a[contains(@href, '/?p=sitestager&domain=')]", $row)->item(0);
                
                if ($domain_link && $actions_list && !$existing_button) {
                    // Extract domain name from href
                    $href = $domain_link->getAttribute('href');
                    parse_str(parse_url($href, PHP_URL_QUERY), $query);
                    $domain_name = $query['domain'];
                    
                    // Create button elements
                    $li = $xpath->document->createElement('li');
                    $li->setAttribute('class', 'units-table-row-action');
                    
                    $a = $xpath->document->createElement('a');
                    $a->setAttribute('class', 'units-table-row-action-link');
                    $a->setAttribute('href', '/?p=sitestager&domain=' . urlencode($domain_name));
                    $a->setAttribute('title', 'Create Staging Site');
                    
                    $i = $xpath->document->createElement('i');
                    $i->setAttribute('class', 'fas fa-copy icon-blue');
                    
                    $span = $xpath->document->createElement('span', 'Create Staging');
                    $span->setAttribute('class', 'u-hide-desktop');
                    
                    // Assemble and insert DOM elements
                    $a->appendChild($i);
                    $a->appendChild($span);
                    $li->appendChild($a);
                    $actions_list->insertBefore($li, $actions_list->firstChild);
                }
            }
            
            return $xpath;
        }

        /**
         * Handles plugin actions triggered from the UI
         * Processes the staging site creation request
         */
        public function handle_invocations($args)
        {
            if ($args[0] === 'sitestager_create') {
                $user = $args[1];
                $source_domain = $args[2];
                $staging_prefix = $args[3];
                $source_db = $args[4];
                $config_type = $args[5];
                $config_path = $args[6];
                $env_db_name = $args[7];
                $env_db_user = $args[8];
                $env_db_pass = $args[9];
                
                // Generate secure random password for staging database
                $staging_db_pass = bin2hex(random_bytes(12));

                // Execute staging script in background
                $command = __DIR__ . "/pages/staging_script.sh "
                    . escapeshellarg($user) . " "
                    . escapeshellarg($source_domain) . " "
                    . escapeshellarg($staging_prefix) . " "
                    . escapeshellarg($source_db) . " "
                    . escapeshellarg($staging_db_pass) . " "
                    . escapeshellarg($config_type) . " "
                    . escapeshellarg($config_path) . " "
                    . escapeshellarg($env_db_name) . " "
                    . escapeshellarg($env_db_user) . " "
                    . escapeshellarg($env_db_pass)
                    . " > /tmp/staging_{$staging_prefix}.{$source_domain}.log 2>&1 &";

                shell_exec($command);
            }
            
            return $args;
        }
    }
    
    global $hcpp;
    $hcpp->register_plugin(SiteStager::class);
}