// auth.js - Frontend validation and form handling

// Sign Up Form Handler
if (document.querySelector('form[action="signup_process.php"]')) {
    const signupForm = document.querySelector('form[action="signup_process.php"]');
    
    signupForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Clear previous errors
        clearErrors();
        
        // Get form data
        const formData = new FormData(signupForm);
        
        // Client-side validation
        const password = formData.get('password');
        const confirmPassword = formData.get('confirm-password');
        
        if (password !== confirmPassword) {
            showError('Passwords do not match!');
            return;
        }
        
        // Show loading state
        const submitBtn = signupForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Creating Account...';
        submitBtn.disabled = true;
        
        try {
            // Send data to PHP
            const response = await fetch('signup_process.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showSuccess(result.message);
                // Redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = result.redirect || 'index.html';
                }, 2000);
            } else {
                showError(result.message);
                if (result.errors && result.errors.length > 0) {
                    result.errors.forEach(error => {
                        showError(error);
                    });
                }
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            showError('An error occurred. Please try again.');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    });
}

// Login Form Handler
if (document.querySelector('form[action="login_process.php"]')) {
    const loginForm = document.querySelector('form[action="login_process.php"]');
    
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Clear previous errors
        clearErrors();
        
        // Get form data
        const formData = new FormData(loginForm);
        
        // Show loading state
        const submitBtn = loginForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Logging in...';
        submitBtn.disabled = true;
        
        try {
            // Send data to PHP
            const response = await fetch('login_process.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showSuccess(result.message);
                // Redirect after 1 second
                setTimeout(() => {
                    window.location.href = result.redirect || 'index.html';
                }, 1000);
            } else {
                showError(result.message);
                if (result.errors && result.errors.length > 0) {
                    result.errors.forEach(error => {
                        showError(error);
                    });
                }
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            showError('An error occurred. Please try again.');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    });
}

// Helper function to show errors
function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'alert alert-error';
    errorDiv.textContent = message;
    errorDiv.style.cssText = 'background: #fee; border: 1px solid #fcc; color: #c33; padding: 12px; border-radius: 8px; margin-bottom: 16px;';
    
    const form = document.querySelector('form');
    form.insertBefore(errorDiv, form.firstChild);
    
    // Auto remove after 5 seconds
    setTimeout(() => errorDiv.remove(), 5000);
}

// Helper function to show success
function showSuccess(message) {
    const successDiv = document.createElement('div');
    successDiv.className = 'alert alert-success';
    successDiv.textContent = message;
    successDiv.style.cssText = 'background: #efe; border: 1px solid #cfc; color: #3c3; padding: 12px; border-radius: 8px; margin-bottom: 16px;';
    
    const form = document.querySelector('form');
    form.insertBefore(successDiv, form.firstChild);
}

// Helper function to clear errors
function clearErrors() {
    document.querySelectorAll('.alert').forEach(alert => alert.remove());
}

// Password strength indicator (optional enhancement)
const passwordInput = document.querySelector('#password');
if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const strength = checkPasswordStrength(this.value);
        // You can add visual indicator here
    });
}

function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/\d/)) strength++;
    if (password.match(/[^a-zA-Z\d]/)) strength++;
    return strength;
}