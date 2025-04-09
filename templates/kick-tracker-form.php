<?php
/**
 * Baby Kick Tracker Form Template
 */
?>
<div class="baby-kick-tracker-container">
    <div class="baby-kick-tracker-header">
        <h2>Baby Kick Tracker</h2>
        <p>Track your baby's movements during pregnancy</p>
    </div>
    
    <div class="baby-kick-tracker-form">
        <form id="baby-kick-tracker-form">
            <?php if (!$active_session): ?>
                <div class="baby-kick-tracker-field">
                    <label for="baby-kick-start-time">Start a new kick counting session:</label>
                    <p>Current time: <?php echo date('g:i a'); ?></p>
                </div>
            <?php else: ?>
                <div class="baby-kick-tracker-field">
                    <label>Current Session:</label>
                    <p>Started at: <span id="baby-kick-start-time"><?php echo date('g:i a', strtotime($active_session->start_time)); ?></span></p>
                </div>
            <?php endif; ?>
            
            <div class="baby-kick-tracker-field">
                <label for="baby-kick-count">Number of Kicks:</label>
                <input type="number" id="baby-kick-count" name="kick_count" min="1" value="1">
                <input type="hidden" id="baby-kick-session-id" value="<?php echo $active_session ? $active_session->id : ''; ?>">
            </div>
            
            <div class="baby-kick-tracker-timer" id="baby-kick-tracker-timer">
                <?php if ($active_session): ?>
                    <?php 
                    $time_diff = time() - strtotime($active_session->start_time);
                    $hours = floor($time_diff / 3600);
                    $minutes = floor(($time_diff % 3600) / 60);
                    $seconds = $time_diff % 60;
                    $timer_text = 'Session time: ';
                    if ($hours > 0) $timer_text .= $hours . 'h ';
                    $timer_text .= $minutes . 'm ' . $seconds . 's';
                    
                    if ($time_diff >= 7200) {
                        $timer_text = 'Session completed (2 hours)';
                    }
                    
                    echo $timer_text;
                    ?>
                <?php else: ?>
                    Session time: 0m 0s
                <?php endif; ?>
            </div>
            
            <div class="baby-kick-tracker-buttons">
                <button type="button" id="baby-kick-tracker-start" class="baby-kick-tracker-button start">
                    <?php echo $active_session ? 'Active Session In Progress' : 'Start New Session'; ?>
                </button>
                
                <button type="button" id="baby-kick-tracker-add-kick" class="baby-kick-tracker-button add" <?php echo !$active_session ? 'disabled' : ''; ?>>
                    Add Kicks
                </button>
            </div>
        </form>
        
        <div class="baby-kick-tracker-status <?php echo $active_session && $active_session->total_kicks >= $kicks_threshold ? 'good' : 'warning'; ?>" id="baby-kick-tracker-status" <?php echo !$active_session ? 'style="display:none;"' : ''; ?>>
            <?php if ($active_session): ?>
                <?php if ($active_session->total_kicks >= $kicks_threshold): ?>
                    <strong>Good!</strong> You've reached the recommended number of kicks.
                <?php else: ?>
                    <strong>In Progress:</strong> <span id="baby-kick-total-kicks"><?php echo $active_session->total_kicks; ?></span> kicks recorded. 
                    Goal is <span id="baby-kick-threshold"><?php echo $kicks_threshold; ?></span> kicks within 2 hours.
                <?php endif; ?>
            <?php else: ?>
                <span id="baby-kick-total-kicks">0</span> kicks recorded. 
                Goal is <span id="baby-kick-threshold"><?php echo $kicks_threshold; ?></span> kicks within 2 hours.
            <?php endif; ?>
        </div>
        
        <div class="baby-kick-tracker-summary">
            <h3>About Kick Counting</h3>
            <p>Kick counting is a way to monitor your baby's health during pregnancy. A healthy baby should have at least 10 movements in a 2-hour period.</p>
            <p>If you notice a significant decrease in your baby's movements, contact your healthcare provider right away.</p>
        </div>
    </div>
</div>