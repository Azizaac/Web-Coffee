</main>
    
    <footer class="mt-5 py-4 text-center text-muted">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <p class="mb-1">
                        <i class="fas fa-coffee coffee-icon me-2"></i>
                        <strong>SIM Coffee Shop</strong> - Management System
                    </p>
                    <p class="small mb-0">
                        &copy; <?= date('Y') ?> Coffee Shop Management System. 
                        Version <?= APP_VERSION ?? '1.0.0' ?>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <script>
        // Additional JavaScript functions for better UX
        
        // Confirm delete actions
        function confirmDelete(message = 'Are you sure you want to delete this item?') {
            return confirm(message);
        }
        
        // Show loading state
        function showLoading(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="loading"></span> Processing...';
            button.disabled = true;
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 3000);
        }
        
        // Format number input as currency
        function formatNumberInput(input) {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^\d]/g, '');
                if (value) {
                    e.target.value = parseInt(value).toLocaleString('id-ID');
                }
            });
        }
        
        // Initialize number inputs
        document.addEventListener('DOMContentLoaded', function() {
            const numberInputs = document.querySelectorAll('input[type="number"]');
            numberInputs.forEach(input => {
                if (input.step === '100' || input.name.includes('price') || input.name.includes('amount')) {
                    formatNumberInput(input);
                }
            });
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Auto-focus first input in modals
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('shown.bs.modal', function () {
                const firstInput = modal.querySelector('input, select, textarea');
                if (firstInput) {
                    firstInput.focus();
                }
            });
        });
        
        // Table row click handler
        function handleTableRowClick() {
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    // Don't trigger if clicked on button or link
                    if (e.target.closest('a, button')) return;
                    
                    // Add selection effect
                    tableRows.forEach(r => r.classList.remove('table-active'));
                    this.classList.add('table-active');
                });
            });
        }
        
        // Initialize table interactions
        document.addEventListener('DOMContentLoaded', handleTableRowClick);
        
        // Notification system
        function showNotification(message, type = 'info', duration = 3000) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    bootstrap.Alert.getOrCreateInstance(alertDiv).close();
                }
            }, duration);
        }
        
        // Form validation enhancement
        function enhanceFormValidation() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        showNotification('Please fill all required fields', 'danger');
                    }
                });
                
                // Remove validation class on input
                form.querySelectorAll('input, select, textarea').forEach(field => {
                    field.addEventListener('input', function() {
                        this.classList.remove('is-invalid');
                    });
                });
            });
        }
        
        // Initialize form enhancements
        document.addEventListener('DOMContentLoaded', enhanceFormValidation);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K for search (if search input exists)
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                const searchInput = document.querySelector('#product-search, input[type="search"]');
                if (searchInput) {
                    e.preventDefault();
                    searchInput.focus();
                }
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    bootstrap.Modal.getInstance(openModal).hide();
                }
            }
        });
        
        // Print function for reports
        function printReport() {
            window.print();
        }
        
        // Export table to CSV
        function exportTableToCSV(tableId, filename = 'export.csv') {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const rows = table.querySelectorAll('tr');
            const csvContent = [];
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('th, td');
                const rowData = Array.from(cells).map(cell => 
                    `"${cell.textContent.trim().replace(/"/g, '""')}"`
                );
                csvContent.push(rowData.join(','));
            });
            
            const blob = new Blob([csvContent.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>