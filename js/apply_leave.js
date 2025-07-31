document.addEventListener('DOMContentLoaded', function () {
  const modalContainerId = 'modal-apply-leave-container';
  const triggerBtn = document.getElementById('apply-leave-link');

  if (!triggerBtn) return;

  triggerBtn.addEventListener('click', function (e) {
    e.preventDefault();

    // Build modalUrl with userid param if present
    let modalUrl = '/pulse/profile_options/apply_leave_modal.php';
    const urlParams = new URLSearchParams(window.location.search);
    const userid = urlParams.get('userid');
    if (userid) {
      modalUrl += '?userid=' + encodeURIComponent(userid);
    }

    if (document.getElementById('hs-apply-leave-modal')) {
      if (typeof window.HSOverlay !== 'undefined') {
        window.HSOverlay.open(document.getElementById('hs-apply-leave-modal'));
      }
      return;
    }

    fetch(modalUrl, { credentials: 'same-origin' })
      .then(response => response.text())
      .then(html => {
        let container = document.getElementById(modalContainerId);
        if (!container) {
          container = document.createElement('div');
          container.id = modalContainerId;
          document.body.appendChild(container);
        }
        container.innerHTML = html;

        if (typeof window.HSOverlay !== 'undefined') {
          window.HSOverlay.autoInit();
          window.HSOverlay.open(document.getElementById('hs-apply-leave-modal'));
        }

        // Handle form submission: just show alert with profile_userid
        const form = document.getElementById('apply-leave-form');
        if (form) {
          form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            // Select the value from the form
            const profileUserIdInput = form.querySelector('input[name="profile_userid"]');
            const profileUserId = profileUserIdInput ? profileUserIdInput.value : '(not found)';
            alert('profile_userid: ' + profileUserId);
          });
        }
      })
      .catch(err => {
        alert('Failed to load modal: ' + err.message);
      });
  });
});