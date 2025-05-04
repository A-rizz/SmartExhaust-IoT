<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card smooth-fade">
                <div class="card-body">
                    <h5 class="card-title mb-4">About Us</h5>
                    <p>
                        <strong>Sensor Monitoring System</strong> is a web-based platform designed to monitor environmental conditions such as temperature, humidity, gas levels, and fan status in real time. Our goal is to provide a user-friendly and efficient way to keep track of sensor data and ensure a safe and comfortable environment.
                    </p>
                    <p>
                        <strong>Our Team:</strong><br>
                        We are a group of passionate developers and engineers dedicated to building smart solutions for everyday challenges. Thank you for using our system!
                    </p>
                    <p class="text-muted small">&copy; <?php echo date('Y'); ?> Sensor Monitoring System</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Smooth transition on page load
document.addEventListener('DOMContentLoaded', function() {
    var el = document.querySelector('.smooth-fade');
    if (el) {
        el.classList.add('is-leaving');
        setTimeout(function() {
            el.classList.remove('is-leaving');
        }, 350);
    }
});
</script> 