<?php // index.php ?>
<?php
session_start();
require_once "../authCookieSessionValidate.php";
require_once __DIR__ . '/../config/config.php';
$appConfig = dp_get_config();

if (!$isLoggedIn) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <title>Patienten-Verwaltung</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.3/font/bootstrap-icons.min.css">
  <style>
    .main-content { padding: 2em; }
    #worker-filter { padding: 0.5em; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; margin-left: 0.5em; }

    /* Sortable table headers */
    th.sortable { cursor: pointer; position: relative; user-select: none; }
    th.sortable:hover { background-color: #f0f0f0; }
    th.sortable::after { content: ' ↕'; color: #ccc; font-size: 0.8em; }
    th.sortable.asc::after { content: ' ↑'; color: #333; }
    th.sortable.desc::after { content: ' ↓'; color: #333; }

    table { width: 100%; border-collapse: collapse; margin-top: 1em; }
    th, td { border: 1px solid #ddd; padding: 0.5em; text-align: left; }
    tr.expired { background: #fdd; }
    #report { margin-top: 1em; }

    /* Edit mode styles */
    #edit-mode-toggle.active { background-color: #ffc107; border-color: #ffc107; color: #212529; }

    .edit-mode tr.other-brief { background-color: #e3f2fd; cursor: pointer; transition: background-color 0.2s; }
    .edit-mode tr.other-brief:hover { background-color: #bbdefb; }
    .edit-mode tr.other-brief td:first-child { border-left: 4px solid #2196f3; }

    /* Navbar button styling */
    .navbar-btn { margin: 0 !important; }

    /* Full-page drop overlay */
    #full-page-drop-overlay {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: linear-gradient(135deg, rgba(128, 128, 128, 0.9), rgba(34, 139, 34, 0.9));
      display: flex; align-items: center; justify-content: center;
      z-index: 9999; opacity: 0; visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    #full-page-drop-overlay.active { opacity: 1; visibility: visible; }
    #drop-overlay-content {
      text-align: center; color: white; font-size: 2em; font-weight: bold;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.5); pointer-events: none; transform: scale(0.8);
      transition: transform 0.3s ease;
    }
    #full-page-drop-overlay.active #drop-overlay-content { transform: scale(1); animation: pulse 2s infinite; }
    #drop-overlay-content .subtitle { font-size: 0.6em; margin-top: 0.5em; font-weight: normal; opacity: 0.9; }

    /* Pulse animation */
    @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
  </style>
</head>
<body>
<?php
$navbarActivePage = 'liste';
$navbarBasePath = '..';
ob_start();
?>
          <button id="edit-mode-toggle" class="btn btn-outline-warning navbar-btn me-3" type="button">
            <i class="bi bi-pencil-square"></i> Bearbeiten
          </button>
<?php
$navbarCenterContent = ob_get_clean();
require __DIR__ . '/../navbar.php';
?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="m-0">Patienten-Verwaltung</h1>
            <div class="text-end">
                <div style="font-size: 1rem;">
                    <span class="badge bg-info text-dark d-block mb-1">
                        Tage bis zum Wechsel auf Privatstation: <span id="days-remaining">...</span>
                    </span>
                    <span class="badge bg-secondary d-block">
                        Nächster Kandidat: <strong>Abdul</strong>
                    </span>
      </div>
    </div>
  </div>

  <!-- Filter & Search Row -->
  <div class="row mb-3">
    <div class="col-md-6">
      <label for="worker-filter" class="form-label fw-bold">Nach Zuständigkeit filtern:</label>
      <select id="worker-filter" class="form-select">
        <option value="">Alle anzeigen</option>
      </select>
    </div>
    <div class="col-md-6">
      <label for="search-discharge" class="form-label fw-bold">Entlassene Patienten suchen:</label>
      <div class="input-group">
        <input type="text" id="search-discharge" class="form-control" placeholder="Name oder Vorname">
        <button id="clear-search" class="btn btn-outline-secondary" type="button">Zurücksetzen</button>
        <label for="file-upload" class="btn btn-outline-primary mb-0" style="margin-left:0.5rem; cursor:pointer;">
          <i class="bi bi-upload"></i> Datei auswählen
        </label>
        <input type="file" id="file-upload" accept=".csv,text/csv" style="display:none" />
      </div>
    </div>
  </div>

  <div id="report" class="mb-3 text-muted small"></div>

  <table id="client-table">
    <thead>
      <tr>
        <th class="sortable" data-column="worker_name">Zuständig</th>
        <th class="sortable" data-column="name">Name</th>
        <th class="sortable" data-column="vorname">Vorname</th>
        <th class="sortable" data-column="geschlecht">Geschlecht</th>
        <th class="sortable" data-column="geburtsdatum">Geburtsdatum</th>
        <th class="sortable" data-column="alter">Alter</th>
        <th class="sortable" data-column="aufnahmedatum">Aufnahmedatum</th>
        <th class="sortable" data-column="status">Entlassen</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>

  <!-- Full-page drop overlay -->
  <div id="full-page-drop-overlay">
    <div id="drop-overlay-content">
      <div>CSV-Datei hier ablegen</div>
      <div class="subtitle">(Name;Vorname;Geschlecht;Geburtsdatum;P/S;Aufnahmedatum;Raum)</div>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="confirmationModalLabel">
            <i class="bi bi-person-check"></i> Zuständigkeit übernehmen
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p id="modal-patient-info"></p>
          <div class="alert alert-info" role="alert">
            <i class="bi bi-info-circle"></i>
            Sie werden zum neuen Zuständigen für diesen Patienten.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle"></i> Abbrechen
          </button>
          <button type="button" class="btn btn-primary" id="confirm-takeover">
            <i class="bi bi-check-circle"></i> Zuständigkeit übernehmen
          </button>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const dz = null,
        report = document.getElementById('report'),
        tbody = document.querySelector('#client-table tbody'),
        fullPageOverlay = document.getElementById('full-page-drop-overlay'),
        workerFilter = document.getElementById('worker-filter'),
        sortableHeaders = document.querySelectorAll('th.sortable'),
        editModeToggle = document.getElementById('edit-mode-toggle');

  let dragCounter = 0;
  let allClients = [];      // All clients
  let filteredClients = []; // Filtered + view set
  let currentSort = { column: null, direction: 'asc' };
  let isEditMode = false;
  let currentUserId = <?php echo $_SESSION["member_id"] ?? 'null'; ?>;

  // Declare these BEFORE any assignment in loadClients
  let activeClients = [];   // Only active ones
  let expiredClients = [];  // Only discharged ones

  // Edit mode toggle functionality
  editModeToggle.addEventListener('click', () => {
    isEditMode = !isEditMode;
    editModeToggle.classList.toggle('active', isEditMode);
    document.body.classList.toggle('edit-mode', isEditMode);

    if (isEditMode) {
      editModeToggle.innerHTML = '<i class="bi bi-check-square"></i> Bearbeiten Aktiv';
      report.innerHTML = '<span style="color:#0066cc"><strong>Bearbeitungsmodus aktiv:</strong> Klicken Sie auf Patienten anderer Kollegen, um die Zuständigkeit zu übernehmen.</span>';
    } else {
      editModeToggle.innerHTML = '<i class="bi bi-pencil-square"></i> Bearbeiten';
      if (report.innerHTML.includes('Bearbeitungsmodus aktiv')) {
        report.innerHTML = '';
      }
    }

    // Re-render table to apply/remove edit mode classes
    displayClients(filteredClients);
  });

  // Worker filter functionality
  workerFilter.addEventListener('change', () => {
    filterClients();
  });

  // Sorting functionality
  sortableHeaders.forEach(header => {
    header.addEventListener('click', () => {
      const column = header.dataset.column;

      if (currentSort.column === column) {
        currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
      } else {
        currentSort.column = column;
        currentSort.direction = 'asc';
      }

      sortableHeaders.forEach(h => h.classList.remove('asc', 'desc'));
      header.classList.add(currentSort.direction);

      sortClients();
    });
  });

  function filterClients() {
    const selectedWorker = workerFilter.value;
    filteredClients = allClients.filter(c => {
      const isActiveClient = c.status === "active";
      const matchesWorker = selectedWorker === '' || c.worker_name === selectedWorker;
      return isActiveClient && matchesWorker;
    });

    if (currentSort.column) {
      sortClients();
    } else {
      displayClients(filteredClients);
    }
  }

  function sortClients() {
    const sorted = [...filteredClients].sort((a, b) => {
      const column = currentSort.column;
      let aVal = a[column] || '';
      let bVal = b[column] || '';

      if (column === 'alter') {
        aVal = parseInt(aVal) || 0;
        bVal = parseInt(bVal) || 0;
      } else if (column === 'geburtsdatum' || column === 'aufnahmedatum') {
        aVal = new Date(aVal) || new Date(0);
        bVal = new Date(bVal) || new Date(0);
      } else if (column === 'status') {
        aVal = a.status === 'expired' ? 'Ja' : 'Nein';
        bVal = b.status === 'expired' ? 'Ja' : 'Nein';
      } else {
        aVal = aVal.toString().toLowerCase();
        bVal = bVal.toString().toLowerCase();
      }

      let comparison = 0;
      if (aVal < bVal) comparison = -1;
      else if (aVal > bVal) comparison = 1;

      return currentSort.direction === 'desc' ? -comparison : comparison;
    });

    displayClients(sorted);
  }

  function displayClients(clients) {
    tbody.innerHTML = '';
    clients.forEach(c => {
      const tr = document.createElement('tr');
      if (c.status === 'expired') tr.classList.add('expired');

      // Rows that belong to others become claimable in edit mode
      const isOtherBrief = c.user_id && c.user_id != currentUserId;
      if (isEditMode && isOtherBrief) {
        tr.classList.add('other-brief');
        tr.style.cursor = 'pointer';
        tr.title = 'Klicken, um die Zuständigkeit zu übernehmen';

        tr.addEventListener('click', () => {
          takeOverResponsibility(c.id, c.name, c.vorname);
        });
      }

      tr.innerHTML = `
        <td>${c.worker_name || ''}</td>
        <td>${c.name}</td>
        <td>${c.vorname}</td>
        <td>${c.geschlecht || ''}</td>
        <td>${c.geburtsdatum}</td>
        <td>${c.alter}</td>
        <td>${c.aufnahmedatum}</td>
        <td>${c.status === 'expired' ? 'Ja' : 'Nein'}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  function updateWorkerFilter() {
    const workers = [...new Set(allClients.map(c => c.worker_name).filter(w => w))].sort();

    workerFilter.innerHTML = '<option value="">Alle anzeigen</option>';

    workers.forEach(worker => {
      const option = document.createElement('option');
      option.value = worker;
      option.textContent = worker;
      workerFilter.appendChild(option);
    });
  }

  // Takeover flow
  let pendingTakeover = null;

  function takeOverResponsibility(clientId, clientName, clientVorname) {
    pendingTakeover = { clientId, clientName, clientVorname };
    document.getElementById('modal-patient-info').innerHTML =
      `Möchten Sie die Zuständigkeit für <strong>${clientName}, ${clientVorname}</strong> übernehmen?`;
    const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    modal.show();
  }

  document.getElementById('confirm-takeover').addEventListener('click', async () => {
    if (!pendingTakeover) return;

    const btn = document.getElementById('confirm-takeover');
    btn.disabled = true;

    try {
      const { clientId } = pendingTakeover;
      const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
      modal.hide();

      const resp = await fetch('update_responsible.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ client_id: clientId })
      });
      const data = await resp.json();

      if (data.success) {
        report.innerHTML = `<span style="color:green">${data.message}: ${data.client_name}</span>`;
        loadClients();
      } else {
        report.innerHTML = `<span style="color:red">Fehler: ${data.error}</span>`;
      }
    } catch (error) {
      report.innerHTML = '<span style="color:red">Fehler bei der Übertragung der Zuständigkeit</span>';
    } finally {
      pendingTakeover = null;
      btn.disabled = false;
    }
  });

  // Full-page drag and drop functionality
  document.addEventListener('dragenter', e => {
    e.preventDefault();
    dragCounter++;
    if (e.dataTransfer && e.dataTransfer.types && Array.from(e.dataTransfer.types).includes('Files')) {
      fullPageOverlay.classList.add('active');
    }
  });

  const fileUploadInput = document.getElementById('file-upload');
  fileUploadInput.addEventListener('change', (e) => {
    const files = e.target.files;
    if (files.length > 0) {
      const file = files[0];
      if (file.type === 'text/csv' || file.name.toLowerCase().endsWith('.csv')) {
        uploadFile(file);
      } else {
        report.innerHTML = `<span style="color:red">Bitte nur CSV-Dateien hochladen</span>`;
      }
      e.target.value = ''; // allow selecting the same file again
    }
  });

  document.addEventListener('dragleave', e => {
    e.preventDefault();
    dragCounter--;
    if (dragCounter <= 0) {
      dragCounter = 0;
      fullPageOverlay.classList.remove('active');
    }
  });

  document.addEventListener('dragover', e => {
    e.preventDefault();
    if (e.dataTransfer && e.dataTransfer.types && Array.from(e.dataTransfer.types).includes('Files')) {
      fullPageOverlay.classList.add('active');
    }
  });

  document.addEventListener('drop', e => {
    e.preventDefault();
    dragCounter = 0;
    fullPageOverlay.classList.remove('active');

    if (e.dataTransfer.files.length > 0) {
      const file = e.dataTransfer.files[0];
      if (file.type === 'text/csv' || file.name.toLowerCase().endsWith('.csv')) {
        uploadFile(file);
      } else {
        report.innerHTML = `<span style="color:red">Bitte nur CSV-Dateien hochladen</span>`;
      }
    }
  });

  fullPageOverlay.addEventListener('click', () => {
    fullPageOverlay.classList.remove('active');
    dragCounter = 0;
  });

  // Days remaining display (6 months after 2025-11-24)
  const startDate = new Date("2025-11-24");
  const targetDate = new Date(startDate);
  targetDate.setMonth(targetDate.getMonth() + 6);
  const today = new Date();
  const msPerDay = 1000 * 60 * 60 * 24;
  const diffMs = targetDate - today;
  const remainingDays = Math.max(0, Math.floor(diffMs / msPerDay));
  document.getElementById("days-remaining").textContent = remainingDays;

  function uploadFile(file) {
    report.textContent = 'Verarbeite…';
    const fd = new FormData();
    fd.append('file', file);
    fetch('upload.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(j => {
        if (!j.success) {
          report.innerHTML = `<span style="color:red">Fehler: ${j.error}</span>`;
        } else {
          report.textContent = `Entlassen: ${j.expired_count} | Eingefügt: ${j.inserted_count} | Reaktiviert: ${j.reactivated_count}`;
          loadClients();
        }
      })
      .catch(() => report.innerHTML = `<span style="color:red">Upload fehlgeschlagen</span>`);
  }

  function loadClients() {
    fetch('clients.php')
      .then(r => r.json())
      .then(list => {
        allClients = list;
        activeClients = list.filter(c => c.status !== 'expired');
        expiredClients = list.filter(c => c.status === 'expired');
        updateWorkerFilter();
        filteredClients = [...activeClients]; // initially show active
        displayClients(filteredClients);
      });
  }

  // Initial load
  loadClients();

  // Discharge search handlers
  const searchInput = document.getElementById('search-discharge');
  const clearButton = document.getElementById('clear-search');

  searchInput.addEventListener('input', () => {
    const query = searchInput.value.toLowerCase().trim();

    if (query === '') {
      displayClients(activeClients);
      return;
    }

    const results = expiredClients.filter(c =>
      (c.name || '').toLowerCase().includes(query) ||
      (c.vorname || '').toLowerCase().includes(query)
    );

    displayClients(results);
  });

  clearButton.addEventListener('click', () => {
    searchInput.value = '';
    workerFilter.value = ''; // reset the select dropdown
    displayClients(activeClients); // revert to original view
  });
  
})();
</script>

<script src="../js/sw-manager.js"></script>
<script src="../js/dienstplan-persistent-cache.js"></script>
<script src="../js/nav-cache-refresh.js"></script>

</body>
</html>
