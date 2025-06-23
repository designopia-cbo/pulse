// Utility function to get URL parameter
function getQueryParam(param) {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get(param);
}

function setEffectivityFieldsReadonly(readonly) {
  document.querySelectorAll('#effectivity-dates-form input[type="date"]').forEach(function(input) {
    input.readOnly = readonly;
  });
}

function resetEffectivityFieldsToOriginal() {
  // Optionally, reset field values to their original values (as in HTML value attribute)
  var form = document.getElementById('effectivity-dates-form');
  if (!form) return;
  Array.from(form.elements).forEach(function(el) {
    if (el.type === "date" && el.defaultValue !== undefined) {
      el.value = el.defaultValue;
    }
  });
}

document.addEventListener('DOMContentLoaded', function () {
  const modalContainerId = 'modal-container';
  const triggerBtn = document.getElementById('admin-option-link');

  if (!triggerBtn) return;

  triggerBtn.addEventListener('click', function (e) {
    e.preventDefault();

    // Get userid from profile page URL, if present
    const userid = getQueryParam('userid');

    // If modal already loaded, just open it
    if (document.getElementById('hs-medium-modal')) {
      window.HSOverlay.open(document.getElementById('hs-medium-modal'));
      return;
    }

    // Build modalUrl with userid param if present
    let modalUrl = '/pulse/profile_options/effectivity_date';
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
          window.HSOverlay.open(document.getElementById('hs-medium-modal'));
        }

        // Attach form submit handler
        const form = document.getElementById('effectivity-dates-form');
        if (form) {
          form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            submitEffectivityDatesForm(form, userid);
          });
        }

        // Edit/Cancel/Update button logic
        setEffectivityFieldsReadonly(true);
        if (document.getElementById('edit-effectivity-btn')) document.getElementById('edit-effectivity-btn').style.display = "";
        if (document.getElementById('cancel-effectivity-btn')) document.getElementById('cancel-effectivity-btn').style.display = "none";
        if (document.getElementById('update-effectivity-btn')) document.getElementById('update-effectivity-btn').style.display = "none";

        // Delegated event listeners for the modal's buttons
        const modal = document.getElementById('hs-medium-modal');
        if (modal) {
          modal.addEventListener('click', function(e) {
            // Edit
            if (e.target && e.target.id === "edit-effectivity-btn") {
              setEffectivityFieldsReadonly(false);
              document.getElementById('edit-effectivity-btn').style.display = "none";
              document.getElementById('cancel-effectivity-btn').style.display = "";
              document.getElementById('update-effectivity-btn').style.display = "";
            }
            // Cancel (reset to readonly and hide cancel/update, show edit)
            if (e.target && e.target.id === "cancel-effectivity-btn") {
              setEffectivityFieldsReadonly(true);
              document.getElementById('edit-effectivity-btn').style.display = "";
              document.getElementById('cancel-effectivity-btn').style.display = "none";
              document.getElementById('update-effectivity-btn').style.display = "none";
              resetEffectivityFieldsToOriginal();
            }
          });
        }
      })
      .catch(err => {
        alert('Failed to load modal: ' + err.message);
      });
  });
});

/**
 * Handles the submission of the Effectivity Dates form via AJAX.
 * Shows errors or refreshes the page upon success.
 * Now includes userid in the fetch URL if present, to update the correct user.
 */
function submitEffectivityDatesForm(form, userid) {
  const errorDiv = document.getElementById('effectivity-dates-error');
  if (errorDiv) {
    errorDiv.classList.add('hidden');
    errorDiv.textContent = '';
  }
  const formData = new FormData(form);

  // Build fetch URL with userid as query param if present
  let fetchUrl = '/pulse/profile_options/effectivity_date';
  if (userid) {
    fetchUrl += '?userid=' + encodeURIComponent(userid);
  }

  fetch(fetchUrl, {
    method: 'POST',
    credentials: 'same-origin',
    body: formData
  })
    .then(response => {
      const contentType = response.headers.get("content-type");
      if (contentType && contentType.indexOf("application/json") !== -1) {
        return response.json();
      } else {
        return response.text().then(html => {
          throw new Error('Unexpected response: ' + html);
        });
      }
    })
    .then(data => {
      if (data.success) {
        window.HSOverlay.close(document.getElementById('hs-medium-modal'));
        location.reload();
      } else {
        if (errorDiv) {
          errorDiv.textContent = data.error || 'Failed to update.';
          errorDiv.classList.remove('hidden');
        }
      }
    })
    .catch((err) => {
      if (errorDiv) {
        errorDiv.textContent = 'An error occurred while submitting.';
        errorDiv.classList.remove('hidden');
      }
    });
}