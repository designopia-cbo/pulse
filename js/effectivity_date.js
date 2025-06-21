document.addEventListener('DOMContentLoaded', function () {
  const modalContainerId = 'modal-container'; // The container where the modal HTML will be inserted
  const modalUrl = '/pulse/profile_options/effectivity_date.php';
  const triggerBtn = document.getElementById('admin-option-link');

  if (!triggerBtn) return;

  triggerBtn.addEventListener('click', function (e) {
    e.preventDefault();

    // If modal already loaded, just open it
    if (document.getElementById('hs-medium-modal')) {
      window.HSOverlay.open(document.getElementById('hs-medium-modal'));
      return;
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
          window.HSOverlay.open(document.getElementById('hs-medium-modal'));
        }

        // Attach form submit handler
        const form = document.getElementById('effectivity-dates-form');
        if (form) {
          form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            submitEffectivityDatesForm(form);
          });
        }
      })
      .catch(err => {
        alert('Failed to load modal: ' + err.message);
      });
  });
});

// Handles the AJAX form submission
function submitEffectivityDatesForm(form) {
  const errorDiv = document.getElementById('effectivity-dates-error');
  if (errorDiv) {
    errorDiv.classList.add('hidden');
    errorDiv.textContent = '';
  }
  const formData = new FormData(form);

  fetch('/pulse/profile_options/effectivity_date_submit.php', {
    method: 'POST',
    credentials: 'same-origin',
    body: formData
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Optionally: update the profile page with new info
        window.HSOverlay.close(document.getElementById('hs-medium-modal'));
        location.reload(); // or update fields via JS
      } else {
        if (errorDiv) {
          errorDiv.textContent = data.error || 'Failed to update.';
          errorDiv.classList.remove('hidden');
        }
      }
    })
    .catch(() => {
      if (errorDiv) {
        errorDiv.textContent = 'An error occurred while submitting.';
        errorDiv.classList.remove('hidden');
      }
    });
}