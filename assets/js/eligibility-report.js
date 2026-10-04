(function () {
  const form = document.querySelector('#eligibility-form');
  const message = document.querySelector('#eligibility-message');
  const loading = document.querySelector('#eligibility-loading');
  const empty = document.querySelector('#eligibility-empty');
  const grid = document.querySelector('#eligibility-grid');
  const exportButton = document.querySelector('#export-google-sheet');
  let report = null;

  function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
  }

  function showMessage(text, isError) {
    message.textContent = text;
    message.className = text ? `report-message ${isError ? 'error' : 'success'}` : 'report-message';
  }

  function render() {
    if (!report) return;
    const limit = Number(form.elements.eligibility_limit.value);
    let html = '<table class="eligibility-table"><thead><tr><th>Registration Number</th><th>Student Name</th>';
    report.subjects.forEach(subject => { html += `<th title="${escapeHtml(subject.subject_name)}">${escapeHtml(subject.subject_code)}</th>`; });
    html += '</tr></thead><tbody>';
    report.students.forEach(student => {
      const registration = String(student.registration_number ?? '');
      html += `<tr><td class="report-registration" aria-label="Registration number ${escapeHtml(registration)}"><span class="registration-full">${escapeHtml(registration)}</span><span class="registration-short">${escapeHtml(registration.slice(-4))}</span></td><td>${escapeHtml(student.name)}</td>`;
      report.subjects.forEach(subject => {
        const value = student.attendance[String(subject.id)];
        const status = value === null ? 'neutral' : (Number(value) >= limit ? 'eligible' : 'not-eligible');
        html += `<td class="attendance-${status}">${value === null ? '&mdash;' : `${Number(value).toFixed(1)}%`}</td>`;
      });
      html += '</tr>';
    });
    grid.innerHTML = html + '</tbody></table>';
  }

  function exportGoogleSheet() {
    if (!report) return;
    const limit = Number(form.elements.eligibility_limit.value);
    const batch = form.elements.batch_id.selectedOptions[0].textContent;
    const semester = form.elements.semester_id.selectedOptions[0].textContent;
    const csvCell = value => `"${String(value).replace(/"/g, '""')}"`;
    let csv = `${csvCell('Batch')},${csvCell(batch)}\r\n${csvCell('Semester')},${csvCell(semester)}\r\n${csvCell('Eligibility Limit (%)')},${csvCell(limit.toFixed(1))}\r\n\r\n`;
    csv += [csvCell('Registration Number'), csvCell('Student Name'), ...report.subjects.map(subject => csvCell(`${subject.subject_code} - ${subject.subject_name}`))].join(',') + '\r\n';
    report.students.forEach(student => {
      const row = [csvCell(student.registration_number), csvCell(student.name)];
      report.subjects.forEach(subject => {
        const value = student.attendance[String(subject.id)];
        row.push(csvCell(value === null ? '—' : `${Number(value).toFixed(1)}%`));
      });
      csv += row.join(',') + '\r\n';
    });
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `eligibility-${batch.replace(/[^a-z0-9]+/gi, '-')}-${semester.replace(/[^a-z0-9]+/gi, '-')}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
  }

  form.addEventListener('input', event => {
    if (report && event.target.name === 'eligibility_limit' && event.target.validity.valid) render();
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const data = new FormData(form);
    const limit = Number(data.get('eligibility_limit'));
    if (!form.reportValidity() || !Number.isFinite(limit) || limit < 0 || limit > 100) {
      showMessage('Enter an eligibility limit between 0 and 100.', true);
      return;
    }
    report = null; exportButton.hidden = true;
    loading.hidden = false; empty.hidden = true; grid.hidden = true; showMessage('', false);
    try {
      const params = new URLSearchParams({ batch_id: data.get('batch_id'), semester_id: data.get('semester_id'), eligibility_limit: String(limit) });
      const response = await fetch(form.dataset.apiUrl + '?' + params);
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || 'The report could not be generated.');
      report = payload; loading.hidden = true;
      if (!payload.students.length) { empty.textContent = 'No active students found in this batch.'; empty.hidden = false; return; }
      if (!payload.subjects.length) { empty.textContent = 'No subjects are assigned to this batch and semester.'; empty.hidden = false; return; }
      render(); grid.hidden = false;
      exportButton.hidden = false;
      if (!payload.has_attendance_data) showMessage('No attendance data is available for these subjects yet.', false);
    } catch (error) {
      loading.hidden = true; empty.textContent = 'The report could not be loaded.'; empty.hidden = false; showMessage(error.message, true);
    }
  });

  exportButton.addEventListener('click', exportGoogleSheet);
}());
