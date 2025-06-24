// Utility to get URL parameter
function getQueryParam(param) {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get(param);
}

function resetSeparateFieldsToOriginal() {
  var reasonSelect = document.getElementById('separation-reason');
  var dateInput = document.getElementById('date-of-separation');
  if (reasonSelect) reasonSelect.selectedIndex = 0;
  if (dateInput) dateInput.value = "";
}

// Validation: enable button only if all required fields are filled
function checkSeparateFormComplete() {
  const reasonSelect = document.getElementById('separation-reason');
  const dateInput = document.getElementById('date-of-separation');
  const separateBtn = document.getElementById('submit-separate-btn');

  const isComplete =
    reasonSelect &&
    reasonSelect.value &&
    dateInput &&
    dateInput.value;

  if (separateBtn) {
    separateBtn.disabled = !isComplete;
  }
}

document.addEventListener('DOMContentLoaded', function () {
  const modalContainerId = 'modal-separate-container';
  const triggerBtn = document.getElementById('admin-separate-link');

  if (!triggerBtn) return;

  triggerBtn.addEventListener('click', function (e) {
    e.preventDefault();

    // Get userid from URL param if present
    const userid = getQueryParam('userid');

    // If modal already loaded, just open it
    if (document.getElementById('hs-separate-modal')) {
      if (typeof window.HSOverlay !== 'undefined') {
        window.HSOverlay.open(document.getElementById('hs-separate-modal'));
      }
      return;
    }

    // Build modalUrl with userid param if present
    let modalUrl = '/pulse/profile_options/separate_employee';
    if (userid) {
      modalUrl += '?userid=' + encodeURIComponent(userid);
    }

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

        if (typeof window.HSOverlay !== 'undefined') {
          window.HSOverlay.autoInit();
          window.HSOverlay.open(document.getElementById('hs-separate-modal'));
        }

        // Attach form submit handler (AJAX)
        const form = document.getElementById('separate-employee-form');
        if (form) {
          form.addEventListener('submit', function (ev) {
            ev.preventDefault();

            const formData = new FormData(form);
            const profileUserIdInput = form.querySelector('input[name="profile_userid"]');
            if (profileUserIdInput && !formData.has('profile_userid')) {
              formData.append('profile_userid', profileUserIdInput.value);
            }

            fetch('/pulse/profile_options/separate_employee', {
              method: 'POST',
              credentials: 'same-origin',
              body: formData
            })
              .then(r => r.json())
              .then(data => {
                if (data.success) {
                  if (typeof window.HSOverlay !== 'undefined') {
                    window.HSOverlay.close(document.getElementById('hs-separate-modal'));
                  }
                  location.reload();
                } else {
                  document.getElementById('separate-employee-error').textContent = data.error || "Separation failed.";
                  document.getElementById('separate-employee-error').classList.remove('hidden');
                }
              })
              .catch(err => {
                document.getElementById('separate-employee-error').textContent = err.message;
                document.getElementById('separate-employee-error').classList.remove('hidden');
              });
          });
        }

        // Cancel button logic
        const cancelBtn = document.getElementById('cancel-separate-btn');
        if (cancelBtn) {
          cancelBtn.addEventListener('click', function () {
            resetSeparateFieldsToOriginal();
            checkSeparateFormComplete();
          });
        }

        // Validate form on input changes
        const reasonSelect = document.getElementById('separation-reason');
        const dateInput = document.getElementById('date-of-separation');
        if (reasonSelect) reasonSelect.addEventListener('change', checkSeparateFormComplete);
        if (dateInput) dateInput.addEventListener('input', checkSeparateFormComplete);

        // Set initial state
        resetSeparateFieldsToOriginal();
        checkSeparateFormComplete();
      })
      .catch(err => {
        alert('Failed to load modal: ' + err.message);
      });
  });
});