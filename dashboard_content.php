<?php
include 'config.php';
// Get latest sensor data
$sql = "SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 1";
$result = $conn->query($sql);
if ($result) {
    $latest_data = $result->fetch_assoc();
}
// Get relay control settings
$relay_sql = "SELECT * FROM relay_control LIMIT 1";
$relay_result = $conn->query($relay_sql);
$relay = $relay_result ? $relay_result->fetch_assoc() : null;
?>
<div class="container-fluid">
    <!-- Toast for critical/warning alerts -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000;">
      <div id="criticalAlert" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body" id="criticalAlertMsg">
            <!-- Alert message will be set by JS -->
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Fan Control</h5>
                </div>
                <div class="card-body">
                    <div class="control-section mb-4">
                        <div class="mode-toggle-container">
                            <div class="form-check form-switch custom-switch">
                                <input class="form-check-input" type="checkbox" id="autoModeToggle" <?php if($relay && $relay['auto_mode']) echo 'checked'; ?>>
                                <label class="form-check-label" for="autoModeToggle">
                                    <i class="fas fa-robot me-2"></i>Auto Mode
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="fan-status-container mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="fan-status-display">
                                <h6 class="status-label mb-2">Fan Status</h6>
                                <div class="d-flex align-items-center">
                                    <div class="fan-icon-wrapper me-3">
                                        <i class="fas fa-fan"></i>
                                    </div>
                                    <div class="status-text">
                                        <span class="status-badge" id="relayState">
                                            <?php echo ($relay && $relay['current_state']) ? 'RUNNING' : 'STOPPED'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="fan-control">
                                <div class="form-check form-switch custom-switch">
                                    <input class="form-check-input" type="checkbox" id="relayToggle" <?php if($relay && $relay['current_state']) echo 'checked'; ?>>
                                    <label class="form-check-label" for="relayToggle">
                                        <i class="fas fa-power-off me-2"></i>Power
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Temperature</h5>
                    <h2 class="card-text" id="tempValue"><?php echo $latest_data['temperature'] ?? '<span class=\"no-data\">N/A</span>'; ?>°C</h2>
                    <?php if ($latest_data): ?>
                        <div class="last-update" id="tempUpdate">Last update: <?php echo $latest_data['timestamp']; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Humidity</h5>
                    <h2 class="card-text" id="humidityValue"><?php echo $latest_data['humidity'] ?? '<span class=\"no-data\">N/A</span>'; ?>%</h2>
                    <?php if ($latest_data): ?>
                        <div class="last-update" id="humidityUpdate">Last update: <?php echo $latest_data['timestamp']; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Gas Level</h5>
                    <h2 class="card-text" id="gasValue"><?php echo $latest_data['gas_level'] ?? '<span class=\"no-data\">N/A</span>'; ?> <small class="text-muted">ADC</small></h2>
                    <?php if ($latest_data): ?>
                        <div class="last-update" id="gasUpdate">Last update: <?php echo $latest_data['timestamp']; ?></div>
                        <div class="gas-info">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>Range: 0-4095 (ADC value)
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Sensor Data History</h5>
                    <div class="table-responsive">
                        <table class="table table-striped" id="sensorHistoryTable">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Temperature (°C)</th>
                                    <th>Humidity (%)</th>
                                    <th>Gas Level (ADC)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be inserted here by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Prevent form submission on enter key
    $(document).on('keydown', 'input[type="number"]', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            return false;
        }
    });

    function showAlert(message, type = 'danger') {
        $('#criticalAlertMsg').text(message);
        $('#criticalAlert').removeClass('bg-danger bg-success').addClass('bg-' + type);
        var toast = new bootstrap.Toast($('#criticalAlert'));
        toast.show();
    }

    function updateFanIcon(state) {
        var iconWrapper = $('.fan-icon-wrapper');
        var statusBadge = $('#relayState');
        
        if (state) {
            iconWrapper.addClass('spinning');
            iconWrapper.css('color', '#43cea2');
            statusBadge.text('RUNNING').removeClass('stopped').addClass('running');
        } else {
            iconWrapper.removeClass('spinning');
            iconWrapper.css('color', '#0077b6');
            statusBadge.text('STOPPED').removeClass('running').addClass('stopped');
        }
    }

    // Function to update only fan status and mode via AJAX
    function updateFanStatus() {
        $.ajax({
            url: 'api/control_relay.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                $('#relayState').text(data.current_state ? 'RUNNING' : 'STOPPED');
                $('#relayToggle').prop('checked', data.current_state);
                updateFanIcon(data.current_state);
                $('#autoModeToggle').prop('checked', data.auto_mode);
                $('#relayToggle').prop('disabled', data.auto_mode);
            }
        });
    }

    // Load initial threshold values
    $.ajax({
        url: 'api/control_relay.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            $('#gasThreshold').val(data.gas_threshold);
            $('#tempThreshold').val(data.temperature_threshold);
            $('#humidityThreshold').val(data.humidity_threshold);
            $('#currentGasThreshold').text(data.gas_threshold);
            $('#currentTempThreshold').text(data.temperature_threshold);
            $('#currentHumidityThreshold').text(data.humidity_threshold);
        }
    });

    $('#relayToggle').change(function() {
        const action = $(this).prop('checked') ? 'on' : 'off';
        const $toggle = $(this);
        $toggle.prop('disabled', true);

        $.ajax({
            url: 'api/control_relay.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: action }),
            success: function(response) {
                if (response.success) {
                    showAlert('Fan turned ' + action.toUpperCase(), 'success');
                    updateFanStatus();
                } else {
                    showAlert('Failed to control fan: ' + (response.error || 'Unknown error'));
                    $toggle.prop('checked', !$toggle.prop('checked'));
                }
            },
            error: function() {
                showAlert('Failed to control fan. Please try again.');
                $toggle.prop('checked', !$toggle.prop('checked'));
            },
            complete: function() {
                $toggle.prop('disabled', false);
            }
        });
    });

    $('#autoModeToggle').change(function() {
        const autoMode = $(this).prop('checked') ? 1 : 0;
        const $toggle = $(this);
        $toggle.prop('disabled', true);

        $.ajax({
            url: 'api/control_relay.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ auto_mode: autoMode }),
            success: function(response) {
                if (response.success) {
                    showAlert('Auto mode ' + (autoMode ? 'enabled' : 'disabled'), 'success');
                    updateFanStatus();
                } else {
                    showAlert('Failed to change mode: ' + (response.error || 'Unknown error'));
                    $toggle.prop('checked', !$toggle.prop('checked'));
                }
            },
            error: function() {
                showAlert('Failed to change mode. Please try again.');
                $toggle.prop('checked', !$toggle.prop('checked'));
            },
            complete: function() {
                $toggle.prop('disabled', false);
            }
        });
    });

    $('#saveThresholds').click(function() {
        const thresholds = {
            gas: parseInt($('#gasThreshold').val()),
            temperature: parseFloat($('#tempThreshold').val()),
            humidity: parseFloat($('#humidityThreshold').val())
        };

        if (isNaN(thresholds.gas) || isNaN(thresholds.temperature) || isNaN(thresholds.humidity)) {
            showAlert('Please enter valid numbers for all thresholds');
            return;
        }

        const $button = $(this);
        const originalText = $button.html();
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Updating...');

        $.ajax({
            url: 'api/control_relay.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ thresholds: thresholds }),
            success: function(response) {
                if (response.success) {
                    $('#currentGasThreshold').text(thresholds.gas);
                    $('#currentTempThreshold').text(thresholds.temperature);
                    $('#currentHumidityThreshold').text(thresholds.humidity);
                    showAlert('Thresholds updated successfully!', 'success');
                } else {
                    showAlert('Failed to update thresholds: ' + (response.error || 'Unknown error'));
                }
            },
            error: function() {
                showAlert('Failed to update thresholds. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    // Update sensor values via AJAX
    function updateSensorValues() {
        $.ajax({
            url: 'get_latest_data.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data && !data.error) {
                    $('#tempValue').html(data.temperature + '°C');
                    $('#humidityValue').html(data.humidity + '%');
                    $('#gasValue').html(data.gas_level + ' <small class="text-muted">ADC</small>');
                    $('#tempUpdate').html('Last update: ' + data.timestamp);
                    $('#humidityUpdate').html('Last update: ' + data.timestamp);
                    $('#gasUpdate').html('Last update: ' + data.timestamp);
                }
            }
        });
    }

    // Update history table via AJAX
    function updateHistoryTable() {
        $.ajax({
            url: 'get_sensor_history.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                let rows = '';
                data.forEach(row => {
                    rows += `<tr>
                        <td>${row.timestamp}</td>
                        <td>${parseFloat(row.temperature).toFixed(2)}</td>
                        <td>${parseFloat(row.humidity).toFixed(2)}</td>
                        <td>${parseFloat(row.gas_level).toFixed(2)}</td>
                    </tr>`;
                });
                $('#sensorHistoryTable tbody').html(rows);
            }
        });
    }

    // Set up periodic updates
    setInterval(updateFanStatus, 5000);
    setInterval(updateSensorValues, 3000);
    setInterval(updateHistoryTable, 10000);

    // Initial updates
    updateFanStatus();
    updateSensorValues();
    updateHistoryTable();
});
</script>
<style>
.card {
    border: none;
    border-radius: 18px;
    box-shadow: 0 4px 24px rgba(0, 119, 182, 0.08);
    margin-bottom: 24px;
}
.card-title {
    color: #0077b6;
    font-weight: 600;
}
.card-text {
    color: #222;
    font-size: 2.2rem;
    font-weight: 700;
}
.fan-status {
    font-size: 1.5rem;
    font-weight: bold;
}
.fan-on {
    color: #43cea2;
}
.fan-off {
    color: #0077b6;
}
.last-update {
    font-size: 0.8rem;
    color: #666;
}
.no-data {
    color: #dc3545;
    font-style: italic;
}
.card-body {
    background: #f8fcff;
    border-radius: 18px;
}
.card .card-body .last-update {
    margin-top: 10px;
}
.card .card-body small {
    color: #0077b6;
}
.fan-icon {
    font-size: 2.2rem;
    transition: color 0.3s;
}
.fan-on-spin {
    animation: spin 1s linear infinite;
}
.fan-off-static {
    opacity: 0.5;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Enhanced Fan Control Styles */
.control-section {
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    padding: 1.2rem;
}

.mode-toggle-container {
    text-align: center;
}

.custom-switch {
    display: inline-flex;
    align-items: center;
}

.custom-switch .form-check-input {
    width: 3rem;
    height: 1.5rem;
    margin-right: 0.8rem;
    cursor: pointer;
}

.custom-switch .form-check-input:checked {
    background-color: #43cea2;
    border-color: #43cea2;
}

.fan-status-container {
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    padding: 1.2rem;
}

.status-label {
    color: #0077b6;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.9rem;
}

.fan-icon-wrapper {
    font-size: 2.5rem;
    color: #0077b6;
    transition: all 0.3s ease;
}

.fan-icon-wrapper i {
    transition: transform 0.3s ease;
}

.fan-icon-wrapper.spinning i {
    animation: spin 2s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.status-badge {
    display: inline-block;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

#relayState.running {
    background: rgba(67, 206, 162, 0.2);
    color: #43cea2;
}

#relayState.stopped {
    background: rgba(0, 119, 182, 0.2);
    color: #0077b6;
}

.threshold-container {
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    padding: 1.2rem;
    margin-top: 1rem;
}

.threshold-title {
    color: #0077b6;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.threshold-controls .input-group {
    box-shadow: 0 2px 4px rgba(0, 119, 182, 0.05);
    border-radius: 8px;
    overflow: hidden;
}

.threshold-controls .input-group-text {
    background: #f8fcff;
    border: 1px solid rgba(0, 119, 182, 0.2);
    color: #0077b6;
}

.threshold-controls .form-control {
    border: 1px solid rgba(0, 119, 182, 0.2);
    padding: 0.6rem 1rem;
}

.threshold-controls .form-control:focus {
    border-color: #43cea2;
    box-shadow: 0 0 0 0.2rem rgba(67, 206, 162, 0.25);
}

.threshold-current {
    font-size: 0.9rem;
    color: #666;
}

.update-btn {
    background: linear-gradient(135deg, #0077b6 0%, #43cea2 100%);
    border: none;
    padding: 0.8rem 1.5rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    margin-top: 1rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.update-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 119, 182, 0.2);
}

.update-btn:active {
    transform: translateY(0);
}

.gas-info {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid rgba(0, 119, 182, 0.1);
}

.rpm-display {
    background: rgba(255, 255, 255, 0.8);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 1px solid rgba(0, 119, 182, 0.1);
    display: inline-block;
}

.rpm-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #0077b6;
    margin-left: 0.5rem;
}

.rpm-value small {
    font-size: 0.8rem;
    opacity: 0.8;
    margin-left: 0.2rem;
}

.fan-icon-wrapper.spinning {
    animation: spin calc(60s / var(--rpm)) linear infinite;
    animation-play-state: running;
}
</style> 