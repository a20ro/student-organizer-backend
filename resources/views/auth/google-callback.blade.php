<!DOCTYPE html>
<html>
<head>
    <title>Redirecting...</title>
    <meta charset="utf-8">
</head>
<body>
    <script>
        // Store token immediately
        const token = '{{ $token }}';
        const redirectUrl = '{{ $redirect_url }}';
        
        if (token && token !== '') {
            try {
                // Store token in localStorage
                localStorage.setItem('auth_token', token);
                sessionStorage.setItem('auth_token', token);
                
                // Set flag to indicate successful Google login
                localStorage.setItem('google_login_success', 'true');
                
                // Verify token was stored
                const storedToken = localStorage.getItem('auth_token');
                
                if (storedToken === token) {
                    console.log('Token stored and verified successfully');
                    
                    // Wait longer to ensure frontend is ready
                    // Also add token to URL as backup
                    const urlWithToken = redirectUrl + (redirectUrl.includes('?') ? '&' : '?') + 
                        'token=' + encodeURIComponent(token) + '&google_login=success&from_callback=1';
                    
                    setTimeout(function() {
                        // Redirect with token in URL as backup
                        window.location.href = urlWithToken;
                    }, 500); // Increased delay to 500ms
                } else {
                    console.error('Token storage verification failed');
                    // Fallback: redirect with token in URL
                    window.location.href = redirectUrl + (redirectUrl.includes('?') ? '&' : '?') + 
                        'token=' + encodeURIComponent(token) + '&google_login=success';
                }
            } catch (e) {
                console.error('Error storing token:', e);
                // Fallback: redirect with token in URL
                window.location.href = redirectUrl + (redirectUrl.includes('?') ? '&' : '?') + 
                    'token=' + encodeURIComponent(token) + '&google_login=success';
            }
        } else {
            console.error('No token received');
            // No token, redirect to login with error
            const loginUrl = redirectUrl.replace(/\/[^\/]*$/, '/login') || redirectUrl + '/login';
            window.location.href = loginUrl + '?error=Authentication failed';
        }
    </script>
    <p>Logging you in...</p>
    <p>Please wait while we redirect you.</p>
    <p style="font-size: 12px; color: #666;">This may take a moment...</p>
</body>
</html>

