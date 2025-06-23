// Utility to get URL parameter
function getQueryParam(param) {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get(param);
}

document.addEventListener('DOMContentLoaded', function () {
  const modalContainerId = 'modal-promote-container';
  const triggerBtn = document.getElementById('admin-promote-link');

  if (!triggerBtn) return;

  triggerBtn.addEventListener('click', function (e) {
    e.preventDefault();

    // Get userid from profile page URL if present
    const userid = getQueryParam('userid');

    // If modal already loaded, just open it
    if (document.getElementById('hs-promote-modal')) {
      window.HSOverlay.open(document.getElementById('hs-promote-modal'));
      return;
    }

    // Build modalUrl with userid param if present
    let modalUrl = '/pulse/profile_options/promote_employee';
    if (userid) {
      modalUrl += '?userid=' + encodeURIComponent(userid);
    }

    // Otherwise, load modal via AJAX
    fetch(modalUrl, { credentials: 'same-origin' })
      .then(response => {
        if (!response.ok) throw new Error('Unable to load modal');
        return response.text();
      })
      .then(html => {
        let container = document.getElementById(modalContainerId);
        if (!container) {
          container = document.createElement('div');
          container.id = modalContainerId;
          document.body.appendChild(container);
        }
        container.innerHTML = html;

        // Preline: re-run overlay init if needed (usually auto, but safety)
        if (typeof window.HSOverlay !== 'undefined') {
          window.HSOverlay.autoInit();
          window.HSOverlay.open(document.getElementById('hs-promote-modal'));
        }
      })
      .catch(err => {
        alert('Failed to load modal: ' + err.message);
      });
  });
});