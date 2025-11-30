/**
 * Email Form Handler
 * Handles form submission and validation
 */

(function() {
    'use strict';

    /**
     * Initialize the email form
     */
    function initEmailForm() {
        const form = document.getElementById('emailForm');
        
        if (!form) {
            console.error('Email form not found');
            return;
        }

        form.addEventListener('submit', handleFormSubmit);
    }

    /**
     * Handle form submission
     * @param {Event} event - The submit event
     */
    function handleFormSubmit(event) {
        event.preventDefault();
        
        const emailInput = document.getElementById('email');
        const email = emailInput.value.trim();

        if (!email) {
            showMessage('Silakan masukkan alamat email', 'error');
            return;
        }

        if (!isValidEmail(email)) {
            showMessage('Format email tidak valid', 'error');
            return;
        }

        // Process email submission
        processEmailSubmission(email);
    }

    /**
     * Validate email format
     * @param {string} email - Email address to validate
     * @returns {boolean} - True if email is valid
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Process email submission
     * @param {string} email - Email address
     */
    function processEmailSubmission(email) {
        const submitButton = document.querySelector('.btn-submit');
        const form = document.getElementById('emailForm');
        
        if (!submitButton || submitButton.disabled) {
            return; // Prevent double submission
        }
        
        const originalText = submitButton.innerHTML;
        
        // Disable button and show loading state
        submitButton.disabled = true;
        submitButton.setAttribute('aria-busy', 'true');
        submitButton.innerHTML = 'Mengirim...';
        
        // Disable form inputs
        const inputs = form.querySelectorAll('input, button');
        inputs.forEach(input => {
            if (input !== submitButton) {
                input.disabled = true;
            }
        });
        
        // Prepare form data
        const formData = new FormData();
        formData.append('email', email);
        
        // Send AJAX request
        fetch('email/kirim_email.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    throw new Error('Invalid response format: ' + text.substring(0, 100));
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                // Reset form
                form.reset();
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Terjadi kesalahan saat mengirim email. Silakan coba lagi.', 'error');
        })
        .finally(() => {
            // Re-enable button and inputs
            submitButton.disabled = false;
            submitButton.removeAttribute('aria-busy');
            submitButton.innerHTML = originalText;
            
            inputs.forEach(input => {
                input.disabled = false;
            });
        });
    }

    /**
     * Show notification to user
     * @param {string} message - Message to display
     * @param {string} type - Message type (success, error)
     * @param {number} duration - Duration in milliseconds (default: 5000)
     */
    function showMessage(message, type = 'success', duration = 5000) {
        const container = document.getElementById('notificationContainer');
        
        if (!container) {
            // Fallback to alert if container not found
            alert(message);
            return;
        }

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.setAttribute('role', 'alert');
        notification.setAttribute('aria-live', 'assertive');

        // Determine icon and title based on type
        const icons = {
            success: '✓',
            error: '✕'
        };
        
        const titles = {
            success: 'Berhasil!',
            error: 'Error!'
        };

        notification.innerHTML = `
            <div class="notification-icon">${icons[type] || icons.success}</div>
            <div class="notification-content">
                <div class="notification-title">${titles[type] || titles.success}</div>
                <div class="notification-message">${escapeHtml(message)}</div>
            </div>
            <button class="notification-close" aria-label="Tutup notifikasi" type="button">×</button>
            <div class="notification-progress"></div>
        `;

        // Add to container
        container.appendChild(notification);

        // Trigger animation
        requestAnimationFrame(() => {
            notification.classList.add('show');
        });

        // Close button handler
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            removeNotification(notification);
        });

        // Auto remove after duration
        let timeoutId = setTimeout(() => {
            removeNotification(notification);
        }, duration);

        // Pause progress on hover
        notification.addEventListener('mouseenter', () => {
            clearTimeout(timeoutId);
            notification.querySelector('.notification-progress').style.animationPlayState = 'paused';
        });

        notification.addEventListener('mouseleave', () => {
            const remainingTime = duration - (Date.now() - (notification._startTime || Date.now()));
            timeoutId = setTimeout(() => {
                removeNotification(notification);
            }, remainingTime);
            notification.querySelector('.notification-progress').style.animationPlayState = 'running';
        });

        notification._startTime = Date.now();
    }

    /**
     * Remove notification with animation
     * @param {HTMLElement} notification - Notification element to remove
     */
    function removeNotification(notification) {
        notification.classList.remove('show');
        notification.classList.add('hide');
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} - Escaped text
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEmailForm);
    } else {
        initEmailForm();
    }
})();

