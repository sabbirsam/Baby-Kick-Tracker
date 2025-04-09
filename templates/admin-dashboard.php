<?php
/**
 * Baby Kick Tracker Admin Dashboard Template
 */
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="baby-kick-admin-container">
        <!-- Stats Overview -->
        <div class="baby-kick-admin-card">
            <div class="baby-kick-admin-card-header">
                <h2>Kick Tracking Statistics</h2>
            </div>
            
            <div class="baby-kick-admin-stats">
                <div class="baby-kick-admin-stat-card">
                    <h3 class="baby-kick-admin-stat-title">Total Sessions</h3>
                    <div class="baby-kick-admin-stat-value"><?php echo $total_sessions; ?></div>
                </div>
                
                <div class="baby-kick-admin-stat-card">
                    <h3 class="baby-kick-admin-stat-title">Total Kicks Recorded</h3>
                    <div class="baby-kick-admin-stat-value"><?php echo $total_kicks; ?></div>
                </div>
                
                <div class="baby-kick-admin-stat-card">
                    <h3 class="baby-kick-admin-stat-title">Avg. Kicks per Session</h3>
                    <div class="baby-kick-admin-stat-value"><?php echo $avg_kicks_per_session; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Latest Session -->
        <div class="baby-kick-admin-card">
            <div class="baby-kick-admin-card-header">
                <h2>Latest Session</h2>
            </div>
            
            <?php if ($latest_session): ?>
                <table class="widefat">
                    <tr>
                        <th>Start Time</th>
                        <td><?php echo date('F j, Y, g:i a', strtotime($latest_session->start_time)); ?></td>
                    </tr>
                    <tr>
                        <th>End Time</th>
                        <td>
                            <?php 
                            if (!empty($latest_session->end_time)) {
                                echo date('F j, Y, g:i a', strtotime($latest_session->end_time));
                            } else {
                                echo 'In Progress';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Total Kicks</th>
                        <td><?php echo $latest_session->total_kicks; ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <?php 
                            $options = get_option('baby_kick_tracker_options');
                            $threshold = isset($options['kicks_threshold']) ? (int) $options['kicks_threshold'] : 10;
                            
                            if ($latest_session->total_kicks >= $threshold) {
                                echo '<span style="color:#4CAF50;font-weight:bold;">GOOD</span>';
                            } else {
                                echo '<span style="color:#FF9800;font-weight:bold;">BELOW THRESHOLD</span>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            <?php else: ?>
                <p>No sessions recorded yet.</p>
            <?php endif; ?>
        </div>
        
        <!-- Weekly Chart -->
        <div class="baby-kick-admin-card">
            <div class="baby-kick-admin-card-header">
                <h2>Kicks by Day (Last 7 Days)</h2>
            </div>
            
            <?php if (!empty($last_week_data)): ?>
                <div class="baby-kick-admin-chart-container">
                    <canvas id="baby-kick-chart-weekly"></canvas>
                </div>
                <script id="chart-labels" type="application/json"><?php echo json_encode($chart_labels); ?></script>
                <script id="chart-data" type="application/json"><?php echo json_encode($chart_data); ?></script>
            <?php else: ?>
                <p>No data available for the last 7 days.</p>
            <?php endif; ?>
        </div>
    </div>
</div>