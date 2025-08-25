document.addEventListener('DOMContentLoaded', () => {
    loadDashboardData();
    setupAuthButton();
});

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

async function loadDashboardData() {
    try {
        const response = await fetch('api_dashboard.php', {
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            if (response.status === 401) {
                // User not authenticated - change button to Login
                console.log('Not authenticated - showing placeholder content');
                const authBtn = document.getElementById('authBtn');
                if (authBtn) {
                    authBtn.textContent = 'Login';
                }
                return;
            }
            throw new Error('Failed to load dashboard data');
        }
        
        const data = await response.json();
        updateDashboard(data);
        
        // User is authenticated - button already shows Logout by default
    } catch (error) {
        console.error('Error loading dashboard:', error);
        // Still show the dashboard with placeholder data
    }
}

function updateDashboard(data) {
    // Update user name
    if (data.user && data.user.name) {
        document.getElementById('userName').textContent = data.user.name;
    }
    
    // Update next/last appointment
    if (data.next_appointment) {
        const appointment = data.next_appointment;
        const dateTime = new Date(appointment.date + 'T' + appointment.time + ':00Z');
        const now = new Date();
        
        // Determine if appointment is in the past or future
        const isPast = dateTime < now;
        document.getElementById('appointmentHeading').textContent = isPast ? 'Last Appointment' : 'Next Appointment';
        
        // Convert UTC to local time
        const localDate = dateTime.toLocaleDateString('en-US', { 
            month: 'long', 
            day: 'numeric', 
            year: 'numeric' 
        });
        const localTime = dateTime.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        });
        
        document.getElementById('appointmentDate').textContent = `${localDate} at ${localTime}`;
        document.getElementById('appointmentDoctor').textContent = appointment.doctor_name;
        document.getElementById('appointmentType').textContent = appointment.appointment_type || 'Appointment';
        
        if (appointment.location) {
            const locationEl = document.getElementById('appointmentLocation');
            locationEl.textContent = `Location: ${appointment.location}`;
            locationEl.style.display = 'block';
        }
        
        document.getElementById('appointmentInfo').style.display = 'block';
        document.getElementById('noAppointment').style.display = 'none';
    } else {
        // No appointments - show (none)
        document.getElementById('appointmentInfo').style.display = 'none';
        document.getElementById('noAppointment').style.display = 'block';
    }
    
    // Update recent chats
    if (data.recent_chats && data.recent_chats.length > 0) {
        const chatsContainer = document.getElementById('recentChats');
        chatsContainer.innerHTML = '';
        
        data.recent_chats.forEach(chat => {
            const chatItem = createChatItem(chat);
            chatsContainer.appendChild(chatItem);
        });
        
        document.getElementById('noChats').style.display = 'none';
        chatsContainer.style.display = 'flex';
    } else {
        document.getElementById('recentChats').style.display = 'none';
        document.getElementById('noChats').style.display = 'block';
    }
}

function createChatItem(chat) {
    const div = document.createElement('div');
    div.className = 'chat-item';
    
    const preview = document.createElement('div');
    preview.className = 'chat-preview';
    
    const excerpt = document.createElement('p');
    excerpt.className = 'chat-excerpt';
    excerpt.textContent = chat.preview || 'Chat session';
    
    const date = document.createElement('span');
    date.className = 'chat-date';
    date.textContent = formatDate(chat.timestamp);
    
    preview.appendChild(excerpt);
    preview.appendChild(date);
    
    const link = document.createElement('a');
    link.href = `../pg_chat/?session=${chat.id}`;
    link.className = 'btn-link';
    link.textContent = 'Resume';
    
    div.appendChild(preview);
    div.appendChild(link);
    
    return div;
}

function formatDate(timestamp) {
    if (!timestamp) return 'Recently';
    
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    
    if (hours < 1) return 'Just now';
    if (hours === 1) return '1 hour ago';
    if (hours < 24) return `${hours} hours ago`;
    if (days === 1) return 'Yesterday';
    if (days < 7) return `${days} days ago`;
    
    return date.toLocaleDateString();
}

function setupAuthButton() {
    const authBtn = document.getElementById('authBtn');
    if (authBtn) {
        authBtn.addEventListener('click', handleAuth);
    }
}

async function handleAuth() {
    const authBtn = document.getElementById('authBtn');
    const buttonText = authBtn.textContent.toLowerCase();
    
    if (buttonText === 'logout') {
        // User is logged in - perform logout
        // First call the logout API to invalidate server-side session
        try {
            await fetch('api_logout.php', {
                method: 'POST',
                credentials: 'same-origin'
            });
        } catch (error) {
            // Continue with logout even if API call fails
        }
        
        // Clear the cookie client-side as well
        document.cookie = 'aiofc_session=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        
        // Redirect to root URL which will show landing page
        window.location.href = '../';
    } else {
        // User is not logged in - redirect to login page
        window.location.href = '../pg_login/';
    }
}