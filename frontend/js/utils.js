// Utility Functions

/**
 * Show error message
 * @param {HTMLElement} element - Element to show error in
 * @param {string} message - Error message
 */
function showError(element, message) {
    element.textContent = message;
    element.classList.remove('hidden');
    element.classList.add('block');
}

/**
 * Hide error message
 * @param {HTMLElement} element - Element to hide
 */
function hideError(element) {
    element.classList.add('hidden');
    element.classList.remove('block');
    element.textContent = '';
}

/**
 * Show success message
 * @param {HTMLElement} element - Element to show success in
 * @param {string} message - Success message
 */
function showSuccess(element, message) {
    element.textContent = message;
    element.classList.remove('hidden');
    element.classList.add('block', 'text-green-600');
    element.classList.remove('text-red-600');
}

/**
 * Toggle loading state
 * @param {HTMLElement} button - Button element
 * @param {boolean} isLoading - Loading state
 */
function setLoading(button, isLoading) {
    if (isLoading) {
        button.disabled = true;
        button.classList.add('opacity-50', 'cursor-not-allowed');
        const originalText = button.dataset.originalText || button.textContent;
        button.dataset.originalText = originalText;
        button.innerHTML = `
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Loading...
        `;
    } else {
        button.disabled = false;
        button.classList.remove('opacity-50', 'cursor-not-allowed');
        button.textContent = button.dataset.originalText || button.textContent;
    }
}

/**
 * Validate email format
 * @param {string} email - Email to validate
 * @returns {boolean}
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Validate password strength
 * @param {string} password - Password to validate
 * @returns {Object} - { valid: boolean, message: string }
 */
function validatePassword(password) {
    if (password.length < 8) {
        return { valid: false, message: 'Password must be at least 8 characters long' };
    }
    return { valid: true, message: '' };
}

// Export functions
window.utils = {
    showError,
    hideError,
    showSuccess,
    setLoading,
    isValidEmail,
    validatePassword,
};

