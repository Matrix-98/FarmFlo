</div> <!-- end of #wrapper -->

<!-- Bootstrap Bundle (with Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Simple sidebar functionality -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add active class to current page link
        var currentPath = window.location.pathname;
        var navLinks = document.querySelectorAll('.sidebar-nav .nav-link');

        navLinks.forEach(function(link) {
            if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href').split('/').pop())) {
                link.classList.add('active');
            }
        });

        // Fade in animation for cards
        var cards = document.querySelectorAll('.card, .dashboard-card');
        cards.forEach(function(card, index) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(function() {
                card.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });

    // Utility functions
    function showLoading() {
        var loading = document.getElementById('loading');
        if (loading) {
            loading.style.display = 'flex';
        }
    }

    function hideLoading() {
        var loading = document.getElementById('loading');
        if (loading) {
            loading.style.display = 'none';
        }
    }

    function showAlert(message, type = 'info') {
        var alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-' + type + ' alert-dismissible fade show';
        alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

        var container = document.querySelector('.container-fluid') || document.querySelector('.container');
        if (container) {
            container.insertBefore(alertDiv, container.firstChild);

            // Auto-hide after 5 seconds
            setTimeout(function() {
                if (alertDiv.parentNode) {
                    alertDiv.style.transition = 'opacity 0.5s ease-out';
                    alertDiv.style.opacity = '0';
                    setTimeout(function() {
                        if (alertDiv.parentNode) {
                            alertDiv.parentNode.removeChild(alertDiv);
                        }
                    }, 500);
                }
            }, 5000);
        }
    }

    function formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }

    function formatDate(date) {
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }).format(new Date(date));
    }
</script>

<!-- Optional footer info -->
<!--
    <footer class="text-center py-3 text-muted small">
        &copy; <?php echo date("Y"); ?> Agri-Logistics System. All rights reserved.
    </footer>
    -->

</body>
</html>
