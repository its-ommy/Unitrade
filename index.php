<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already signed in? Skip the landing page and go straight to the dashboard.
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniTrade — The University Marketplace for Students</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/landing.css">
</head>
<body>

<section class="hero">
    <div class="hero-bg" id="heroBg"></div>

    <nav class="hero-nav">
        <a class="hero-logo" href="index.php">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 3L1 8l11 5 9-4.09V17h2V8L12 3z" fill="currentColor"/>
                <path d="M5 10.18V14c0 2.21 3.13 4 7 4s7-1.79 7-4v-3.82l-7 3.18-7-3.18z" fill="currentColor"/>
            </svg>
            UniTrade
        </a>
        
    </nav><br><br>
    <div class="hero-dots" id="heroDots"></div>

    <div class="hero-content">
        <p class="hero-tagline">Buy <span class="sep">·</span> Sell <span class="sep">·</span> Connect</p>
        <h1>The University Marketplace for Students</h1>
        <a href="Register.php" class="hero-cta">
            Get Started
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
    </div>
    
</section>

<section class="landing-section">
    <h2>How UniTrade works</h2>
    <p class="lead">A campus-only marketplace — verified with your student registration number, so you know you're dealing with someone from your own university.</p>

    <div class="landing-steps">
        <div class="landing-step">
            <div class="num">01</div>
            <h3>Register with your college</h3>
            <p>Sign up with your campus email and registration number. We verify it against your university's student registry.</p>
        </div>
        <div class="landing-step">
            <div class="num">02</div>
            <h3>Browse or list an item</h3>
            <p>Search listings by category, college, or price — or post your own item for sale in a few steps.</p>
        </div>
        <div class="landing-step">
            <div class="num">03</div>
            <h3>Message, buy, and rate</h3>
            <p>Chat with the seller, send a purchase request, and once it's approved, meet up and leave a review.</p>
        </div>
    </div>
</section> 

<script src="assets/landing.js"></script> 

</body>
</html>