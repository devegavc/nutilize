<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
<title>NUtilize | Home</title>
  <link rel="stylesheet" href="/css/landing.css?v=5" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
</head>
<body>
  <header class="top-header">
    <div class="header-inner">
      <a href="/" class="brand" aria-label="NU-TILIZE home">
        <img src="/img/nutilize_logo.png" alt="NU-TILIZE" class="brand-logo" />
      </a>

      <nav class="top-nav" aria-label="Primary">
        <a href="#home" class="nav-link">Home</a>
        <a href="#facilities" class="nav-link">Facilities</a>
        <a href="#about" class="nav-link">About</a>
        <a href="#contact" class="nav-link">Contact</a>
        <a href="/login" class="login-btn">Login</a>
      </nav>
    </div>
  </header>

  <main class="hero" id="home">
    <div class="hero-overlay"></div>
    @php
      $nutilizeAppUrl = trim((string) config('services.nutilize.play_store_url', ''));
      $hasNutilizeAppUrl = $nutilizeAppUrl !== '';
    @endphp
    <section class="hero-content">
      <div class="hero-copy">
        <p class="hero-kicker">Welcome to</p>
        <h1>NUtilize</h1>
        <p class="hero-tagline">Smart Facilities Management,<br />Made Simple.</p>
        <p class="hero-lead">Manage facilities, equipment, reservations, requests, and maintenance in one centralized platform.</p>
      </div>

      <div class="hero-actions">
        <a href="/login" class="cta-btn">Get Started</a>
        <a
          href="{{ $hasNutilizeAppUrl ? $nutilizeAppUrl : '#' }}"
          class="cta-btn cta-btn-secondary{{ $hasNutilizeAppUrl ? '' : ' is-pending' }}"
          @if ($hasNutilizeAppUrl)
            target="_blank"
            rel="noopener noreferrer"
          @else
            aria-disabled="true"
            title="NUtilize mobile app listing coming soon"
          @endif
        >
          <i class="bi bi-google-play" aria-hidden="true"></i>
          Get the NUtilize App
        </a>
      </div>
    </section>
  </main>

  <section class="placeholder-section" id="facilities" aria-hidden="true"></section>
    <section class="facilities" id="facilities">
      <div class="facilities-content">
        <h2 class="facilities-title">OUR FACILITIES</h2>
        <p class="facilities-subtitle">Explore the facilities we manage at NUtilize and see how platform helps keep everything running smoothly.</p>
      
        <div class="facilities-grid">
          <!-- Classrooms Card -->
          <div class="facility-card">
            <div class="facility-image">
              <img src="/img/nu_classroom.jpg" alt="Classrooms" />
            </div>
            <div class="facility-info">
              <h3 class="facility-name">CLASSROOMS</h3>
              <p class="facility-description">Modern classrooms designed to support effective learning through organized scheduling and optimized space utilization.</p>
            </div>
          </div>

          <!-- Gym Card -->
          <div class="facility-card">
            <div class="facility-image">
              <img src="/img/nu_gym.jpg" alt="Gym" />
            </div>
            <div class="facility-info">
              <h3 class="facility-name">GYM</h3>
              <p class="facility-description">Well-maintained gym facilities that promote fitness, school activities, and a safe environment for physical development.</p>
            </div>
          </div>

          <!-- Computer Lab Card -->
          <div class="facility-card">
            <div class="facility-image">
              <img src="/img/nu_comlab.jpg" alt="Computer Lab" />
            </div>
            <div class="facility-info">
              <h3 class="facility-name">COMPUTER LAB</h3>
              <p class="facility-description">Technology-equipped computer laboratories designed to support programming, research, multimedia activities, and collaborative learning sessions.</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  <section class="about" id="about">
    <div class="about-content">
      <h2 class="about-title">WHY CHOOSE NUTILIZE?</h2>
      <p class="about-subtitle">Benefits of Our Facilities Management Platform</p>

      <div class="about-grid">
        <div class="about-card">
          <div class="about-icon">
            <i class="bi bi-check2-square"></i>
          </div>
          <h3 class="about-card-title">Streamlined Operation</h3>
          <p class="about-card-desc">Simplify facility management with an organized system that reduces manual work and improves overall efficiency.</p>
        </div>

        <div class="about-card">
          <div class="about-icon">
            <i class="bi bi-gear-fill"></i>
          </div>
          <h3 class="about-card-title">Real-Time Monitoring</h3>
          <p class="about-card-desc">Stay updated with instant access to facility status, helping you respond quickly and make informed decisions.</p>
        </div>

        <div class="about-card">
          <div class="about-icon">
            <i class="bi bi-shield-check"></i>
          </div>
          <h3 class="about-card-title">Cost Efficiency</h3>
          <p class="about-card-desc">Optimize resource usage, minimize unnecessary expenses, and maximize operational value.</p>
        </div>
      </div>
    </div>
  </section>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const headerOffset = 88; // should match CSS scroll-margin-top / header height
      const navLinks = Array.from(document.querySelectorAll('.top-nav .nav-link'));
      const hashLinks = navLinks.filter(l => l.getAttribute('href') && l.getAttribute('href').startsWith('#'));

      const sections = hashLinks.map(l => document.querySelector(l.getAttribute('href'))).filter(Boolean);

      function setActiveLink() {
        const fromTop = window.scrollY + headerOffset + 2;
        let current = sections[0] || null;
        for (let i = 0; i < sections.length; i++) {
          const sec = sections[i];
          if (sec.offsetTop <= fromTop) current = sec;
        }

        hashLinks.forEach(link => link.classList.remove('active'));
        if (current) {
          const activeLink = hashLinks.find(l => l.getAttribute('href') === ('#' + current.id));
          if (activeLink) activeLink.classList.add('active');
        }
      }

      // smooth scroll on click with header offset
      hashLinks.forEach(link => {
        link.addEventListener('click', function (e) {
          e.preventDefault();
          const target = document.querySelector(this.getAttribute('href'));
          if (!target) return;
          const y = target.getBoundingClientRect().top + window.scrollY - headerOffset;
          window.scrollTo({ top: y, behavior: 'smooth' });
        });
      });

      window.addEventListener('scroll', setActiveLink, { passive: true });
      // initial
      setActiveLink();
    });
  </script>
  <footer class="contact" id="contact">
    <div class="contact-content">
      <div class="contact-brand-block">
        <img src="/img/nulp_logo.png" alt="National University Lipa logo" class="contact-brand-logo" />
      </div>

      <div class="contact-details">
        <h3>CONTACT US</h3>
        <ul class="contact-list">
          <li><i class="bi bi-geo-alt-fill"></i><span>NU Bldg. SM City Lipa, JP Laurel Highway, Lipa City, Batangas</span></li>
          <li><i class="bi bi-telephone-fill"></i><span>+63917-568-5472</span></li>
          <li><i class="bi bi-envelope-fill"></i><span>admissions@nu-lipa.edu.ph</span></li>
          <li><i class="bi bi-clock-fill"></i><span>Monday to Friday (8:30AM - 5:30PM); Saturday (8:30AM - 12:30PM)</span></li>
        </ul>
      </div>
    </div>

    <div class="contact-bottom">
      <p>All Rights Reserved. National University</p>
    </div>
  </footer>
</body>
</html>
