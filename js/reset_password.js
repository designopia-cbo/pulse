document.addEventListener('DOMContentLoaded', function () {
  const modalContainerId = 'modal-reset-password-container';
  const triggerBtn = document.getElementById('admin-reset-password-link');

  if (!triggerBtn) return;

  triggerBtn.addEventListener('click', function (e) {
    e.preventDefault();

    // If modal already loaded, just open it
    if (document.getElementById('hs-reset-password-modal')) {
      if (typeof window.HSOverlay !== 'undefined') {
        window.HSOverlay.open(document.getElementById('hs-reset-password-modal'));
      }
      return;
    }

    // Build modalUrl with userid param if present
    let modalUrl = '/pulse/profile_options/reset_password';
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
          window.HSOverlay.open(document.getElementById('hs-reset-password-modal'));
        }

        // Password matching and enable/disable save button
        function checkPasswordFields() {
          const newPassword = document.getElementById('new-password').value;
          const confirmPassword = document.getElementById('confirm-password').value;
          const saveBtn = document.getElementById('submit-reset-password-btn');

          // Only enable if both fields are non-empty and match
          if (
            newPassword.length > 0 &&
            confirmPassword.length > 0 &&
            newPassword === confirmPassword
          ) {
            saveBtn.disabled = false;
          } else {
            saveBtn.disabled = true;
          }
        }

        // Password visibility toggle
        function setupPasswordToggles() {
          document.querySelectorAll('.password-toggle-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
              const input = document.getElementById(btn.getAttribute('data-target'));
              if (!input) return;
              if (input.type === "password") {
                input.type = "text";
                btn.querySelectorAll('.eye').forEach(e => e.style.display = 'none');
                btn.querySelectorAll('.eye-off').forEach(e => e.style.display = '');
              } else {
                input.type = "password";
                btn.querySelectorAll('.eye').forEach(e => e.style.display = '');
                btn.querySelectorAll('.eye-off').forEach(e => e.style.display = 'none');
              }
            });
            // Initial icon state
            btn.querySelectorAll('.eye-off').forEach(e => e.style.display = 'none');
          });
        }

        // Attach form submit handler (AJAX)
        const form = document.getElementById('reset-password-form');
        if (form) {
          form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            const formData = new FormData(form);

            fetch('/pulse/profile_options/reset_password', {
              method: 'POST',
              credentials: 'same-origin',
              body: formData
            })
              .then(r => r.json())
              .then(data => {
                if (data.success) {
                  if (typeof window.HSOverlay !== 'undefined') {
                    window.HSOverlay.close(document.getElementById('hs-reset-password-modal'));
                  }
                  // You may want to show a toast or reload the page
                } else {
                  document.getElementById('reset-password-error').textContent = data.error || "Save failed.";
                  document.getElementById('reset-password-error').classList.remove('hidden');
                }
              })
              .catch(err => {
                document.getElementById('reset-password-error').textContent = err.message;
                document.getElementById('reset-password-error').classList.remove('hidden');
              });
          });

          // Add input event listeners for validation
          document.getElementById('new-password').addEventListener('input', checkPasswordFields);
          document.getElementById('confirm-password').addEventListener('input', checkPasswordFields);

          // Initial state
          checkPasswordFields();

          // Setup password toggles
          setupPasswordToggles();
        }
      })
      .catch(err => {
        alert('Failed to load modal: ' + err.message);
      });
  });
});