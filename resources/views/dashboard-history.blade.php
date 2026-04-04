<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NUtilize | History</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="/css/db-inventory.css" />
</head>
<body>
  <header class="top-header">
    <div class="top-header-inner toolbar-card">
      <img src="/img/nutilize_logo.png" alt="NU-TILIZE" class="toolbar-logo" />

      <div class="search-wrap">
        <i class="bi bi-search"></i>
        <input id="dashboard-search" type="text" placeholder="Search" />
      </div>

      <button class="toolbar-icon" type="button" aria-label="Messages">
        <i class="bi bi-chat-fill"></i>
      </button>
      <button class="toolbar-icon" type="button" aria-label="Notifications">
        <i class="bi bi-bell-fill"></i>
      </button>
      <button class="profile-btn" type="button" aria-label="Profile">
        <i class="bi bi-person-circle"></i>
      </button>
    </div>
  </header>

  <main class="dashboard-shell">
    <section class="workspace-grid">
      <div id="navbar-container"></div>

      <section class="content-card history-content-card">
        <h1 class="section-title">LENDING HISTORY</h1>

        <section class="history-head-row">
          <div>
            <p><i class="bi bi-clock-history"></i> Lending Details</p>
          </div>

          <div class="history-head-actions">
            <button class="history-print-btn" type="button" onclick="window.print()">Print File</button>
          </div>
        </section>

        <section class="history-filter-row">
          <div class="history-tab-group">
            <button class="history-tab active" type="button" data-history-tab="latest">Latest</button>
            <button class="history-tab" type="button" data-history-tab="oldest">Oldest</button>
            <button class="history-tab" type="button" data-history-tab="damaged">Damaged</button>
          </div>
          <button
            class="history-email-btn"
            type="button"
            onclick="window.location.href='mailto:?subject=NU-TILIZE%20Lending%20History&body=Please%20review%20the%20latest%20lending%20history%20report.'"
          >
            Send to Email
          </button>
        </section>

        <section class="inventory-grid history-grid">
          <div class="table-wrap">
            <table class="inventory-table history-table">
              <thead>
                <tr>
                  <th><i class="bi bi-credit-card-2-front-fill"></i> Lending ID</th>
                  <th><i class="bi bi-person-workspace"></i> User Name</th>
                  <th><i class="bi bi-calendar3"></i> Date</th>
                  <th><i class="bi bi-pc-display-horizontal"></i> Item Borrowed</th>
                  <th><i class="bi bi-archive-fill"></i> Item Status</th>
                </tr>
              </thead>
              <tbody id="history-table-body">
                <tr>
                  <td>#74fAy51</td>
                  <td>Marites Espinal</td>
                  <td>01/05/2026 - 01/08/2026</td>
                  <td>Room 543</td>
                  <td>Returned</td>
                </tr>
                <tr>
                  <td>#X9D2k8A</td>
                  <td>Ryan Mendoza</td>
                  <td>02/09/2026 - 02/16/2026</td>
                  <td>Tablet</td>
                  <td>Returned</td>
                </tr>
                <tr>
                  <td>#4fHqWZ7</td>
                  <td>Angela Cruz</td>
                  <td>03/01/2026 - 03/04/2026</td>
                  <td>Router</td>
                  <td>Returned</td>
                </tr>
                <tr>
                  <td>#R8A3xM9</td>
                  <td>John Mark Padilla</td>
                  <td>04/12/2026 - 04/14/2026</td>
                  <td>Printer</td>
                  <td>Returned</td>
                </tr>
                <tr>
                  <td>#3Fq8Dk2</td>
                  <td>Carlo Miguel Lim</td>
                  <td>06/03/2026 - 06/07/2026</td>
                  <td>Laptop</td>
                  <td>Returned</td>
                </tr>
                <tr>
                  <td>#9A7MzxQ</td>
                  <td>Mark Lester Dizon</td>
                  <td>07/18/2026 - 07/22/2026</td>
                  <td>Library</td>
                  <td>Returned</td>
                </tr>
                <tr>
                  <td>#W5DkF8R</td>
                  <td>Grace Valdez</td>
                  <td>08/01/2026 - 08/05/2026</td>
                  <td>Room 203</td>
                  <td>Returned</td>
                </tr>
                <tr>
                  <td>#X2q9A7M</td>
                  <td>Faith Delgado</td>
                  <td>09/10/2026 - 09/13/2026</td>
                  <td>AVR Room</td>
                  <td>Returned</td>
                </tr>
                <tr>
                  <td>#P7Lm2Qx</td>
                  <td>Janine Flores</td>
                  <td>10/08/2026 - 10/10/2026</td>
                  <td>Projector</td>
                  <td>Returned</td>
                </tr>
                <tr>
                  <td>#K1Dz9Rv</td>
                  <td>Noel Ramos</td>
                  <td>11/02/2026 - 11/03/2026</td>
                  <td>Sound Mixer</td>
                  <td>Returned</td>
                </tr>
                <tr>
                  <td>#N6Qw4By</td>
                  <td>Sarah Castillo</td>
                  <td>11/18/2026 - 11/19/2026</td>
                  <td>Wireless Mic</td>
                  <td>Returned</td>
                </tr>
                <tr>
                  <td>#T3Va8Hp</td>
                  <td>Patrick Javier</td>
                  <td>12/05/2026 - 12/09/2026</td>
                  <td>TV</td>
                  <td>Returned</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </section>
    </section>
  </main>

  <script src="/js/dashboard.js"></script>
</body>
</html>

