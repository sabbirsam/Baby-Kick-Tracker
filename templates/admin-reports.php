<?php
/**
 * Baby Kick Tracker Admin Reports Template
 */
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="baby-kick-admin-container">
        <!-- Date Filter -->
        <div class="baby-kick-admin-card">
            <div class="baby-kick-admin-card-header">
                <h2>Filter by Date</h2>
            </div>
            
            <form id="baby-kick-date-filter" method="get">
                <input type="hidden" name="page" value="baby-kick-tracker-reports">
                
                <div style="display: flex; gap: 10px; align-items: center;">
                    <div>
                        <label for="start-date">Start Date:</label>
                        <input type="date" id="start-date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                    </div>
                    
                    <div>
                        <label for="end-date">End Date:</label>
                        <input type="date" id="end-date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                    </div>
                    
                    <div>
                        <button type="submit" class="button button-primary">Apply Filter</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Sessions Table -->
        <div class="baby-kick-admin-card">
            <div class="baby-kick-admin-card-header">
                <h2>Session Reports</h2>
            </div>
            
            <?php if (!empty($sessions)): ?>
                <table class="widefat baby-kick-admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Total Kicks</th>
                            <th>Status</th>
                            <th>Notifications</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $options = get_option('baby_kick_tracker_options');
                        $threshold = isset($options['kicks_threshold']) ? (int) $options['kicks_threshold'] : 10;
                        
                        foreach ($sessions as $session): 
                        ?>
                            <tr>
                                <td><?php echo $session->id; ?></td>
                                <td><?php echo date('F j, Y, g:i a', strtotime($session->start_time)); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($session->end_time)) {
                                        echo date('F j, Y, g:i a', strtotime($session->end_time));
                                    } else {
                                        echo 'In Progress';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $session->total_kicks; ?></td>
                                <td>
                                    <?php 
                                    if ($session->status == 'in_progress') {
                                        echo '<span style="color:#2196F3;font-weight:bold;">IN PROGRESS</span>';
                                    } else {
                                        if ($session->total_kicks >= $threshold) {
                                            echo '<span style="color:#4CAF50;font-weight:bold;">GOOD</span>';
                                        } else {
                                            echo '<span style="color:#FF9800;font-weight:bold;">BELOW THRESHOLD</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?php echo $session->notification_count; ?></td>
                                <td>
                                    <a href="?page=baby-kick-tracker-reports&action=view&session_id=<?php echo $session->id; ?>" class="button button-small">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No sessions found for the selected date range.</p>
            <?php endif; ?>
        </div>
        
        <?php
        // If viewing a specific session
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['session_id'])) {
            $session_id = intval($_GET['session_id']);
            $session = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $table_sessions WHERE id = %d
            ", $session_id));
            
            if ($session) {
                $kicks = $wpdb->get_results($wpdb->prepare("
                    SELECT * FROM $table_kicks WHERE session_id = %d ORDER BY kick_time ASC
                ", $session_id));
                
                $notifications = $wpdb->get_results($wpdb->prepare("
                    SELECT * FROM $table_notifications WHERE session_id = %d ORDER BY sent_at DESC
                ", $session_id));
                ?>
                
                <div class="baby-kick-admin-card">
                    <div class="baby-kick-admin-card-header">
                        <h2>Session Details - #<?php echo $session_id; ?></h2>
                    </div>
                    
                    <table class="widefat">
                        <tr>
                            <th>Start Time</th>
                            <td><?php echo date('F j, Y, g:i a', strtotime($session->start_time)); ?></td>
                        </tr>
                        <tr>
                            <th>End Time</th>
                            <td>
                                <?php 
                                if (!empty($session->end_time)) {
                                    echo date('F j, Y, g:i a', strtotime($session->end_time));
                                } else {
                                    echo 'In Progress';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Kicks</th>
                            <td><?php echo $session->total_kicks; ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <?php 
                                if ($session->status == 'in_progress') {
                                    echo '<span style="color:#2196F3;font-weight:bold;">IN PROGRESS</span>';
                                } else {
                                    if ($session->total_kicks >= $threshold) {
                                        echo '<span style="color:#4CAF50;font-weight:bold;">GOOD</span>';
                                    } else {
                                        echo '<span style="color:#FF9800;font-weight:bold;">BELOW THRESHOLD</span>';
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Kicks Table -->
                <div class="baby-kick-admin-card">
                    <div class="baby-kick-admin-card-header">
                        <h2>Recorded Kicks</h2>
                    </div>
                    
                    <?php if (!empty($kicks)): ?>
                        <table class="widefat baby-kick-admin-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Kick Count</th>
                                    <th>Time from Start</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kicks as $kick): ?>
                                    <tr>
                                        <td><?php echo date('g:i:s a', strtotime($kick->kick_time)); ?></td>
                                        <td><?php echo $kick->kick_count; ?></td>
                                        <td>
                                            <?php 
                                            $time_diff = strtotime($kick->kick_time) - strtotime($session->start_time);
                                            $minutes = floor($time_diff / 60);
                                            $seconds = $time_diff % 60;
                                            echo $minutes . 'm ' . $seconds . 's';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No kicks recorded for this session.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Notifications Table -->
                <div class="baby-kick-admin-card">
                    <div class="baby-kick-admin-card-header">
                        <h2>Notifications</h2>
                    </div>
                    
                    <?php if (!empty($notifications)): ?>
                        <table class="widefat baby-kick-admin-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Sent At</th>
                                    <th>Recipient</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            switch ($notification->notification_type) {
                                                case 'one_hour_reminder':
                                                    echo 'One Hour Reminder';
                                                    break;
                                                case 'session_completion':
                                                    echo 'Session Completion';
                                                    break;
                                                case 'daily_summary':
                                                    echo 'Daily Summary';
                                                    break;
                                                default:
                                                    echo ucfirst(str_replace('_', ' ', $notification->notification_type));
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo date('F j, Y, g:i a', strtotime($notification->sent_at)); ?></td>
                                        <td><?php echo esc_html($notification->recipient); ?></td>
                                        <td><?php echo ucfirst($notification->status); ?></td>
                                        <td>
                                            <button class="button button-small view-notification" data-message="<?php echo esc_attr($notification->message); ?>">View Message</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Message Modal -->
                        <div id="notification-message-modal" style="display:none;">
                            <div id="notification-message-content" style="white-space: pre-wrap;"></div>
                        </div>
                        <script>
                            jQuery(document).ready(function($) {
                                $('.view-notification').on('click', function() {
                                    var message = $(this).data('message');
                                    $('#notification-message-content').text(message);
                                    
                                    $('#notification-message-modal').dialog({
                                        title: 'Notification Message',
                                        width: 600,
                                        modal: true,
                                        buttons: {
                                            Close: function() {
                                                $(this).dialog('close');
                                            }
                                        }
                                    });
                                });
                            });
                        </script>
                    <?php else: ?>
                        <p>No notifications sent for this session.</p>
                    <?php endif; ?>
                </div>
                
            <?php
            } else {
                echo '<div class="notice notice-error"><p>Session not found.</p></div>';
            }
        }
        ?>
        
    </div>
</div>