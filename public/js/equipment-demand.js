(function () {
  const configNode = document.getElementById('equipment-demand-config');
  const root = document.getElementById('equipment-demand');
  if (!configNode || !root) return;

  const config = JSON.parse(configNode.textContent);
  const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const loading = document.getElementById('equipment-demand-loading');
  const errorBox = document.getElementById('equipment-demand-error');
  const errorText = document.getElementById('equipment-demand-error-text');
  const emptyBox = document.getElementById('equipment-demand-empty');
  const emptyTitle = document.getElementById('equipment-demand-empty-title');
  const emptyCopy = document.getElementById('equipment-demand-empty-copy');
  const results = document.getElementById('equipment-demand-results');
  const termForm = document.getElementById('equipment-term-form');
  const termFormError = document.getElementById('equipment-term-form-error');
  const monthSelect = document.getElementById('equipment-demand-month');
  const yearSelect = document.getElementById('equipment-demand-year');
  const yearFilter = document.getElementById('equipment-demand-academic-year');
  const termSelect = document.getElementById('equipment-demand-term');
  const termLength = document.getElementById('equipment-demand-term-length');

  let dashboardRequestId = 0;
  let lastDashboard = null;
  let terms = [];
  let editingTermId = null;
  let weeklyChart = null;
  let started = false;

  const fallbackMonth = (config.monthKey || '').split('-');
  const state = {
    mode: 'month',
    year: fallbackMonth[0] || String(new Date().getFullYear()),
    month: fallbackMonth[1] || String(new Date().getMonth() + 1).padStart(2, '0'),
    termId: '',
    academicYear: '',
    rank: 'reservations',
    chartItemId: '',
  };

  try {
    const saved = JSON.parse(sessionStorage.getItem('nutilize.equipmentDemand') || 'null');
    if (saved && typeof saved === 'object') {
      Object.keys(state).forEach((key) => {
        if (saved[key] !== undefined && saved[key] !== null) state[key] = String(saved[key]);
      });
    }
  } catch (error) {}

  const saveState = () => {
    try {
      sessionStorage.setItem('nutilize.equipmentDemand', JSON.stringify(state));
    } catch (error) {}
  };

  const equipmentViewActive = () => !document.body.classList.contains('analytics-view-rooms');

  const fillSelect = (select, options, selected) => {
    select.replaceChildren();
    options.forEach((option) => {
      const node = document.createElement('option');
      node.value = String(option.value);
      node.textContent = option.label;
      if (String(option.value) === String(selected)) node.selected = true;
      select.appendChild(node);
    });
  };

  const buildMonthControls = () => {
    fillSelect(monthSelect, monthNames.map((label, index) => ({
      value: String(index + 1).padStart(2, '0'),
      label,
    })), state.month);

    const selectedYear = Number(state.year) || new Date().getFullYear();
    const years = [];
    for (let year = selectedYear - 3; year <= selectedYear + 1; year += 1) years.push(year);
    fillSelect(yearSelect, years.map((year) => ({ value: String(year), label: String(year) })), String(selectedYear));
  };

  const termsForYear = () => terms.filter((term) => term.academic_year === state.academicYear);

  const syncTermControls = () => {
    const years = [];
    terms.forEach((term) => {
      if (!years.includes(term.academic_year)) years.push(term.academic_year);
    });
    if (state.academicYear && !years.includes(state.academicYear)) years.unshift(state.academicYear);
    fillSelect(yearFilter, years.map((year) => ({ value: year, label: year })), state.academicYear);

    const available = termsForYear();
    fillSelect(termSelect, available.map((term) => ({
      value: String(term.id),
      label: term.name,
    })), state.termId);

    const selected = available.find((term) => String(term.id) === String(state.termId)) || available[0];
    if (selected) {
      state.termId = String(selected.id);
      termLength.textContent = `${selected.weeks} weeks · ${selected.starts_on} to ${selected.ends_on}`;
    } else {
      termLength.textContent = years.length ? '' : 'No terms yet';
    }

    document.getElementById('equipment-demand-edit-term').hidden = !selected;
  };

  const applyMode = () => {
    document.querySelectorAll('[data-demand-controls]').forEach((node) => {
      node.hidden = node.getAttribute('data-demand-controls') !== state.mode;
    });
    document.querySelectorAll('[data-demand-mode]').forEach((button) => {
      const selected = button.getAttribute('data-demand-mode') === state.mode;
      button.setAttribute('aria-selected', selected ? 'true' : 'false');
      button.classList.toggle('is-selected', selected);
    });
    document.querySelectorAll('[data-demand-rank]').forEach((button) => {
      const selected = button.getAttribute('data-demand-rank') === state.rank;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });
  };

  const showForm = (term) => {
    termForm.hidden = false;
    editingTermId = term ? term.id : null;
    termForm.querySelector('[name="academic_year"]').value = term ? term.academic_year : (state.academicYear || '');
    termForm.querySelector('[name="name"]').value = term ? term.name : '';
    termForm.querySelector('[name="term_key"]').value = term ? (term.term_key || '') : '';
    termForm.querySelector('[name="starts_on"]').value = term ? term.starts_on : '';
    termForm.querySelector('[name="ends_on"]').value = term ? term.ends_on : '';
    document.getElementById('equipment-term-form-title').textContent = term ? 'Edit academic term' : 'Add academic term';
    termFormError.hidden = true;
  };

  const hideForm = () => {
    termForm.hidden = true;
    editingTermId = null;
    termFormError.hidden = true;
  };

  const setLoading = (active) => {
    loading.hidden = !active;
    loading.setAttribute('aria-busy', active ? 'true' : 'false');
    if (active) {
      results.hidden = true;
      emptyBox.hidden = true;
      const planning = document.getElementById('equipment-planning');
      if (planning) planning.hidden = true;
    }
  };

  const showDashboardError = (message) => {
    errorText.textContent = message || 'The equipment report could not be loaded.';
    errorBox.hidden = false;
  };

  const readError = async (response) => {
    try {
      const payload = await response.json();
      if (payload.errors) {
        const first = Object.values(payload.errors).flat()[0];
        if (first) return String(first);
      }
      if (payload.message) return String(payload.message);
    } catch (error) {}
    return 'The equipment report could not be loaded.';
  };

  const textCell = (value) => {
    const cell = document.createElement('td');
    cell.textContent = value === null || value === undefined || value === '' ? '—' : String(value);
    return cell;
  };

  const renderDefinitions = (definitions) => {
    const list = document.getElementById('equipment-demand-definition-list');
    list.replaceChildren();
    (definitions || []).forEach((line) => {
      const item = document.createElement('li');
      item.textContent = line;
      list.appendChild(item);
    });
  };

  const renderItems = (items) => {
    const body = document.getElementById('equipment-demand-items');
    body.replaceChildren();
    if (!items || items.length === 0) {
      const row = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 5;
      cell.className = 'insight-empty';
      cell.textContent = 'No approved equipment demand in this period.';
      row.appendChild(cell);
      body.appendChild(row);
      return;
    }

    items.forEach((item) => {
      const row = document.createElement('tr');
      row.append(
        textCell(item.item_name),
        textCell(item.reservation_count),
        textCell(item.approved_quantity),
        textCell(item.requested_quantity),
        textCell(item.issued_label || 'Not recorded')
      );
      body.appendChild(row);
    });
  };

  const renderAvailability = (rows) => {
    const body = document.getElementById('equipment-demand-availability');
    body.replaceChildren();
    (rows || []).forEach((item) => {
      const row = document.createElement('tr');
      if (item.shortage) row.classList.add('is-shortage');
      row.append(
        textCell(item.item_name),
        textCell(item.peak_quantity),
        textCell(item.serviceable_stock === null ? 'Not recorded' : item.serviceable_stock),
        textCell(item.comparison)
      );
      body.appendChild(row);
    });
  };

  const renderInsights = (insights) => {
    const list = document.getElementById('equipment-demand-insights');
    list.replaceChildren();
    (insights || []).forEach((insight) => {
      (insight.sentences || []).forEach((sentence) => {
        const item = document.createElement('li');
        item.textContent = sentence;
        list.appendChild(item);
      });
    });
  };

  const renderChart = (weekly) => {
    const canvas = document.getElementById('equipment-weekly-chart');
    const note = document.getElementById('equipment-weekly-note');
    const unit = document.getElementById('equipment-weekly-unit');
    const title = document.getElementById('equipment-demand-chart-title');
    if (title) title.textContent = weekly?.title || 'Weekly demand';
    note.textContent = weekly?.note || '';
    unit.textContent = weekly ? `Unit: ${weekly.unit}` : '';

    const itemSelect = document.getElementById('equipment-weekly-item');
    const options = [{ value: '', label: 'All items (reservation count)' }];
    (lastDashboard?.chart_items || []).forEach((item) => {
      options.push({ value: String(item.item_id), label: item.item_name });
    });
    fillSelect(itemSelect, options, weekly?.item_id ? String(weekly.item_id) : '');

    if (weeklyChart) {
      weeklyChart.destroy();
      weeklyChart = null;
    }
    if (!weekly || !Array.isArray(weekly.points) || typeof Chart === 'undefined') return;

    weeklyChart = new Chart(canvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: weekly.points.map((point) => point.label),
        datasets: [{
          label: weekly.unit,
          data: weekly.points.map((point) => Number(point.value) || 0),
          borderColor: '#4457ab',
          backgroundColor: 'rgba(68, 87, 171, 0.12)',
          fill: true,
          tension: 0.25,
          pointRadius: 3,
          pointBackgroundColor: '#4457ab',
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { grid: { display: false }, ticks: { maxRotation: 50, minRotation: 0, font: { size: 10 } } },
          y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: weekly.unit } },
        },
        plugins: { legend: { display: false } },
      },
    });
  };

  const renderSummary = (summary) => {
    document.getElementById('equipment-summary-reservations').textContent = String(summary.approved_reservations);
    document.getElementById('equipment-summary-types').textContent = String(summary.distinct_items);
    document.getElementById('equipment-summary-returned').textContent = String(summary.marked_returned);
    document.getElementById('equipment-summary-returned-hint').textContent = summary.marked_returned_label;
    document.getElementById('equipment-summary-top').textContent = summary.top_item_name || '—';
    document.getElementById('equipment-summary-top-hint').textContent = summary.top_item_name
      ? `${summary.top_item_reservations} approved reservation${summary.top_item_reservations === 1 ? '' : 's'}`
      : 'No approved equipment reservations';
  };

  const renderDashboard = (payload) => {
    lastDashboard = payload;
    terms = Array.isArray(payload.terms) ? payload.terms : [];
    if (!state.academicYear && terms[0]) state.academicYear = terms[0].academic_year;
    if (payload.period?.term_id) state.termId = String(payload.period.term_id);
    syncTermControls();
    renderDefinitions(payload.definitions);
    applyMode();

    const noTerms = payload.empty_reason === 'no_terms' || payload.empty_reason === 'terms_unavailable';
    if (noTerms) {
      results.hidden = true;
      document.getElementById('equipment-planning').hidden = true;
      emptyBox.hidden = false;
      emptyTitle.textContent = 'Add a term to see demand';
      emptyCopy.textContent = payload.empty_reason === 'terms_unavailable'
        ? 'Academic terms are not available yet.'
        : 'Per term uses start and end dates you enter here. No terms are stored yet.';
      showForm(null);
      return;
    }

    renderPlanning(payload.planning);

    if (payload.empty) {
      results.hidden = true;
      emptyBox.hidden = false;
      const requested = payload.summary?.requested_reservations || 0;
      emptyTitle.textContent = 'No approved equipment demand in this period';
      emptyCopy.textContent = requested > 0
        ? `${payload.period.label} has ${requested} submitted equipment reservation${requested === 1 ? '' : 's'}, and none are in the approved planning set. Rejected, cancelled, timed out, and pending requests stay out of approved demand.`
        : `${payload.period.label} has no equipment reservations whose scheduled use overlaps this period.`;
      document.getElementById('equipment-demand-period-note').textContent = payload.period?.boundary || '';
      return;
    }

    emptyBox.hidden = true;
    results.hidden = false;
    const period = payload.period || {};
    document.getElementById('equipment-demand-period-note').textContent =
      `${period.label} · ${period.starts_on} through ${period.ends_on} · ${period.weeks} weeks · ${period.timezone}. ${period.boundary || ''}`;
    renderSummary(payload.summary);
    renderItems(payload.items);
    renderAvailability(payload.availability);
    renderInsights(payload.insights);
    renderChart(payload.weekly);
    requestAnimationFrame(() => weeklyChart && weeklyChart.resize());
  };

  const renderPlanning = (planning) => {
    const section = document.getElementById('equipment-planning');
    if (!section) return;
    if (!planning || planning.applicable === false) {
      section.hidden = true;
      return;
    }

    section.hidden = false;
    document.getElementById('equipment-planning-message').textContent = planning.message || '';
    const target = planning.target;
    document.getElementById('equipment-planning-target').textContent = target
      ? `Target: ${target.label} (${target.starts_on} to ${target.ends_on}). Matching key: ${target.term_key || 'not set'}.`
      : '';
    const references = planning.references || [];
    document.getElementById('equipment-planning-references').textContent = references.length
      ? `Historical reference: ${references.map((term) => `${term.label} (${term.starts_on} to ${term.ends_on})`).join('; ')}.`
      : 'Historical reference: none.';
    document.getElementById('equipment-planning-detail').textContent = planning.detail || '';
    document.getElementById('equipment-planning-basis').textContent = planning.basis || '';
    document.getElementById('equipment-planning-unmet').textContent = planning.unmet?.note || '';

    const items = planning.items || [];
    document.getElementById('equipment-planning-table').hidden = items.length === 0;
    const body = document.getElementById('equipment-planning-items');
    const notes = document.getElementById('equipment-planning-notes');
    body.replaceChildren();
    notes.replaceChildren();

    items.forEach((item) => {
      const row = document.createElement('tr');
      if ((item.historical_gap || 0) > 0) row.classList.add('is-shortage');
      const history = (item.history || []).map((term) => `${term.label}: ${term.reservations} reservations, peak ${term.peak}`).join('; ');
      row.append(
        textCell(item.item_name),
        textCell(item.historical_reservation_count),
        textCell(`${item.historical_peak} (${item.baseline_term})`),
        textCell(item.serviceable_stock === null ? 'Not recorded' : item.serviceable_stock),
        textCell(item.historical_gap === null ? 'Not recorded' : item.historical_gap),
        textCell(item.confirmed_peak === null ? 'None' : item.confirmed_peak),
        textCell(item.confirmed_gap === null ? 'None' : item.confirmed_gap),
        textCell(item.issued_label || 'Not recorded')
      );
      const historyCell = document.createElement('td');
      historyCell.colSpan = 8;
      historyCell.textContent = history;
      const historyRow = document.createElement('tr');
      historyRow.appendChild(historyCell);
      body.append(row, historyRow);

      (item.sentences || []).forEach((sentence) => {
        const note = document.createElement('li');
        note.textContent = sentence;
        notes.appendChild(note);
      });
    });
  };

  const dashboardParams = () => {
    const params = new URLSearchParams({ mode: state.mode, rank: state.rank });
    if (state.mode === 'term') {
      if (state.termId) params.set('term_id', state.termId);
    } else {
      params.set('year', state.year);
      params.set('month', String(Number(state.month)));
    }
    if (state.chartItemId) params.set('chart_item_id', state.chartItemId);
    return params;
  };

  const loadDashboard = async () => {
    if (!equipmentViewActive()) return;
    const requestId = ++dashboardRequestId;
    setLoading(true);
    errorBox.hidden = true;
    saveState();

    try {
      const response = await fetch(`${config.dashboardUrl}?${dashboardParams().toString()}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (requestId !== dashboardRequestId) return;
      if (!response.ok) throw new Error(await readError(response));
      const payload = await response.json();
      if (requestId !== dashboardRequestId) return;
      if (!payload || payload.ok === false) throw new Error(payload?.message || 'The equipment report could not be loaded.');
      setLoading(false);
      renderDashboard(payload);
    } catch (error) {
      if (requestId !== dashboardRequestId) return;
      setLoading(false);
      showDashboardError(error.message || 'The equipment report could not be loaded.');
      if (lastDashboard) renderDashboard(lastDashboard);
    }
  };

  const activate = () => {
    if (!equipmentViewActive()) return;
    if (!started) {
      started = true;
      buildMonthControls();
      applyMode();
      syncTermControls();
    }
    loadDashboard();
  };

  document.querySelectorAll('[data-demand-mode]').forEach((button) => {
    button.addEventListener('click', () => {
      state.mode = button.getAttribute('data-demand-mode') === 'term' ? 'term' : 'month';
      applyMode();
      if (state.mode === 'month') hideForm();
      loadDashboard();
    });
  });

  monthSelect.addEventListener('change', () => {
    state.month = monthSelect.value;
    loadDashboard();
  });
  yearSelect.addEventListener('change', () => {
    state.year = yearSelect.value;
    buildMonthControls();
    loadDashboard();
  });
  yearFilter.addEventListener('change', () => {
    state.academicYear = yearFilter.value;
    state.termId = '';
    syncTermControls();
    loadDashboard();
  });
  termSelect.addEventListener('change', () => {
    state.termId = termSelect.value;
    syncTermControls();
    loadDashboard();
  });

  document.querySelectorAll('[data-demand-rank]').forEach((button) => {
    button.addEventListener('click', () => {
      state.rank = button.getAttribute('data-demand-rank') === 'quantity' ? 'quantity' : 'reservations';
      applyMode();
      loadDashboard();
    });
  });

  document.getElementById('equipment-weekly-item').addEventListener('change', (event) => {
    state.chartItemId = event.target.value;
    loadDashboard();
  });
  document.getElementById('equipment-demand-retry').addEventListener('click', loadDashboard);
  document.getElementById('equipment-demand-add-term').addEventListener('click', () => {
    state.mode = 'term';
    applyMode();
    showForm(null);
  });
  document.getElementById('equipment-demand-edit-term').addEventListener('click', () => {
    const term = terms.find((item) => String(item.id) === String(state.termId));
    if (term) showForm(term);
  });
  document.getElementById('equipment-term-cancel').addEventListener('click', hideForm);

  termForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    termFormError.hidden = true;
    const body = {
      academic_year: termForm.querySelector('[name="academic_year"]').value.trim(),
      name: termForm.querySelector('[name="name"]').value.trim(),
      term_key: termForm.querySelector('[name="term_key"]').value.trim(),
      starts_on: termForm.querySelector('[name="starts_on"]').value,
      ends_on: termForm.querySelector('[name="ends_on"]').value,
    };
    const url = editingTermId
      ? config.updateTermUrl.replace('__ID__', String(editingTermId))
      : config.storeTermUrl;

    try {
      const response = await fetch(url, {
        method: editingTermId ? 'PATCH' : 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': config.csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
      });
      if (!response.ok) throw new Error(await readError(response));
      const payload = await response.json();
      terms = payload.terms || [];
      state.mode = 'term';
      state.termId = String(payload.term.id);
      state.academicYear = payload.term.academic_year;
      hideForm();
      syncTermControls();
      applyMode();
      loadDashboard();
    } catch (error) {
      termFormError.textContent = error.message || 'The term could not be saved.';
      termFormError.hidden = false;
    }
  });

  document.addEventListener('analytics-view-changed', (event) => {
    if (event.detail?.view === 'equipment') activate();
    else if (weeklyChart) weeklyChart.resize();
  });

  buildMonthControls();
  applyMode();
  if (equipmentViewActive()) activate();
})();
