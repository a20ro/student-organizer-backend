// Authentication and Route Protection

/**
 * Check if user is authenticated
 * @returns {boolean}
 */
function isAuthenticated() {
    const token = localStorage.getItem('auth_token');
    // For session-based auth, check if token exists (even if it's 'session-based')
    const isAuth = !!token && token !== 'null' && token !== 'undefined';
    console.log('isAuthenticated check:', isAuth, 'token:', token ? token.substring(0, 20) + '...' : 'null');
    return isAuth;
}

/**
 * Protect route - redirect to login if not authenticated
 */
function protectRoute() {
    if (!isAuthenticated()) {
        window.location.href = 'login.html';
    }
}

/**
 * Redirect if already authenticated (for login/register pages)
 */
function redirectIfAuthenticated() {
    if (isAuthenticated()) {
        window.location.href = 'index.html';
    }
}

/**
 * Initialize auth check on page load
 */
function initAuth() {
    // Get current page
    const currentPage = window.location.pathname;
    
    // Pages that require authentication
    const protectedPages = ['/index.html', '/dashboard.html'];
    
    // Pages that should redirect if authenticated
    const authPages = ['/login.html', '/register.html'];
    
    // Don't auto-protect index.html - it has its own auth check in loadDashboardData
    // This prevents race conditions when redirecting from OAuth
    if (protectedPages.some(page => currentPage.includes(page)) && !currentPage.includes('index.html')) {
        protectRoute();
    }
    
    // Check if current page is auth page
    if (authPages.some(page => currentPage.includes(page))) {
        // Add small delay to allow token to be set after OAuth redirect
        setTimeout(() => {
            redirectIfAuthenticated();
        }, 200);
    }
}

// Run on page load
document.addEventListener('DOMContentLoaded', initAuth);

// Export functions
window.auth = {
    isAuthenticated,
    protectRoute,
    redirectIfAuthenticated,
};

