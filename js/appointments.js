document.addEventListener('DOMContentLoaded', function () {
  const link = document.getElementById('admin-appointments-link');
  const container = document.getElementById('modal-appointments-container');
  const urlParams = new URLSearchParams(window.location.search);
  const userid = urlParams.get('userid');

  if (!link || !container) return;

  link.addEventListener('click', function (e) {
    e.preventDefault();

    // Don't reopen if we just saved changes
    if (window.justSavedChanges) {
      window.justSavedChanges = false;
      return;
    }

    let modal = document.getElementById('hs-appointments-modal');
    if (modal) {
      if (typeof window.HSOverlay !== 'undefined') {
        window.HSOverlay.open(modal);
      }
      setupEditButtons(modal);
      setupModalCloseHandlers(modal);
      applyInitialHideFields(modal);
      return;
    }

    let modalUrl = '/pulse/profile_options/appointments';
    if (userid) {
      modalUrl += '?userid=' + encodeURIComponent(userid);
    }

    fetch(modalUrl, { credentials: 'same-origin' })
      .then(response => response.text())
      .then(html => {
        container.innerHTML = html;

        modal = document.getElementById('hs-appointments-modal');
        if (typeof window.HSOverlay !== 'undefined' && modal) {
          window.HSOverlay.autoInit();
          window.HSOverlay.open(modal);
        }
        setupEditButtons(modal);
        setupModalCloseHandlers(modal);
        applyInitialHideFields(modal);
      })
      .catch(err => {
        container.innerHTML = '<div class="p-4 text-red-600">Failed to load appointments modal.</div>';
        console.error('Modal fetch error:', err);
      });
  });

  function setupEditButtons(modal) {
    if (!modal) return;

    const footer = modal.querySelector('.flex.justify-end.items-center.py-3.px-4');
    if (!footer) return;

    const editBtn = footer.querySelector('.modal-edit-btn');
    const closeBtn = footer.querySelector('.modal-close-btn');
    const saveBtn = footer.querySelector('.modal-save-btn');

    if (editBtn) editBtn.style.display = '';
    if (closeBtn) closeBtn.style.display = 'none';
    if (saveBtn) saveBtn.style.display = 'none';

    applyInitialHideFields(modal);

    if (editBtn && !editBtn.dataset.bound) {
      editBtn.dataset.bound = 'true';
      editBtn.addEventListener('click', function () {
        editBtn.style.display = 'none';
        if (closeBtn) closeBtn.style.display = '';
        if (saveBtn) saveBtn.style.display = '';
        showEditableFields(modal);
      });
    }

    if (closeBtn && !closeBtn.dataset.bound) {
      closeBtn.dataset.bound = 'true';
      closeBtn.addEventListener('click', function (evt) {
        evt.preventDefault();
        if (typeof window.HSOverlay !== 'undefined') {
          window.HSOverlay.close(modal);
          const backdrop = document.querySelector('.hs-overlay-backdrop');
          if (backdrop) {
            backdrop.remove();
          }
        }
      });
    }

    // Save button handles file upload and deletions
    if (saveBtn && !saveBtn.dataset.bound) {
      saveBtn.dataset.bound = 'true';
      saveBtn.addEventListener('click', async function () {
        const form = modal.querySelector('#appointments-upload-form');
        if (!form) return;

        const formData = new FormData(form);

        try {
          const response = await fetch('/pulse/profile_options/appointments', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
          });

          const result = await response.json();
          if (result.success) {
            alert(result.message || 'Changes saved successfully!');
            window.justSavedChanges = true;
            if (typeof window.HSOverlay !== 'undefined') {
              window.HSOverlay.close(modal);
              const backdrop = document.querySelector('.hs-overlay-backdrop');
              if (backdrop) {
                backdrop.remove();
              }
              // Reload the page after closing the modal
              window.location.reload();
            }
          } else {
            alert(result.message || 'Failed to save changes');
          }
        } catch (error) {
          console.error('Save error:', error);
          alert('Failed to save changes. Please try again.');
        }
      });
    }
  }

  function setupModalCloseHandlers(modal) {
    if (!modal) return;

    const closeIcons = modal.querySelectorAll('.modal-close-icon, [data-hs-overlay-close]');
    closeIcons.forEach(function (icon) {
      if (!icon.dataset.bound) {
        icon.dataset.bound = 'true';
        icon.addEventListener('click', function (evt) {
          evt.preventDefault();
          if (typeof window.HSOverlay !== 'undefined') {
            window.HSOverlay.close(modal);
            const backdrop = document.querySelector('.hs-overlay-backdrop');
            if (backdrop) {
              backdrop.remove();
            }
          }
        });
      }
    });

    function escKeyHandler(e) {
      if (e.key === "Escape" && modal.classList.contains('hs-overlay-open')) {
        if (typeof window.HSOverlay !== 'undefined') {
          window.HSOverlay.close(modal);
          const backdrop = document.querySelector('.hs-overlay-backdrop');
          if (backdrop) {
            backdrop.remove();
          }
        }
      }
    }
    document.addEventListener('keydown', escKeyHandler);

    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        if (typeof window.HSOverlay !== 'undefined') {
          window.HSOverlay.close(modal);
        }
      }
    });

    modal.addEventListener('hsOverlay.afterClose', function () {
      document.removeEventListener('keydown', escKeyHandler);
    });
  }

  function applyInitialHideFields(modal) {
    if (!modal) return;

    // Initialize dropdowns if HSDropdown is available
    if (typeof window.HSDropdown !== 'undefined') {
      window.HSDropdown.autoInit();
    }

    const fileInputs = modal.querySelectorAll('input[type="file"][id^="appointment-attachments"]');
    fileInputs.forEach(input => {
      input.style.display = 'none';
    });

    // Hide badges and show file counts in view mode
    const badges = modal.querySelectorAll('.appointment-badges');
    badges.forEach(badge => {
      badge.style.display = 'none';
    });
    
    const fileCounts = modal.querySelectorAll('.file-count-text');
    fileCounts.forEach(count => {
      count.style.display = '';
    });

    // Always show download dropdowns in view mode
    const downloadDropdowns = modal.querySelectorAll('.hs-dropdown');
    downloadDropdowns.forEach(dropdown => {
      dropdown.style.display = 'inline-flex';
      if (typeof window.HSDropdown !== 'undefined') {
        window.HSDropdown.autoInit();
      }
    });

    // Always show disabled download buttons in view mode
    const disabledDownloadBtns = modal.querySelectorAll('button[aria-label="No attachments"]');
    disabledDownloadBtns.forEach(btn => {
      btn.style.display = '';
    });
  }

  function showEditableFields(modal) {
    if (!modal) return;

    // Show file inputs
    const fileInputs = modal.querySelectorAll('input[type="file"][id^="appointment-attachments"]');
    fileInputs.forEach(input => {
      input.style.display = '';
    });

    // Show badges and hide file counts in edit mode
    const badges = modal.querySelectorAll('.appointment-badges');
    badges.forEach(badge => {
      badge.style.display = 'flex';
    });
    
    const fileCounts = modal.querySelectorAll('.file-count-text');
    fileCounts.forEach(count => {
      count.style.display = 'none';
    });

    // Hide all download dropdowns in edit mode
    const downloadDropdowns = modal.querySelectorAll('.hs-dropdown');
    downloadDropdowns.forEach(dropdown => {
      dropdown.style.display = 'none';
    });

    // Hide disabled download buttons in edit mode
    const disabledDownloadBtns = modal.querySelectorAll('button[aria-label="No attachments"]');
    disabledDownloadBtns.forEach(btn => {
      btn.style.display = 'none';
    });
  }
});