// assets/js/app.js
document.addEventListener('DOMContentLoaded', function() {
  // Mobile sidebar toggle
  const sidebarToggle = document.getElementById('sidebarToggle');
  const appSidebar = document.getElementById('appSidebar');
  if (sidebarToggle && appSidebar) {
    sidebarToggle.addEventListener('click', function() {
      appSidebar.classList.toggle('show');
    });
  }

  // Smooth tooltip activation
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
