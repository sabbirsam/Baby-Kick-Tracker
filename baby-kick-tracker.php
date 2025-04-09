<?php
/**
 * Plugin Name: Baby Kick Tracker
 * Plugin URI: sabbirsam/baby-kick-tracker
 * Description: A WordPress plugin to track baby kicks during pregnancy, send alerts, and display statistics.
 * Version: 1.0.1
 * Author: sabbirsam
 * Author URI: https://sabbirsam.com
 * Text Domain: baby-kick-tracker
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Baby_Kick_Tracker {
    
    // Constructor - setup hooks and filters
    public function __construct() {
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add shortcode for the kick tracking form
        add_shortcode('baby_kick_tracker', array($this, 'render_kick_tracker_form'));
        
        // Ajax actions for the form submission
        add_action('wp_ajax_record_baby_kick', array($this, 'record_baby_kick'));
        add_action('wp_ajax_nopriv_record_baby_kick', array($this, 'record_baby_kick'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Schedule cron events for email alerts
        add_action('init', array($this, 'setup_cron_events'));
        
        // Cron event hooks
        add_action('baby_kick_tracker_hourly_check', array($this, 'hourly_check_kicks'));
        add_action('baby_kick_tracker_daily_summary', array($this, 'send_daily_summary'));
    }
    
    // Plugin activation
    public function activate_plugin() {
        // Create custom database tables
        $this->create_database_tables();
        
        // Set default options
        $default_options = array(
            'admin_email' => get_option('admin_email'),
            'mother_email' => '',
            'kicks_threshold' => 10,
            'assessment_period_hours' => 2,
        );
        
        add_option('baby_kick_tracker_options', $default_options);
        
        // Clear the cron schedules
        wp_clear_scheduled_hook('baby_kick_tracker_hourly_check');
        wp_clear_scheduled_hook('baby_kick_tracker_daily_summary');
        
        // Schedule the cron events
        if (!wp_next_scheduled('baby_kick_tracker_hourly_check')) {
            wp_schedule_event(time(), 'hourly', 'baby_kick_tracker_hourly_check');
        }
        
        if (!wp_next_scheduled('baby_kick_tracker_daily_summary')) {
            wp_schedule_event(time(), 'daily', 'baby_kick_tracker_daily_summary');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    // Plugin deactivation
    public function deactivate_plugin() {
        // Clear the cron schedules
        wp_clear_scheduled_hook('baby_kick_tracker_hourly_check');
        wp_clear_scheduled_hook('baby_kick_tracker_daily_summary');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    // Create database tables
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for kick sessions
        $table_sessions = $wpdb->prefix . 'baby_kick_sessions';
        $sql_sessions = "CREATE TABLE $table_sessions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            start_time datetime NOT NULL,
            end_time datetime DEFAULT NULL,
            total_kicks int(11) NOT NULL DEFAULT 0,
            status varchar(50) DEFAULT 'in_progress',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Table for individual kicks
        $table_kicks = $wpdb->prefix . 'baby_kick_records';
        $sql_kicks = "CREATE TABLE $table_kicks (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id mediumint(9) NOT NULL,
            kick_time datetime NOT NULL,
            kick_count int(11) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        // Table for notification logs
        $table_notifications = $wpdb->prefix . 'baby_kick_notifications';
        $sql_notifications = "CREATE TABLE $table_notifications (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id mediumint(9) NOT NULL,
            notification_type varchar(50) NOT NULL,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            recipient varchar(255) NOT NULL,
            status varchar(50) DEFAULT 'sent',
            message longtext,
            PRIMARY KEY  (id),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sessions);
        dbDelta($sql_kicks);
        dbDelta($sql_notifications);
    }
    
    // Add admin menu
    public function add_admin_menu() {
        add_menu_page(
            'Baby Kick Tracker',
            'Baby Kick Tracker',
            'manage_options',
            'baby-kick-tracker',
            array($this, 'render_admin_page'),
            'dashicons-heart',
            30
        );
        
        add_submenu_page(
            'baby-kick-tracker',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'baby-kick-tracker',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'baby-kick-tracker',
            'Settings',
            'Settings',
            'manage_options',
            'baby-kick-tracker-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'baby-kick-tracker',
            'Reports',
            'Reports',
            'manage_options',
            'baby-kick-tracker-reports',
            array($this, 'render_reports_page')
        );
    }
    
    // Register settings
    public function register_settings() {
        register_setting('baby_kick_tracker_settings', 'baby_kick_tracker_options');
        
        add_settings_section(
            'baby_kick_tracker_main_settings',
            'Main Settings',
            array($this, 'settings_section_callback'),
            'baby_kick_tracker_settings'
        );
        
        add_settings_field(
            'mother_email',
            'Mother\'s Email',
            array($this, 'mother_email_callback'),
            'baby_kick_tracker_settings',
            'baby_kick_tracker_main_settings'
        );
        
        add_settings_field(
            'admin_email',
            'Admin Email',
            array($this, 'admin_email_callback'),
            'baby_kick_tracker_settings',
            'baby_kick_tracker_main_settings'
        );
        
        add_settings_field(
            'kicks_threshold',
            'Kicks Threshold (2 hours)',
            array($this, 'kicks_threshold_callback'),
            'baby_kick_tracker_settings',
            'baby_kick_tracker_main_settings'
        );
    }
    
    // Settings section callback
    public function settings_section_callback() {
        echo '<p>Configure the Baby Kick Tracker settings below:</p>';
    }
    
    // Mother email field callback
    public function mother_email_callback() {
        $options = get_option('baby_kick_tracker_options');
        echo '<input type="email" id="mother_email" name="baby_kick_tracker_options[mother_email]" value="' . esc_attr($options['mother_email']) . '" class="regular-text" />';
        echo '<p class="description">Enter the mother\'s email address for notifications.</p>';
    }
    
    // Admin email field callback
    public function admin_email_callback() {
        $options = get_option('baby_kick_tracker_options');
        echo '<input type="email" id="admin_email" name="baby_kick_tracker_options[admin_email]" value="' . esc_attr($options['admin_email']) . '" class="regular-text" />';
        echo '<p class="description">Enter the admin email address for notifications.</p>';
    }
    
    // Kicks threshold field callback
    public function kicks_threshold_callback() {
        $options = get_option('baby_kick_tracker_options');
        echo '<input type="number" id="kicks_threshold" name="baby_kick_tracker_options[kicks_threshold]" value="' . esc_attr($options['kicks_threshold']) . '" class="small-text" min="1" />';
        echo '<p class="description">Minimum number of kicks expected in a 2-hour period (default: 10).</p>';
    }
    
    // Render admin dashboard page
    public function render_admin_page() {
        global $wpdb;
        
        // Get the latest session data
        $table_sessions = $wpdb->prefix . 'baby_kick_sessions';
        $table_kicks = $wpdb->prefix . 'baby_kick_records';
        
        $latest_session = $wpdb->get_row("
            SELECT * FROM $table_sessions 
            ORDER BY start_time DESC 
            LIMIT 1
        ");
        
        // Get kicks data for the last 7 days
        $last_week_data = $wpdb->get_results("
            SELECT 
                DATE(s.start_time) as date,
                SUM(s.total_kicks) as total_kicks,
                COUNT(s.id) as session_count
            FROM $table_sessions s
            WHERE s.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(s.start_time)
            ORDER BY DATE(s.start_time) ASC
        ");
        
        // Get total stats
        $total_sessions = $wpdb->get_var("SELECT COUNT(*) FROM $table_sessions");
        $total_kicks = $wpdb->get_var("SELECT SUM(total_kicks) FROM $table_sessions");
        $avg_kicks_per_session = $total_sessions > 0 ? round($total_kicks / $total_sessions, 1) : 0;
        
        // Prepare data for charts
        $chart_labels = array();
        $chart_data = array();
        
        foreach ($last_week_data as $day_data) {
            $chart_labels[] = date('M d', strtotime($day_data->date));
            $chart_data[] = (int) $day_data->total_kicks;
        }
        
        // Include the dashboard template
        include(plugin_dir_path(__FILE__) . 'templates/admin-dashboard.php');
    }
    
    // Render settings page
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('baby_kick_tracker_settings');
                do_settings_sections('baby_kick_tracker_settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }
    
    // Render reports page
    public function render_reports_page() {
        global $wpdb;
        
        // Get date range from GET parameters or use defaults
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
        
        // Get session data for the date range
        $table_sessions = $wpdb->prefix . 'baby_kick_sessions';
        $table_kicks = $wpdb->prefix . 'baby_kick_records';
        $table_notifications = $wpdb->prefix . 'baby_kick_notifications';
        
        $sessions = $wpdb->get_results($wpdb->prepare("
            SELECT 
                s.*,
                (SELECT COUNT(*) FROM $table_notifications n WHERE n.session_id = s.id) as notification_count
            FROM $table_sessions s
            WHERE DATE(s.start_time) BETWEEN %s AND %s
            ORDER BY s.start_time DESC
        ", $start_date, $end_date));
        
        // Include the reports template
        include(plugin_dir_path(__FILE__) . 'templates/admin-reports.php');
    }
    
    // Frontend form to track kicks
    public function render_kick_tracker_form() {
        global $wpdb;
        
        // Check if there's an active session
        $table_sessions = $wpdb->prefix . 'baby_kick_sessions';
        $active_session = $wpdb->get_row("
            SELECT * FROM $table_sessions 
            WHERE status = 'in_progress' 
            ORDER BY start_time DESC 
            LIMIT 1
        ");
        
        // Get the plugin options
        $options = get_option('baby_kick_tracker_options');
        $kicks_threshold = isset($options['kicks_threshold']) ? (int) $options['kicks_threshold'] : 10;
        
        // Start output buffering
        ob_start();
        
        // Include the form template
        include(plugin_dir_path(__FILE__) . 'templates/kick-tracker-form.php');
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    // Ajax handler to record a kick
    public function record_baby_kick() {
        global $wpdb;
        
        check_ajax_referer('baby_kick_tracker_nonce', 'security');
        
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
        $kick_count = isset($_POST['kick_count']) ? intval($_POST['kick_count']) : 1;
        $start_new_session = isset($_POST['start_new_session']) ? (bool) $_POST['start_new_session'] : false;
        
        $table_sessions = $wpdb->prefix . 'baby_kick_sessions';
        $table_kicks = $wpdb->prefix . 'baby_kick_records';
        
        // If starting a new session
        if ($start_new_session) {
            // Close any existing active sessions
            $wpdb->update(
                $table_sessions,
                array(
                    'status' => 'completed',
                    'end_time' => current_time('mysql')
                ),
                array('status' => 'in_progress')
            );
            
            // Create a new session
            $wpdb->insert(
                $table_sessions,
                array(
                    'start_time' => current_time('mysql'),
                    'total_kicks' => $kick_count,
                    'status' => 'in_progress'
                )
            );
            
            $session_id = $wpdb->insert_id;
            
            // Record the kick
            $wpdb->insert(
                $table_kicks,
                array(
                    'session_id' => $session_id,
                    'kick_time' => current_time('mysql'),
                    'kick_count' => $kick_count
                )
            );
            
            // Schedule the one-hour reminder
            wp_schedule_single_event(time() + 3600, 'baby_kick_tracker_hourly_check');
            
            // Schedule the two-hour completion
            wp_schedule_single_event(time() + 7200, 'baby_kick_tracker_session_completion', array($session_id));
            
            $response = array(
                'success' => true,
                'message' => 'New session started and kick recorded successfully!',
                'session_id' => $session_id,
                'start_time' => current_time('mysql'),
                'total_kicks' => $kick_count
            );
        } 
        // Add to existing session
        elseif ($session_id > 0) {
            // Get the current session
            $session = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $table_sessions WHERE id = %d
            ", $session_id));
            
            if ($session) {
                // Record the kick
                $wpdb->insert(
                    $table_kicks,
                    array(
                        'session_id' => $session_id,
                        'kick_time' => current_time('mysql'),
                        'kick_count' => $kick_count
                    )
                );
                
                // Update the session total
                $new_total = $session->total_kicks + $kick_count;
                $wpdb->update(
                    $table_sessions,
                    array('total_kicks' => $new_total),
                    array('id' => $session_id)
                );
                
                $response = array(
                    'success' => true,
                    'message' => 'Kick recorded successfully!',
                    'session_id' => $session_id,
                    'total_kicks' => $new_total
                );
            } else {
                $response = array(
                    'success' => false,
                    'message' => 'Session not found!'
                );
            }
        } else {
            $response = array(
                'success' => false,
                'message' => 'Invalid session ID!'
            );
        }
        
        wp_send_json($response);
        wp_die();
    }
    
    // Check kicks after one hour
    public function hourly_check_kicks() {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'baby_kick_sessions';
        $table_kicks = $wpdb->prefix . 'baby_kick_records';
        $table_notifications = $wpdb->prefix . 'baby_kick_notifications';
        
        // Get active sessions that started more than 1 hour ago
        $active_sessions = $wpdb->get_results("
            SELECT * FROM $table_sessions 
            WHERE status = 'in_progress' 
            AND start_time <= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND start_time > DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ");
        
        if (!$active_sessions) {
            return;
        }
        
        $options = get_option('baby_kick_tracker_options');
        $admin_email = isset($options['admin_email']) ? $options['admin_email'] : get_option('admin_email');
        $mother_email = isset($options['mother_email']) ? $options['mother_email'] : '';
        $kicks_threshold = isset($options['kicks_threshold']) ? (int) $options['kicks_threshold'] : 10;
        
        foreach ($active_sessions as $session) {
            // Check if we already sent a reminder for this session
            $reminder_sent = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM $table_notifications 
                WHERE session_id = %d AND notification_type = 'one_hour_reminder'
            ", $session->id));
            
            if ($reminder_sent > 0) {
                continue;
            }
            
            // Calculate remaining kicks needed
            $remaining_kicks = $kicks_threshold - $session->total_kicks;
            $time_elapsed = round((time() - strtotime($session->start_time)) / 60);
            $time_remaining = 120 - $time_elapsed;
            
            // Create the email content
            $subject = 'Baby Kick Tracker - One Hour Reminder';
            $message = "Hello,\n\n";
            $message .= "This is a reminder from your Baby Kick Tracker.\n\n";
            $message .= "Session started at: " . date('F j, Y, g:i a', strtotime($session->start_time)) . "\n";
            $message .= "Time elapsed: " . $time_elapsed . " minutes\n";
            $message .= "Time remaining: " . $time_remaining . " minutes\n";
            $message .= "Kicks recorded so far: " . $session->total_kicks . "\n";
            
            if ($remaining_kicks > 0) {
                $message .= "Kicks needed to reach threshold (" . $kicks_threshold . "): " . $remaining_kicks . "\n\n";
                $message .= "Have you felt any more kicks that you haven't recorded yet?\n";
            } else {
                $message .= "Great job! You've already reached the threshold of " . $kicks_threshold . " kicks.\n";
            }
            
            $message .= "\nPlease continue to monitor and record baby kicks for the remaining hour.\n\n";
            $message .= "Thank you,\nBaby Kick Tracker";
            
            // Send emails
            $this->send_notification_email($admin_email, $subject, $message);
            
            if (!empty($mother_email) && $mother_email != $admin_email) {
                $this->send_notification_email($mother_email, $subject, $message);
            }
            
            // Log the notification
            $wpdb->insert(
                $table_notifications,
                array(
                    'session_id' => $session->id,
                    'notification_type' => 'one_hour_reminder',
                    'recipient' => $mother_email . ($mother_email != $admin_email ? ', ' . $admin_email : ''),
                    'message' => $message
                )
            );
        }
    }
    
    // Complete a session after two hours
    public function session_completion($session_id) {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'baby_kick_sessions';
        $table_kicks = $wpdb->prefix . 'baby_kick_records';
        $table_notifications = $wpdb->prefix . 'baby_kick_notifications';
        
        // Get the session
        $session = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_sessions WHERE id = %d
        ", $session_id));
        
        if (!$session || $session->status != 'in_progress') {
            return;
        }
        
        // Update the session status
        $wpdb->update(
            $table_sessions,
            array(
                'status' => 'completed',
                'end_time' => current_time('mysql')
            ),
            array('id' => $session_id)
        );
        
        // Get the options
        $options = get_option('baby_kick_tracker_options');
        $admin_email = isset($options['admin_email']) ? $options['admin_email'] : get_option('admin_email');
        $mother_email = isset($options['mother_email']) ? $options['mother_email'] : '';
        $kicks_threshold = isset($options['kicks_threshold']) ? (int) $options['kicks_threshold'] : 10;
        
        // Determine the status based on kick count
        $status = $session->total_kicks >= $kicks_threshold ? 'good' : 'below_threshold';
        
        // Create the email content
        $subject = 'Baby Kick Tracker - Session Completed';
        $message = "Hello,\n\n";
        $message .= "Your baby kick tracking session has been completed.\n\n";
        $message .= "Session details:\n";
        $message .= "Started at: " . date('F j, Y, g:i a', strtotime($session->start_time)) . "\n";
        $message .= "Ended at: " . date('F j, Y, g:i a', strtotime(current_time('mysql'))) . "\n";
        $message .= "Total kicks recorded: " . $session->total_kicks . "\n";
        $message .= "Target threshold: " . $kicks_threshold . " kicks\n\n";
        
        if ($status == 'good') {
            $message .= "Status: GOOD - The number of kicks is at or above the recommended threshold.\n";
        } else {
            $message .= "Status: BELOW THRESHOLD - The number of kicks is below the recommended threshold.\n";
            $message .= "Please consult with your healthcare provider if you notice a significant decrease in fetal movement.\n";
        }
        
        $message .= "\nThank you for using the Baby Kick Tracker.\n\n";
        $message .= "Best regards,\nBaby Kick Tracker";
        
        // Send emails
        $this->send_notification_email($admin_email, $subject, $message);
        
        if (!empty($mother_email) && $mother_email != $admin_email) {
            $this->send_notification_email($mother_email, $subject, $message);
        }
        
        // Log the notification
        $wpdb->insert(
            $table_notifications,
            array(
                'session_id' => $session_id,
                'notification_type' => 'session_completion',
                'recipient' => $mother_email . ($mother_email != $admin_email ? ', ' . $admin_email : ''),
                'message' => $message
            )
        );
    }
    
    // Send daily summary
    public function send_daily_summary() {
        global $wpdb;
        
        $table_sessions = $wpdb->prefix . 'baby_kick_sessions';
        
        // Get sessions from the last 24 hours
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $today = current_time('mysql');
        
        $sessions = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_sessions 
            WHERE start_time BETWEEN %s AND %s
            ORDER BY start_time ASC
        ", $yesterday, $today));
        
        if (empty($sessions)) {
            return;
        }
        
        // Get the options
        $options = get_option('baby_kick_tracker_options');
        $admin_email = isset($options['admin_email']) ? $options['admin_email'] : get_option('admin_email');
        $mother_email = isset($options['mother_email']) ? $options['mother_email'] : '';
        $kicks_threshold = isset($options['kicks_threshold']) ? (int) $options['kicks_threshold'] : 10;
        
        // Create the summary
        $total_kicks = 0;
        $total_sessions = count($sessions);
        $sessions_below_threshold = 0;
        
        $session_details = '';
        foreach ($sessions as $session) {
            $total_kicks += $session->total_kicks;
            
            if ($session->total_kicks < $kicks_threshold) {
                $sessions_below_threshold++;
            }
            
            $start_time = date('F j, Y, g:i a', strtotime($session->start_time));
            $end_time = !empty($session->end_time) ? date('g:i a', strtotime($session->end_time)) : 'In Progress';
            $status = $session->total_kicks >= $kicks_threshold ? 'GOOD' : 'BELOW THRESHOLD';
            
            $session_details .= "- Session at $start_time to $end_time: {$session->total_kicks} kicks ($status)\n";
        }
        
        $avg_kicks = $total_sessions > 0 ? round($total_kicks / $total_sessions, 1) : 0;
        
        // Create the email content
        $subject = 'Baby Kick Tracker - Daily Summary';
        $message = "Hello,\n\n";
        $message .= "Here is your daily summary of baby kick tracking sessions for the last 24 hours.\n\n";
        $message .= "Summary:\n";
        $message .= "Period: " . date('F j, Y, g:i a', strtotime($yesterday)) . " to " . date('F j, Y, g:i a', strtotime($today)) . "\n";
        $message .= "Total sessions: $total_sessions\n";
        $message .= "Total kicks recorded: $total_kicks\n";
        $message .= "Average kicks per session: $avg_kicks\n";
        $message .= "Sessions below threshold: $sessions_below_threshold\n\n";
        
        $message .= "Session Details:\n";
        $message .= $session_details . "\n";
        
        if ($sessions_below_threshold > 0) {
            $message .= "Note: Some sessions were below the recommended threshold of $kicks_threshold kicks.\n";
            $message .= "Please consult with your healthcare provider if you notice a significant decrease in fetal movement.\n\n";
        } else {
            $message .= "Great job! All sessions met or exceeded the recommended threshold of $kicks_threshold kicks.\n\n";
        }
        
        $message .= "Thank you for using the Baby Kick Tracker.\n\n";
        $message .= "Best regards,\nBaby Kick Tracker";
        
        // Send emails
        $this->send_notification_email($admin_email, $subject, $message);
        
        if (!empty($mother_email) && $mother_email != $admin_email) {
            $this->send_notification_email($mother_email, $subject, $message);
        }
        
        // Log the notification
        $table_notifications = $wpdb->prefix . 'baby_kick_notifications';
        $wpdb->insert(
            $table_notifications,
            array(
                'session_id' => 0,
                'notification_type' => 'daily_summary',
                'recipient' => $mother_email . ($mother_email != $admin_email ? ', ' . $admin_email : ''),
                'message' => $message
            )
        );
    }
    
    // Helper function to send emails
    private function send_notification_email($recipient, $subject, $message) {
        $headers = 'From: Baby Kick Tracker <' . get_option('admin_email') . '>' . "\r\n";
        
        return wp_mail($recipient, $subject, $message, $headers);
    }
    
    // Set up cron events
    public function setup_cron_events() {
        if (!wp_next_scheduled('baby_kick_tracker_hourly_check')) {
            wp_schedule_event(time(), 'hourly', 'baby_kick_tracker_hourly_check');
        }
        
        if (!wp_next_scheduled('baby_kick_tracker_daily_summary')) {
            wp_schedule_event(time(), 'daily', 'baby_kick_tracker_daily_summary');
        }
        
        // Register the session completion hook
        add_action('baby_kick_tracker_session_completion', array($this, 'session_completion'));
    }
    
    // Enqueue frontend scripts and styles
    public function enqueue_frontend_scripts() {
        wp_enqueue_style(
            'baby-kick-tracker-style',
            plugin_dir_url(__FILE__) . 'assets/css/baby-kick-tracker.css',
            array(),
            '2.0.0'
        );
        
        wp_enqueue_script(
            'baby-kick-tracker-script',
            plugin_dir_url(__FILE__) . 'assets/js/baby-kick-tracker.js',
            array('jquery'),
            '2.0.0',
            true
        );
        
        wp_localize_script(
            'baby-kick-tracker-script',
            'babyKickTracker',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('baby_kick_tracker_nonce'),
                'kicks_threshold' => get_option('baby_kick_tracker_options')['kicks_threshold'] ?? 10
            )
        );
    }
    
    // Enqueue admin scripts and styles
    public function enqueue_admin_scripts($hook) {
        $plugin_screens = array(
            'toplevel_page_baby-kick-tracker',
            'baby-kick-tracker_page_baby-kick-tracker-settings',
            'baby-kick-tracker_page_baby-kick-tracker-reports'
        );
        
        if (!in_array($hook, $plugin_screens)) {
            return;
        }
        
        wp_enqueue_style(
            'baby-kick-tracker-admin-style',
            plugin_dir_url(__FILE__) . 'assets/css/baby-kick-tracker-admin.css',
            array(),
            '1.0.0'
        );
        
        // Enqueue Chart.js for the admin dashboard
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.7.0',
            true
        );
        
        wp_enqueue_script(
            'baby-kick-tracker-admin-script',
            plugin_dir_url(__FILE__) . 'assets/js/baby-kick-tracker-admin.js',
            array('jquery', 'chartjs'),
            '1.0.0',
            true
        );
    }
}

// Initialize the plugin
$baby_kick_tracker = new Baby_Kick_Tracker();
