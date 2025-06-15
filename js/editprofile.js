// editprofile.js

document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('#edit-profile-form'); // Use your form's selector
  const editBtn = document.getElementById('edit-button');
  const cancelBtn = document.getElementById('cancel-button');
  const saveBtn = document.getElementById('save-button');
  const inputs = form.querySelectorAll('input, select, textarea');
  const profileUserIdInput = document.getElementById('profile_userid');

  // Store original values for cancel
  let originalValues = {};

  // Helper: Disable or enable all fields
  function setFieldsDisabled(disabled = true) {
    inputs.forEach(input => {
      if (input.type !== "hidden") input.disabled = disabled;
    });
  }

  // Helper: Store current field values
  function cacheOriginalValues() {
    originalValues = {};
    inputs.forEach(input => {
      originalValues[input.name || input.id] = input.value;
    });
  }

  // Helper: Restore cached values
  function restoreOriginalValues() {
    inputs.forEach(input => {
      const key = input.name || input.id;
      if (originalValues.hasOwnProperty(key)) {
        input.value = originalValues[key];
        // For selects, update selected option
        if (input.tagName === 'SELECT') {
          for (let i = 0; i < input.options.length; i++) {
            if (input.options[i].value == originalValues[key]) {
              input.selectedIndex = i;
              break;
            }
          }
        }
      }
    });
  }

  // Initial state: fields disabled, buttons hidden
  setFieldsDisabled(true);
  cancelBtn.style.display = 'none';
  saveBtn.style.display = 'none';

  // Edit button: enable fields and show action buttons
  editBtn.addEventListener('click', function () {
    setFieldsDisabled(false);
    cacheOriginalValues();
    editBtn.style.display = 'none';
    cancelBtn.style.display = '';
    saveBtn.style.display = '';
  });

  // Cancel button: restore values, disable fields, hide action buttons
  cancelBtn.addEventListener('click', function () {
    restoreOriginalValues();
    setFieldsDisabled(true);
    cancelBtn.style.display = 'none';
    saveBtn.style.display = 'none';
    editBtn.style.display = '';
  });

  // Save changes: AJAX submit
  saveBtn.addEventListener('click', function () {
    saveBtn.disabled = true;
    cancelBtn.disabled = true;
    const formData = new FormData(form);

    // Ensure profile_userid is always sent
    if (profileUserIdInput) {
      formData.append('profile_userid', profileUserIdInput.value);
    }

    fetch('ajax/update_profile_tab1.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        setFieldsDisabled(true);
        cancelBtn.style.display = 'none';
        saveBtn.style.display = 'none';
        editBtn.style.display = '';
        alert('Profile updated successfully!');
        cacheOriginalValues(); // update originals
      } else {
        alert('Update failed: ' + (data.error || 'Unknown error.'));
      }
    })
    .catch(err => {
      alert('Update failed: ' + err);
    })
    .finally(() => {
      saveBtn.disabled = false;
      cancelBtn.disabled = false;
    });
  });
});