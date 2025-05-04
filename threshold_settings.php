<?php
include 'config.php';
// Get relay control settings
$relay_sql = "SELECT * FROM relay_control LIMIT 1";
$relay_result = $conn->query($relay_sql);
$relay = $relay_result ? $relay_result->fetch_assoc() : null;
?>

<div class="container-fluid">
    <!-- Toast for alerts -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 2000;">
        <div id="thresholdAlert" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="thresholdAlertMsg">
                    <!-- Alert message will be set by JS -->
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-sliders-h me-2"></i>Threshold Settings
                        <small class="text-muted">(Auto Mode Activation)</small>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="threshold-section">
                        <div class="threshold-info mb-4">
                            <p class="text-muted">
                                <i class="fas fa-info-circle me-2"></i>
                                These thresholds determine when the fan will automatically turn on in Auto Mode.
                                The fan will activate if any of these values are exceeded.
                            </p>
                        </div>

                        <div class="threshold-controls">
                            <div class="threshold-item mb-4">
                                <label class="form-label d-flex align-items-center">
                                    <i class="fas fa-wind me-2 text-primary"></i>
                                    Gas Level Threshold
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="gasThreshold" 
                                           value="<?php echo $relay ? $relay['gas_threshold'] : ''; ?>" 
                                           placeholder="Gas threshold (0-4095)">
                                    <span class="input-group-text">ADC</span>
                                </div>
                                <small class="text-muted">Current: <span id="currentGasThreshold"><?php echo $relay ? $relay['gas_threshold'] : ''; ?></span> ADC</small>
                                <div class="threshold-description mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Range: 0-4095 (ADC value). Higher values indicate higher gas concentration.
                                    </small>
                                </div>
                            </div>

                            <div class="threshold-item mb-4">
                                <label class="form-label d-flex align-items-center">
                                    <i class="fas fa-temperature-high me-2 text-primary"></i>
                                    Temperature Threshold
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="tempThreshold" 
                                           value="<?php echo $relay ? $relay['temperature_threshold'] : ''; ?>" 
                                           placeholder="Temperature threshold">
                                    <span class="input-group-text">°C</span>
                                </div>
                                <small class="text-muted">Current: <span id="currentTempThreshold"><?php echo $relay ? $relay['temperature_threshold'] : ''; ?></span> °C</small>
                                <div class="threshold-description mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Recommended range: 25-35°C. Fan activates above this temperature.
                                    </small>
                                </div>
                            </div>

                            <div class="threshold-item mb-4">
                                <label class="form-label d-flex align-items-center">
                                    <i class="fas fa-tint me-2 text-primary"></i>
                                    Humidity Threshold
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="humidityThreshold" 
                                           value="<?php echo $relay ? $relay['humidity_threshold'] : ''; ?>" 
                                           placeholder="Humidity threshold">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">Current: <span id="currentHumidityThreshold"><?php echo $relay ? $relay['humidity_threshold'] : ''; ?></span> %</small>
                                <div class="threshold-description mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Recommended range: 60-80%. Fan activates above this humidity level.
                                    </small>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button class="btn btn-primary btn-lg update-btn" id="saveThresholds">
                                    <i class="fas fa-save me-2"></i>Update Thresholds
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: none;
    border-radius: 18px;
    box-shadow: 0 4px 24px rgba(0, 119, 182, 0.08);
}

.card-header {
    background: linear-gradient(90deg, #0077b6 0%, #43cea2 100%);
    color: white;
    border-radius: 18px 18px 0 0 !important;
    border: none;
}

.threshold-section {
    padding: 1rem;
}

.threshold-item {
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid rgba(0, 119, 182, 0.1);
}

.threshold-item label {
    color: #0077b6;
    font-weight: 600;
    font-size: 1.1rem;
}

.threshold-item .input-group {
    box-shadow: 0 2px 4px rgba(0, 119, 182, 0.05);
    border-radius: 8px;
    overflow: hidden;
}

.threshold-item .input-group-text {
    background: #f8fcff;
    border: 1px solid rgba(0, 119, 182, 0.2);
    color: #0077b6;
}

.threshold-item .form-control {
    border: 1px solid rgba(0, 119, 182, 0.2);
    padding: 0.8rem 1rem;
    font-size: 1.1rem;
}

.threshold-item .form-control:focus {
    border-color: #43cea2;
    box-shadow: 0 0 0 0.2rem rgba(67, 206, 162, 0.25);
}

.threshold-description {
    color: #666;
    font-size: 0.9rem;
}

.update-btn {
    background: linear-gradient(135deg, #0077b6 0%, #43cea2 100%);
    border: none;
    padding: 1rem 2rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.update-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 119, 182, 0.2);
    background: linear-gradient(135deg, #43cea2 0%, #0077b6 100%);
}

.update-btn:active {
    transform: translateY(0);
}

.text-primary {
    color: #0077b6 !important;
}
</style>

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
        $('#thresholdAlert').removeClass('bg-danger bg-success').addClass('bg-' + type);
        $('#thresholdAlertMsg').text(message);
        var toast = new bootstrap.Toast($('#thresholdAlert'));
        toast.show();
    }

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
            error: function(xhr, status, error) {
                showAlert('Failed to update thresholds. Please try again.');
                console.error('Error:', error);
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script> 