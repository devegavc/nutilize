const searchInput = document.getElementById('dashboard-search');
const reportTableBody = document.getElementById('report-table-body');
const inventoryTableBody = document.getElementById('inventory-table-body');
const historyTableBody = document.getElementById('history-table-body');
const maintenanceTableBody = document.getElementById('maintenance-table-body');
const facilitiesTableBody = document.getElementById('facilities-table-body');
const equipmentTableBody = document.getElementById('equipment-table-body');
const workloadProgress = document.getElementById('workload-progress');
const workloadLabel = document.getElementById('workload-label');
const inventoryShortcut = document.getElementById('inventory-shortcut');
const navbarContainer = document.getElementById('navbar-container');
const facilitiesTabs = document.querySelectorAll('.facilities-tab');
const facilitiesInlineSearchInput = document.querySelector('.facilities-inline-search input');
const facilitiesEditModal = document.getElementById('facilities-edit-modal');
const facilitiesItemNameInput = document.getElementById('facility-item-name');
const facilitiesCategoryInput = document.getElementById('facility-category');
const facilitiesDescriptionInput = document.getElementById('facility-description');
const facilitiesCancelButton = document.getElementById('facility-cancel-btn');
const facilitiesSaveButton = document.getElementById('facility-save-btn');
const facilitiesAddButton = document.getElementById('facilities-add-btn');
const facilitiesModalTitle = document.getElementById('facilities-modal-title');
const facilitiesUploadInput = document.getElementById('facility-upload-input');
const facilitiesUploadButton = document.getElementById('facility-upload-btn');
const facilitiesUploadName = document.getElementById('facility-upload-name');
const equipmentEditModal = document.getElementById('equipment-edit-modal');
const equipmentItemNameInput = document.getElementById('equipment-item-name');
const equipmentCategoryInput = document.getElementById('equipment-category');
const equipmentTotalCountInput = document.getElementById('equipment-total-count');
const equipmentInUseInput = document.getElementById('equipment-in-use');
const equipmentStatusInput = document.getElementById('equipment-status');
const equipmentDescriptionInput = document.getElementById('equipment-description');
const equipmentCancelButton = document.getElementById('equipment-cancel-btn');
const equipmentSaveButton = document.getElementById('equipment-save-btn');
const equipmentAddButton = document.getElementById('equipment-add-btn');
const equipmentModalTitle = document.getElementById('equipment-modal-title');
const equipmentUploadInput = document.getElementById('equipment-upload-input');
const equipmentUploadButton = document.getElementById('equipment-upload-btn');
const equipmentUploadName = document.getElementById('equipment-upload-name');
const equipmentTabs = document.querySelectorAll('[data-equipment-tab]');
const historyTabs = document.querySelectorAll('[data-history-tab]');
const maintenanceTabs = document.querySelectorAll('[data-maintenance-tab]');
const equipmentInlineSearchInput = document.getElementById('equipment-inline-search');
const maintenanceInlineSearchInput = document.getElementById('maintenance-inline-search');
const maintenanceEvalModal = document.getElementById('maintenance-eval-modal');
const maintenanceEvalItemName = document.getElementById('maintenance-eval-item-name');
const maintenanceEvalReason = document.getElementById('maintenance-eval-reason');
const maintenanceEvalBackButton = document.getElementById('maintenance-eval-back-btn');
const maintenanceEvalSettleButton = document.getElementById('maintenance-eval-settle-btn');
const maintenanceFormModal = document.getElementById('maintenance-form-modal');
const maintenanceFormItemName = document.getElementById('maintenance-form-item-name');
const maintenanceAssessmentInput = document.getElementById('maintenance-assessment-input');
const maintenanceStatusSelect = document.getElementById('maintenance-status-select');
const maintenanceFormSubmitButton = document.getElementById('maintenance-form-submit-btn');
const scheduleFilterButtons = document.querySelectorAll('[data-schedule-filter]');
const scheduleDayCells = document.querySelectorAll('.calendar-grid .day[data-day]');
const scheduleRequestModal = document.getElementById('schedule-request-modal');
const scheduleRequestBody = document.getElementById('schedule-request-body');
const scheduleModalDate = document.getElementById('schedule-modal-date');
const scheduleInlineDate = document.getElementById('schedule-inline-date');
const scheduleInlineRequestBody = document.getElementById('schedule-inline-request-body');
const scheduleInlineDetailName = document.getElementById('schedule-inline-detail-name');
const scheduleInlineDetailTitle = document.getElementById('schedule-inline-detail-title');
const scheduleInlineDetailDate = document.getElementById('schedule-inline-detail-date');
const scheduleInlineDetailTime = document.getElementById('schedule-inline-detail-time');
const scheduleInlineDetailAttendance = document.getElementById('schedule-inline-detail-attendance');
const scheduleInlineDetailResource = document.getElementById('schedule-inline-detail-resource');
const scheduleInlineDetailChairs = document.getElementById('schedule-inline-detail-chairs');
const scheduleInlineDetailTables = document.getElementById('schedule-inline-detail-tables');
const requestItems = document.querySelectorAll('.request-item');
const requestTabs = document.querySelectorAll('[data-request-tab]');
const requestContentCard = document.querySelector('.request-content-card');
const scheduleDetailModal = document.getElementById('schedule-detail-modal');
const scheduleDetailName = document.getElementById('schedule-detail-name');
const scheduleDetailTitleActivity = document.getElementById('schedule-detail-title-activity');
const scheduleDetailDate = document.getElementById('schedule-detail-date');
const scheduleDetailTime = document.getElementById('schedule-detail-time');
const scheduleDetailAttendance = document.getElementById('schedule-detail-attendance');
const scheduleDetailResource = document.getElementById('schedule-detail-resource');
const scheduleDetailChairs = document.getElementById('schedule-detail-chairs');
const scheduleDetailTables = document.getElementById('schedule-detail-tables');
const scheduleDetailCancel = document.getElementById('schedule-detail-cancel');
const profileEditButton = document.querySelector('.profile-edit-btn');
const profileAvatar = document.getElementById('profile-avatar');
const profileAvatarImage = document.getElementById('profile-avatar-image');
const profileFirstNameInput = document.getElementById('profile-first-name');
const profileMiddleNameInput = document.getElementById('profile-middle-name');
const profileLastNameInput = document.getElementById('profile-last-name');
const profileSuffixInput = document.getElementById('profile-suffix');
const profileAdminIdInput = document.getElementById('profile-admin-id');
const profileEmailInput = document.getElementById('profile-email');
const profileContactInput = document.getElementById('profile-contact');
const profilePhoneInput = document.getElementById('profile-phone');
const profileEditModal = document.getElementById('profile-edit-modal');
const profileModalFirstNameInput = document.getElementById('profile-modal-first-name');
const profileModalMiddleNameInput = document.getElementById('profile-modal-middle-name');
const profileModalLastNameInput = document.getElementById('profile-modal-last-name');
const profileModalSuffixInput = document.getElementById('profile-modal-suffix');
const profileModalAdminIdInput = document.getElementById('profile-modal-admin-id');
const profileModalEmailInput = document.getElementById('profile-modal-email');
const profileModalContactInput = document.getElementById('profile-modal-contact');
const profileModalPhoneInput = document.getElementById('profile-modal-phone');
const profileEditCancelButton = document.getElementById('profile-edit-cancel-btn');
const profileEditSaveButton = document.getElementById('profile-edit-save-btn');
const profileEditAvatar = document.getElementById('profile-edit-avatar');
const profileEditAvatarImage = document.getElementById('profile-edit-avatar-image');
const profileEditUploadButton = document.getElementById('profile-edit-upload-btn');
const profileAvatarUploadInput = document.getElementById('profile-avatar-upload');
const toolbarMessageButtons = document.querySelectorAll('.toolbar-icon[aria-label="Messages"]');
const toolbarNotificationButtons = document.querySelectorAll('.toolbar-icon[aria-label="Notifications"]');
const toolbarProfileButtons = document.querySelectorAll('.profile-btn[aria-label="Profile"]');
const messageContacts = document.querySelectorAll('[data-message-contact]');
const messageCurrentName = document.getElementById('message-current-name');
const messageEmptyState = document.getElementById('message-empty-state');
const messageThread = document.getElementById('message-thread');
const messageThreadWrap = document.getElementById('message-thread-wrap');
const messageForm = document.getElementById('message-form');
const messageInput = document.getElementById('message-input');
const toolbarSearchWrap = searchInput ? searchInput.closest('.search-wrap') : null;

let activeFacilitiesTab = 'rooms';
let activeEquipmentTab = 'multimedia';
let activeHistoryTab = 'latest';
let activeMaintenanceTab = 'maintenance';
let activeEditingRow = null;
let activeEquipmentEditingRow = null;
let activeScheduleCategory = 'all';
let visibleScheduleRequests = [];
let visibleScheduleInlineRequests = [];
let selectedScheduleDay = null;
let messagePopover = null;
let activeMessageButton = null;
let notificationPopover = null;
let activeNotificationButton = null;
let profilePopover = null;
let activeProfileButton = null;
let pendingProfileAvatarDataUrl = '';
let sidebarToggleButton = null;
let sidebarBackdrop = null;
let isToolbarSearchExpanded = false;
let messageOutsidePointerHandlerBound = false;
let activeMaintenanceAddressRow = null;

const notificationItems = [
  { name: 'Maria Lerma', unread: true },
  { name: 'Joel Enriquez', unread: true },
  { name: 'Mars Fha Uthang', unread: false },
  { name: 'Maria Santos', unread: true },
  { name: 'Anne Lopez', unread: false },
  { name: 'Juan Dela Cruz', unread: false },
  { name: 'Phenge Pira', unread: false },
  { name: 'Jepoy Dizon', unread: true },
  { name: 'Andrew Dano', unread: true },
];

const messagePreviewItems = [
  { name: 'Dela Cruz, Jon', unread: true, snippet: 'Sent a photo' },
  { name: 'Santos, Ivan', unread: true, snippet: 'Can we reserve room 502?' },
  { name: 'Rivera, Martin', unread: false, snippet: 'Thank you for the update' },
  { name: 'Gonzales, Pat', unread: false, snippet: 'Noted on this one' },
  { name: 'Tan, Maricar', unread: true, snippet: 'Follow up on request status' },
  { name: 'Ramirez, Carla', unread: false, snippet: 'Will submit by 4:00 PM' },
  { name: 'Custudio, Van', unread: false, snippet: 'Received the schedule' },
  { name: 'De Vega, Val', unread: true, snippet: 'Can we move it tomorrow?' },
];

const maintenanceRowsByTab = (window.maintenanceRowsByTab && typeof window.maintenanceRowsByTab === 'object')
  ? window.maintenanceRowsByTab
  : {
  maintenance: [
    { id: '#9985fht', item: 'Podium', count: '1', date: '31/03/2025', status: 'Maintenance', statusClass: 'maintenance', location: 'Storage A' },
    { id: '#X9D2k8A', item: 'HDMI', count: '3', date: '30/03/2025', status: 'Maintenance', statusClass: 'maintenance', location: 'Storage B' },
    { id: '#4fHqWZ7', item: 'Tripod', count: '1', date: '29/03/2025', status: 'Maintenance', statusClass: 'maintenance', location: 'Storage A' },
    { id: '#R8A3xM9', item: 'Lapel Mics', count: '2', date: '28/03/2025', status: 'Maintenance', statusClass: 'maintenance', location: 'Storage A' },
    { id: '#3Fq8Dk2', item: 'Speaker', count: '1', date: '27/03/2025', status: 'Maintenance', statusClass: 'maintenance', location: 'Storage A' },
    { id: '#9A7MzxQ', item: 'Tripod', count: '2', date: '28/03/2025', status: 'Maintenance', statusClass: 'maintenance', location: 'Storage A' },
    { id: '#W5DkF8R', item: 'Camera', count: '1', date: '26/03/2025', status: 'Maintenance', statusClass: 'maintenance', location: 'Storage A' },
  ],
  damaged: [
    { id: '#9985fht', item: 'Projector', count: '1', date: '31/03/2025', status: 'Damaged', statusClass: 'damaged', location: 'Storage A' },
    { id: '#X9D2k8A', item: 'Port Dongle', count: '2', date: '30/03/2025', status: 'Damaged', statusClass: 'damaged', location: 'Storage B' },
    { id: '#4fHqWZ7', item: 'Industrial fan', count: '4', date: '29/03/2025', status: 'Damaged', statusClass: 'damaged', location: 'Storage C' },
    { id: '#R8A3xM9', item: 'Lapel Mics', count: '2', date: '28/03/2025', status: 'Damaged', statusClass: 'damaged', location: 'Storage A' },
    { id: '#3Fq8Dk2', item: 'power strip', count: '2', date: '27/03/2025', status: 'Damaged', statusClass: 'damaged', location: 'Storage B' },
    { id: '#9A7MzxQ', item: 'Extension Cords', count: '1', date: '28/03/2025', status: 'Damaged', statusClass: 'damaged', location: 'Storage B' },
    { id: '#W5DkF8R', item: 'Wired Mic', count: '2', date: '26/03/2025', status: 'Damaged', statusClass: 'damaged', location: 'Storage A' },
  ],
};

const historyRowsByTab = {
  latest: [
    { id: '#74fAy51', user: 'Marites Espinal', date: '01/05/2026 - 01/08/2026', item: 'Room 543', status: 'Returned' },
    { id: '#X9D2k8A', user: 'Ryan Mendoza', date: '02/09/2026 - 02/16/2026', item: 'Tablet', status: 'Returned' },
    { id: '#4fHqWZ7', user: 'Angela Cruz', date: '03/01/2026 - 03/04/2026', item: 'Router', status: 'Returned' },
    { id: '#R8A3xM9', user: 'John Mark Padilla', date: '04/12/2026 - 04/14/2026', item: 'Printer', status: 'Returned' },
    { id: '#3Fq8Dk2', user: 'Carlo Miguel Lim', date: '06/03/2026 - 06/07/2026', item: 'Laptop', status: 'Returned' },
    { id: '#9A7MzxQ', user: 'Mark Lester Dizon', date: '07/18/2026 - 07/22/2026', item: 'Library', status: 'Returned' },
    { id: '#W5DkF8R', user: 'Grace Valdez', date: '08/01/2026 - 08/05/2026', item: 'Room 203', status: 'Returned' },
    { id: '#X2q9A7M', user: 'Faith Delgado', date: '09/10/2026 - 09/13/2026', item: 'AVR Room', status: 'Returned' },
  ],
  oldest: [
    { id: '#34fDy56', user: 'Mariah Espenosa', date: '03/01/2025 - 03/04/2025', item: 'Room 543', status: 'Returned' },
    { id: '#7Aq9Xf2', user: 'Juan Dela Cruz', date: '01/05/2025 - 01/08/2025', item: 'Conference Room', status: 'Returned' },
    { id: '#B4mT8eK', user: 'Maria Santos', date: '02/10/2025 - 02/15/2025', item: 'Podium', status: 'Returned' },
    { id: '#9fD2QwA', user: 'Mark Villanueva', date: '03/01/2025 - 03/04/2025', item: 'Barbell', status: 'Returned' },
    { id: '#L8Zp3Rk', user: 'Anne Lopez', date: '04/12/2025 - 04/14/2025', item: 'Dumbbell Set', status: 'Returned' },
    { id: '#5Mxd7QH', user: 'Joshua Reyes', date: '05/20/2025 - 05/25/2025', item: 'Ladder', status: 'Returned' },
    { id: '#A2W9fK6', user: 'Paul Garcia', date: '06/03/2025 - 06/07/2025', item: 'Room 203', status: 'Returned' },
  ],
  damaged: [
    { id: '#F7B2kL8', user: 'Mariah Espenosa', date: '01/03/2025 - 01/11/2025', item: 'Camera', status: 'Damaged' },
    { id: '#X1C9vP4', user: 'Liana Cortez', date: '02/07/2025 - 02/19/2025', item: 'Calculator', status: 'Damaged' },
    { id: '#M5D8qJ2', user: 'Selina Marquez', date: '03/02/2025 - 03/14/2025', item: 'Lab Kits', status: 'Damaged' },
    { id: '#Z3H6rT7', user: 'Althea Villanueva', date: '04/05/2025 - 04/22/2025', item: 'Notebooks', status: 'Damaged' },
    { id: '#Q4L0bN5', user: 'Kiara Santos', date: '05/01/2025 - 05/18/2025', item: 'Lab Coats', status: 'Damaged' },
    { id: '#V9A1xK3', user: 'Danica Ordonez', date: '06/04/2025 - 06/20/2025', item: 'Music Room', status: 'Damaged' },
    { id: '#S2G7mR6', user: 'Amara Reyes', date: '07/09/2025 - 07/25/2025', item: 'Speakers', status: 'Damaged' },
    { id: '#T8E5pW1', user: 'Celina Navarro', date: '08/02/2025 - 08/19/2025', item: 'Art Supplies', status: 'Damaged' },
  ],
};

function applyHistoryFilters() {
  if (!historyTableBody) {
    return;
  }

  const rows = historyRowsByTab[activeHistoryTab] || historyRowsByTab.latest;
  const term = searchInput ? searchInput.value.trim().toLowerCase() : '';
  const filteredRows = term
    ? rows.filter((row) => Object.values(row).join(' ').toLowerCase().includes(term))
    : rows;

  if (!filteredRows.length) {
    historyTableBody.innerHTML = `
      <tr>
        <td colspan="5">No history records found.</td>
      </tr>
    `;
    return;
  }

  historyTableBody.innerHTML = filteredRows
    .map((row) => `
      <tr>
        <td>${row.id}</td>
        <td>${row.user}</td>
        <td>${row.date}</td>
        <td>${row.item}</td>
        <td>${row.status}</td>
      </tr>
    `)
    .join('');
}

function applyMaintenanceFilters() {
  if (!maintenanceTableBody) {
    return;
  }

  const rows = maintenanceRowsByTab[activeMaintenanceTab] || maintenanceRowsByTab.maintenance;
  const topTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
  const inlineTerm = maintenanceInlineSearchInput ? maintenanceInlineSearchInput.value.trim().toLowerCase() : '';

  const filteredRows = rows.filter((row) => {
    const rowText = `${row.id} ${row.item} ${row.count} ${row.date} ${row.status} ${row.location}`.toLowerCase();
    const matchesTopSearch = !topTerm || rowText.includes(topTerm);
    const matchesInlineSearch = !inlineTerm || rowText.includes(inlineTerm);

    return matchesTopSearch && matchesInlineSearch;
  });

  if (!filteredRows.length) {
    maintenanceTableBody.innerHTML = `
      <tr>
        <td colspan="7">No maintenance records found.</td>
      </tr>
    `;
    return;
  }

  maintenanceTableBody.innerHTML = filteredRows
    .map((row) => `
      <tr>
        <td>${row.id}</td>
        <td>${row.item}</td>
        <td>${row.count}</td>
        <td>${row.date}</td>
        <td><span class="maintenance-status ${row.statusClass}">${row.status}</span></td>
        <td>${row.location}</td>
        <td><button class="maintenance-action-btn" type="button">Address</button></td>
      </tr>
    `)
    .join('');
}

function closeMaintenanceEvalModal() {
  if (!maintenanceEvalModal) {
    return;
  }

  maintenanceEvalModal.classList.remove('is-open');
  maintenanceEvalModal.setAttribute('aria-hidden', 'true');
}

function openMaintenanceEvalModal(row) {
  if (!maintenanceEvalModal) {
    return;
  }

  activeMaintenanceAddressRow = row;
  const itemCell = row ? row.children[1] : null;
  const countCell = row ? row.children[2] : null;
  const itemName = itemCell ? itemCell.textContent.trim() : 'Podium';
  const itemCount = countCell ? countCell.textContent.trim() : '10';

  if (maintenanceEvalItemName) {
    maintenanceEvalItemName.textContent = itemName || '-';
  }

  if (maintenanceEvalReason) {
    maintenanceEvalReason.textContent = `${itemCount || '10'}x Used`;
  }

  if (maintenanceFormItemName) {
    maintenanceFormItemName.textContent = itemName || '-';
  }

  maintenanceEvalModal.classList.add('is-open');
  maintenanceEvalModal.setAttribute('aria-hidden', 'false');
}

function closeMaintenanceFormModal() {
  if (!maintenanceFormModal) {
    return;
  }

  maintenanceFormModal.classList.remove('is-open');
  maintenanceFormModal.setAttribute('aria-hidden', 'true');
}

function openMaintenanceFormModal() {
  if (!maintenanceFormModal) {
    return;
  }

  if (maintenanceAssessmentInput) {
    maintenanceAssessmentInput.value = '';
  }

  if (maintenanceStatusSelect) {
    maintenanceStatusSelect.value = '';
  }

  maintenanceFormModal.classList.add('is-open');
  maintenanceFormModal.setAttribute('aria-hidden', 'false');
}

const scheduleMarkedDays = {
  all: [4, 5, 7, 10, 12, 13, 16, 18, 21, 27],
  rooms: [4, 7, 21],
  tv: [4, 12, 16, 21],
  speaker: [4, 5, 13, 27],
  furniture: [10, 18],
};

const scheduleRequestData = {
  4: [
    { studentId: '2021-182127', resource: 'ROOM 525', category: 'rooms', name: 'Menesis, Archie', title: 'Faculty Meeting', time: '1:00 PM - 3:00 PM', attendance: '10 People', chairs: 20, tables: 5 },
    { studentId: '2021-182127', resource: 'TV', category: 'tv', name: 'Menesis, Archie', title: 'Faculty Meeting', time: '1:00 PM - 3:00 PM', attendance: '10 People', chairs: 0, tables: 0 },
    { studentId: '2021-182127', resource: 'SPEAKER', category: 'speaker', name: 'Menesis, Archie', title: 'Faculty Meeting', time: '1:00 PM - 3:00 PM', attendance: '10 People', chairs: 0, tables: 0 },
    { studentId: '2021-182127', resource: 'FURNITURE', category: 'furniture', name: 'Menesis, Archie', title: 'Faculty Meeting', time: '1:00 PM - 3:00 PM', attendance: '10 People', chairs: 20, tables: 5 },
    { studentId: '2021-182127', resource: 'TV', category: 'tv', name: 'Menesis, Archie', title: 'Faculty Meeting', time: '1:00 PM - 3:00 PM', attendance: '10 People', chairs: 0, tables: 0 },
  ],
  5: [
    { studentId: '2021-182145', resource: 'SPEAKER', category: 'speaker' },
  ],
  7: [
    { studentId: '2021-182165', resource: 'ROOM 401', category: 'rooms' },
  ],
  10: [
    { studentId: '2021-182178', resource: 'FURNITURE', category: 'furniture' },
  ],
  12: [
    { studentId: '2021-182188', resource: 'TV', category: 'tv' },
  ],
  13: [
    { studentId: '2021-182194', resource: 'SPEAKER', category: 'speaker' },
  ],
  16: [
    { studentId: '2021-182201', resource: 'TV', category: 'tv' },
  ],
  18: [
    { studentId: '2021-182214', resource: 'FURNITURE', category: 'furniture' },
  ],
  21: [
    { studentId: '2021-182225', resource: 'ROOM 215', category: 'rooms' },
    { studentId: '2021-182230', resource: 'TV', category: 'tv' },
  ],
  27: [
    { studentId: '2021-182240', resource: 'SPEAKER', category: 'speaker' },
  ],
};

function getScheduleDateLabel(day) {
  return `02-${String(day).padStart(2, '0')}-2026`;
}

function closeScheduleRequestModal() {
  if (!scheduleRequestModal) {
    return;
  }

  scheduleRequestModal.classList.remove('is-open');
  scheduleRequestModal.setAttribute('aria-hidden', 'true');
}

function closeScheduleDetailModal() {
  if (!scheduleDetailModal) {
    return;
  }

  scheduleDetailModal.classList.remove('is-open');
  scheduleDetailModal.setAttribute('aria-hidden', 'true');
}

function openScheduleDetailModal(request, dateLabel) {
  if (!scheduleDetailModal) {
    return;
  }

  if (scheduleDetailName) {
    scheduleDetailName.textContent = request.name || 'Menesis, Archie';
  }

  if (scheduleDetailTitleActivity) {
    scheduleDetailTitleActivity.textContent = request.title || 'Faculty Meeting';
  }

  if (scheduleDetailDate) {
    scheduleDetailDate.textContent = dateLabel;
  }

  if (scheduleDetailTime) {
    scheduleDetailTime.textContent = request.time || '1:00 PM - 3:00 PM';
  }

  if (scheduleDetailAttendance) {
    scheduleDetailAttendance.textContent = request.attendance || '10 People';
  }

  if (scheduleDetailResource) {
    scheduleDetailResource.textContent = request.resource || '-';
  }

  if (scheduleDetailChairs) {
    scheduleDetailChairs.textContent = String(request.chairs ?? 0);
  }

  if (scheduleDetailTables) {
    scheduleDetailTables.textContent = String(request.tables ?? 0);
  }

  scheduleDetailModal.classList.add('is-open');
  scheduleDetailModal.setAttribute('aria-hidden', 'false');
}

function renderScheduleInlineDetail(request, dateLabel) {
  if (scheduleInlineDetailName) {
    scheduleInlineDetailName.textContent = request.name || 'Menesis, Archie';
  }

  if (scheduleInlineDetailTitle) {
    scheduleInlineDetailTitle.textContent = request.title || 'Faculty Meeting';
  }

  if (scheduleInlineDetailDate) {
    scheduleInlineDetailDate.textContent = dateLabel;
  }

  if (scheduleInlineDetailTime) {
    scheduleInlineDetailTime.textContent = request.time || '1:00 PM - 3:00 PM';
  }

  if (scheduleInlineDetailAttendance) {
    scheduleInlineDetailAttendance.textContent = request.attendance || '10 People';
  }

  if (scheduleInlineDetailResource) {
    scheduleInlineDetailResource.textContent = request.resource || '-';
  }

  if (scheduleInlineDetailChairs) {
    scheduleInlineDetailChairs.textContent = String(request.chairs ?? 0);
  }

  if (scheduleInlineDetailTables) {
    scheduleInlineDetailTables.textContent = String(request.tables ?? 0);
  }
}

function openScheduleInlineDetails(day) {
  if (!scheduleInlineDate || !scheduleInlineRequestBody) {
    return;
  }

  const dateLabel = getScheduleDateLabel(day);
  const dayRequests = scheduleRequestData[day] || [];
  const filteredRequests = activeScheduleCategory === 'all'
    ? dayRequests
    : dayRequests.filter((request) => request.category === activeScheduleCategory);

  selectedScheduleDay = day;
  visibleScheduleInlineRequests = filteredRequests;

  scheduleDayCells.forEach((cell) => {
    const dayValue = Number.parseInt(cell.dataset.day || '', 10);
    cell.classList.toggle('selected', dayValue === day);
  });

  scheduleInlineDate.textContent = `Date Requested: ${dateLabel}`;

  if (!filteredRequests.length) {
    scheduleInlineRequestBody.innerHTML = `
      <tr>
        <td colspan="3">No requests for the selected category on this date.</td>
      </tr>
    `;

    renderScheduleInlineDetail({
      name: '-',
      title: '-',
      time: '-',
      attendance: '-',
      resource: '-',
      chairs: 0,
      tables: 0,
    }, '-');

    return;
  }

  scheduleInlineRequestBody.innerHTML = filteredRequests
    .map((request, index) => `
      <tr data-inline-request-index="${index}">
        <td>${request.studentId}</td>
        <td>${dateLabel}</td>
        <td>${request.resource}</td>
      </tr>
    `)
    .join('');

  renderScheduleInlineDetail(filteredRequests[0], dateLabel);
}

function openScheduleRequestModal(day) {
  if (!scheduleRequestModal || !scheduleRequestBody || !scheduleModalDate) {
    return;
  }

  const dateLabel = getScheduleDateLabel(day);
  const dayRequests = scheduleRequestData[day] || [];
  const filteredRequests = activeScheduleCategory === 'all'
    ? dayRequests
    : dayRequests.filter((request) => request.category === activeScheduleCategory);
  visibleScheduleRequests = filteredRequests;

  scheduleModalDate.textContent = `Date Requested: ${dateLabel}`;

  if (!filteredRequests.length) {
    scheduleRequestBody.innerHTML = `
      <tr>
        <td colspan="4">No requests for the selected category on this date.</td>
      </tr>
    `;
  } else {
    scheduleRequestBody.innerHTML = filteredRequests
      .map((request, index) => `
        <tr>
          <td>${request.studentId}</td>
          <td>${dateLabel}</td>
          <td>${request.resource}</td>
          <td><button class="schedule-view-btn" type="button" data-request-index="${index}" data-request-date="${dateLabel}"><i class="bi bi-person"></i> View</button></td>
        </tr>
      `)
      .join('');
  }

  scheduleRequestModal.classList.add('is-open');
  scheduleRequestModal.setAttribute('aria-hidden', 'false');
}

if (scheduleRequestBody) {
  scheduleRequestBody.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
      return;
    }

    const viewButton = target.closest('.schedule-view-btn');

    if (!(viewButton instanceof HTMLButtonElement)) {
      return;
    }

    const requestIndex = Number.parseInt(viewButton.dataset.requestIndex || '', 10);
    const requestDate = viewButton.dataset.requestDate || '--';
    const request = visibleScheduleRequests[requestIndex];

    if (request) {
      openScheduleDetailModal(request, requestDate);
    }
  });
}

function applyScheduleCategory(category) {
  if (!scheduleFilterButtons.length || !scheduleDayCells.length) {
    return;
  }

  const selectedCategory = scheduleMarkedDays[category] ? category : 'all';
  activeScheduleCategory = selectedCategory;
  const markedSet = new Set(scheduleMarkedDays[selectedCategory]);

  scheduleFilterButtons.forEach((button) => {
    button.classList.toggle('active', button.dataset.scheduleFilter === selectedCategory);
  });

  scheduleDayCells.forEach((cell) => {
    const dayValue = Number.parseInt(cell.dataset.day || '', 10);
    cell.classList.toggle('marked', markedSet.has(dayValue));

    if (!markedSet.has(dayValue)) {
      cell.classList.remove('selected');
    }
  });

  if (selectedScheduleDay !== null) {
    openScheduleInlineDetails(selectedScheduleDay);
  }
}

function applyRequestDecision(item, status) {
  const decisionName = item.querySelector('.request-decision-name');
  const decisionText = item.querySelector('.request-decision-text');
  const decisionBadge = item.querySelector('.request-decision-badge');
  const requesterName = item.dataset.requester || 'Mr. Minesis';
  const isApproved = status === 'approved';
  const possessive = requesterName.toLowerCase().endsWith('s') ? `${requesterName}'` : `${requesterName}'s`;
  const decisionSentence = `${possessive} request has been ${isApproved ? 'approved' : 'rejected'}`;

  item.classList.remove('is-approved', 'is-rejected');
  item.classList.add(isApproved ? 'is-approved' : 'is-rejected');

  if (decisionName) {
    decisionName.textContent = decisionSentence;
  }

  if (decisionText) {
    decisionText.textContent = '';
  }

  if (decisionBadge) {
    decisionBadge.textContent = isApproved ? 'Approved' : 'Rejected';
  }
}

async function submitRequestDecision(item, button, status) {
  const approvalId = button ? button.dataset.approvalId : '';

  if (!approvalId) {
    window.alert('Approval record is not available for this request.');
    return;
  }

  const action = status === 'approved' ? 'approve' : 'reject';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  if (!csrfToken) {
    window.alert('Missing CSRF token. Please refresh the page and try again.');
    return;
  }

  if (button) {
    button.disabled = true;
  }

  try {
    const response = await fetch(`/dashboard/approval/${approvalId}/${action}`, {
      method: 'PATCH',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      window.alert(data.error || data.message || 'Unable to process this request.');
      return;
    }

    applyRequestDecision(item, status);
  } catch (error) {
    window.console.error('Request approval error:', error);
    window.alert('An error occurred while processing the request.');
  } finally {
    if (button) {
      button.disabled = false;
    }
  }
}

async function submitFinalRequestDecision(item, button, status) {
  const reservationId = button ? button.dataset.reservationId : '';

  if (!reservationId) {
    window.alert('Reservation record is not available for this request.');
    return;
  }

  const action = status === 'approved' ? 'final-approve' : 'final-reject';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  if (!csrfToken) {
    window.alert('Missing CSRF token. Please refresh the page and try again.');
    return;
  }

  if (button) {
    button.disabled = true;
  }

  try {
    const response = await fetch(`/dashboard/request/${reservationId}/${action}`, {
      method: 'PATCH',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    });

    const responseText = await response.text();
    let data = {};

    if (responseText) {
      try {
        data = JSON.parse(responseText);
      } catch (parseError) {
        data = { error: responseText };
      }
    }

    if (!response.ok || !data.success) {
      const statusMessage = response.status ? ` (HTTP ${response.status})` : '';
      window.alert((data.error || data.message || 'Unable to process this request.') + statusMessage);
      return;
    }

    applyRequestDecision(item, status);
  } catch (error) {
    window.console.error('Final request approval error:', error);
    window.alert('An error occurred while processing the request.');
  } finally {
    if (button) {
      button.disabled = false;
    }
  }
}

function setRequestTabMode(mode) {
  if (!requestTabs.length || !requestContentCard) {
    return;
  }

  const requestMode = mode === 'pending' ? 'pending' : 'final';

  requestTabs.forEach((tab) => {
    tab.classList.toggle('active', tab.dataset.requestTab === requestMode);
  });

  requestItems.forEach((item) => {
    item.classList.remove('is-selected', 'is-approved', 'is-rejected');
  });

  requestContentCard.classList.toggle('pending-mode', requestMode === 'pending');
}

function openFacilitiesEditModal(row) {
  if (!facilitiesEditModal) {
    return;
  }

  const cells = row.querySelectorAll('td');
  activeEditingRow = row;

  if (facilitiesItemNameInput) {
    facilitiesItemNameInput.value = cells[1] ? cells[1].textContent.trim() : '';
  }

  if (facilitiesCategoryInput) {
    facilitiesCategoryInput.value = row.dataset.facilityCategory || 'rooms';
  }

  if (facilitiesDescriptionInput) {
    facilitiesDescriptionInput.value = '';
  }

  if (facilitiesUploadInput) {
    facilitiesUploadInput.value = '';
  }

  if (facilitiesUploadName) {
    facilitiesUploadName.textContent = 'No file selected';
  }

  if (facilitiesModalTitle) {
    facilitiesModalTitle.textContent = 'Edit Room/Facility';
  }

  if (facilitiesSaveButton) {
    facilitiesSaveButton.textContent = 'Save Room/Facility';
  }

  facilitiesEditModal.classList.add('is-open');
  facilitiesEditModal.setAttribute('aria-hidden', 'false');
}

function openFacilitiesAddModal() {
  if (!facilitiesEditModal) {
    return;
  }

  activeEditingRow = null;

  if (facilitiesItemNameInput) {
    facilitiesItemNameInput.value = '';
  }

  if (facilitiesCategoryInput) {
    facilitiesCategoryInput.value = '';
  }

  if (facilitiesDescriptionInput) {
    facilitiesDescriptionInput.value = '';
  }

  if (facilitiesUploadInput) {
    facilitiesUploadInput.value = '';
  }

  if (facilitiesUploadName) {
    facilitiesUploadName.textContent = 'No file selected';
  }

  if (facilitiesModalTitle) {
    facilitiesModalTitle.textContent = 'Add Room/Facility';
  }

  if (facilitiesSaveButton) {
    facilitiesSaveButton.textContent = 'Add Room/Facility';
  }

  facilitiesEditModal.classList.add('is-open');
  facilitiesEditModal.setAttribute('aria-hidden', 'false');
}

function closeFacilitiesEditModal() {
  if (!facilitiesEditModal) {
    return;
  }

  facilitiesEditModal.classList.remove('is-open');
  facilitiesEditModal.setAttribute('aria-hidden', 'true');
  activeEditingRow = null;
}

function getFacilitiesRowCategory(row) {
  return row.dataset.facilityCategory || 'others';
}

function applyFacilitiesFilters() {
  if (!facilitiesTableBody) {
    return;
  }

  const rows = facilitiesTableBody.querySelectorAll('tr');
  const topTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
  const inlineTerm = facilitiesInlineSearchInput ? facilitiesInlineSearchInput.value.trim().toLowerCase() : '';

  rows.forEach((row) => {
    const rowCategory = getFacilitiesRowCategory(row);
    const rowText = row.textContent.toLowerCase();
    const matchesTab = rowCategory === activeFacilitiesTab;
    const matchesTopSearch = !topTerm || rowText.includes(topTerm);
    const matchesInlineSearch = !inlineTerm || rowText.includes(inlineTerm);

    row.style.display = matchesTab && matchesTopSearch && matchesInlineSearch ? '' : 'none';
  });
}

function applyEquipmentFilters() {
  if (!equipmentTableBody) {
    return;
  }

  const rows = equipmentTableBody.querySelectorAll('tr');
  const topTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
  const inlineTerm = equipmentInlineSearchInput ? equipmentInlineSearchInput.value.trim().toLowerCase() : '';

  rows.forEach((row) => {
    const rowCategory = row.dataset.equipmentRow || '';
    const rowText = row.textContent.toLowerCase();
    const matchesTab = rowCategory === activeEquipmentTab;
    const matchesTopSearch = !topTerm || rowText.includes(topTerm);
    const matchesInlineSearch = !inlineTerm || rowText.includes(inlineTerm);

    row.style.display = matchesTab && matchesTopSearch && matchesInlineSearch ? '' : 'none';
  });
}

function getEquipmentStatusClass(statusValue) {
  if (statusValue === 'maintenance') {
    return 'maintenance';
  }

  if (statusValue === 'damaged') {
    return 'damaged';
  }

  return 'good';
}

function getEquipmentStatusLabel(statusValue) {
  if (statusValue === 'maintenance') {
    return 'Maintenance';
  }

  if (statusValue === 'damaged') {
    return 'Damaged';
  }

  return 'Good';
}

function showSaveSuccessToast(message) {
  const existing = document.getElementById('save-success-toast');

  if (existing) {
    existing.remove();
  }

  const toast = document.createElement('div');
  toast.id = 'save-success-toast';
  toast.textContent = message;
  toast.style.position = 'fixed';
  toast.style.top = '24px';
  toast.style.right = '24px';
  toast.style.zIndex = '9999';
  toast.style.background = '#1f8b4c';
  toast.style.color = '#ffffff';
  toast.style.padding = '10px 14px';
  toast.style.borderRadius = '8px';
  toast.style.fontSize = '14px';
  toast.style.boxShadow = '0 8px 24px rgba(0, 0, 0, 0.2)';
  toast.style.opacity = '0';
  toast.style.transform = 'translateY(-6px)';
  toast.style.transition = 'opacity 140ms ease, transform 140ms ease';

  document.body.appendChild(toast);

  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
  });

  window.setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(-6px)';
    window.setTimeout(() => toast.remove(), 180);
  }, 1500);
}

function closeEquipmentEditModal() {
  if (!equipmentEditModal) {
    return;
  }

  equipmentEditModal.classList.remove('is-open');
  equipmentEditModal.setAttribute('aria-hidden', 'true');
  activeEquipmentEditingRow = null;
}

function openEquipmentEditModal(row) {
  if (!equipmentEditModal) {
    return;
  }

  const cells = row.querySelectorAll('td');
  activeEquipmentEditingRow = row;

  if (equipmentItemNameInput) {
    equipmentItemNameInput.value = cells[1] ? cells[1].textContent.trim() : '';
  }

  if (equipmentCategoryInput) {
    equipmentCategoryInput.value = row.dataset.equipmentRow || 'multimedia';
  }

  if (equipmentTotalCountInput) {
    equipmentTotalCountInput.value = cells[2] ? cells[2].textContent.trim() : '0';
  }

  if (equipmentInUseInput) {
    equipmentInUseInput.value = cells[3] ? cells[3].textContent.trim() : '0';
  }

  if (equipmentStatusInput) {
    const statusPill = cells[4] ? cells[4].querySelector('.status-pill') : null;

    if (statusPill) {
      if (statusPill.classList.contains('maintenance')) {
        equipmentStatusInput.value = 'maintenance';
      } else if (statusPill.classList.contains('damaged')) {
        equipmentStatusInput.value = 'damaged';
      } else {
        equipmentStatusInput.value = 'good';
      }
    } else {
      equipmentStatusInput.value = 'good';
    }
  }

  if (equipmentDescriptionInput) {
    equipmentDescriptionInput.value = '';
  }

  if (equipmentUploadInput) {
    equipmentUploadInput.value = '';
  }

  if (equipmentUploadName) {
    equipmentUploadName.textContent = 'No file selected';
  }

  if (equipmentModalTitle) {
    equipmentModalTitle.textContent = 'Edit Equipment';
  }

  if (equipmentSaveButton) {
    equipmentSaveButton.textContent = 'Save Item';
  }

  equipmentEditModal.classList.add('is-open');
  equipmentEditModal.setAttribute('aria-hidden', 'false');
}

function openEquipmentAddModal() {
  if (!equipmentEditModal) {
    return;
  }

  activeEquipmentEditingRow = null;

  if (equipmentItemNameInput) {
    equipmentItemNameInput.value = '';
  }

  if (equipmentCategoryInput) {
    equipmentCategoryInput.value = '';
  }

  if (equipmentTotalCountInput) {
    equipmentTotalCountInput.value = '1';
  }

  if (equipmentInUseInput) {
    equipmentInUseInput.value = '0';
  }

  if (equipmentStatusInput) {
    equipmentStatusInput.value = '';
  }

  if (equipmentDescriptionInput) {
    equipmentDescriptionInput.value = '';
  }

  if (equipmentUploadInput) {
    equipmentUploadInput.value = '';
  }

  if (equipmentUploadName) {
    equipmentUploadName.textContent = 'No file selected';
  }

  if (equipmentModalTitle) {
    equipmentModalTitle.textContent = 'Add Equipment';
  }

  if (equipmentSaveButton) {
    equipmentSaveButton.textContent = 'Add Item';
  }

  equipmentEditModal.classList.add('is-open');
  equipmentEditModal.setAttribute('aria-hidden', 'false');
}

function setActiveNavByPage() {
  const path = window.location.pathname.toLowerCase();
  const navTarget = path.includes('/dashboard/office/requests')
    ? 'requests'
    : path.includes('/dashboard/office/archive')
    ? 'archive'
    : path.includes('/dashboard/messages')
    ? ''
    : path.includes('/dashboard/profile')
      ? ''
      : path.includes('/dashboard/inventory')
        ? 'inventory'
        : path.includes('/dashboard/maintenance')
          ? 'maintenance'
          : path.includes('/dashboard/history')
            ? 'history'
            : path.includes('/dashboard/schedule')
              ? 'schedule'
              : path.includes('/dashboard/request')
                ? 'requests'
                : 'home';
  const navItems = document.querySelectorAll('.nav-item[data-nav]');
  const subNavItems = document.querySelectorAll('.nav-subitem[data-subnav]');
  const subTarget = path.includes('/dashboard/inventory/facilities')
    ? 'facilities'
    : path.includes('/dashboard/inventory/equipments')
      ? 'equipments'
      : path.includes('/dashboard/inventory/analytics')
        ? 'analytics'
        : '';

  navItems.forEach((item) => {
    item.classList.toggle('active', item.dataset.nav === navTarget);

    if (item.dataset.nav === 'inventory') {
      item.classList.toggle('sub-active', subTarget !== '');
    }
  });

  subNavItems.forEach((item) => {
    item.classList.toggle('active', item.dataset.subnav === subTarget);
  });
}

function closeSidebarDrawer() {
  document.body.classList.remove('sidebar-open');

  if (sidebarToggleButton) {
    sidebarToggleButton.setAttribute('aria-expanded', 'false');
  }
}

function openSidebarDrawer() {
  document.body.classList.add('sidebar-open');

  if (sidebarToggleButton) {
    sidebarToggleButton.setAttribute('aria-expanded', 'true');
  }
}

function toggleSidebarDrawer() {
  if (document.body.classList.contains('sidebar-open')) {
    closeSidebarDrawer();
    return;
  }

  openSidebarDrawer();
}

function ensureSidebarBackdrop() {
  if (sidebarBackdrop) {
    return;
  }

  sidebarBackdrop = document.createElement('button');
  sidebarBackdrop.type = 'button';
  sidebarBackdrop.className = 'sidebar-backdrop';
  sidebarBackdrop.setAttribute('aria-label', 'Close navigation');
  sidebarBackdrop.addEventListener('click', closeSidebarDrawer);
  document.body.appendChild(sidebarBackdrop);
}

function ensureSidebarToggleButton() {
  if (!navbarContainer) {
    return;
  }

  const toolbar = document.querySelector('.toolbar-card');

  if (!toolbar) {
    return;
  }

  const existingToggle = toolbar.querySelector('.sidebar-toggle');

  if (existingToggle instanceof HTMLButtonElement) {
    sidebarToggleButton = existingToggle;
    return;
  }

  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'toolbar-icon sidebar-toggle';
  toggle.setAttribute('aria-label', 'Toggle navigation');
  toggle.setAttribute('aria-expanded', 'false');
  toggle.innerHTML = '<i class="bi bi-list"></i>';
  toggle.addEventListener('click', toggleSidebarDrawer);

  toolbar.insertBefore(toggle, toolbar.firstChild);
  sidebarToggleButton = toggle;
}

async function loadNavbar() {
  if (!navbarContainer) {
    return;
  }

  const navComponentPath =
    (typeof window.dashboardNavComponent === 'string' && window.dashboardNavComponent.trim())
      ? window.dashboardNavComponent.trim()
      : '/components/navbar.html';

  try {
    const response = await fetch(navComponentPath);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    navbarContainer.innerHTML = await response.text();
    setActiveNavByPage();
    ensureSidebarToggleButton();
    ensureSidebarBackdrop();

    navbarContainer.querySelectorAll('a.nav-item, a.nav-subitem').forEach((link) => {
      link.addEventListener('click', closeSidebarDrawer);
    });

    const logoutButton = navbarContainer.querySelector('[data-nav-action="logout"]');
    if (logoutButton instanceof HTMLButtonElement) {
      logoutButton.addEventListener('click', () => {
        const token = document.querySelector('meta[name="csrf-token"]');

        if (!token || !token.content) {
          alert('Unable to logout. Missing CSRF token.');
          return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/logout';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_token';
        input.value = token.content;

        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
      });
    }
  } catch (error) {
    console.error('Unable to load navbar component:', error);
  }
}

if (inventoryShortcut) {
  inventoryShortcut.addEventListener('click', () => {
    window.location.href = '/dashboard/inventory';
  });
}

function closeMessagesPopover() {
  if (messagePopover) {
    messagePopover.remove();
    messagePopover = null;
  }

  if (activeMessageButton) {
    activeMessageButton.classList.remove('message-open');
    activeMessageButton = null;
  }
}

function positionMessagesPopover(button) {
  if (!messagePopover) {
    return;
  }

  const rect = button.getBoundingClientRect();
  const top = rect.bottom + 10;
  const preferredLeft = rect.right - messagePopover.offsetWidth + 14;
  const left = Math.max(8, Math.min(window.innerWidth - messagePopover.offsetWidth - 8, preferredLeft));

  messagePopover.style.top = `${top}px`;
  messagePopover.style.left = `${left}px`;
}

function buildMessagesPopover() {
  const panel = document.createElement('div');
  panel.className = 'message-popover';

  panel.innerHTML = `
    <div class="message-popover-head">
      <strong>Chats</strong>
    </div>
    <div class="message-popover-list">
      ${messagePreviewItems
        .map((item) => `
          <button type="button" class="message-popover-item" data-message-name="${item.name}">
            <span class="message-popover-avatar"><i class="bi bi-person-fill"></i></span>
            <span class="message-popover-copy">
              <span class="message-popover-name">${item.name}</span>
              <span class="message-popover-snippet">${item.snippet}</span>
            </span>
            <span class="message-popover-dot ${item.unread ? 'unread' : 'read'}" aria-hidden="true"></span>
          </button>
        `)
        .join('')}
    </div>
  `;

  panel.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
      return;
    }

    const item = target.closest('.message-popover-item');

    if (!(item instanceof HTMLButtonElement)) {
      return;
    }

    const messageName = item.dataset.messageName || '';

    if (!messageName) {
      return;
    }

    closeMessagesPopover();

    if (window.location.pathname.includes('/dashboard/messages')) {
      setActiveMessageContact(messageName);
      return;
    }

    window.location.href = `/dashboard/messages?contact=${encodeURIComponent(messageName)}`;
  });

  return panel;
}

if (toolbarMessageButtons.length) {
  toolbarMessageButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.stopPropagation();

      if (messagePopover && activeMessageButton === button) {
        closeMessagesPopover();
        return;
      }

      closeNotificationsPopover();
      closeProfilePopover();
      closeMessagesPopover();
      messagePopover = buildMessagesPopover();
      document.body.appendChild(messagePopover);
      activeMessageButton = button;
      button.classList.add('message-open');
      positionMessagesPopover(button);
    });
  });

  if (!messageOutsidePointerHandlerBound) {
    document.addEventListener('pointerdown', (event) => {
      const target = event.target;

      if (!(target instanceof Node)) {
        return;
      }

      if (messagePopover && !messagePopover.contains(target) && !Array.from(toolbarMessageButtons).some((button) => button.contains(target))) {
        closeMessagesPopover();
      }
    }, true);

    messageOutsidePointerHandlerBound = true;
  }
}

function closeNotificationsPopover() {
  if (notificationPopover) {
    notificationPopover.remove();
    notificationPopover = null;
  }

  if (activeNotificationButton) {
    activeNotificationButton.classList.remove('notification-open');
    activeNotificationButton = null;
  }
}

function closeProfilePopover() {
  if (profilePopover) {
    profilePopover.remove();
    profilePopover = null;
  }

  if (activeProfileButton) {
    activeProfileButton.classList.remove('profile-open');
    activeProfileButton = null;
  }
}

function positionProfilePopover(button) {
  if (!profilePopover) {
    return;
  }

  const rect = button.getBoundingClientRect();
  const top = rect.bottom + 10;
  const preferredLeft = rect.right - profilePopover.offsetWidth;
  const left = Math.max(8, Math.min(window.innerWidth - profilePopover.offsetWidth - 8, preferredLeft));

  profilePopover.style.top = `${top}px`;
  profilePopover.style.left = `${left}px`;
}

function buildProfilePopover() {
  const panel = document.createElement('div');
  panel.className = 'profile-popover';
  const userName = (window.authUser && (window.authUser.full_name || window.authUser.username))
    ? (window.authUser.full_name || window.authUser.username)
    : 'User';
  panel.innerHTML = `
    <button type="button" class="profile-action" data-profile-action="account">${userName}</button>
    <button type="button" class="profile-action logout" data-profile-action="logout">Logout</button>
  `;

  panel.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
      return;
    }

    const actionButton = target.closest('[data-profile-action]');

    if (!(actionButton instanceof HTMLButtonElement)) {
      return;
    }

    const action = actionButton.dataset.profileAction;

    closeProfilePopover();

    if (action === 'account') {
      window.location.href = '/dashboard/profile';
      return;
    }

    if (action === 'logout') {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '/logout';
      const token = document.querySelector('meta[name="csrf-token"]');
      if (token) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_token';
        input.value = token.content;
        form.appendChild(input);
      }
      document.body.appendChild(form);
      form.submit();
    }
  });

  return panel;
}

function positionNotificationsPopover(button) {
  if (!notificationPopover) {
    return;
  }

  const rect = button.getBoundingClientRect();
  const top = rect.bottom + 10;
  const preferredLeft = rect.right - notificationPopover.offsetWidth + 14;
  const left = Math.max(8, Math.min(window.innerWidth - notificationPopover.offsetWidth - 8, preferredLeft));

  notificationPopover.style.top = `${top}px`;
  notificationPopover.style.left = `${left}px`;
}

function buildNotificationsPopover() {
  const panel = document.createElement('div');
  panel.className = 'notification-popover';

  panel.innerHTML = `
    <div class="notification-popover-head">
      <strong>Notifications</strong>
    </div>
    <div class="notification-popover-list">
      ${notificationItems
        .map((item, index) => `
          <article class="notification-item${item.unread ? ' unread' : ''}" data-notification-index="${index}">
            <span class="notification-avatar"><i class="bi bi-person-fill"></i></span>
            <div class="notification-copy">
              <strong>${item.name} has submitted a request</strong>
              <span class="notification-sub">Check the status update</span>
            </div>
            <span class="notification-indicator ${item.unread ? 'unread' : 'read'}" aria-label="${item.unread ? 'Unread notification' : 'Read notification'}"></span>
          </article>
        `)
        .join('')}
    </div>
  `;

  panel.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
      return;
    }

    const item = target.closest('.notification-item');

    if (!(item instanceof HTMLElement)) {
      return;
    }

    const index = Number.parseInt(item.dataset.notificationIndex || '', 10);

    if (Number.isNaN(index) || !notificationItems[index]) {
      return;
    }

    notificationItems[index].unread = false;
    item.classList.remove('unread');

    const indicator = item.querySelector('.notification-indicator');

    if (indicator instanceof HTMLElement) {
      indicator.classList.remove('unread');
      indicator.classList.add('read');
      indicator.setAttribute('aria-label', 'Read notification');
    }
  });

  return panel;
}

if (toolbarNotificationButtons.length) {
  toolbarNotificationButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.stopPropagation();

      if (notificationPopover && activeNotificationButton === button) {
        closeNotificationsPopover();
        return;
      }

      closeNotificationsPopover();
      notificationPopover = buildNotificationsPopover();
      document.body.appendChild(notificationPopover);
      activeNotificationButton = button;
      button.classList.add('notification-open');
      positionNotificationsPopover(button);
    });
  });

  window.addEventListener('resize', () => {
    if (messagePopover && activeMessageButton) {
      positionMessagesPopover(activeMessageButton);
    }

    if (notificationPopover && activeNotificationButton) {
      positionNotificationsPopover(activeNotificationButton);
    }

    if (profilePopover && activeProfileButton) {
      positionProfilePopover(activeProfileButton);
    }
  });

  document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Node)) {
      return;
    }

    if (notificationPopover && !notificationPopover.contains(target) && !Array.from(toolbarNotificationButtons).some((button) => button.contains(target))) {
      closeNotificationsPopover();
    }
  });
}

if (toolbarProfileButtons.length) {
  toolbarProfileButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
      event.stopPropagation();

      if (profilePopover && activeProfileButton === button) {
        closeProfilePopover();
        return;
      }

      closeProfilePopover();
      profilePopover = buildProfilePopover();
      document.body.appendChild(profilePopover);
      activeProfileButton = button;
      button.classList.add('profile-open');
      positionProfilePopover(button);
    });
  });

  document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Node)) {
      return;
    }

    if (profilePopover && !profilePopover.contains(target) && !Array.from(toolbarProfileButtons).some((button) => button.contains(target))) {
      closeProfilePopover();
    }
  });
}

function closeProfileEditModal() {
  if (!profileEditModal) {
    return;
  }

  profileEditModal.classList.remove('is-open');
  profileEditModal.setAttribute('aria-hidden', 'true');
}

function openProfileEditModal() {
  if (!profileEditModal || !profileModalFirstNameInput || !profileModalMiddleNameInput || !profileModalLastNameInput || !profileModalSuffixInput) {
    return;
  }

  profileModalFirstNameInput.value = profileFirstNameInput ? profileFirstNameInput.value : '';
  profileModalMiddleNameInput.value = profileMiddleNameInput ? profileMiddleNameInput.value : '';
  profileModalLastNameInput.value = profileLastNameInput ? profileLastNameInput.value : '';
  profileModalSuffixInput.value = profileSuffixInput ? profileSuffixInput.value : '';

  if (profileModalAdminIdInput) {
    profileModalAdminIdInput.value = profileAdminIdInput ? profileAdminIdInput.value : '';
  }

  if (profileModalEmailInput) {
    profileModalEmailInput.value = profileEmailInput ? profileEmailInput.value : '';
  }

  if (profileModalContactInput) {
    profileModalContactInput.value = profileContactInput ? profileContactInput.value : '';
  }

  if (profileModalPhoneInput) {
    profileModalPhoneInput.value = profilePhoneInput ? profilePhoneInput.value : '';
  }

  pendingProfileAvatarDataUrl = profileAvatarImage && profileAvatarImage.src ? profileAvatarImage.src : '';

  if (profileEditAvatar && profileEditAvatarImage) {
    if (pendingProfileAvatarDataUrl) {
      profileEditAvatarImage.src = pendingProfileAvatarDataUrl;
      profileEditAvatar.classList.add('has-image');
    } else {
      profileEditAvatarImage.removeAttribute('src');
      profileEditAvatar.classList.remove('has-image');
    }
  }

  if (profileAvatarUploadInput) {
    profileAvatarUploadInput.value = '';
  }

  profileEditModal.classList.add('is-open');
  profileEditModal.setAttribute('aria-hidden', 'false');
}

if (profileEditButton && profileEditModal) {
  profileEditButton.addEventListener('click', openProfileEditModal);
}

if (profileEditCancelButton) {
  profileEditCancelButton.addEventListener('click', closeProfileEditModal);
}

if (profileEditSaveButton) {
  profileEditSaveButton.addEventListener('click', async () => {
    const token = document.querySelector('meta[name="csrf-token"]');
    const updateUrl = window.authUser && window.authUser.profile_update_url
      ? window.authUser.profile_update_url
      : '/dashboard/profile';

    const payload = {
      first_name: profileModalFirstNameInput ? profileModalFirstNameInput.value.trim() : '',
      middle_initial: profileModalMiddleNameInput ? profileModalMiddleNameInput.value.trim().replace(/[^A-Za-z]/g, '').slice(0, 1).toUpperCase() : '',
      last_name: profileModalLastNameInput ? profileModalLastNameInput.value.trim() : '',
      suffix: profileModalSuffixInput ? profileModalSuffixInput.value.trim() : '',
      email: profileModalEmailInput ? profileModalEmailInput.value.trim() : '',
      contact_number: profileModalContactInput ? profileModalContactInput.value.trim() : '',
      phone_number: profileModalPhoneInput ? profileModalPhoneInput.value.trim() : '',
    };

    try {
      profileEditSaveButton.disabled = true;

      const response = await fetch(updateUrl, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token ? token.content : '',
        },
        body: JSON.stringify(payload),
      });

      const result = await response.json();

      if (!response.ok) {
        const message = result && result.message ? result.message : 'Failed to update profile.';
        throw new Error(message);
      }

      const user = result.user || {};
      const firstName = user.first_name || payload.first_name;
      const middleInitial = user.middle_initial || payload.middle_initial;
      const lastName = user.last_name || payload.last_name;
      const fullName = user.full_name || [firstName, middleInitial ? `${middleInitial}.` : '', lastName].filter(Boolean).join(' ');

      if (profileFirstNameInput) {
        profileFirstNameInput.value = firstName || '';
      }

      if (profileMiddleNameInput) {
        profileMiddleNameInput.value = middleInitial || '';
      }

      if (profileLastNameInput) {
        profileLastNameInput.value = lastName || '';
      }

      if (profileSuffixInput) {
        profileSuffixInput.value = user.suffix || payload.suffix || 'Not Set';
      }

      if (profileEmailInput) {
        profileEmailInput.value = user.email || payload.email || '';
      }

      if (profileContactInput) {
        profileContactInput.value = user.contact_number || payload.contact_number || 'Not Set';
      }

      if (profilePhoneInput) {
        profilePhoneInput.value = user.phone_number || payload.phone_number || 'Not Set';
      }

      if (window.authUser) {
        window.authUser.first_name = user.first_name || firstName || window.authUser.first_name;
        window.authUser.middle_initial = user.middle_initial || middleInitial || window.authUser.middle_initial;
        window.authUser.last_name = user.last_name || lastName || window.authUser.last_name;
        window.authUser.full_name = user.full_name || fullName || window.authUser.full_name;
        window.authUser.email = user.email || window.authUser.email;
        window.authUser.suffix = user.suffix || '';
        window.authUser.contact_number = user.contact_number || '';
        window.authUser.phone_number = user.phone_number || '';
      }

      if (profileAvatar && profileAvatarImage) {
        if (pendingProfileAvatarDataUrl) {
          profileAvatarImage.src = pendingProfileAvatarDataUrl;
          profileAvatar.classList.add('has-image');
        } else {
          profileAvatarImage.removeAttribute('src');
          profileAvatar.classList.remove('has-image');
        }
      }

      closeProfileEditModal();
      window.alert(result.message || 'Profile updated successfully.');
    } catch (error) {
      window.alert(error instanceof Error ? error.message : 'Failed to update profile.');
    } finally {
      profileEditSaveButton.disabled = false;
    }
  });
}

if (profileEditAvatar && profileAvatarUploadInput) {
  profileEditAvatar.addEventListener('click', () => {
    profileAvatarUploadInput.click();
  });

  profileEditAvatar.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    event.preventDefault();
    profileAvatarUploadInput.click();
  });
}

if (profileEditUploadButton && profileAvatarUploadInput) {
  profileEditUploadButton.addEventListener('click', () => {
    profileAvatarUploadInput.click();
  });
}

if (profileAvatarUploadInput && profileEditAvatar && profileEditAvatarImage) {
  profileAvatarUploadInput.addEventListener('change', () => {
    const file = profileAvatarUploadInput.files && profileAvatarUploadInput.files[0];

    if (!file) {
      return;
    }

    const validTypes = ['image/jpeg', 'image/png'];
    const lowerName = file.name.toLowerCase();
    const validExt = lowerName.endsWith('.jpg') || lowerName.endsWith('.jpeg') || lowerName.endsWith('.png');
    const validType = validTypes.includes(file.type) || (file.type === '' && validExt);

    if (!validType) {
      profileAvatarUploadInput.value = '';
      window.alert('Invalid image type. Please upload JPG or PNG only.');
      return;
    }

    const maxSizeBytes = 5 * 1024 * 1024;

    if (file.size > maxSizeBytes) {
      profileAvatarUploadInput.value = '';
      window.alert('Image is too large. Maximum size is 5MB.');
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      if (typeof reader.result !== 'string') {
        return;
      }

      pendingProfileAvatarDataUrl = reader.result;
      profileEditAvatarImage.src = reader.result;
      profileEditAvatar.classList.add('has-image');
    };
    reader.readAsDataURL(file);
  });
}

if (profileEditModal) {
  profileEditModal.addEventListener('click', (event) => {
    const target = event.target;

    if (target instanceof HTMLElement && target.dataset.closeProfileModal === 'true') {
      closeProfileEditModal();
    }
  });
}

function setActiveMessageContact(name) {
  if (messageCurrentName) {
    messageCurrentName.textContent = name;
  }

  messageContacts.forEach((contact) => {
    contact.classList.toggle('active', contact.dataset.messageContact === name);
  });

  if (messageThread) {
    messageThread.innerHTML = '';
    delete messageThread.dataset.lastDateKey;
  }

  if (messageThreadWrap) {
    messageThreadWrap.classList.remove('has-messages');
  }

  if (messageEmptyState) {
    messageEmptyState.style.display = '';
  }
}

function formatChatDate(date) {
  return date.toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatChatTime(date) {
  return date
    .toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
    .toLowerCase()
    .replace(' ', '');
}

function appendChatDateDivider(date) {
  if (!messageThread) {
    return;
  }

  const dateKey = date.toISOString().slice(0, 10);

  if (messageThread.dataset.lastDateKey === dateKey) {
    return;
  }

  const divider = document.createElement('p');
  divider.className = 'message-day-divider';
  divider.textContent = formatChatDate(date);

  messageThread.appendChild(divider);
  messageThread.dataset.lastDateKey = dateKey;
}

if (messageContacts.length) {
  messageContacts.forEach((contact) => {
    contact.addEventListener('click', () => {
      const name = contact.dataset.messageContact || '';

      if (!name) {
        return;
      }

      setActiveMessageContact(name);
    });
  });

  const searchParams = new URLSearchParams(window.location.search);
  const selectedContact = searchParams.get('contact') || '';
  const preselectedByQuery = selectedContact
    ? Array.from(messageContacts).find((contact) => contact.dataset.messageContact === selectedContact)
    : null;

  const initiallyActive = preselectedByQuery || document.querySelector('.message-contact.active');

  if (initiallyActive instanceof HTMLElement && initiallyActive.dataset.messageContact) {
    setActiveMessageContact(initiallyActive.dataset.messageContact);
  }
}

if (messageForm && messageInput && messageThread) {
  messageForm.addEventListener('submit', (event) => {
    event.preventDefault();

    const text = messageInput.value.trim();

    if (!text) {
      return;
    }

    const now = new Date();
    appendChatDateDivider(now);

    const item = document.createElement('div');
    item.className = 'message-item outgoing';

    const bubble = document.createElement('p');
    bubble.className = 'message-bubble outgoing';
    bubble.textContent = text;

    const meta = document.createElement('span');
    meta.className = 'message-meta';
    meta.textContent = `sent ${formatChatTime(now)}`;

    item.appendChild(bubble);
    item.appendChild(meta);

    messageThread.appendChild(item);

    if (messageEmptyState) {
      messageEmptyState.style.display = 'none';
    }

    if (messageThreadWrap) {
      messageThreadWrap.classList.add('has-messages');
    }

    messageInput.value = '';
    const scrollTarget = messageThreadWrap || messageThread;
    scrollTarget.scrollTop = scrollTarget.scrollHeight;
  });
}

function setToolbarSearchExpanded(expanded, focusInput = false) {
  if (!toolbarSearchWrap || !searchInput) {
    return;
  }

  isToolbarSearchExpanded = expanded;
  toolbarSearchWrap.classList.toggle('is-expanded', expanded);
  toolbarSearchWrap.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  searchInput.tabIndex = expanded ? 0 : -1;
  searchInput.setAttribute('aria-hidden', expanded ? 'false' : 'true');

  if (expanded && focusInput) {
    searchInput.focus();
  }
}

function initToolbarSearchToggle() {
  if (!toolbarSearchWrap || !searchInput) {
    return;
  }

  toolbarSearchWrap.setAttribute('role', 'button');
  toolbarSearchWrap.setAttribute('tabindex', '0');
  setToolbarSearchExpanded(false);

  toolbarSearchWrap.addEventListener('click', (event) => {
    const target = event.target;

    if (!isToolbarSearchExpanded) {
      event.preventDefault();
      event.stopPropagation();
      setToolbarSearchExpanded(true, true);
      return;
    }

    if (target instanceof HTMLElement && target.matches('i')) {
      searchInput.focus();
    }
  });

  toolbarSearchWrap.addEventListener('keydown', (event) => {
    if ((event.key === 'Enter' || event.key === ' ') && !isToolbarSearchExpanded) {
      event.preventDefault();
      setToolbarSearchExpanded(true, true);
    }
  });

  searchInput.addEventListener('focus', () => {
    if (!isToolbarSearchExpanded) {
      setToolbarSearchExpanded(true);
    }
  });

  document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Node)) {
      return;
    }

    if (isToolbarSearchExpanded && !toolbarSearchWrap.contains(target)) {
      setToolbarSearchExpanded(false);
    }
  });
}

function initOfficeQuickSortControl() {
  const sortShell = document.querySelector('.quick-control-shell.is-sort');
  const sortInput = document.getElementById('quick-view-sort');
  const sortTrigger = document.getElementById('quick-view-sort-trigger');
  const sortLabel = sortTrigger ? sortTrigger.querySelector('.quick-sort-label') : null;
  const sortMenu = document.getElementById('quick-view-sort-menu');

  if (!(sortShell instanceof HTMLElement)
    || !(sortInput instanceof HTMLInputElement)
    || !(sortTrigger instanceof HTMLButtonElement)
    || !(sortMenu instanceof HTMLElement)
    || !(sortLabel instanceof HTMLElement)) {
    return;
  }

  const optionButtons = Array.from(sortMenu.querySelectorAll('.quick-sort-option[data-sort-value]'));

  const closeMenu = () => {
    sortShell.classList.remove('is-open');
    sortTrigger.setAttribute('aria-expanded', 'false');
  };

  const openMenu = () => {
    sortShell.classList.add('is-open');
    sortTrigger.setAttribute('aria-expanded', 'true');
  };

  const setSelected = (value, labelText) => {
    sortInput.value = value;
    sortLabel.textContent = labelText;

    optionButtons.forEach((button) => {
      const isActive = button.dataset.sortValue === value;
      button.classList.toggle('is-active', isActive);
      if (isActive) {
        button.setAttribute('aria-selected', 'true');
      } else {
        button.removeAttribute('aria-selected');
      }
    });
  };

  sortTrigger.addEventListener('click', (event) => {
    event.stopPropagation();
    if (sortShell.classList.contains('is-open')) {
      closeMenu();
      return;
    }

    openMenu();
  });

  optionButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const value = button.dataset.sortValue || 'all';
      const labelText = (button.textContent || 'All').trim();
      setSelected(value, labelText);
      closeMenu();
    });
  });

  document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Node)) {
      return;
    }

    if (!sortShell.contains(target)) {
      closeMenu();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && sortShell.classList.contains('is-open')) {
      closeMenu();
    }
  });

  const initialActive = optionButtons.find((button) => button.classList.contains('is-active'));
  if (initialActive instanceof HTMLButtonElement) {
    const value = initialActive.dataset.sortValue || 'all';
    const labelText = (initialActive.textContent || 'All').trim();
    setSelected(value, labelText);
  }
}

function initOfficeQuickDateControl() {
  const dateInput = document.getElementById('quick-view-date');

  if (!(dateInput instanceof HTMLInputElement) || typeof window.flatpickr !== 'function') {
    return;
  }

  const defaultDate = dateInput.dataset.defaultDate || dateInput.value || undefined;

  window.flatpickr(dateInput, {
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'M j, Y',
    defaultDate,
    disableMobile: true,
    allowInput: false,
    monthSelectorType: 'dropdown',
    shorthandCurrentMonth: false,
    prevArrow: '<i class="bi bi-chevron-left"></i>',
    nextArrow: '<i class="bi bi-chevron-right"></i>',
  });
}

function initOfficeReservationModal() {
  const modal = document.getElementById('office-request-modal');
  const rows = document.querySelectorAll('.office-reservation-row');
  const nameField = document.getElementById('office-request-name');
  const titleField = document.getElementById('office-request-title');
  const dateField = document.getElementById('office-request-date');
  const timeField = document.getElementById('office-request-time');
  const attendanceField = document.getElementById('office-request-attendance');
  const resourceField = document.getElementById('office-request-resource');
  const chairsField = document.getElementById('office-request-chairs');
  const tablesField = document.getElementById('office-request-tables');
  const noteField = document.getElementById('office-request-note');
  const cancelButton = document.getElementById('office-request-cancel');
  const rejectButton = document.getElementById('office-request-reject');
  const approveButton = document.getElementById('office-request-approve');
  const approveConfirmModal = document.getElementById('office-approve-confirm-modal');
  const approveConfirmCancel = document.getElementById('office-approve-confirm-cancel');
  const approveConfirmApprove = document.getElementById('office-approve-confirm-approve');
  const approveFeedbackModal = document.getElementById('office-approve-feedback-modal');
  const approveFeedbackFinish = document.getElementById('office-approve-feedback-finish');
  const rejectReasonModal = document.getElementById('office-reject-reason-modal');
  const rejectReasonOptions = document.querySelectorAll('.office-reject-reason-option[data-reject-reason]');
  const rejectReasonCancel = document.getElementById('office-reject-reason-cancel');
  const rejectReasonConfirm = document.getElementById('office-reject-reason-confirm');
  const rejectOtherWrap = document.getElementById('office-reject-other-wrap');
  const rejectOtherInput = document.getElementById('office-reject-other-input');
  const rejectFeedbackModal = document.getElementById('office-reject-feedback-modal');
  const rejectFeedbackFinish = document.getElementById('office-reject-feedback-finish');

  if (!(modal instanceof HTMLElement)
    || !rows.length
    || !(nameField instanceof HTMLElement)
    || !(titleField instanceof HTMLElement)
    || !(dateField instanceof HTMLElement)
    || !(timeField instanceof HTMLElement)
    || !(attendanceField instanceof HTMLElement)
    || !(resourceField instanceof HTMLElement)
    || !(chairsField instanceof HTMLElement)
    || !(tablesField instanceof HTMLElement)) {
    return;
  }

  let activeRow = null;
  let activeRejectReason = '';

  const closeModal = (resetActiveRow = true) => {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (resetActiveRow) {
      activeRow = null;
    }
  };

  const openApproveConfirmModal = () => {
    if (!(approveConfirmModal instanceof HTMLElement)) {
      return;
    }

    approveConfirmModal.classList.add('is-open');
    approveConfirmModal.setAttribute('aria-hidden', 'false');
  };

  const resetRejectReasonState = () => {
    activeRejectReason = '';

    rejectReasonOptions.forEach((option) => {
      option.classList.remove('is-active');
      option.removeAttribute('aria-selected');
    });

    if (rejectReasonConfirm instanceof HTMLButtonElement) {
      rejectReasonConfirm.disabled = true;
    }

    if (rejectOtherWrap instanceof HTMLElement) {
      rejectOtherWrap.hidden = true;
    }

    if (rejectOtherInput instanceof HTMLInputElement) {
      rejectOtherInput.value = '';
    }
  };

  const syncRejectConfirmState = () => {
    if (!(rejectReasonConfirm instanceof HTMLButtonElement)) {
      return;
    }

    if (!activeRejectReason) {
      rejectReasonConfirm.disabled = true;
      return;
    }

    if (activeRejectReason !== 'Others') {
      rejectReasonConfirm.disabled = false;
      return;
    }

    const hasOtherText = rejectOtherInput instanceof HTMLInputElement
      ? rejectOtherInput.value.trim().length > 0
      : false;

    rejectReasonConfirm.disabled = !hasOtherText;
  };

  const openRejectReasonModal = () => {
    if (!(rejectReasonModal instanceof HTMLElement)) {
      return;
    }

    resetRejectReasonState();
    rejectReasonModal.classList.add('is-open');
    rejectReasonModal.setAttribute('aria-hidden', 'false');
  };

  const closeRejectReasonModal = () => {
    if (!(rejectReasonModal instanceof HTMLElement)) {
      return;
    }

    rejectReasonModal.classList.remove('is-open');
    rejectReasonModal.setAttribute('aria-hidden', 'true');
  };

  const openRejectFeedbackModal = () => {
    if (!(rejectFeedbackModal instanceof HTMLElement)) {
      return;
    }

    rejectFeedbackModal.classList.add('is-open');
    rejectFeedbackModal.setAttribute('aria-hidden', 'false');
  };

  const closeRejectFeedbackModal = () => {
    if (!(rejectFeedbackModal instanceof HTMLElement)) {
      return;
    }

    rejectFeedbackModal.classList.remove('is-open');
    rejectFeedbackModal.setAttribute('aria-hidden', 'true');
  };

  const closeApproveConfirmModal = () => {
    if (!(approveConfirmModal instanceof HTMLElement)) {
      return;
    }

    approveConfirmModal.classList.remove('is-open');
    approveConfirmModal.setAttribute('aria-hidden', 'true');
  };

  const openApproveFeedbackModal = () => {
    if (!(approveFeedbackModal instanceof HTMLElement)) {
      return;
    }

    approveFeedbackModal.classList.add('is-open');
    approveFeedbackModal.setAttribute('aria-hidden', 'false');
  };

  const closeApproveFeedbackModal = () => {
    if (!(approveFeedbackModal instanceof HTMLElement)) {
      return;
    }

    approveFeedbackModal.classList.remove('is-open');
    approveFeedbackModal.setAttribute('aria-hidden', 'true');
  };

  const openModal = (row) => {
    activeRow = row;
    nameField.textContent = row.dataset.requestName || '';
    titleField.textContent = row.dataset.requestTitle || '';
    dateField.textContent = row.dataset.requestDate || '';
    timeField.textContent = row.dataset.requestTime || '';
    attendanceField.textContent = row.dataset.requestAttendance || '';
    resourceField.textContent = row.dataset.requestResource || '';
    chairsField.textContent = row.dataset.requestChairs || '0';
    tablesField.textContent = row.dataset.requestTables || '0';

    if (noteField instanceof HTMLTextAreaElement) {
      noteField.value = '';
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  };

  const applyDecisionToRow = (status) => {
    if (!(activeRow instanceof HTMLTableRowElement)) {
      return;
    }

    const statusBadge = activeRow.querySelector('.badge');

    if (!(statusBadge instanceof HTMLElement)) {
      return;
    }

    if (status === 'approve') {
      statusBadge.classList.remove('pending', 'rejected');
      statusBadge.classList.add('solved');
      statusBadge.textContent = 'Approve';
      return;
    }

    statusBadge.classList.remove('solved', 'pending');
    statusBadge.classList.add('rejected');
    statusBadge.textContent = 'Rejected';
  };

  rows.forEach((row) => {
    row.addEventListener('click', () => {
      if (row instanceof HTMLTableRowElement) {
        openModal(row);
      }
    });
  });

  modal.addEventListener('click', (event) => {
    const target = event.target;

    if (target instanceof HTMLElement && target.dataset.closeOfficeRequestModal === 'true') {
      closeModal();
    }
  });

  if (cancelButton instanceof HTMLButtonElement) {
    cancelButton.addEventListener('click', closeModal);
  }

  if (rejectButton instanceof HTMLButtonElement) {
    rejectButton.addEventListener('click', () => {
      closeModal(false);
      openRejectReasonModal();
    });
  }

  rejectReasonOptions.forEach((optionButton) => {
    optionButton.addEventListener('click', () => {
      activeRejectReason = optionButton.dataset.rejectReason || '';

      rejectReasonOptions.forEach((option) => {
        const isActive = option === optionButton;
        option.classList.toggle('is-active', isActive);

        if (isActive) {
          option.setAttribute('aria-selected', 'true');
        } else {
          option.removeAttribute('aria-selected');
        }
      });

      if (rejectOtherWrap instanceof HTMLElement) {
        rejectOtherWrap.hidden = activeRejectReason !== 'Others';
      }

      if (rejectOtherInput instanceof HTMLInputElement && activeRejectReason === 'Others') {
        rejectOtherInput.focus();
      }

      syncRejectConfirmState();
    });
  });

  if (rejectOtherInput instanceof HTMLInputElement) {
    rejectOtherInput.addEventListener('input', () => {
      syncRejectConfirmState();
    });
  }

  if (rejectReasonModal instanceof HTMLElement) {
    rejectReasonModal.addEventListener('click', (event) => {
      const target = event.target;

      if (target instanceof HTMLElement && target.dataset.closeOfficeRejectReason === 'true') {
        closeRejectReasonModal();

        if (activeRow instanceof HTMLTableRowElement) {
          openModal(activeRow);
        }
      }
    });
  }

  if (rejectReasonCancel instanceof HTMLButtonElement) {
    rejectReasonCancel.addEventListener('click', () => {
      closeRejectReasonModal();

      if (activeRow instanceof HTMLTableRowElement) {
        openModal(activeRow);
      }
    });
  }

  if (rejectReasonConfirm instanceof HTMLButtonElement) {
    rejectReasonConfirm.addEventListener('click', () => {
      if (!activeRejectReason) {
        return;
      }

      applyDecisionToRow('reject');
      closeRejectReasonModal();
      openRejectFeedbackModal();
    });
  }

  if (rejectFeedbackFinish instanceof HTMLButtonElement) {
    rejectFeedbackFinish.addEventListener('click', () => {
      closeRejectFeedbackModal();
      activeRow = null;
    });
  }

  if (approveButton instanceof HTMLButtonElement) {
    approveButton.addEventListener('click', () => {
      closeModal(false);
      openApproveConfirmModal();
    });
  }

  if (approveConfirmModal instanceof HTMLElement) {
    approveConfirmModal.addEventListener('click', (event) => {
      const target = event.target;

      if (target instanceof HTMLElement && target.dataset.closeOfficeApproveConfirm === 'true') {
        closeApproveConfirmModal();

        if (activeRow instanceof HTMLTableRowElement) {
          openModal(activeRow);
        }
      }
    });
  }

  if (approveConfirmCancel instanceof HTMLButtonElement) {
    approveConfirmCancel.addEventListener('click', () => {
      closeApproveConfirmModal();

      if (activeRow instanceof HTMLTableRowElement) {
        openModal(activeRow);
      }
    });
  }

  if (approveConfirmApprove instanceof HTMLButtonElement) {
    approveConfirmApprove.addEventListener('click', () => {
      applyDecisionToRow('approve');
      closeApproveConfirmModal();
      openApproveFeedbackModal();
    });
  }

  if (approveFeedbackFinish instanceof HTMLButtonElement) {
    approveFeedbackFinish.addEventListener('click', () => {
      closeApproveFeedbackModal();
      activeRow = null;
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) {
      closeModal();
    }

    if (event.key === 'Escape' && approveConfirmModal instanceof HTMLElement && approveConfirmModal.classList.contains('is-open')) {
      closeApproveConfirmModal();

      if (activeRow instanceof HTMLTableRowElement) {
        openModal(activeRow);
      }
    }

    if (event.key === 'Escape' && approveFeedbackModal instanceof HTMLElement && approveFeedbackModal.classList.contains('is-open')) {
      closeApproveFeedbackModal();
      activeRow = null;
    }

    if (event.key === 'Escape' && rejectReasonModal instanceof HTMLElement && rejectReasonModal.classList.contains('is-open')) {
      closeRejectReasonModal();

      if (activeRow instanceof HTMLTableRowElement) {
        openModal(activeRow);
      }
    }

    if (event.key === 'Escape' && rejectFeedbackModal instanceof HTMLElement && rejectFeedbackModal.classList.contains('is-open')) {
      closeRejectFeedbackModal();
      activeRow = null;
    }
  });
}

initToolbarSearchToggle();

if (searchInput && (reportTableBody || inventoryTableBody || historyTableBody || maintenanceTableBody || facilitiesTableBody || equipmentTableBody)) {
  searchInput.addEventListener('input', () => {
    if (facilitiesTableBody && facilitiesTabs.length) {
      applyFacilitiesFilters();
      return;
    }

    if (equipmentTableBody && equipmentTabs.length) {
      applyEquipmentFilters();
      return;
    }

    if (maintenanceTableBody && maintenanceTabs.length) {
      applyMaintenanceFilters();
      return;
    }

    if (historyTableBody && historyTabs.length) {
      applyHistoryFilters();
      return;
    }

    const term = searchInput.value.trim().toLowerCase();
    const activeTableBody = reportTableBody || inventoryTableBody || historyTableBody || maintenanceTableBody || facilitiesTableBody || equipmentTableBody;
    const rows = activeTableBody ? activeTableBody.querySelectorAll('tr') : [];

    rows.forEach((row) => {
      const rowText = row.textContent.toLowerCase();
      row.style.display = rowText.includes(term) ? '' : 'none';
    });
  });
}

if (historyTableBody && historyTabs.length) {
  historyTabs.forEach((tabButton) => {
    tabButton.addEventListener('click', () => {
      activeHistoryTab = tabButton.dataset.historyTab || 'latest';

      historyTabs.forEach((button) => {
        button.classList.toggle('active', button === tabButton);
      });

      applyHistoryFilters();
    });
  });

  applyHistoryFilters();
}

if (maintenanceTableBody && maintenanceTabs.length) {
  maintenanceTabs.forEach((tabButton) => {
    tabButton.addEventListener('click', () => {
      activeMaintenanceTab = tabButton.dataset.maintenanceTab || 'maintenance';

      maintenanceTabs.forEach((button) => {
        button.classList.toggle('active', button === tabButton);
      });

      applyMaintenanceFilters();
    });
  });

  if (maintenanceInlineSearchInput) {
    maintenanceInlineSearchInput.addEventListener('input', applyMaintenanceFilters);
  }

  maintenanceTableBody.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
      return;
    }

    const actionButton = target.closest('.maintenance-action-btn');

    if (!(actionButton instanceof HTMLButtonElement)) {
      return;
    }

    const row = actionButton.closest('tr');

    if (!(row instanceof HTMLTableRowElement)) {
      return;
    }

    openMaintenanceEvalModal(row);
  });

  applyMaintenanceFilters();
}

if (maintenanceEvalBackButton) {
  maintenanceEvalBackButton.addEventListener('click', () => {
    closeMaintenanceEvalModal();
    activeMaintenanceAddressRow = null;
  });
}

if (maintenanceEvalSettleButton) {
  maintenanceEvalSettleButton.addEventListener('click', () => {
    closeMaintenanceEvalModal();
    openMaintenanceFormModal();
  });
}

if (maintenanceFormSubmitButton) {
  maintenanceFormSubmitButton.addEventListener('click', () => {
    if (!maintenanceAssessmentInput || !maintenanceStatusSelect) {
      closeMaintenanceFormModal();
      activeMaintenanceAddressRow = null;
      return;
    }

    const assessmentValue = maintenanceAssessmentInput.value.trim();
    const statusValue = maintenanceStatusSelect.value.trim();

    if (!assessmentValue || !statusValue) {
      window.alert('Please complete Assessment and Status.');
      return;
    }

    closeMaintenanceFormModal();
    activeMaintenanceAddressRow = null;
    window.alert('Maintenance evaluation submitted.');
  });
}

if (facilitiesTableBody && facilitiesTabs.length) {
  facilitiesTableBody.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
      return;
    }

    const editButton = target.closest('.table-edit-btn');

    if (!(editButton instanceof HTMLButtonElement)) {
      return;
    }

    const row = editButton.closest('tr');

    if (row) {
      openFacilitiesEditModal(row);
    }
  });

  facilitiesTabs.forEach((tabButton) => {
    tabButton.addEventListener('click', () => {
      activeFacilitiesTab = tabButton.dataset.tab || 'rooms';

      facilitiesTabs.forEach((button) => {
        button.classList.toggle('active', button === tabButton);
      });

      applyFacilitiesFilters();
    });
  });

  if (facilitiesInlineSearchInput) {
    facilitiesInlineSearchInput.addEventListener('input', applyFacilitiesFilters);
  }

  applyFacilitiesFilters();
}

if (facilitiesAddButton && facilitiesEditModal) {
  facilitiesAddButton.addEventListener('click', openFacilitiesAddModal);
}

if (equipmentTableBody && equipmentTabs.length) {
  equipmentTableBody.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
      return;
    }

    const editButton = target.closest('.table-edit-btn');

    if (!(editButton instanceof HTMLButtonElement)) {
      return;
    }

    const row = editButton.closest('tr');

    if (row) {
      openEquipmentEditModal(row);
    }
  });

  equipmentTabs.forEach((tabButton) => {
    tabButton.addEventListener('click', () => {
      activeEquipmentTab = tabButton.dataset.equipmentTab || 'multimedia';

      equipmentTabs.forEach((button) => {
        button.classList.toggle('active', button === tabButton);
      });

      applyEquipmentFilters();
    });
  });

  if (equipmentInlineSearchInput) {
    equipmentInlineSearchInput.addEventListener('input', applyEquipmentFilters);
  }

  applyEquipmentFilters();
}

if (equipmentAddButton && equipmentEditModal) {
  equipmentAddButton.addEventListener('click', openEquipmentAddModal);
}

if (scheduleFilterButtons.length && scheduleDayCells.length) {
  scheduleFilterButtons.forEach((button) => {
    button.addEventListener('click', () => {
      applyScheduleCategory(button.dataset.scheduleFilter || 'all');
    });
  });

  scheduleDayCells.forEach((dayCell) => {
    dayCell.addEventListener('click', () => {
      if (!dayCell.classList.contains('marked')) {
        return;
      }

      const day = Number.parseInt(dayCell.dataset.day || '', 10);

      if (!Number.isNaN(day)) {
        openScheduleInlineDetails(day);
      }
    });
  });

  applyScheduleCategory('all');
}

if (scheduleInlineRequestBody) {
  scheduleInlineRequestBody.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof HTMLElement)) {
      return;
    }

    const requestRow = target.closest('tr[data-inline-request-index]');

    if (!(requestRow instanceof HTMLTableRowElement)) {
      return;
    }

    const requestIndex = Number.parseInt(requestRow.dataset.inlineRequestIndex || '', 10);
    const requestDate = selectedScheduleDay ? getScheduleDateLabel(selectedScheduleDay) : '--';
    const request = visibleScheduleInlineRequests[requestIndex];

    if (request) {
      renderScheduleInlineDetail(request, requestDate);
    }
  });
}

if (requestItems.length) {
  requestItems.forEach((item) => {
    const approveButton = item.querySelector('.approve-btn');
    const rejectButton = item.querySelector('.reject-btn');

    if (approveButton) {
      approveButton.addEventListener('click', (event) => {
        event.stopPropagation();
        if (approveButton.dataset.reservationId) {
          submitFinalRequestDecision(item, approveButton, 'approved');
          return;
        }

        submitRequestDecision(item, approveButton, 'approved');
      });
    }

    if (rejectButton) {
      rejectButton.addEventListener('click', (event) => {
        event.stopPropagation();
        if (rejectButton.dataset.reservationId) {
          submitFinalRequestDecision(item, rejectButton, 'rejected');
          return;
        }

        submitRequestDecision(item, rejectButton, 'rejected');
      });
    }

    item.addEventListener('click', (event) => {
      const target = event.target;

      if (target instanceof HTMLElement && target.closest('.approve-btn, .reject-btn')) {
        return;
      }

      const isAlreadySelected = item.classList.contains('is-selected');

      requestItems.forEach((node) => {
        node.classList.remove('is-selected');
      });

      if (!isAlreadySelected) {
        item.classList.add('is-selected');
      }
    });
  });
}

if (requestTabs.length) {
  requestTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      setRequestTabMode(tab.dataset.requestTab || 'final');
    });
  });

  const defaultActiveTab = document.querySelector('[data-request-tab].active');
  setRequestTabMode(defaultActiveTab instanceof HTMLElement ? defaultActiveTab.dataset.requestTab || 'final' : 'final');
}

if (facilitiesCancelButton) {
  facilitiesCancelButton.addEventListener('click', closeFacilitiesEditModal);
}

if (facilitiesAddButton) {
  facilitiesAddButton.addEventListener('click', openFacilitiesAddModal);
}

if (facilitiesSaveButton) {
  facilitiesSaveButton.addEventListener('click', async () => {
    if (!facilitiesTableBody || !facilitiesItemNameInput || !facilitiesCategoryInput) {
      closeFacilitiesEditModal();
      return;
    }

    const itemName = facilitiesItemNameInput.value.trim();
    const category = facilitiesCategoryInput.value.trim();

    if (!itemName || !category) {
      window.alert('Please complete Facility Name and Room Type.');
      return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]');

    if (!csrfToken) {
      window.alert('Unable to save changes. Missing CSRF token.');
      return;
    }

    try {
      const isEditing = Boolean(activeEditingRow && activeEditingRow.dataset.facilityId);
      const endpoint = isEditing
        ? `/dashboard/inventory/facilities/${encodeURIComponent(activeEditingRow.dataset.facilityId)}`
        : '/dashboard/inventory/facilities';
      const response = await fetch(endpoint, {
        method: isEditing ? 'PATCH' : 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken.content,
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          item_name: itemName,
          category,
        }),
      });

      const responseText = await response.text();
      let payload = {};

      try {
        payload = responseText ? JSON.parse(responseText) : {};
      } catch (error) {
        payload = {};
      }

      if (!response.ok || !payload.success) {
        window.alert(payload.error || `Unable to save facility changes. (HTTP ${response.status})`);
        return;
      }

      const facility = payload.facility;

      if (isEditing && activeEditingRow) {
        const cells = activeEditingRow.querySelectorAll('td');
        activeEditingRow.dataset.facilityCategory = facility.classification_key || facility.category || 'rooms';
        activeEditingRow.dataset.facilityRoomType = facility.room_type || '';

        if (cells[0]) {
          cells[0].textContent = facility.asset_id;
        }

        if (cells[1]) {
          cells[1].textContent = facility.item_name;
        }

        if (cells[2]) {
          cells[2].textContent = facility.classification;
        }

        if (cells[3]) {
          cells[3].textContent = facility.location;
        }
      } else {
        const row = document.createElement('tr');
        row.dataset.facilityId = facility.room_id;
        row.dataset.facilityCategory = facility.classification_key || facility.category || 'rooms';
        row.dataset.facilityRoomType = facility.room_type || '';
        row.innerHTML = `
          <td>${facility.asset_id}</td>
          <td>${facility.item_name}</td>
          <td>${facility.classification}</td>
          <td>${facility.location}</td>
          <td><button class="table-edit-btn" type="button">Edit</button></td>
        `;

        facilitiesTableBody.prepend(row);
      }

      closeFacilitiesEditModal();
      applyFacilitiesFilters();
      showSaveSuccessToast(isEditing ? 'Edited successfully.' : 'Added successfully.');
    } catch (error) {
      window.alert('Unable to save facility changes right now. Please check your connection and try again.');
    }
  });
}

if (equipmentSaveButton) {
  equipmentSaveButton.addEventListener('click', async () => {
    if (!equipmentTableBody || !equipmentItemNameInput || !equipmentCategoryInput || !equipmentTotalCountInput || !equipmentInUseInput || !equipmentStatusInput) {
      closeEquipmentEditModal();
      return;
    }

    const itemName = equipmentItemNameInput.value.trim();
    const category = equipmentCategoryInput.value.trim();
    const totalCount = equipmentTotalCountInput.value.trim() || '0';
    const inUse = equipmentInUseInput.value.trim() || '0';
    const status = equipmentStatusInput.value.trim();
    if (!itemName || !category || !status) {
      window.alert('Please complete Item Name, Category, and Status.');
      return;
    }

    if (!activeEquipmentEditingRow) {
      const csrfToken = document.querySelector('meta[name="csrf-token"]');

      if (!csrfToken) {
        window.alert('Unable to save changes. Missing CSRF token.');
        return;
      }

      try {
        const response = await fetch('/dashboard/inventory/equipments', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken.content,
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            item_name: itemName,
            category,
            total_count: Number.parseInt(totalCount, 10) || 1,
            in_use: Number.parseInt(inUse, 10) || 0,
            status,
          }),
        });

        const responseText = await response.text();
        let payload = {};

        try {
          payload = responseText ? JSON.parse(responseText) : {};
        } catch (error) {
          payload = {};
        }

        if (!response.ok || !payload.success) {
          window.alert(payload.error || `Unable to save equipment changes. (HTTP ${response.status})`);
          return;
        }

        const createdItem = payload.item;
        const row = document.createElement('tr');
        row.dataset.equipmentRow = createdItem.category;
        row.dataset.itemId = createdItem.item_id;
        row.innerHTML = `
          <td>${createdItem.asset_id}</td>
          <td>${createdItem.item_name}</td>
          <td>${createdItem.total_count}</td>
          <td>${createdItem.in_use}</td>
          <td><span class="status-pill ${createdItem.status_key}">${createdItem.status_label}</span></td>
          <td><button class="table-edit-btn" type="button">Edit</button></td>
        `;

        equipmentTableBody.prepend(row);
        closeEquipmentEditModal();
        applyEquipmentFilters();
        showSaveSuccessToast('Added successfully.');
        return;
      } catch (error) {
        window.alert('Unable to save equipment changes right now. Please check your connection and try again.');
        return;
      }
    }

    const statusClass = getEquipmentStatusClass(status);
    const statusLabel = getEquipmentStatusLabel(status);

    const itemId = activeEquipmentEditingRow.dataset.itemId;

    if (itemId) {
      const csrfToken = document.querySelector('meta[name="csrf-token"]');

      if (!csrfToken) {
        window.alert('Unable to save changes. Missing CSRF token.');
        return;
      }

      try {
        const response = await fetch(`/dashboard/inventory/equipments/${encodeURIComponent(itemId)}`, {
          method: 'PATCH',
          headers: {
            'X-CSRF-TOKEN': csrfToken.content,
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            item_name: itemName,
            category,
            total_count: Number.parseInt(totalCount, 10) || 1,
            in_use: Number.parseInt(inUse, 10) || 0,
            status,
          }),
        });

        const responseText = await response.text();
        let payload = {};

        try {
          payload = responseText ? JSON.parse(responseText) : {};
        } catch (error) {
          payload = {};
        }

        if (!response.ok || !payload.success) {
          window.alert(payload.error || `Unable to save equipment changes. (HTTP ${response.status})`);
          return;
        }

        const updatedItem = payload.item;
        const cells = activeEquipmentEditingRow.querySelectorAll('td');

        activeEquipmentEditingRow.dataset.equipmentRow = updatedItem.category;

        if (cells[0]) {
          cells[0].textContent = updatedItem.asset_id;
        }

        if (cells[1]) {
          cells[1].textContent = updatedItem.item_name;
        }

        if (cells[2]) {
          cells[2].textContent = String(updatedItem.total_count);
        }

        if (cells[3]) {
          cells[3].textContent = String(updatedItem.in_use);
        }

        if (cells[4]) {
          cells[4].innerHTML = `<span class="status-pill ${updatedItem.status_key}">${updatedItem.status_label}</span>`;
        }

        closeEquipmentEditModal();
        applyEquipmentFilters();
        showSaveSuccessToast('Edited successfully.');
        return;
      } catch (error) {
        window.alert('Unable to save equipment changes right now. Please check your connection and try again.');
        return;
      }
    }

    const cells = activeEquipmentEditingRow.querySelectorAll('td');
    activeEquipmentEditingRow.dataset.equipmentRow = category;

    if (cells[1]) {
      cells[1].textContent = itemName;
    }

    if (cells[2]) {
      cells[2].textContent = totalCount;
    }

    if (cells[3]) {
      cells[3].textContent = inUse;
    }

    if (cells[4]) {
      cells[4].innerHTML = `<span class="status-pill ${statusClass}">${statusLabel}</span>`;
    }

    closeEquipmentEditModal();
    applyEquipmentFilters();
    showSaveSuccessToast('Edited successfully.');
  });
}

if (facilitiesUploadButton && facilitiesUploadInput) {
  facilitiesUploadButton.addEventListener('click', () => {
    facilitiesUploadInput.click();
  });
}

if (facilitiesUploadInput && facilitiesUploadName) {
  facilitiesUploadInput.addEventListener('change', () => {
    const file = facilitiesUploadInput.files && facilitiesUploadInput.files[0];

    if (!file) {
      facilitiesUploadName.textContent = 'No file selected';
      return;
    }

    const validTypes = ['image/jpeg', 'image/png'];
    const maxSizeBytes = 5 * 1024 * 1024;
    const lowerName = file.name.toLowerCase();
    const validExt = lowerName.endsWith('.jpg') || lowerName.endsWith('.jpeg') || lowerName.endsWith('.png');
    const validType = validTypes.includes(file.type) || (file.type === '' && validExt);

    if (!validType) {
      facilitiesUploadInput.value = '';
      facilitiesUploadName.textContent = 'No file selected';
      window.alert('Invalid file type. Please upload JPG or PNG only.');
      return;
    }

    if (file.size > maxSizeBytes) {
      facilitiesUploadInput.value = '';
      facilitiesUploadName.textContent = 'No file selected';
      window.alert('File is too large. Maximum size is 5MB.');
      return;
    }

    facilitiesUploadName.textContent = file.name;
  });
}

if (equipmentUploadButton && equipmentUploadInput) {
  equipmentUploadButton.addEventListener('click', () => {
    equipmentUploadInput.click();
  });
}

if (equipmentUploadInput && equipmentUploadName) {
  equipmentUploadInput.addEventListener('change', () => {
    const file = equipmentUploadInput.files && equipmentUploadInput.files[0];

    if (!file) {
      equipmentUploadName.textContent = 'No file selected';
      return;
    }

    const validTypes = ['image/jpeg', 'image/png'];
    const maxSizeBytes = 5 * 1024 * 1024;
    const lowerName = file.name.toLowerCase();
    const validExt = lowerName.endsWith('.jpg') || lowerName.endsWith('.jpeg') || lowerName.endsWith('.png');
    const validType = validTypes.includes(file.type) || (file.type === '' && validExt);

    if (!validType) {
      equipmentUploadInput.value = '';
      equipmentUploadName.textContent = 'No file selected';
      window.alert('Invalid file type. Please upload JPG or PNG only.');
      return;
    }

    if (file.size > maxSizeBytes) {
      equipmentUploadInput.value = '';
      equipmentUploadName.textContent = 'No file selected';
      window.alert('File is too large. Maximum size is 5MB.');
      return;
    }

    equipmentUploadName.textContent = file.name;
  });
}

if (facilitiesEditModal) {
  facilitiesEditModal.addEventListener('click', (event) => {
    const target = event.target;

    if (target instanceof HTMLElement && target.dataset.closeModal === 'true') {
      closeFacilitiesEditModal();
    }
  });
}

if (equipmentEditModal) {
  equipmentEditModal.addEventListener('click', (event) => {
    const target = event.target;

    if (target instanceof HTMLElement && target.dataset.closeEquipmentModal === 'true') {
      closeEquipmentEditModal();
    }
  });
}

if (scheduleRequestModal) {
  scheduleRequestModal.addEventListener('click', (event) => {
    const target = event.target;

    if (target instanceof HTMLElement && target.dataset.closeScheduleModal === 'true') {
      closeScheduleRequestModal();
    }
  });
}

if (scheduleDetailModal) {
  scheduleDetailModal.addEventListener('click', (event) => {
    const target = event.target;

    if (target instanceof HTMLElement && target.dataset.closeScheduleDetail === 'true') {
      closeScheduleDetailModal();
    }
  });
}

if (maintenanceEvalModal) {
  maintenanceEvalModal.addEventListener('click', (event) => {
    const target = event.target;

    if (target instanceof HTMLElement && target.dataset.closeMaintenanceEval === 'true') {
      closeMaintenanceEvalModal();
      activeMaintenanceAddressRow = null;
    }
  });
}

if (maintenanceFormModal) {
  maintenanceFormModal.addEventListener('click', (event) => {
    const target = event.target;

    if (target instanceof HTMLElement && target.dataset.closeMaintenanceForm === 'true') {
      closeMaintenanceFormModal();
      activeMaintenanceAddressRow = null;
    }
  });
}

if (scheduleDetailCancel) {
  scheduleDetailCancel.addEventListener('click', closeScheduleDetailModal);
}

if (equipmentCancelButton) {
  equipmentCancelButton.addEventListener('click', closeEquipmentEditModal);
}

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && messagePopover) {
    closeMessagesPopover();
  }

  if (event.key === 'Escape' && isToolbarSearchExpanded) {
    setToolbarSearchExpanded(false);
  }

  if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
    closeSidebarDrawer();
  }

  if (event.key === 'Escape' && profilePopover) {
    closeProfilePopover();
  }

  if (event.key === 'Escape' && profileEditModal && profileEditModal.classList.contains('is-open')) {
    closeProfileEditModal();
  }

  if (event.key === 'Escape' && notificationPopover) {
    closeNotificationsPopover();
  }

  if (event.key === 'Escape' && facilitiesEditModal && facilitiesEditModal.classList.contains('is-open')) {
    closeFacilitiesEditModal();
  }

  if (event.key === 'Escape' && equipmentEditModal && equipmentEditModal.classList.contains('is-open')) {
    closeEquipmentEditModal();
  }

  if (event.key === 'Escape' && scheduleRequestModal && scheduleRequestModal.classList.contains('is-open')) {
    closeScheduleRequestModal();
  }

  if (event.key === 'Escape' && scheduleDetailModal && scheduleDetailModal.classList.contains('is-open')) {
    closeScheduleDetailModal();
  }

  if (event.key === 'Escape' && maintenanceEvalModal && maintenanceEvalModal.classList.contains('is-open')) {
    closeMaintenanceEvalModal();
    activeMaintenanceAddressRow = null;
  }

  if (event.key === 'Escape' && maintenanceFormModal && maintenanceFormModal.classList.contains('is-open')) {
    closeMaintenanceFormModal();
    activeMaintenanceAddressRow = null;
  }
});

if (workloadProgress && workloadLabel) {
  const percent = 65;
  workloadProgress.style.width = `${percent}%`;
  workloadLabel.textContent = `${percent}%`;
}

window.addEventListener('resize', () => {
  if (window.innerWidth > 1100) {
    closeSidebarDrawer();
  }
});

document.addEventListener('DOMContentLoaded', async () => {
  await loadNavbar();
  initOfficeQuickDateControl();
  initOfficeQuickSortControl();
  initOfficeReservationModal();
});
