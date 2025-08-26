<?php
require_once '../infrastructure/include.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Office Assistant</title>
    <link rel="stylesheet" href="../infrastructure/glassmorphic.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Video Background -->
    <div class="video-background">
        <video autoplay muted loop playsinline>
            <source src="bg.mp4" type="video/mp4">
        </video>
        <div class="video-overlay"></div>
    </div>
    
    <header>
        <div class="container">
            <h1>Medical Office Assistant</h1>
            <p class="tagline">Your Personal Medical Assistant</p>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <h2>Prepare for Your Doctor Appointments with AI</h2>
                <p class="lead">Medical Office Assistant reads your medical records and helps you understand your health information, preparing you for more productive consultations with your healthcare provider.</p>
                <a href="../pg_login/" class="btn-primary">Get Started</a>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <h2>How Medical Office Assistant Helps You</h2>
                <div class="feature-grid">
                    <div class="feature">
                        <h3>Smart Document Analysis</h3>
                        <p>Your doctor's office provides your medical records, lab results, prescriptions, and imaging reports. Our AI reads and understands all your documents to provide personalized assistance.</p>
                    </div>
                    <div class="feature">
                        <h3>Personalized Preparation</h3>
                        <p>Ask questions about your medical history, medications, or test results. Get clear explanations to help you understand your health information better.</p>
                    </div>
                    <div class="feature">
                        <h3>Appointment Ready</h3>
                        <p>Prepare a list of questions for your doctor, understand your symptoms better, and make the most of your consultation time.</p>
                    </div>
                    <div class="feature">
                        <h3>Secure & Private</h3>
                        <p>Your medical information is encrypted and stored securely. Only you have access to your documents and conversations.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="how-it-works">
            <div class="container">
                <h2>Simple to Use</h2>
                <ol class="steps">
                    <li>
                        <strong>Sign In</strong>
                        <p>Use our secure passwordless authentication - just enter your email and receive a verification code.</p>
                    </li>
                    <li>
                        <strong>Access Your Records</strong>
                        <p>View your medical records, prescriptions, and lab results provided by your doctor's office.</p>
                    </li>
                    <li>
                        <strong>Start Chatting</strong>
                        <p>Ask questions about your health information and get AI-powered insights to prepare for your appointments.</p>
                    </li>
                </ol>
            </div>
        </section>

        <section class="supported-docs">
            <div class="container">
                <h2>Medical Information We Process</h2>
                <div class="doc-types">
                    <span class="doc-type">Doctor Visit Notes</span>
                    <span class="doc-type">Lab Results</span>
                    <span class="doc-type">Prescriptions</span>
                    <span class="doc-type">Medical History</span>
                    <span class="doc-type">Test Results</span>
                    <span class="doc-type">Discharge Summaries</span>
                </div>
            </div>
        </section>

        <section class="cta">
            <div class="container">
                <h2>Ready to Better Understand Your Health?</h2>
                <p>Join Medical Office Assistant today and take control of your medical information.</p>
                <a href="../pg_login/" class="btn-primary">Get Started Now</a>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Medical Office Assistant. This is not a replacement for professional medical advice.</p>
            <p class="disclaimer">Always consult with your healthcare provider for medical decisions.</p>
        </div>
    </footer>

    <script src="app.js"></script>
</body>
</html>
