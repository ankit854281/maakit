'use strict';
const dateInput = document.getElementById('date');
const today = new Intl.DateTimeFormat('en-CA', {timeZone:'Asia/Kolkata',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
dateInput.value = today;
dateInput.max = today;
let request = 0;
const escapeText = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function loadReport() {
  const current = ++request;
  const status = document.getElementById('status');
  document.getElementById('print').disabled = true;
  status.textContent = 'Updating attendance…';
  try {
    const response = await fetch('office_api.php?a=attendance&date=' + encodeURIComponent(dateInput.value), {cache:'no-store', headers:{'X-School-Token':localStorage.getItem('sch_token') || ''}});
    const data = await response.json();
    if (current !== request) return;
    if (!response.ok || !data.ok) throw Error(data.error || 'Unable to load attendance.');
    document.getElementById('school').textContent = data.school;
    document.getElementById('reportDate').textContent = 'Daily Student Attendance · ' + data.date;
    const columns = ['total','present','absent','leave','pending','holiday'];
    const cells = row => columns.map(key => '<td>' + escapeText(row[key]) + '</td>').join('');
    document.getElementById('report').innerHTML = '<div class="cards">' + columns.map(key => '<div class="card">' + key[0].toUpperCase() + key.slice(1) + '<b>' + escapeText(data.totals[key]) + '</b></div>').join('') + '</div><div class="table"><table><thead><tr><th>Class</th><th>Class teacher</th>' + columns.map(key => '<th>' + key[0].toUpperCase() + key.slice(1) + '</th>').join('') + '<th>Status</th></tr></thead><tbody>' + data.rows.map(row => '<tr><td>' + escapeText(row.class) + '</td><td>' + escapeText(row.teacher) + '</td>' + cells(row) + '<td>' + escapeText(row.status) + '</td></tr>').join('') + '</tbody><tfoot><tr><td colspan="2">School total</td>' + cells(data.totals) + '<td></td></tr></tfoot></table></div>';
    status.textContent = 'Updated ' + new Date().toLocaleTimeString('en-IN');
    document.getElementById('print').disabled = false;
  } catch (error) {
    if (current !== request) return;
    document.getElementById('report').innerHTML = '';
    status.textContent = error.message + ' Open the school app and sign in if needed, then refresh.';
  }
}
dateInput.addEventListener('change', loadReport);
document.getElementById('refresh').addEventListener('click', loadReport);
document.getElementById('print').addEventListener('click', () => window.print());
setInterval(() => { if (!document.hidden) loadReport(); }, 30000);
loadReport();
