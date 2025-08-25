document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const codeInput = document.getElementById('code');
    const codeGroup = document.getElementById('codeGroup');
    const submitBtn = document.getElementById('submitBtn');
    const messageDiv = document.getElementById('message');
    
    let emailSent = false;
    
    // Check for existing session on page load
    checkExistingSession();
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const email = emailInput.value.trim();
        
        if (!emailSent) {
            // Send verification code
            await sendVerificationCode(email);
        } else {
            // Verify code and complete login
            const code = codeInput.value.trim();
            await verifyCode(email, code);
        }
    });
    
    async function checkExistingSession() {
        const sessionToken = getCookie('aiofc_session');
        if (!sessionToken) return;
        
        try {
            const response = await fetch('login.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({token: sessionToken})
            });
            
            const data = await response.json();
            
            if (response.ok && data.redirect) {
                window.location.href = data.redirect;
            }
        } catch (error) {
            console.error('Session check failed:', error);
        }
    }
    
    async function sendVerificationCode(email) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        hideMessage();
        
        try {
            const response = await fetch('login.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: email})
            });
            
            const data = await response.json();
            
            if (response.ok) {
                emailSent = true;
                emailInput.readOnly = true;
                codeGroup.style.display = 'block';
                codeInput.focus();
                submitBtn.textContent = 'Verify & Login';
                showMessage('Verification code sent to your email', 'success');
            } else {
                showMessage(data.error || 'Failed to send verification code', 'error');
            }
        } catch (error) {
            showMessage('Network error. Please try again.', 'error');
        } finally {
            submitBtn.disabled = false;
        }
    }
    
    async function verifyCode(email, code) {
        if (code.length !== 6) {
            showMessage('Please enter a 6-digit code', 'error');
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Verifying...';
        hideMessage();
        
        try {
            const response = await fetch('login.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email: email, code: code})
            });
            
            const data = await response.json();
            
            if (response.ok && data.redirect) {
                showMessage('Login successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1000);
            } else {
                showMessage(data.error || 'Invalid verification code', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Verify & Login';
            }
        } catch (error) {
            showMessage('Network error. Please try again.', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Verify & Login';
        }
    }
    
    function showMessage(text, type) {
        messageDiv.textContent = text;
        messageDiv.className = 'message ' + type;
    }
    
    function hideMessage() {
        messageDiv.className = 'message';
        messageDiv.textContent = '';
    }
    
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
});