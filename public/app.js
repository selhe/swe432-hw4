// All communication with the back end goes through api() using fetch()

const API_URL = 'api.php';

const COLUMNS = {
  students: [
    ['Name', s => `${s.first} ${s.last}`],
    ['Email', s => s.email],
    ['Birthdate', s => s.birthdate],
    ['Enrolled', s => s.date_of_enrollment],
  ],
  courses: [
    ['Code', c => c.code],
    ['Title', c => c.title],
    ['Credits', c => c.credits],
  ],
  prerequisites: [
    ['Course', p => `${p.course_code} – ${p.course_title}`],
    ['Requires', p => `${p.prereq_code} – ${p.prereq_title}`],
  ],
  enrollments: [
    ['Student', e => `${e.first} ${e.last}`],
    ['Course', e => `${e.course_code} – ${e.course_title}`],
    ['Grade', e => e.grade],
  ],
};

// readable labels for the drop down lists
const OPTION_LABELS = {
  students: s => `${s.last}, ${s.first} (${s.email})`,
  courses: c => `${c.code} – ${c.title}`,
};

const statusBox = document.getElementById('status');

// ---------- helpers ----------

/** Calls the back end & resolves with the JSON body/throws an Error with the server's message */
async function api(params, method = 'GET', body = null) {
  const options = { method, headers: {} };
  if (body !== null) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }
  const response = await fetch(`${API_URL}?${new URLSearchParams(params)}`, options);
  const data = await response.json();
  if (!response.ok) {
    throw new Error(data.error || `Request failed (${response.status})`);
  }
  return data;
}

/** type is 'loading', 'success' or 'error' */
function showStatus(message, type) {
  statusBox.textContent = message;
  statusBox.className = type;
  statusBox.hidden = false;
}

/** Fills a <select> with records from the server */
async function fillSelect(select, resource) {
  const { data } = await api({ resource });
  select.replaceChildren(new Option('Choose…', ''));
  for (const row of data) {
    select.add(new Option(OPTION_LABELS[resource](row), row.id));
  }
}

// ---------- menu ----------

/** only switches which section is visible */
function switchTo(name) {
  for (const button of document.querySelectorAll('nav button')) {
    button.setAttribute('aria-pressed', String(button.dataset.view === name));
  }
  for (const section of document.querySelectorAll('main > section')) {
    section.hidden = section.id !== `${name}-view`;
  }
  statusBox.hidden = true;
}

/** switches section and loads its data */
function showView(name) {
  switchTo(name);
  if (name === 'create') showForm(document.getElementById('create-table').value);
  if (name === 'manage') loadRecords();
  if (name === 'transcript') loadTranscriptStudents();
}

for (const button of document.querySelectorAll('nav button')) {
  button.addEventListener('click', () => showView(button.dataset.view));
}

// ---------- create / edit ----------

function formFor(resource) {
  return document.querySelector(`form[data-resource="${resource}"]`);
}

/** shows the form of one table and loads its drop down lists */
async function showForm(resource, record = null) {
  for (const form of document.querySelectorAll('form')) {
    form.hidden = form.dataset.resource !== resource;
  }
  const form = formFor(resource);
  document.getElementById('create-table').value = resource;
  document.getElementById('create-table').disabled = record !== null;  
  document.getElementById('form-heading').textContent = record ? 'Edit record' : 'Create a record';
  form.querySelector('.cancel').hidden = record === null;
  form.reset();
  form.elements.id.value = '';

  try {
    for (const select of form.querySelectorAll('select[data-options]')) {
      await fillSelect(select, select.dataset.options);
    }
  } catch (error) {
    showStatus(error.message, 'error');
  }

  if (record) {
    for (const [name, value] of Object.entries(record)) {
      if (form.elements[name]) form.elements[name].value = value;
    }
  }
}

document.getElementById('create-table').addEventListener('change', e => showForm(e.target.value));

for (const form of document.querySelectorAll('form')) {
  form.addEventListener('submit', async event => {
    event.preventDefault();                  
    const resource = form.dataset.resource;
    const values = Object.fromEntries(new FormData(form));
    const id = values.id;
    delete values.id;

    showStatus('Saving…', 'loading');
    try {
      const result = id
        ? await api({ resource, id }, 'PUT', values)
        : await api({ resource }, 'POST', values);
      showStatus(result.message, 'success');
      if (id) {
        switchTo('manage');                     
        document.getElementById('manage-table').value = resource;
        await loadRecords();
        showStatus(result.message, 'success');
      } else {
        await showForm(resource);              
      }
    } catch (error) {
      showStatus(error.message, 'error');   
    }
  });

  form.querySelector('.cancel').addEventListener('click', () => showView('manage'));
}

// ---------- manage ----------

document.getElementById('manage-table').addEventListener('change', loadRecords);

async function loadRecords() {
  const resource = document.getElementById('manage-table').value;
  const table = document.getElementById('records');
  showStatus('Loading…', 'loading');
  try {
    const { data } = await api({ resource });

    const headerRow = document.createElement('tr');
    for (const [header] of COLUMNS[resource]) {
      const th = document.createElement('th');
      th.textContent = header;
      headerRow.append(th);
    }
    headerRow.append(document.createElement('th'));  
    table.tHead.replaceChildren(headerRow);

    table.tBodies[0].replaceChildren();
    for (const record of data) {
      const row = table.tBodies[0].insertRow();
      for (const [, cellText] of COLUMNS[resource]) {
        row.insertCell().textContent = cellText(record);
      }
      const actions = row.insertCell();
      actions.append(link('Edit', () => editRecord(resource, record.id)),
                     link('Delete', () => deleteRecord(resource, record.id)));
    }
    if (data.length === 0) {
      const cell = table.tBodies[0].insertRow().insertCell();
      cell.colSpan = COLUMNS[resource].length + 1;
      cell.textContent = 'No records yet.';
    }
    statusBox.hidden = true;
  } catch (error) {
    showStatus(error.message, 'error');
  }
}

/** an <a> that runs onClick instead of navigating */
function link(text, onClick) {
  const a = document.createElement('a');
  a.href = '#';
  a.textContent = text;
  a.addEventListener('click', event => { event.preventDefault(); onClick(); });
  return a;
}

async function editRecord(resource, id) {
  showStatus('Loading…', 'loading');
  try {
    const { data } = await api({ resource, id });
    switchTo('create');
    await showForm(resource, data);
    statusBox.hidden = true;
  } catch (error) {
    showStatus(error.message, 'error');
  }
}

async function deleteRecord(resource, id) {
  if (!confirm('Delete this record? This cannot be undone.')) return;
  showStatus('Deleting…', 'loading');
  try {
    const result = await api({ resource, id }, 'DELETE');
    await loadRecords();
    showStatus(result.message, 'success');
  } catch (error) {
    showStatus(error.message, 'error');      
  }
}

// ---------- transcript ----------

const transcriptSelect = document.getElementById('transcript-student');

async function loadTranscriptStudents() {
  try {
    await fillSelect(transcriptSelect, 'students');
    clearTranscript();
  } catch (error) {
    showStatus(error.message, 'error');
  }
}

function clearTranscript() {
  document.querySelector('#transcript tbody').replaceChildren();
  document.getElementById('total-credits').textContent = '0';
}

transcriptSelect.addEventListener('change', async () => {
  clearTranscript();
  if (!transcriptSelect.value) return;
  showStatus('Loading…', 'loading');
  try {
    const { data } = await api({ resource: 'transcript', id: transcriptSelect.value });
    const body = document.querySelector('#transcript tbody');
    for (const course of data.courses) {
      const row = body.insertRow();
      for (const value of [course.code, course.title, course.credits, course.grade]) {
        row.insertCell().textContent = value;
      }
    }
    if (data.courses.length === 0) {
      const cell = body.insertRow().insertCell();
      cell.colSpan = 4;
      cell.textContent = 'This student has not taken any courses yet.';
    }
    document.getElementById('total-credits').textContent = data.total_credits;
    statusBox.hidden = true;
  } catch (error) {
    showStatus(error.message, 'error');
  }
});

showView('create');
