/* Shared navigation, responsive table labels, and attendance controls. */
function toggleAttendance(toggle) {
  const input = toggle.previousElementSibling;
  if (!input) return;
  const absent = input.value !== 'absent';
  input.value = absent ? 'absent' : 'present';
  toggle.textContent = absent ? 'Absent' : 'Present';
  toggle.classList.toggle('is-absent', absent);
  toggle.classList.toggle('is-present', !absent);
  toggle.setAttribute('aria-pressed', String(absent));
}

function attendanceToggles() {
  return Array.from(document.querySelectorAll('[data-attendance-toggle]'));
}

function updateMobileTableCards() {
  const compact = window.matchMedia('(max-width: 760px)').matches;
  document.querySelectorAll('.table-wrap table').forEach(table => {
    const rows = Array.from(table.querySelectorAll('tr'));
    const heading = rows.find(row => row.querySelector(':scope > th'));
    const headers = heading ? Array.from(heading.querySelectorAll(':scope > th')).map(cell => cell.textContent.trim()) : [];
    const statusMatrix = headers.some(text => /^present$/i.test(text))
      && headers.some(text => /^medical$/i.test(text))
      && headers.some(text => /^absent$/i.test(text));
    const useCards = compact && headers.length > 0 && headers.length <= 7
      && !statusMatrix && !table.classList.contains('eligibility-table')
      && !table.closest('.confirm-save,.eligibility-table-wrap');

    table.classList.toggle('mobile-card-table', useCards);
    table.closest('.table-wrap')?.classList.toggle('card-list-wrap', useCards);
    if (heading) heading.classList.toggle('mobile-table-heading', useCards);
    rows.forEach(row => {
      if (row === heading) return;
      Array.from(row.querySelectorAll(':scope > td')).forEach((cell, index) => {
        if (useCards && headers[index]) cell.dataset.label = headers[index];
        else delete cell.dataset.label;
      });
    });
  });
}

document.addEventListener('click', event => {
  const toggle = event.target.closest('[data-attendance-toggle]');
  if (toggle) toggleAttendance(toggle);

  if (event.target.closest('[data-mark-all]')) {
    document.querySelectorAll('[data-attendance-toggle]').forEach(button => {
      const input = button.previousElementSibling;
      if (input) input.value = 'present';
      button.textContent = 'Present';
      button.classList.add('is-present');
      button.classList.remove('is-absent');
      button.setAttribute('aria-pressed', 'false');
    });
  }
});

document.addEventListener('keydown', event => {
  const toggle = event.target.closest('[data-attendance-toggle]');
  if (!toggle) return;
  const toggles = attendanceToggles();
  const index = toggles.indexOf(toggle);
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault();
    const next = toggles[index + (event.key === 'ArrowDown' ? 1 : -1)];
    if (next) next.focus();
  } else if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    toggleAttendance(toggle);
    if (event.key === 'Enter' && toggles[index + 1]) toggles[index + 1].focus();
  }
}, true);

document.addEventListener('submit', event => {
  if (event.target.matches('.confirm-save') && !confirm('Save this attendance session?')) event.preventDefault();
});

document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('[data-sidebar]');
  const openButton = document.querySelector('[data-nav-toggle]');
  const closeButtons = document.querySelectorAll('[data-nav-close]');
  const backdrop = document.querySelector('.nav-backdrop');
  const drawerQuery = window.matchMedia('(max-width: 900px)');
  let returnFocus = null;

  document.querySelectorAll('.attendance-matrix-wrap,.eligibility-table-wrap').forEach(scroller => {
    const updateIdentity = () => scroller.classList.toggle('is-scrolled', scroller.scrollLeft > 8);
    scroller.addEventListener('scroll', updateIdentity, { passive: true });
    updateIdentity();
  });

  function setDrawerOpen(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('open', open);
    backdrop?.classList.toggle('is-visible', open);
    document.body.classList.toggle('nav-open', open);
    openButton?.setAttribute('aria-expanded', String(open));
    openButton?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    if (open && drawerQuery.matches) {
      returnFocus = document.activeElement;
      sidebar.querySelector('.sidebar-close')?.focus();
    } else if (returnFocus instanceof HTMLElement) {
      returnFocus.focus();
    }
  }

  openButton?.addEventListener('click', () => setDrawerOpen(!sidebar?.classList.contains('open')));
  closeButtons.forEach(button => button.addEventListener('click', () => setDrawerOpen(false)));
  sidebar?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setDrawerOpen(false)));
  drawerQuery.addEventListener('change', event => { if (!event.matches) setDrawerOpen(false); });
  document.addEventListener('keydown', event => {
    if (!sidebar?.classList.contains('open')) return;
    if (event.key === 'Escape') {
      setDrawerOpen(false);
      return;
    }
    if (event.key === 'Tab') {
      const items = Array.from(sidebar.querySelectorAll('a[href],button:not([disabled])'));
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
  });

  updateMobileTableCards();
});

window.addEventListener('resize', updateMobileTableCards, { passive: true });
