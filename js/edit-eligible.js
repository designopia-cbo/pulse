// --- Eligibility Tab Edit Mode Management ---

document.addEventListener('DOMContentLoaded', function() {
  // Utility: Enable/disable all fields in the eligibility tab
  function setEligibilityFieldsEnabled(enabled) {
    const container = document.getElementById('eligibility-container');
    if (!container) return;
    const inputs = container.querySelectorAll('input');
    inputs.forEach(input => {
      input.disabled = !enabled;
    });

    // Also: enable/disable remove buttons
    const removeBtns = container.querySelectorAll('.remove-eligibility-row');
    removeBtns.forEach(btn => {
      btn.style.display = enabled ? '' : 'none';
      btn.disabled = !enabled;
    });

    // Enable/disable add button
    const addBtn = document.getElementById('add-eligibility-btn');
    if (addBtn) {
      addBtn.style.display = enabled ? '' : 'none';
      addBtn.disabled = !enabled;
    }
  }

  // Utility: Show/hide action buttons
  function setEligibilityActionButtons(editMode) {
    // Find the action buttons in the eligibility tab
    const form = document.querySelector('#eligibility-container')?.closest('form');
    if (!form) return;
    const buttons = form.querySelectorAll('button');
    buttons.forEach(btn => {
      if (btn.id === 'edit-button') {
        btn.style.display = editMode ? 'none' : '';
      } else if (btn.textContent.trim() === 'Cancel' || btn.textContent.trim() === 'Save changes') {
        btn.style.display = editMode ? '' : 'none';
      }
    });
  }

  // Utility: Reset fields to view mode (disable all, hide action buttons, show edit)
  function switchEligibilityViewMode() {
    setEligibilityFieldsEnabled(false);
    setEligibilityActionButtons(false);
  }

  // Utility: Switch to edit mode (enable all, show action buttons, hide edit)
  function switchEligibilityEditMode() {
    setEligibilityFieldsEnabled(true);
    setEligibilityActionButtons(true);
  }

  // On page load, fields start in view mode
  switchEligibilityViewMode();

  // Event: Click edit button -> switch to edit mode
  document.body.addEventListener('click', function(e) {
    const editBtn = e.target.closest('#edit-button');
    if (editBtn) {
      switchEligibilityEditMode();
      e.preventDefault();
    }
    // Cancel button
    if (e.target.textContent.trim() === 'Cancel') {
      switchEligibilityViewMode();
      e.preventDefault();
    }
    // Optionally: Save button returns to view mode (after AJAX in later steps)
    // if (e.target.textContent.trim() === 'Save changes') {
    //   switchEligibilityViewMode();
    //   e.preventDefault();
    // }
  });
});