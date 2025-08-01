document.addEventListener('DOMContentLoaded', function () {
  const modalContainerId = 'modal-set-approvers-container';
  const triggerBtn = document.getElementById('admin-set-approvers-link');

  if (!triggerBtn) return;

  triggerBtn.addEventListener('click', function (e) {
    e.preventDefault();

    // If modal already loaded, just open it
    if (document.getElementById('hs-set-approvers-modal')) {
      if (typeof window.HSOverlay !== 'undefined') {
        window.HSOverlay.open(document.getElementById('hs-set-approvers-modal'));
      }
      return;
    }

    // Build modalUrl with userid param if present
    let modalUrl = '/pulse/profile_options/set_approvers';
    const urlParams = new URLSearchParams(window.location.search);
    const userid = urlParams.get('userid');
    if (userid) {
      modalUrl += '?userid=' + encodeURIComponent(userid);
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
          window.HSOverlay.open(document.getElementById('hs-set-approvers-modal'));
        }

        // Enable edit mode
        function enableEditMode() {
          document.getElementById('approver-1').disabled = false;
          document.getElementById('approver-2').disabled = false;
          document.getElementById('approver-3').disabled = false;
          document.getElementById('submit-set-approvers-btn').style.display = '';
          document.getElementById('cancel-set-approvers-btn').style.display = '';
          document.getElementById('edit-approvers-btn').style.display = 'none';
        }

        // Restore view mode (optional, reload for now)
        function resetEditMode() {
          location.reload();
        }

        // Attach form submit handler (AJAX)
        const form = document.getElementById('set-approvers-form');
        if (form) {
          form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            const formData = new FormData(form);

            fetch('/pulse/profile_options/set_approvers', {
              method: 'POST',
              credentials: 'same-origin',
              body: formData
            })
              .then(r => r.json())
              .then(data => {
                if (data.success) {
                  if (typeof window.HSOverlay !== 'undefined') {
                    window.HSOverlay.close(document.getElementById('hs-set-approvers-modal'));
                  }
                  location.reload();
                } else {
                  document.getElementById('set-approvers-error').textContent = data.error || "Save failed.";
                  document.getElementById('set-approvers-error').classList.remove('hidden');
                }
              })
              .catch(err => {
                document.getElementById('set-approvers-error').textContent = err.message;
                document.getElementById('set-approvers-error').classList.remove('hidden');
              });
          });

          // Edit button
          const editBtn = document.getElementById('edit-approvers-btn');
          if (editBtn) {
            editBtn.addEventListener('click', function () {
              enableEditMode();
            });
          }

          // Cancel button
          const cancelBtn = document.getElementById('cancel-set-approvers-btn');
          if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
              resetEditMode();
            });
          }
        }
      })
      .catch(err => {
        alert('Failed to load modal: ' + err.message);
      });
  });
});