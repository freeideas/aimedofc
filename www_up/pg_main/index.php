<?php
require_once '../infrastructure/include.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Medical Office Assistant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo-section">
                    <h1>Medical Office Assistant</h1>
                    <p class="welcome">Welcome, <span id="userName">Patient</span></p>
                </div>
                <div class="header-actions">
                    <button id="authBtn" class="btn-secondary">Logout</button>
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <section class="main-actions">
                <h2>What would you like to do?</h2>
                <div class="action-cards">
                    <a href="../pg_chat/" class="action-card">
                        <div class="card-icon">💬</div>
                        <h3>Chat with AI</h3>
                        <p>Ask questions about your medical records and prepare for appointments</p>
                    </a>
                    <a href="../pg_records/" class="action-card">
                        <div class="card-icon">📋</div>
                        <h3>View Medical Records</h3>
                        <p>Access your medical history, lab results, and prescriptions</p>
                    </a>
                    <a href="../pg_profile/" class="action-card">
                        <div class="card-icon">👤</div>
                        <h3>My Profile</h3>
                        <p>Manage your account settings and personal information</p>
                    </a>
                </div>
            </section>

            <section class="next-appointment">
                <h2 id="appointmentHeading">Next Appointment</h2>
                <div id="appointmentInfo" class="appointment-card">
                    <div class="appointment-details">
                        <p class="appointment-date" id="appointmentDate">January 1, 2000 at 12:00 PM</p>
                        <p class="appointment-doctor" id="appointmentDoctor">Dr. Smith</p>
                        <p class="appointment-type" id="appointmentType">Routine Check-up</p>
                        <p class="appointment-location" id="appointmentLocation" style="display: none;"></p>
                    </div>
                </div>
                <div class="no-appointment" id="noAppointment" style="display: none;">
                    <p>(none)</p>
                </div>
            </section>

            <section class="recent-activity">
                <h2>Recent Activity</h2>
                <div id="recentChats" class="recent-chats">
                    <div class="chat-item">
                        <div class="chat-title">Questions about lab results</div>
                        <div class="chat-time">2 hours ago</div>
                    </div>
                    <div class="chat-item">
                        <div class="chat-title">Medication review</div>
                        <div class="chat-time">Yesterday</div>
                    </div>
                    <div class="chat-item">
                        <div class="chat-title">Appointment preparation</div>
                        <div class="chat-time">3 days ago</div>
                    </div>
                </div>
            </section>

            <section class="quick-actions">
                <a href="../pg_chat/" class="btn-primary">Chat with AI Assistant</a>
                <a href="../pg_records/" class="btn-secondary">View All Records</a>
            </section>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Medical Office Assistant. Not a replacement for professional medical advice.</p>
        </div>
    </footer>

    <script src="app.js"></script>
</body>
</html>
