document.addEventListener('DOMContentLoaded', function () {
  const modalContainerId = 'modal-edit-credit-container';
  const triggerBtn = document.getElementById('admin-edit-credit-link');

  if (!triggerBtn) return;

  triggerBtn.addEventListener('click', function (e) {
    e.preventDefault();

    // If modal already loaded, just open it
    if (document.getElementById('hs-edit-credit-modal')) {
      if (typeof window.HSOverlay !== 'undefined') {
        window.HSOverlay.open(document.getElementById('hs-edit-credit-modal'));
      }
      return;
    }

    // Build modalUrl with userid param if present
    let modalUrl = '/pulse/profile_options/edit_credit';
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
          window.HSOverlay.open(document.getElementById('hs-edit-credit-modal'));
        }

        // Enable edit mode
        function enableEditMode() {
          document.getElementById('vacation-leave').disabled = false;
          document.getElementById('sick-leave').disabled = false;
          document.getElementById('special-privilege-leave').disabled = false;
          document.getElementById('submit-edit-credit-btn').style.display = '';
          document.getElementById('cancel-edit-credit-btn').style.display = '';
          document.getElementById('edit-credit-btn').style.display = 'none';
        }

        // Restore view mode (optional, reload for now)
        function resetEditMode() {
          location.reload();
        }

        // Attach form submit handler (AJAX)
        const form = document.getElementById('edit-credit-form');
        if (form) {
          form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            const formData = new FormData(form);

            fetch('/pulse/profile_options/edit_credit', {
              method: 'POST',
              credentials: 'same-origin',
              body: formData
            })
              .then(r => r.json())
              .then(data => {
                if (data.success) {
                  if (typeof window.HSOverlay !== 'undefined') {
                    window.HSOverlay.close(document.getElementById('hs-edit-credit-modal'));
                  }
                  location.reload();
                } else {
                  document.getElementById('edit-credit-error').textContent = data.error || "Save failed.";
                  document.getElementById('edit-credit-error').classList.remove('hidden');
                }
              })
              .catch(err => {
                document.getElementById('edit-credit-error').textContent = err.message;
                document.getElementById('edit-credit-error').classList.remove('hidden');
              });
          });

          // Edit button
          const editBtn = document.getElementById('edit-credit-btn');
          if (editBtn) {
            editBtn.addEventListener('click', function () {
              enableEditMode();
            });
          }

          // Cancel button
          const cancelBtn = document.getElementById('cancel-edit-credit-btn');
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