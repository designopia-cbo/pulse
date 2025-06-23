// Utility to get URL parameter
function getQueryParam(param) {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get(param);
}

// Reset the fields to their initial state (dropdown to default, others blank)
function resetPromoteFieldsToOriginal() {
  var plantillaDropdown = document.getElementById('plantilla-item-number');
  var positionTitleField = document.getElementById('position-title');
  var dateOfAssumption = document.getElementById('dateofassumption');
  var dateOfAppointment = document.getElementById('dateofappointment');

  if (plantillaDropdown) {
    plantillaDropdown.selectedIndex = 0;
    plantillaDropdown.dispatchEvent(new Event('change', {bubbles:true}));
  }
  if (positionTitleField) positionTitleField.value = "";
  if (dateOfAssumption) dateOfAssumption.value = "";
  if (dateOfAppointment) dateOfAppointment.value = "";
}

// Validation: enable Promote button only if all fields are filled
function checkPromoteFormComplete() {
  const plantillaDropdown = document.getElementById('plantilla-item-number');
  const positionTitleField = document.getElementById('position-title');
  const dateOfAssumption = document.getElementById('dateofassumption');
  const dateOfAppointment = document.getElementById('dateofappointment');
  const promoteBtn = document.getElementById('update-promote-btn');

  const isComplete =
    plantillaDropdown &&
    plantillaDropdown.value &&
    positionTitleField &&
    positionTitleField.value.trim() !== "" &&
    dateOfAssumption &&
    dateOfAssumption.value &&
    dateOfAppointment &&
    dateOfAppointment.value;

  if (promoteBtn) {
    promoteBtn.disabled = !isComplete;
  }
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

        // Attach form submit handler (no actual backend yet, just prevent default for now)
        const form = document.getElementById('promote-employee-form');
        if (form) {
          form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            // You can add AJAX submit logic here later
            window.HSOverlay.close(document.getElementById('hs-promote-modal'));
            // location.reload();
          });
        }

        // Cancel button logic
        const cancelBtn = document.getElementById('cancel-promote-btn');
        if (cancelBtn) {
          cancelBtn.addEventListener('click', function () {
            resetPromoteFieldsToOriginal();
            checkPromoteFormComplete();
          });
        }

        // Populate position title and validate form when plantilla changes
        const plantillaDropdown = document.getElementById('plantilla-item-number');
        const positionTitleField = document.getElementById('position-title');
        if (plantillaDropdown && positionTitleField) {
          plantillaDropdown.addEventListener('change', function() {
            var selected = plantillaDropdown.options[plantillaDropdown.selectedIndex];
            var title = selected.getAttribute('data-title') || "";
            positionTitleField.value = title;
            checkPromoteFormComplete();
          });
        }

        // Validate form on input changes
        const dateOfAssumption = document.getElementById('dateofassumption');
        const dateOfAppointment = document.getElementById('dateofappointment');
        if (dateOfAssumption) dateOfAssumption.addEventListener('input', checkPromoteFormComplete);
        if (dateOfAppointment) dateOfAppointment.addEventListener('input', checkPromoteFormComplete);

        // Also validate on position title change (in case it's manually changed elsewhere)
        if (positionTitleField) positionTitleField.addEventListener('input', checkPromoteFormComplete);

        // Set initial state
        resetPromoteFieldsToOriginal();
        checkPromoteFormComplete();
      })
      .catch(err => {
        alert('Failed to load modal: ' + err.message);
      });
  });
});