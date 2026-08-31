<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="icon" type="image/png" href="/img/nutilize_favicon.png" />
  <title>NUtilize | Home</title>

  {{-- Preload LCP hero (WebP only — avoid downloading JPEG + WebP) --}}
  <link rel="preload" as="image" href="/img/nulipa_front.webp" type="image/webp" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="/css/landing.css?v=6" />
</head>
<body>
  <header class="top-header">
    <div class="header-inner">
      <a href="/" class="brand" aria-label="NU-TILIZE home">
        <picture>
          <source srcset="/img/nutilize_logo.webp" type="image/webp" />
          <img
            src="/img/nutilize_logo.png"
            alt="NU-TILIZE"
            class="brand-logo"
            width="146"
            height="49"
            decoding="async"
          />
        </picture>
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
          <svg class="icon" viewBox="0 0 16 16" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M14.222 9.374c1.037-.61 1.037-2.137 0-2.748L11.528 5.04 8.32 8.115l3.207 3.074 2.695-1.815zm-3.595 2.116L7.583 8.68 1.03 14.73c.201.248.5.396.826.396.137 0 .274-.028.404-.086l8.367-3.55zM1 1.274C.966 1.345.95 1.421.95 1.5v13c0 .079.016.155.05.226l6.633-6.35L1 1.274zm.86-.78 6.722 6.434 3.207-3.075L3.23.59A.996.996 0 0 0 1.86.494z"/></svg>
          Get the NUtilize App
        </a>
      </div>
    </section>
  </main>

  <section class="placeholder-section" aria-hidden="true"></section>
    <section class="facilities" id="facilities">
      <div class="facilities-content">
        <h2 class="facilities-title">OUR FACILITIES</h2>
        <p class="facilities-subtitle">Explore the facilities we manage at NUtilize and see how platform helps keep everything running smoothly.</p>

        <div class="facilities-grid">
          <!-- Classrooms Card -->
          <div class="facility-card">
            <div class="facility-image">
              <picture>
                <source srcset="/img/nu_classroom.webp" type="image/webp" />
                <img
                  src="/img/nu_classroom.jpg"
                  alt="Classrooms"
                  width="800"
                  height="600"
                  loading="lazy"
                  decoding="async"
                />
              </picture>
            </div>
            <div class="facility-info">
              <h3 class="facility-name">CLASSROOMS</h3>
              <p class="facility-description">Modern classrooms designed to support effective learning through organized scheduling and optimized space utilization.</p>
            </div>
          </div>

          <!-- Gym Card -->
          <div class="facility-card">
            <div class="facility-image">
              <picture>
                <source srcset="/img/nu_gym.webp" type="image/webp" />
                <img
                  src="/img/nu_gym.jpg"
                  alt="Gym"
                  width="800"
                  height="600"
                  loading="lazy"
                  decoding="async"
                />
              </picture>
            </div>
            <div class="facility-info">
              <h3 class="facility-name">GYM</h3>
              <p class="facility-description">Well-maintained gym facilities that promote fitness, school activities, and a safe environment for physical development.</p>
            </div>
          </div>

          <!-- Computer Lab Card -->
          <div class="facility-card">
            <div class="facility-image">
              <picture>
                <source srcset="/img/nu_comlab.webp" type="image/webp" />
                <img
                  src="/img/nu_comlab.jpg"
                  alt="Computer Lab"
                  width="800"
                  height="600"
                  loading="lazy"
                  decoding="async"
                />
              </picture>
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
            <svg class="icon" viewBox="0 0 16 16" width="28" height="28" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 14.5A1.5 1.5 0 0 1 1.5 13V3A1.5 1.5 0 0 1 3 1.5h8a.5.5 0 0 1 0 1H3a.5.5 0 0 0-.5.5v10a.5.5 0 0 0 .5.5h10a.5.5 0 0 0 .5-.5V8a.5.5 0 0 1 1 0v5a1.5 1.5 0 0 1-1.5 1.5z"/><path fill="currentColor" d="m8.354 10.354 7-7a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0"/></svg>
          </div>
          <h3 class="about-card-title">Streamlined Operation</h3>
          <p class="about-card-desc">Simplify facility management with an organized system that reduces manual work and improves overall efficiency.</p>
        </div>

        <div class="about-card">
          <div class="about-icon">
            <svg class="icon" viewBox="0 0 16 16" width="28" height="28" aria-hidden="true" focusable="false"><path fill="currentColor" d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/></svg>
          </div>
          <h3 class="about-card-title">Real-Time Monitoring</h3>
          <p class="about-card-desc">Stay updated with instant access to facility status, helping you respond quickly and make informed decisions.</p>
        </div>

        <div class="about-card">
          <div class="about-icon">
            <svg class="icon" viewBox="0 0 16 16" width="28" height="28" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.8 11.8 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7 7 0 0 0 1.048-.625 11.8 11.8 0 0 0 2.517-2.453c1.678-2.195 3.061-5.513 2.465-9.99a1.54 1.54 0 0 0-1.044-1.263 62.7 62.7 0 0 0-2.887-.87C9.843.266 8.69 0 8 0m2.146 5.146a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793z"/></svg>
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

      // #region agent log
      try {
        const imgs = Array.from(document.images).map((img) => ({
          src: (img.currentSrc || img.src || '').split('/').pop(),
          naturalWidth: img.naturalWidth,
          naturalHeight: img.naturalHeight,
          displayW: Math.round(img.getBoundingClientRect().width),
          displayH: Math.round(img.getBoundingClientRect().height),
          hasWH: img.hasAttribute('width') && img.hasAttribute('height'),
        }));
        fetch('http://127.0.0.1:7591/ingest/35e57a72-783b-42fe-bb4e-563f8b0a56b3', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Debug-Session-Id': 'fa7298' },
          body: JSON.stringify({
            sessionId: 'fa7298',
            runId: 'landing-perf',
            hypothesisId: 'A,B',
            location: 'index.blade.php:DOMContentLoaded',
            message: 'landing image metrics',
            data: { imgCount: imgs.length, imgs, speedIndexHintMs: Math.round(performance.now()) },
            timestamp: Date.now(),
          }),
        }).catch(() => {});
      } catch (_e) {}
      // #endregion
    });
  </script>
  <footer class="contact" id="contact">
    <div class="contact-content">
      <div class="contact-brand-block">
        <picture>
          <source srcset="/img/nulp_logo.webp" type="image/webp" />
          <img
            src="/img/nulp_logo.png"
            alt="National University Lipa logo"
            class="contact-brand-logo"
            width="600"
            height="426"
            loading="lazy"
            decoding="async"
          />
        </picture>
      </div>

      <div class="contact-details">
        <h3>CONTACT US</h3>
        <ul class="contact-list">
          <li><svg class="icon" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/></svg><span>NU Bldg. SM City Lipa, JP Laurel Highway, Lipa City, Batangas</span></li>
          <li><svg class="icon" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.438.543.46 1.307.11 1.87L5.495 6.32a11.1 11.1 0 0 0 4.186 4.186l1.47-.905c.563-.35 1.327-.328 1.87.11l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/></svg><span>+63917-568-5472</span></li>
          <li><svg class="icon" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414zM0 4.697v7.104l5.803-3.558zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586zm3.436-.586L16 11.801V4.697z"/></svg><span>admissions@nu-lipa.edu.ph</span></li>
          <li><svg class="icon" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/></svg><span>Monday to Friday (8:30AM - 5:30PM); Saturday (8:30AM - 12:30PM)</span></li>
        </ul>
      </div>
    </div>

    <div class="contact-bottom">
      <p>All Rights Reserved. National University</p>
    </div>
  </footer>
</body>
</html>
