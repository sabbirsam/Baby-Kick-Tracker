/**
 * Baby Kick Tracker Frontend JavaScript
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        const form = $('#baby-kick-tracker-form');
        const startBtn = $('#baby-kick-tracker-start');
        const addKickBtn = $('#baby-kick-tracker-add-kick');
        const statusDiv = $('#baby-kick-tracker-status');
        const timerDiv = $('#baby-kick-tracker-timer');
        const kickCountInput = $('#baby-kick-count');
        const sessionIdInput = $('#baby-kick-session-id');
        const totalKicksSpan = $('#baby-kick-total-kicks');
        const sessionStartTimeSpan = $('#baby-kick-start-time');
        const sessionThresholdSpan = $('#baby-kick-threshold');
        
        let timer;
        let sessionStartTime;
        let sessionDuration = 0;
        let kicksThreshold = babyKickTracker.kicks_threshold;
        
        // Initialize the form
        function initForm() {
            // Display the threshold in the form
            sessionThresholdSpan.text(kicksThreshold);
            
            // Check if there's an active session
            if (sessionIdInput.val()) {
                showActiveSession();
            }
        }
        
        // Start a new kick tracking session
        startBtn.on('click', function(e) {
            e.preventDefault();
            
            const kickCount = parseInt(kickCountInput.val()) || 0;
            
            if (kickCount <= 0) {
                alert('Please enter a valid number of kicks.');
                return;
            }
            
            startSession(kickCount);
        });
        
        // Add kicks to the current session
        addKickBtn.on('click', function(e) {
            e.preventDefault();
            
            const kickCount = parseInt(kickCountInput.val()) || 0;
            const sessionId = sessionIdInput.val();
            
            if (kickCount <= 0) {
                alert('Please enter a valid number of kicks.');
                return;
            }
            
            if (!sessionId) {
                alert('No active session. Please start a new session first.');
                return;
            }
            
            addKicksToSession(sessionId, kickCount);
        });
        
        // Ajax function to start a new session
        function startSession(kickCount) {
            $.ajax({
                type: 'POST',
                url: babyKickTracker.ajaxurl,
                data: {
                    action: 'record_baby_kick',
                    security: babyKickTracker.nonce,
                    kick_count: kickCount,
                    start_new_session: true
                },
                beforeSend: function() {
                    startBtn.prop('disabled', true).text('Starting...');
                },
                success: function(response) {
                    if (response.success) {
                        sessionIdInput.val(response.session_id);
                        totalKicksSpan.text(response.total_kicks);
                        sessionStartTime = new Date(response.start_time);
                        sessionStartTimeSpan.text(formatTime(sessionStartTime));
                        showActiveSession();
                        
                        // Reset the kick count input
                        kickCountInput.val('');
                        startBtn.text('Session Started!').prop('disabled', true);
                        setTimeout(function() {
                            startBtn.text('Start New Session').prop('disabled', false);
                        }, 3000);
                        
                        // Start the timer
                        startTimer();
                    } else {
                        alert(response.message || 'An error occurred while starting the session.');
                        startBtn.prop('disabled', false).text('Start New Session');
                    }
                },
                error: function() {
                    alert('An error occurred while connecting to the server.');
                    startBtn.prop('disabled', false).text('Start New Session');
                }
            });
        }
        
        // Ajax function to add kicks to an existing session
        function addKicksToSession(sessionId, kickCount) {
            $.ajax({
                type: 'POST',
                url: babyKickTracker.ajaxurl,
                data: {
                    action: 'record_baby_kick',
                    security: babyKickTracker.nonce,
                    session_id: sessionId,
                    kick_count: kickCount
                },
                beforeSend: function() {
                    addKickBtn.prop('disabled', true).text('Adding...');
                },
                success: function(response) {
                    if (response.success) {
                        totalKicksSpan.text(response.total_kicks);
                        kickCountInput.val('');
                        addKickBtn.text('Kicks Added!').prop('disabled', false);
                        
                        // Update status based on kick count
                        updateStatus(response.total_kicks);
                        
                        setTimeout(function() {
                            addKickBtn.text('Add Kicks').prop('disabled', false);
                        }, 2000);
                    } else {
                        alert(response.message || 'An error occurred while adding kicks.');
                        addKickBtn.prop('disabled', false).text('Add Kicks');
                    }
                },
                error: function() {
                    alert('An error occurred while connecting to the server.');
                    addKickBtn.prop('disabled', false).text('Add Kicks');
                }
            });
        }
        
        // Show the active session UI
        function showActiveSession() {
            statusDiv.show();
            startBtn.text('Active Session In Progress');
            addKickBtn.prop('disabled', false);
        }
        
        // Start the session timer
        function startTimer() {
            clearInterval(timer);
            updateTimer();
            
            timer = setInterval(function() {
                updateTimer();
            }, 1000);
        }
        
        // Update the timer display
        function updateTimer() {
            if (!sessionStartTime) return;
            
            const now = new Date();
            const diff = Math.floor((now - sessionStartTime) / 1000);
            const hours = Math.floor(diff / 3600);
            const minutes = Math.floor((diff % 3600) / 60);
            const seconds = diff % 60;
            
            sessionDuration = diff;
            
            let timerText = 'Session time: ';
            if (hours > 0) {
                timerText += hours + 'h ';
            }
            timerText += minutes + 'm ' + seconds + 's';
            
            timerDiv.text(timerText);
            
            // If session reaches 2 hours (7200 seconds), show completion message
            /* if (diff >= 7200 && timer) {
                clearInterval(timer);
                timerDiv.text('Session completed (2 hours)');
            } */

            const assessmentPeriodSeconds = babyKickTracker.assessment_period_hours * 3600;
            if (diff >= assessmentPeriodSeconds && timer) {
                clearInterval(timer);
                timerDiv.text('Session completed (' + babyKickTracker.assessment_period_hours + ' hours)');
            }
        }
        
        // Update the status display based on kick count
        function updateStatus(totalKicks) {
            statusDiv.removeClass('good warning');
            
            if (totalKicks >= kicksThreshold) {
                statusDiv.addClass('good');
                statusDiv.html('<strong>Good!</strong> You\'ve reached the recommended number of kicks.');
            } else {
                statusDiv.addClass('warning');
                statusDiv.html('<strong>In Progress:</strong> ' + totalKicks + ' kicks recorded. ' + 
                                'Goal is ' + kicksThreshold + ' kicks within ' + 
                                babyKickTracker.assessment_period_hours + ' hours.');
            }
        }
        
        
        // Format time from date object
        function formatTime(date) {
            const hours = date.getHours();
            const minutes = date.getMinutes();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const formattedHours = hours % 12 || 12;
            const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
            
            return formattedHours + ':' + formattedMinutes + ' ' + ampm;
        }
        
        // Initialize on page load
        initForm();
    });
})(jQuery);